<?php
/**
 * Marcar Error como Resuelto/Pendiente
 * Sistema RRHH
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

if (!$data || !isset($data['id_error']) || !isset($data['resuelto'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

$idError = intval($data['id_error']);
$resuelto = intval($data['resuelto']) === 1 ? 1 : 0;

try {
    $db = Database::getInstance()->getConnection();
    
    // Actualizar estado del error
    $stmt = $db->prepare("
        UPDATE errores_importacion_funcionarios 
        SET resuelto = ?, 
            fecha_resolucion = ?
        WHERE id_error = ?
    ");
    
    $fechaResolucion = $resuelto ? date('Y-m-d H:i:s') : null;
    
    $stmt->execute([$resuelto, $fechaResolucion, $idError]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'mensaje' => $resuelto ? 'Error marcado como resuelto' : 'Error marcado como pendiente'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Error no encontrado']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar el estado: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>

