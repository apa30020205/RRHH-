<?php
/**
 * Eliminar Usuario (Solo Administradores)
 * Módulo: roles_rrhh
 * Sistema RRHH
 */

require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../middleware/admin_middleware.php';

if (!isset($_GET['id'])) {
    mostrarMensaje("ID de usuario no proporcionado", 'error');
    redirect(BASE_URL . '/roles_rrhh/pages/usuarios/listar.php');
}

$id_usuario = intval($_GET['id']);
$currentUser = Auth::getCurrentUser();

// No permitir eliminarse a sí mismo
if ($id_usuario == $currentUser['id']) {
    mostrarMensaje("No puedes eliminar tu propio usuario", 'error');
    redirect(BASE_URL . '/roles_rrhh/pages/usuarios/listar.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el usuario existe
    $stmt = $db->prepare("SELECT username FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        mostrarMensaje("Usuario no encontrado", 'error');
        redirect(BASE_URL . '/roles_rrhh/pages/usuarios/listar.php');
    }
    
    // Eliminar usuario
    $stmt = $db->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    
    mostrarMensaje("Usuario eliminado exitosamente", 'success');
    
} catch (Exception $e) {
    mostrarMensaje("Error al eliminar usuario: " . $e->getMessage(), 'error');
}

redirect(BASE_URL . '/roles_rrhh/pages/usuarios/listar.php');
?>

