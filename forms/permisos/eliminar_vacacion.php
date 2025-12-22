<?php
/**
 * Eliminar Vacación (Solo Administradores)
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/admin_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Obtener parámetros
$idVacacion = isset($_GET['id_vacacion']) ? intval($_GET['id_vacacion']) : 0;
$cedulaFiltro = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';
$fechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

if ($idVacacion <= 0) {
    mostrarMensaje("ID de vacación no proporcionado o inválido", 'error');
    // Construir URL de redirección con parámetros
    $redirectUrl = BASE_URL . '/forms/permisos/vacaciones.php';
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
    
    // Verificar que la vacación existe y obtener sus datos para sumar al acumulado
    $stmt = $db->prepare("SELECT id_vacacion, cedula, dias_vacacion FROM solicitud_vacaciones WHERE id_vacacion = ?");
    $stmt->execute([$idVacacion]);
    $vacacion = $stmt->fetch();
    
    if (!$vacacion) {
        mostrarMensaje("Vacación no encontrada", 'error');
    } else {
        $diasASumar = (int)($vacacion['dias_vacacion'] ?? 0);
        
        // Eliminar vacación
        $stmt = $db->prepare("DELETE FROM solicitud_vacaciones WHERE id_vacacion = ?");
        $stmt->execute([$idVacacion]);
        
        // Sumar días de vuelta al acumulado si hay días a sumar (revertir la resta)
        if ($diasASumar > 0) {
            try {
                // Verificar si la columna existe
                $stmtCheckCol = $db->query("
                    SELECT COUNT(*) as existe 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'funcionarios' 
                    AND COLUMN_NAME = 'vacaciones_dias_acumulados'
                ");
                $columnaExiste = $stmtCheckCol->fetch()['existe'] > 0;
                
                if ($columnaExiste) {
                    // Obtener valor actual
                    $stmtAcum = $db->prepare("
                        SELECT vacaciones_dias_acumulados
                        FROM funcionarios 
                        WHERE cedula = ?
                    ");
                    $stmtAcum->execute([$vacacion['cedula']]);
                    $resultadoAcum = $stmtAcum->fetch();
                    
                    $diasActuales = (int)($resultadoAcum['vacaciones_dias_acumulados'] ?? 0);
                    // SUMAR los días (al eliminar, se revierte la resta, por lo tanto se suma)
                    $nuevosDias = $diasActuales + $diasASumar;
                    
                    // Actualizar acumulado
                    $stmtUpdate = $db->prepare("
                        UPDATE funcionarios 
                        SET vacaciones_dias_acumulados = ? 
                        WHERE cedula = ?
                    ");
                    $stmtUpdate->execute([$nuevosDias, $vacacion['cedula']]);
                }
            } catch (Exception $e) {
                // Log del error pero no fallar la eliminación
                error_log("Error al actualizar vacaciones_dias_acumulados al eliminar: " . $e->getMessage());
            }
        }
        
        mostrarMensaje("Vacación eliminada exitosamente", 'success');
    }

} catch (Exception $e) {
    mostrarMensaje("Error al eliminar vacación: " . $e->getMessage(), 'error');
}

// Construir URL de redirección con parámetros
$redirectUrl = BASE_URL . '/forms/permisos/vacaciones.php';
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

