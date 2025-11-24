<?php
/**
 * Punto de entrada principal del sistema
 * Redirige al dashboard o al login según autenticación
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/roles_rrhh/classes/Auth.php';

// Detectar la ruta base automáticamente
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = $protocol . '://' . $host . $scriptPath;

// Verificar autenticación
if (Auth::isAuthenticated()) {
    // Redirigir al dashboard
    header("Location: " . BASE_URL . "/pages/index.php");
} else {
    // Redirigir al login
    header("Location: " . BASE_URL . "/roles_rrhh/pages/login.php");
}
exit();
?>

