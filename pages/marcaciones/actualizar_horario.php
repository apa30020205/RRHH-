<?php
/**
 * Actualizar Horario de Trabajo del Funcionario
 * Sistema RRHH
 * 
 * Endpoint AJAX para actualizar h_entrada y h_salida de un funcionario
 * IMPORTANTE: Solo actualiza los campos en la BD, NO recalcula marcaciones
 * La visualización se actualiza dinámicamente según el horario
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Solo administradores pueden realizar esta acción
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

if (!$data || !isset($data['cedula']) || !isset($data['h_entrada']) || !isset($data['h_salida'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

$cedula = sanitize($data['cedula']);
$hEntrada = trim($data['h_entrada']);
$hSalida = trim($data['h_salida']);

// Validar cédula
if (empty($cedula)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cédula inválida']);
    exit();
}

// Validar formato de hora (HH:MM:SS)
if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hEntrada)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato de hora de entrada inválido']);
    exit();
}

if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $hSalida)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Formato de hora de salida inválido']);
    exit();
}

// Validar que la salida sea posterior a la entrada
$entradaTime = strtotime($hEntrada);
$salidaTime = strtotime($hSalida);
if ($entradaTime >= $salidaTime) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'La hora de salida debe ser posterior a la hora de entrada']);
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
    
    // Actualizar horario
    $stmt = $db->prepare("
        UPDATE funcionarios 
        SET h_entrada = ?, h_salida = ?
        WHERE cedula = ?
    ");
    
    $stmt->execute([$hEntrada, $hSalida, $cedula]);
    
    if ($stmt->rowCount() > 0 || $stmt->rowCount() === 0) {
        // rowCount puede ser 0 si los valores son iguales, pero la actualización fue exitosa
        echo json_encode([
            'success' => true,
            'mensaje' => 'Horario actualizado correctamente',
            'h_entrada' => $hEntrada,
            'h_salida' => $hSalida
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo actualizar el horario']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar el horario: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>

