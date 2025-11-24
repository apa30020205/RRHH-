<?php
/**
 * Middleware de Administrador
 * Módulo: roles_rrhh
 * Sistema RRHH
 * 
 * Incluir este archivo al inicio de páginas que requieren rol de administrador
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!Auth::isAuthenticated()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . BASE_URL . '/roles_rrhh/pages/login.php');
    exit();
}

// Verificar rol de administrador
if (!Auth::isAdmin()) {
    mostrarMensaje('No tienes permisos de administrador para acceder a esta sección', 'error');
    header('Location: ' . BASE_URL . '/pages/index.php');
    exit();
}
?>

