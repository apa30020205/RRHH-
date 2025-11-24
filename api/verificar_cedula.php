<?php
/**
 * API para verificar si una cédula existe en el sistema
 * Usado para validación en tiempo real
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';

// Solo aceptar método GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Obtener cédula del parámetro
$cedula = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';

if (empty($cedula)) {
    echo json_encode([
        'valida' => false,
        'existe' => false,
        'mensaje' => 'Cédula vacía'
    ]);
    exit();
}

// Validar formato de cédula
$esValida = validarCedula($cedula);

if (!$esValida) {
    echo json_encode([
        'valida' => false,
        'existe' => false,
        'mensaje' => 'Formato de cédula inválido'
    ]);
    exit();
}

// Normalizar cédula para búsqueda
$cedulaNormalizada = normalizarCedula($cedula);

// Verificar si existe en la base de datos
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT cedula, nombre, apellido FROM funcionarios WHERE cedula = ?");
    $stmt->execute([$cedulaNormalizada]);
    $funcionario = $stmt->fetch();
    
    if ($funcionario) {
        echo json_encode([
            'valida' => true,
            'existe' => true,
            'mensaje' => 'La cédula ya está registrada: ' . htmlspecialchars($funcionario['nombre'] . ' ' . $funcionario['apellido'])
        ]);
    } else {
        echo json_encode([
            'valida' => true,
            'existe' => false,
            'mensaje' => 'Cédula disponible'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'valida' => false,
        'existe' => false,
        'mensaje' => 'Error al verificar cédula'
    ]);
}
?>

