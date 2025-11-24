<?php
/**
 * Constantes del Sistema RRHH
 */

// Rutas del sistema
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/SISTEMA%20%20RRHH');

// Rutas de carpetas
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('UPLOADS_DOCUMENTOS', UPLOADS_PATH . '/documentos');
define('UPLOADS_EXCEL', UPLOADS_PATH . '/excel');
define('LOGS_PATH', BASE_PATH . '/logs');

// Configuración de archivos
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXCEL_TYPES', ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);

// Configuración de permisos
define('TIPOS_PERMISOS', [
    'vacaciones' => 'Vacaciones',
    'medico' => 'Permiso Médico',
    'personal' => 'Permiso Personal',
    'maternidad' => 'Licencia de Maternidad',
    'paternidad' => 'Licencia de Paternidad',
    'compensatorio' => 'Día Compensatorio'
]);

// Estados de permisos
define('ESTADOS_PERMISO', [
    'pendiente' => 'Pendiente',
    'aprobado' => 'Aprobado',
    'rechazado' => 'Rechazado',
    'cancelado' => 'Cancelado'
]);
?>

