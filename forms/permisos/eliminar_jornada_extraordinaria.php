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
    
    // Verificar que la jornada existe
    $stmt = $db->prepare("SELECT id_jornada, cedula, fecha FROM jornada_extraordinaria WHERE id_jornada = ?");
    $stmt->execute([$idJornada]);
    $jornada = $stmt->fetch();
    
    if (!$jornada) {
        mostrarMensaje("Jornada extraordinaria no encontrada", 'error');
    } else {
        // Eliminar jornada extraordinaria
        $stmt = $db->prepare("DELETE FROM jornada_extraordinaria WHERE id_jornada = ?");
        $stmt->execute([$idJornada]);
        
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
