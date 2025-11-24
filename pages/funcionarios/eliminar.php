<?php
/**
 * Eliminar Funcionario (Solo Administradores)
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/admin_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

if (!isset($_GET['cedula'])) {
    mostrarMensaje("Cédula no proporcionada", 'error');
    redirect(BASE_URL . '/pages/funcionarios/listar.php');
}

$cedula = sanitize($_GET['cedula']);
$cedulaNormalizada = normalizarCedula($cedula);

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el funcionario existe
    $stmt = $db->prepare("SELECT nombre, apellido FROM funcionarios WHERE cedula = ?");
    $stmt->execute([$cedulaNormalizada]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        mostrarMensaje("Funcionario no encontrado", 'error');
        redirect(BASE_URL . '/pages/funcionarios/listar.php');
    }
    
    // Eliminar funcionario
    $stmt = $db->prepare("DELETE FROM funcionarios WHERE cedula = ?");
    $stmt->execute([$cedulaNormalizada]);
    
    mostrarMensaje("Funcionario eliminado exitosamente", 'success');
    
} catch (Exception $e) {
    mostrarMensaje("Error al eliminar funcionario: " . $e->getMessage(), 'error');
}

redirect(BASE_URL . '/pages/funcionarios/listar.php');
?>

