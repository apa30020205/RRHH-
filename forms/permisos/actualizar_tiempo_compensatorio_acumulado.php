<?php
/**
 * Actualizar Tiempo Compensatorio Acumulado
 * Sistema RRHH
 * 
 * Endpoint AJAX para actualizar el tiempo compensatorio acumulado de un funcionario
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo administradores pueden actualizar tiempo compensatorio acumulado
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
$tipo = isset($data['tipo']) ? trim($data['tipo']) : '';

if (empty($cedula) || empty($tipo)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'La cédula y el tipo son requeridos']);
    exit();
}

if (!in_array($tipo, ['horas', 'dias'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Tipo inválido. Debe ser "horas" o "dias"']);
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
    
    if ($tipo === 'horas') {
        // Actualizar horas acumuladas
        $horasAcumuladas = isset($data['horas_acumuladas']) ? trim($data['horas_acumuladas']) : '';
        
        if (empty($horasAcumuladas)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Las horas acumuladas son requeridas']);
            exit();
        }
        
        // Validar formato de tiempo (H:MM:SS o HH:MM:SS o HHH:MM:SS hasta 838 horas, con o sin signo negativo)
        // Permite desde 1 hasta 3 dígitos en las horas (hasta 838 que es el límite de MySQL TIME)
        if (!preg_match('/^-?\d{1,3}:\d{2}:\d{2}$/', $horasAcumuladas)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Formato de tiempo inválido. Debe ser H:MM:SS o HH:MM:SS o HHH:MM:SS (hasta 838 horas)']);
            exit();
        }
        
        // Validar que las horas no excedan el límite de MySQL TIME (838 horas)
        $partes = explode(':', $horasAcumuladas);
        $horas = (int)abs((int)$partes[0]); // Tomar valor absoluto de las horas
        if ($horas > 838) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El valor de horas no puede exceder 838 horas (límite de MySQL TIME)']);
            exit();
        }
        
        // Verificar si la columna existe
        $stmtCheckCol = $db->query("
            SELECT COUNT(*) as existe 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'tiempo_compensatorio_horas_acumuladas'
        ");
        $columnaExiste = $stmtCheckCol->fetch()['existe'] > 0;
        
        if (!$columnaExiste) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'La columna tiempo_compensatorio_horas_acumuladas no existe en la base de datos']);
            exit();
        }
        
        $stmt = $db->prepare("
            UPDATE funcionarios 
            SET tiempo_compensatorio_horas_acumuladas = ? 
            WHERE cedula = ?
        ");
        $stmt->execute([$horasAcumuladas, $cedula]);
        
        echo json_encode([
            'success' => true,
            'mensaje' => 'Horas acumuladas actualizadas correctamente'
        ]);
        
    } else {
        // Actualizar días acumulados (permitir valores negativos)
        $diasAcumulados = isset($data['dias_acumulados']) ? (int)$data['dias_acumulados'] : 0;
        
        // Verificar si la columna existe
        $stmtCheckCol = $db->query("
            SELECT COUNT(*) as existe 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'tiempo_compensatorio_dias_acumulados'
        ");
        $columnaExiste = $stmtCheckCol->fetch()['existe'] > 0;
        
        if (!$columnaExiste) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'La columna tiempo_compensatorio_dias_acumulados no existe en la base de datos']);
            exit();
        }
        
        $stmt = $db->prepare("
            UPDATE funcionarios 
            SET tiempo_compensatorio_dias_acumulados = ? 
            WHERE cedula = ?
        ");
        $stmt->execute([$diasAcumulados, $cedula]);
        
        echo json_encode([
            'success' => true,
            'mensaje' => 'Días acumulados actualizados correctamente'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error al actualizar tiempo compensatorio acumulado: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar el tiempo compensatorio acumulado: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error inesperado al actualizar tiempo compensatorio acumulado: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error inesperado: ' . $e->getMessage()
    ]);
}
?>

