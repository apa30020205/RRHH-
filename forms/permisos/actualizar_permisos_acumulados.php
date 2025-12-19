<?php
/**
 * Actualizar Permisos Acumulados
 * Sistema RRHH
 * 
 * Endpoint AJAX para actualizar los permisos acumulados de un funcionario
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo administradores pueden actualizar permisos acumulados
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para realizar esta acción']);
    exit();
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Obtener datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit();
}

// Validar campos requeridos
$cedula = isset($data['cedula']) ? trim($data['cedula']) : '';
$permisosAcumulados = isset($data['permisos_acumulados']) ? trim($data['permisos_acumulados']) : '';

if (empty($cedula)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'La cédula es requerida']);
    exit();
}

// Validar formato de tiempo (HH:MM:SS)
if (empty($permisosAcumulados)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Los permisos acumulados son requeridos']);
    exit();
}

// Validar formato HH:MM:SS
if (!preg_match('/^([0-9]{1,2}):([0-5][0-9]):([0-5][0-9])$/', $permisosAcumulados)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato inválido. Use HH:MM:SS']);
    exit();
}

// Validar límite de MySQL TIME (838:59:59)
$partes = explode(':', $permisosAcumulados);
$horas = (int)$partes[0];
$minutos = (int)$partes[1];
$segundos = (int)$partes[2];

if ($horas > 838 || ($horas == 838 && ($minutos > 59 || ($minutos == 59 && $segundos > 59)))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El valor excede el límite de MySQL TIME (838:59:59)']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el funcionario existe
    $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);
    if (!$stmtCheck->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Funcionario no encontrado']);
        exit();
    }
    
    // Verificar si existe la columna
    $stmtCheckCol = $db->query("
        SELECT COUNT(*) as existe
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'funcionarios'
        AND COLUMN_NAME = 'permisos_acumulados'
    ");
    $columnaExiste = $stmtCheckCol->fetch()['existe'] > 0;
    
    if (!$columnaExiste) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'La columna permisos_acumulados no existe en la tabla funcionarios']);
        exit();
    }
    
    // Actualizar permisos_acumulados
    $stmt = $db->prepare("
        UPDATE funcionarios 
        SET permisos_acumulados = ?
        WHERE cedula = ?
    ");
    
    $stmt->execute([$permisosAcumulados, $cedula]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Permisos acumulados actualizados correctamente'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar permisos acumulados: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>




