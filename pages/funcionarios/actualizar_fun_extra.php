<?php
/**
 * Actualizar Campo fun_extra
 * Sistema RRHH
 * 
 * Endpoint AJAX para actualizar el campo fun_extra de un funcionario
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
    echo json_encode(['success' => false, 'message' => 'No tienes permisos para realizar esta acción']);
    exit();
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

// Obtener datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validar que se recibieron los datos necesarios
// Usar array_key_exists en lugar de isset para permitir valores null
if (!isset($data['cedula']) || !array_key_exists('fun_extra', $data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos: se requiere cedula y fun_extra']);
    exit();
}

$cedula = sanitize($data['cedula']);
$fun_extra = $data['fun_extra'] ?? null; // Usar null si no existe

// Si fun_extra es null o vacío, establecer como NULL para borrar
if ($fun_extra === null || $fun_extra === '') {
    $fun_extra = null;
} else {
    // Validar que el valor no exceda 20 caracteres
    $fun_extra = sanitize($fun_extra);
    if (strlen($fun_extra) > 20) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El valor no puede exceder 20 caracteres']);
        exit();
    }
    
    // Validar que el valor sea uno de los permitidos
    $valoresPermitidos = ['Jefe', 'Manual', 'cesante', 'Préstamo', 'Lic. Sueldo', 'Lic. Sin Sueldo', 'otro'];
    if (!in_array($fun_extra, $valoresPermitidos)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Valor no permitido. Solo se permiten: Jefe, Manual, cesante, Préstamo, Lic. Sueldo, Lic. Sin Sueldo, otro']);
        exit();
    }
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el funcionario existe
    $stmt = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
    $stmt->execute([$cedula]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Funcionario no encontrado']);
        exit();
    }
    
    // Actualizar el campo fun_extra
    $stmt = $db->prepare("UPDATE funcionarios SET fun_extra = ? WHERE cedula = ?");
    $stmt->execute([$fun_extra, $cedula]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Campo actualizado correctamente'
        ]);
    } else {
        // Si no se actualizó ninguna fila, puede ser que el valor sea el mismo
        echo json_encode([
            'success' => true,
            'message' => 'Campo actualizado correctamente (sin cambios)'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error al actualizar fun_extra: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error general al actualizar fun_extra: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

