<?php
/**
 * Cerrar Sesión
 * Módulo: roles_rrhh
 * Sistema RRHH
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../classes/Auth.php';

Auth::logout();
header('Location: ' . BASE_URL . '/roles_rrhh/pages/login.php');
exit();
?>

