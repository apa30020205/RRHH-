<?php
/**
 * Middleware de Autenticación
 * Módulo: roles_rrhh
 * Sistema RRHH
 * 
 * Incluir este archivo al inicio de páginas que requieren autenticación
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../classes/Auth.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!Auth::isAuthenticated()) {
    // Guardar URL actual para redirigir después del login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirigir al login
    header('Location: ' . BASE_URL . '/roles_rrhh/pages/login.php');
    exit();
}
?>

