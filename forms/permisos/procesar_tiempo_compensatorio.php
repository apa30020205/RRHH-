<?php
/**
 * Procesar Tiempo Compensatorio
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mostrarMensaje("Método no permitido", 'error');
    redirect(BASE_URL . '/forms/permisos/tiempo_compensatorio.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener usuario actual
    $usuarioId = $_SESSION['id_usuario'] ?? null;
    
    // Validar cédula
    if (empty($_POST['cedula'])) {
        throw new Exception("Cédula no proporcionada");
    }
    
    $cedula = trim($_POST['cedula']);
    
    // Verificar que el funcionario existe
    $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);
    $funcionarioBD = $stmtCheck->fetch();
    
    // Si no se encuentra, intentar normalizada
    if (!$funcionarioBD) {
        $cedulaNormalizada = normalizarCedula($cedula);
        $stmtCheck->execute([$cedulaNormalizada]);
        $funcionarioBD = $stmtCheck->fetch();
        if ($funcionarioBD) {
            $cedula = $funcionarioBD['cedula'];
        }
    } else {
        $cedula = $funcionarioBD['cedula'];
    }
    
    if (!$funcionarioBD) {
        throw new Exception("Funcionario no encontrado");
    }
    
    $cedulaParaGuardar = $cedula;
    
    // Validar campos requeridos
    if (!isset($_POST['horas']) || !isset($_POST['dias']) || empty($_POST['fecha_uso'])) {
        throw new Exception("Todos los campos son obligatorios");
    }
    
    $horas = (int)$_POST['horas'];
    $dias = (int)$_POST['dias'];
    $fechaUso = trim($_POST['fecha_uso']);
    
    // Validar que al menos horas o días sean diferentes de 0 (pueden ser negativos)
    if ($horas == 0 && $dias == 0) {
        throw new Exception("Debe ingresar al menos horas o días (pueden ser negativos)");
    }
    
    // Validar formato de fecha
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaUso);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaUso) {
        throw new Exception("Fecha inválida");
    }
    
    // Validar rangos (permitir valores negativos y positivos)
    if ($horas > 23 || $horas < -23) {
        throw new Exception("Las horas deben estar entre -23 y 23");
    }
    
    if ($dias > 99 || $dias < -99) {
        throw new Exception("Los días deben estar entre -99 y 99");
    }
    
    try {
        // Insertar en la base de datos
        $stmt = $db->prepare("
            INSERT INTO tiempo_compensatorio 
            (cedula, horas, dias, fecha_uso, usuario_registro, estado)
            VALUES (?, ?, ?, ?, ?, 'activa')
        ");
        
        $stmt->execute([
            $cedulaParaGuardar,
            $horas,
            $dias,
            $fechaUso,
            $usuarioId
        ]);
        
        // Actualizar acumulados en funcionarios
        try {
            // Obtener valores actuales de acumulados
            $stmtAcum = $db->prepare("
                SELECT tiempo_compensatorio_horas_acumuladas, tiempo_compensatorio_dias_acumulados
                FROM funcionarios 
                WHERE cedula = ?
            ");
            $stmtAcum->execute([$cedulaParaGuardar]);
            $resultadoAcum = $stmtAcum->fetch();
            
            // Calcular nuevas horas acumuladas (RESTAR porque es tiempo compensatorio usado)
            $horasActuales = 0;
            if (!empty($resultadoAcum['tiempo_compensatorio_horas_acumuladas'])) {
                $timeValue = $resultadoAcum['tiempo_compensatorio_horas_acumuladas'];
                // MySQL TIME puede venir como string en formato 'HH:MM:SS' o '-HH:MM:SS' para negativos
                if (is_string($timeValue)) {
                    // Detectar si es negativo (comienza con '-')
                    $esNegativo = (strpos($timeValue, '-') === 0);
                    $timeValueSinSigno = $esNegativo ? substr($timeValue, 1) : $timeValue;
                    
                    if (strpos($timeValueSinSigno, ':') !== false) {
                        $partes = explode(':', $timeValueSinSigno);
                        $horasActuales = (int)($partes[0] ?? 0);
                        if ($esNegativo) {
                            $horasActuales = -$horasActuales;
                        }
                    } else {
                        $horasActuales = $esNegativo ? -(int)$timeValueSinSigno : (int)$timeValueSinSigno;
                    }
                } elseif (is_object($timeValue) && method_exists($timeValue, 'format')) {
                    // Si viene como objeto DateTime/Time
                    $horas = (int)$timeValue->format('H');
                    // Determinar si es negativo (puede venir como intervalo negativo)
                    $horasActuales = $horas;
                }
            }
            
            // RESTAR las horas (tiempo compensatorio se usa, por lo tanto se resta del acumulado)
            $nuevasHoras = $horasActuales - $horas;
            // Permitir valores negativos, pero limitar el rango absoluto para TIME
            // MySQL TIME puede manejar desde -838:59:59 hasta 838:59:59
            if ($nuevasHoras > 838) {
                $nuevasHoras = 838;
            } elseif ($nuevasHoras < -838) {
                $nuevasHoras = -838;
            }
            // Formatear para TIME: MySQL TIME puede almacenar negativos con formato '-HH:MM:SS'
            if ($nuevasHoras < 0) {
                $nuevasHorasTime = sprintf('-%02d:00:00', abs($nuevasHoras));
            } else {
                $nuevasHorasTime = sprintf('%02d:00:00', $nuevasHoras);
            }
            
            // Calcular nuevos días acumulados (RESTAR porque es tiempo compensatorio usado)
            $diasActuales = (int)($resultadoAcum['tiempo_compensatorio_dias_acumulados'] ?? 0);
            // RESTAR los días (tiempo compensatorio se usa, por lo tanto se resta del acumulado)
            $nuevosDias = $diasActuales - $dias;
            
            // Actualizar acumulados en funcionarios
            $stmtUpdate = $db->prepare("
                UPDATE funcionarios 
                SET tiempo_compensatorio_horas_acumuladas = ?, 
                    tiempo_compensatorio_dias_acumulados = ?
                WHERE cedula = ?
            ");
            $stmtUpdate->execute([
                $nuevasHorasTime,
                $nuevosDias,
                $cedulaParaGuardar
            ]);
            
        } catch (Exception $e) {
            // Log del error pero no fallar la inserción
            error_log("Error al actualizar tiempo_compensatorio acumulados: " . $e->getMessage());
        }
        
        mostrarMensaje("Tiempo compensatorio guardado exitosamente", 'success');
        
    } catch (PDOException $e) {
        mostrarMensaje("Error al guardar tiempo compensatorio: " . $e->getMessage(), 'error');
    }

} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
}

// Redirigir de vuelta al formulario con la cédula
$cedulaParam = isset($_POST['cedula']) ? '?cedula=' . urlencode($_POST['cedula']) : '';
redirect(BASE_URL . '/forms/permisos/tiempo_compensatorio.php' . $cedulaParam);

