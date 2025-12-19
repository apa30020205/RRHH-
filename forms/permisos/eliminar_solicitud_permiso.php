<?php
/**
 * Eliminar Solicitud de Permiso (Solo Administradores)
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/admin_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Obtener parámetros
$idPermiso = isset($_GET['id_permiso']) ? intval($_GET['id_permiso']) : 0;
$cedulaFiltro = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';
$fechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

if ($idPermiso <= 0) {
    mostrarMensaje("ID de permiso no proporcionado o inválido", 'error');
    // Construir URL de redirección con parámetros (redirigir a solicitud_permiso.php)
    $redirectUrl = BASE_URL . '/forms/permisos/solicitud_permiso.php';
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
    
    // Verificar que el permiso existe
    $stmt = $db->prepare("SELECT id_permiso, cedula, motivo FROM permisos WHERE id_permiso = ?");
    $stmt->execute([$idPermiso]);
    $permiso = $stmt->fetch();
    
    if (!$permiso) {
        mostrarMensaje("Solicitud de permiso no encontrada", 'error');
    } else {
        // Eliminar permiso
        $stmt = $db->prepare("DELETE FROM permisos WHERE id_permiso = ?");
        $stmt->execute([$idPermiso]);
        
        mostrarMensaje("Solicitud de permiso eliminada exitosamente", 'success');
    }
    
} catch (Exception $e) {
    mostrarMensaje("Error al eliminar solicitud de permiso: " . $e->getMessage(), 'error');
}

// Construir URL de redirección con parámetros (redirigir a solicitud_permiso.php)
$redirectUrl = BASE_URL . '/forms/permisos/solicitud_permiso.php';
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
