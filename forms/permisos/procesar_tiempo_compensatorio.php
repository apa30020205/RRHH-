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
    
    // Validar que al menos horas o días sean mayor a 0
    if ($horas <= 0 && $dias <= 0) {
        throw new Exception("Debe ingresar al menos horas o días mayor a 0");
    }
    
    // Validar formato de fecha
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaUso);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaUso) {
        throw new Exception("Fecha inválida");
    }
    
    // Validar rangos
    if ($horas < 0 || $horas > 23) {
        throw new Exception("Las horas deben estar entre 0 y 23");
    }
    
    if ($dias < 0 || $dias > 99) {
        throw new Exception("Los días deben estar entre 0 y 99");
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
            
            // Calcular nuevas horas acumuladas
            $horasActuales = 0;
            if (!empty($resultadoAcum['tiempo_compensatorio_horas_acumuladas'])) {
                $timeValue = $resultadoAcum['tiempo_compensatorio_horas_acumuladas'];
                if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                    $partes = explode(':', $timeValue);
                    $horasActuales = (int)($partes[0] ?? 0);
                }
            }
            
            $nuevasHoras = $horasActuales + $horas;
            // Limitar a 838 horas (límite de MySQL TIME)
            if ($nuevasHoras > 838) {
                $nuevasHoras = 838;
            }
            $nuevasHorasTime = sprintf('%02d:00:00', $nuevasHoras);
            
            // Calcular nuevos días acumulados
            $diasActuales = (int)($resultadoAcum['tiempo_compensatorio_dias_acumulados'] ?? 0);
            $nuevosDias = $diasActuales + $dias;
            
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
