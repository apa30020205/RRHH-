<?php
/**
 * Actualizar Vacaciones Días Acumulados
 * Sistema RRHH
 * 
 * Endpoint AJAX para actualizar los días acumulados de vacaciones de un funcionario
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo administradores pueden actualizar días acumulados de vacaciones
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
$diasAcumulados = isset($data['dias_acumulados']) ? (int)$data['dias_acumulados'] : 0;

if (empty($cedula)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'La cédula es requerida']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el funcionario existe
    $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);
    if ($stmtCheck->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Funcionario no encontrado']);
        exit();
    }
    
    // Verificar si la columna existe
    $stmtCheckCol = $db->query("
        SELECT COUNT(*) as existe 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'funcionarios' 
        AND COLUMN_NAME = 'vacaciones_dias_acumulados'
    ");
    $columnaExiste = $stmtCheckCol->fetch()['existe'] > 0;
    
    if (!$columnaExiste) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'La columna vacaciones_dias_acumulados no existe en la base de datos']);
        exit();
    }
    
    // Actualizar días acumulados (permitir valores negativos)
    $stmt = $db->prepare("
        UPDATE funcionarios 
        SET vacaciones_dias_acumulados = ? 
        WHERE cedula = ?
    ");
    $stmt->execute([$diasAcumulados, $cedula]);
    
    echo json_encode([
        'success' => true,
        'mensaje' => 'Días acumulados actualizados correctamente'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error al actualizar días acumulados de vacaciones: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar los días acumulados de vacaciones: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error inesperado al actualizar días acumulados de vacaciones: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error inesperado: ' . $e->getMessage()
    ]);
}
?>
