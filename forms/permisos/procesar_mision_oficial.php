<?php
/**
 * Procesar Misión Oficial
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
    redirect(BASE_URL . '/forms/permisos/mision_oficial.php');
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
            $cedula = $funcionarioBD['cedula']; // Usar la cédula exacta de la BD
        }
    } else {
        $cedula = $funcionarioBD['cedula']; // Usar la cédula exacta de la BD
    }
    
    if (!$funcionarioBD) {
        throw new Exception("Funcionario no encontrado");
    }
    
    // Usar la cédula tal cual está en funcionarios
    $cedulaParaGuardar = $cedula;
    
    // Validar motivo
    if (empty($_POST['motivo'])) {
        throw new Exception("El motivo es obligatorio");
    }
    
    $motivo = sanitize($_POST['motivo']);
    
    // Validar campos requeridos
    if (empty($_POST['fecha']) || empty($_POST['hora_desde']) || empty($_POST['hora_hasta'])) {
        throw new Exception("Todos los campos son obligatorios");
    }
    
    $fecha = trim($_POST['fecha']);
    $horaDesde = trim($_POST['hora_desde']);
    $horaHasta = trim($_POST['hora_hasta']);
    
    // Validar formato de fecha
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
        throw new Exception("Fecha inválida");
    }
    
    // Validar formato de horas
    $horaDesdeObj = DateTime::createFromFormat('H:i', $horaDesde);
    $horaHastaObj = DateTime::createFromFormat('H:i', $horaHasta);
    
    if (!$horaDesdeObj || !$horaHastaObj) {
        throw new Exception("Formato de hora inválido");
    }
    
    // Validar que hora_hasta sea mayor que hora_desde
    if ($horaDesde === $horaHasta) {
        throw new Exception("La hora hasta debe ser diferente a la hora desde");
    }
    
    // Agregar segundos si no los tiene
    if (strlen($horaDesde) == 5) {
        $horaDesde .= ':00';
    }
    if (strlen($horaHasta) == 5) {
        $horaHasta .= ':00';
    }
    
    try {
        // Insertar en la base de datos
        // Las horas_totales se calculan automáticamente por la columna GENERATED
        $stmt = $db->prepare("
            INSERT INTO mision_oficial 
            (cedula, fecha, hora_desde, hora_hasta, motivo, usuario_registro, estado)
            VALUES (?, ?, ?, ?, ?, ?, 'activa')
        ");
        
        $stmt->execute([
            $cedulaParaGuardar,
            $fecha,
            $horaDesde,
            $horaHasta,
            $motivo,
            $usuarioId
        ]);
        
        $idMision = $db->lastInsertId();
        
        // Calcular minutos de esta misión para actualizar acumulados
        $horaDesdeObj = DateTime::createFromFormat('H:i:s', $horaDesde);
        $horaHastaObj = DateTime::createFromFormat('H:i:s', $horaHasta);
        
        $minutosNuevos = 0;
        if ($horaDesdeObj && $horaHastaObj) {
            // Si hora_hasta es menor que hora_desde, asumir que es al día siguiente
            if ($horaHastaObj < $horaDesdeObj) {
                $horaHastaObj->modify('+1 day');
            }
            
            $diferencia = $horaHastaObj->diff($horaDesdeObj);
            $horas = (int)$diferencia->format('%h');
            $minutos = (int)$diferencia->format('%i');
            // Acumular minutos totales (sin redondear)
            $minutosNuevos = ($horas * 60) + $minutos;
        }
        
        // Actualizar mision_oficial_acumuladas si se guardó la misión
        if ($minutosNuevos > 0) {
            try {
                // Obtener valor actual de mision_oficial_acumuladas
                $stmtHorasActual = $db->prepare("
                    SELECT mision_oficial_acumuladas 
                    FROM funcionarios 
                    WHERE cedula = ?
                ");
                $stmtHorasActual->execute([$cedulaParaGuardar]);
                $resultado = $stmtHorasActual->fetch();
                
                $minutosActuales = 0;
                if (!empty($resultado['mision_oficial_acumuladas'])) {
                    // Parsear tiempo acumulado actual
                    $timeValue = $resultado['mision_oficial_acumuladas'];
                    if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                        $partes = explode(':', $timeValue);
                        $horasActuales = (int)($partes[0] ?? 0);
                        $minutosActualesParte = (int)($partes[1] ?? 0);
                        $minutosActuales = ($horasActuales * 60) + $minutosActualesParte;
                    }
                }
                
                // Sumar minutos nuevos
                $minutosTotales = $minutosActuales + $minutosNuevos;
                $horasTotales = floor($minutosTotales / 60);
                $minutosRestantes = $minutosTotales % 60;
                
                // Formatear como TIME (HH:MM:SS)
                $nuevoTiempoAcumulado = sprintf('%02d:%02d:00', $horasTotales, $minutosRestantes);
                
                // Actualizar en funcionarios
                $stmtUpdate = $db->prepare("
                    UPDATE funcionarios 
                    SET mision_oficial_acumuladas = ? 
                    WHERE cedula = ?
                ");
                $stmtUpdate->execute([$nuevoTiempoAcumulado, $cedulaParaGuardar]);
                
            } catch (Exception $e) {
                // Log del error pero no fallar la inserción
                error_log("Error al actualizar mision_oficial_acumuladas: " . $e->getMessage());
            }
        }
        
        mostrarMensaje("Misión oficial guardada exitosamente", 'success');
        
    } catch (PDOException $e) {
        // Si es error de duplicado, mostrar mensaje apropiado
        if ($e->getCode() == 23000) {
            mostrarMensaje("Ya existe una misión oficial para esta fecha", 'error');
        } else {
            mostrarMensaje("Error al guardar misión oficial: " . $e->getMessage(), 'error');
        }
    }

} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
}

// Redirigir de vuelta al formulario con la cédula
$cedulaParam = isset($_POST['cedula']) ? '?cedula=' . urlencode($_POST['cedula']) : '';
redirect(BASE_URL . '/forms/permisos/mision_oficial.php' . $cedulaParam);

