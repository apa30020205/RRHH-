<?php
/**
 * Eliminar Jornada Extraordinaria (Solo Administradores)
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/admin_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Obtener parámetros
$idJornada = isset($_GET['id_jornada']) ? intval($_GET['id_jornada']) : 0;
$cedulaFiltro = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';
$fechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

if ($idJornada <= 0) {
    mostrarMensaje("ID de jornada no proporcionado o inválido", 'error');
    // Construir URL de redirección con parámetros (redirigir a jornada_extraordinaria.php)
    $redirectUrl = BASE_URL . '/forms/permisos/jornada_extraordinaria.php';
    $params = [];
    if (!empty($cedulaFiltro)) {
        $params['buscar'] = $cedulaFiltro;
    }
    if (!empty($fechaDesde)) {
        $params['fecha_desde'] = $fechaDesde;
    }
    if (!empty($fechaHasta)) {
        $params['fecha_hasta'] = $fechaHasta;
    }
    if (!empty($params)) {
        $redirectUrl .= '?' . http_build_query($params);
    }
    redirect($redirectUrl);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que la jornada existe y obtener sus datos para restar del acumulado
    $stmt = $db->prepare("SELECT id_jornada, cedula, fecha, horas_totales FROM jornada_extraordinaria WHERE id_jornada = ?");
    $stmt->execute([$idJornada]);
    $jornada = $stmt->fetch();
    
    if (!$jornada) {
        mostrarMensaje("Jornada extraordinaria no encontrada", 'error');
    } else {
        // Calcular minutos a restar del acumulado
        $minutosARestar = 0;
        if (!empty($jornada['horas_totales'])) {
            $partes = explode(':', $jornada['horas_totales']);
            $horas = (int)($partes[0] ?? 0);
            $minutos = (int)($partes[1] ?? 0);
            $minutosARestar = ($horas * 60) + $minutos;
        }
        
        // Eliminar jornada extraordinaria
        $stmt = $db->prepare("DELETE FROM jornada_extraordinaria WHERE id_jornada = ?");
        $stmt->execute([$idJornada]);
        
        // Restar horas del acumulado si hay minutos a restar
        if ($minutosARestar > 0) {
            try {
                // Obtener valor actual de horas_extraordinarias_acumuladas
                $stmtHorasActual = $db->prepare("
                    SELECT horas_extraordinarias_acumuladas 
                    FROM funcionarios 
                    WHERE cedula = ?
                ");
                $stmtHorasActual->execute([$jornada['cedula']]);
                $resultado = $stmtHorasActual->fetch();
                
                $minutosActuales = 0;
                if (!empty($resultado['horas_extraordinarias_acumuladas'])) {
                    // Parsear tiempo acumulado actual
                    $timeValue = $resultado['horas_extraordinarias_acumuladas'];
                    if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                        $partes = explode(':', $timeValue);
                        $horasActuales = (int)($partes[0] ?? 0);
                        $minutosActualesParte = (int)($partes[1] ?? 0);
                        $minutosActuales = ($horasActuales * 60) + $minutosActualesParte;
                    }
                }
                
                // Restar minutos
                $minutosTotales = max(0, $minutosActuales - $minutosARestar);
                $horasTotales = floor($minutosTotales / 60);
                $minutosRestantes = $minutosTotales % 60;
                
                // Formatear como TIME (HH:MM:SS)
                $nuevoTiempoAcumulado = sprintf('%02d:%02d:00', $horasTotales, $minutosRestantes);
                
                // Actualizar en funcionarios
                $stmtUpdate = $db->prepare("
                    UPDATE funcionarios 
                    SET horas_extraordinarias_acumuladas = ? 
                    WHERE cedula = ?
                ");
                $stmtUpdate->execute([$nuevoTiempoAcumulado, $jornada['cedula']]);
                
            } catch (Exception $e) {
                // Log del error pero no fallar la eliminación
                error_log("Error al actualizar horas_extraordinarias_acumuladas al eliminar: " . $e->getMessage());
            }
        }
        
        mostrarMensaje("Jornada extraordinaria eliminada exitosamente", 'success');
    }

} catch (Exception $e) {
    mostrarMensaje("Error al eliminar jornada extraordinaria: " . $e->getMessage(), 'error');
}

// Construir URL de redirección con parámetros (redirigir a jornada_extraordinaria.php)
$redirectUrl = BASE_URL . '/forms/permisos/jornada_extraordinaria.php';
$params = [];
if (!empty($cedulaFiltro)) {
    $params['buscar'] = $cedulaFiltro;
}
if (!empty($fechaDesde)) {
    $params['fecha_desde'] = $fechaDesde;
}
if (!empty($fechaHasta)) {
    $params['fecha_hasta'] = $fechaHasta;
}
if (!empty($params)) {
    $redirectUrl .= '?' . http_build_query($params);
}

redirect($redirectUrl);
?>

