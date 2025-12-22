<?php
/**
 * Eliminar Misión Oficial (Solo Administradores)
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/admin_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Obtener parámetros
$idMision = isset($_GET['id_mision']) ? intval($_GET['id_mision']) : 0;
$cedulaFiltro = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';
$fechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

if ($idMision <= 0) {
    mostrarMensaje("ID de misión no proporcionado o inválido", 'error');
    // Construir URL de redirección con parámetros (redirigir a mision_oficial.php)
    $redirectUrl = BASE_URL . '/forms/permisos/mision_oficial.php';
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
    
    // Verificar que la misión existe y obtener sus datos para restar del acumulado
    $stmt = $db->prepare("SELECT id_mision, cedula, fecha, horas_totales FROM mision_oficial WHERE id_mision = ?");
    $stmt->execute([$idMision]);
    $mision = $stmt->fetch();
    
    if (!$mision) {
        mostrarMensaje("Misión oficial no encontrada", 'error');
    } else {
        // Calcular minutos a restar del acumulado
        $minutosARestar = 0;
        if (!empty($mision['horas_totales'])) {
            $partes = explode(':', $mision['horas_totales']);
            $horas = (int)($partes[0] ?? 0);
            $minutos = (int)($partes[1] ?? 0);
            $minutosARestar = ($horas * 60) + $minutos;
        }
        
        // Eliminar misión oficial
        $stmt = $db->prepare("DELETE FROM mision_oficial WHERE id_mision = ?");
        $stmt->execute([$idMision]);
        
        // Restar horas del acumulado si hay minutos a restar
        if ($minutosARestar > 0) {
            try {
                // Obtener valor actual de mision_oficial_acumuladas
                $stmtHorasActual = $db->prepare("
                    SELECT mision_oficial_acumuladas 
                    FROM funcionarios 
                    WHERE cedula = ?
                ");
                $stmtHorasActual->execute([$mision['cedula']]);
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
                
                // Restar minutos
                $minutosTotales = max(0, $minutosActuales - $minutosARestar);
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
                $stmtUpdate->execute([$nuevoTiempoAcumulado, $mision['cedula']]);
                
            } catch (Exception $e) {
                // Log del error pero no fallar la eliminación
                error_log("Error al actualizar mision_oficial_acumuladas al eliminar: " . $e->getMessage());
            }
        }
        
        mostrarMensaje("Misión oficial eliminada exitosamente", 'success');
    }

} catch (Exception $e) {
    mostrarMensaje("Error al eliminar misión oficial: " . $e->getMessage(), 'error');
}

// Construir URL de redirección con parámetros (redirigir a mision_oficial.php)
$redirectUrl = BASE_URL . '/forms/permisos/mision_oficial.php';
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

