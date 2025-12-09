<?php
/**
 * Obtener Marcación por AJAX
 * Módulo de Mantenimiento
 * Retorna la marcación de un funcionario para una fecha específica
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Solo administradores pueden acceder
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

if (!$data || !isset($data['cedula']) || !isset($data['fecha'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

$cedula = sanitize($data['cedula']);
$fechaRaw = trim($data['fecha']);

// Normalizar la fecha a formato YYYY-MM-DD
if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $fechaRaw, $matches)) {
    // Formato MM/DD/YYYY -> YYYY-MM-DD
    $fecha = sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
} else {
    // Intentar parsear con strtotime
    $timestamp = strtotime($fechaRaw);
    if ($timestamp !== false) {
        $fecha = date('Y-m-d', $timestamp);
    } else {
        $fecha = $fechaRaw; // Usar tal cual si no se puede parsear
    }
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar con la cédula original (con guiones)
    $stmtMarcacion = $db->prepare("SELECT * FROM marcaciones WHERE cedula = ? AND fecha = ?");
    $stmtMarcacion->execute([$cedula, $fecha]);
    $marcacion = $stmtMarcacion->fetch(PDO::FETCH_ASSOC);
    
    // Si no se encuentra, intentar con la cédula normalizada (sin guiones)
    if (!$marcacion) {
        $cedulaNormalizada = normalizarCedula($cedula);
        $stmtMarcacion2 = $db->prepare("SELECT * FROM marcaciones WHERE cedula = ? AND fecha = ?");
        $stmtMarcacion2->execute([$cedulaNormalizada, $fecha]);
        $marcacion = $stmtMarcacion2->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($marcacion) {
        echo json_encode([
            'success' => true,
            'marcacion' => [
                'hora_entrada' => $marcacion['hora_entrada'],
                'hora_salida' => $marcacion['hora_salida']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'marcacion' => null
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>

