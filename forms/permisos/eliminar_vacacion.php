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
    
    // Verificar que la vacación existe
    $stmt = $db->prepare("SELECT id_vacacion, cedula FROM solicitud_vacaciones WHERE id_vacacion = ?");
    $stmt->execute([$idVacacion]);
    $vacacion = $stmt->fetch();
    
    if (!$vacacion) {
        mostrarMensaje("Vacación no encontrada", 'error');
    } else {
        // Eliminar vacación (no se acumula nada, solo se elimina el registro)
        $stmt = $db->prepare("DELETE FROM solicitud_vacaciones WHERE id_vacacion = ?");
        $stmt->execute([$idVacacion]);
        
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

