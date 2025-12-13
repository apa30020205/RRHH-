<?php
/**
 * Actualizar Derechos de Funcionario
 * Sistema RRHH
 * 
 * Endpoint AJAX para actualizar los derechos de un funcionario (vacaciones y permisos)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Solo administradores pueden actualizar derechos
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
$ano = isset($data['ano']) ? intval($data['ano']) : null;

if (empty($cedula)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'La cédula es requerida']);
    exit();
}

// Validar año (debe ser un año válido)
if ($ano !== null && ($ano < 2000 || $ano > 2100)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Año inválido']);
    exit();
}

// Obtener valores de derechos (convertir a enteros)
$vacacionesDias = isset($data['vacaciones_dias']) ? (int)$data['vacaciones_dias'] : null;
$permisosJustificadosDias = isset($data['permisos_justificados_dias']) ? (int)$data['permisos_justificados_dias'] : null;
$permisosJustificadosHoras = isset($data['permisos_justificados_horas']) ? (int)$data['permisos_justificados_horas'] : null;
$permisosNoJustificadosDias = isset($data['permisos_no_justificados_dias']) ? (int)$data['permisos_no_justificados_dias'] : null;
$permisosNoJustificadosHoras = isset($data['permisos_no_justificados_horas']) ? (int)$data['permisos_no_justificados_horas'] : null;

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
    
    // Verificar si existen los nuevos campos TIME o los antiguos DECIMAL
    $usarCamposTime = false;
    try {
        $stmtCheckTime = $db->query("SHOW COLUMNS FROM funcionarios LIKE 'permisos_justificados_acumulados'");
        $usarCamposTime = $stmtCheckTime->rowCount() > 0;
    } catch (PDOException $e) {
        $usarCamposTime = false;
    }
    
    // Construir query de actualización dinámicamente
    $campos = [];
    $valores = [];
    
    if ($vacacionesDias !== null) {
        $campos[] = "vacaciones_dias_acumulados = ?";
        $valores[] = $vacacionesDias;
    }
    
    if ($usarCamposTime) {
        // Usar nuevos campos TIME
        // Convertir días y horas a formato TIME
        if ($permisosJustificadosDias !== null || $permisosJustificadosHoras !== null) {
            $dias = $permisosJustificadosDias ?? 0;
            $horas = $permisosJustificadosHoras ?? 0;
            $timeValue = diasHorasToTime($dias, $horas);
            $campos[] = "permisos_justificados_acumulados = ?";
            $valores[] = $timeValue;
        }
        
        if ($permisosNoJustificadosDias !== null || $permisosNoJustificadosHoras !== null) {
            $dias = $permisosNoJustificadosDias ?? 0;
            $horas = $permisosNoJustificadosHoras ?? 0;
            $timeValue = diasHorasToTime($dias, $horas);
            $campos[] = "permisos_no_justificados_acumulados = ?";
            $valores[] = $timeValue;
        }
    } else {
        // Usar campos antiguos DECIMAL
        if ($permisosJustificadosDias !== null) {
            $campos[] = "permisos_justificados_dias_acumulados = ?";
            $valores[] = $permisosJustificadosDias;
        }
        if ($permisosJustificadosHoras !== null) {
            $campos[] = "permisos_justificados_horas_acumuladas = ?";
            $valores[] = $permisosJustificadosHoras;
        }
        if ($permisosNoJustificadosDias !== null) {
            $campos[] = "permisos_no_justificados_dias_acumulados = ?";
            $valores[] = $permisosNoJustificadosDias;
        }
        if ($permisosNoJustificadosHoras !== null) {
            $campos[] = "permisos_no_justificados_horas_acumuladas = ?";
            $valores[] = $permisosNoJustificadosHoras;
        }
    }
    
    if ($ano !== null) {
        $campos[] = "ano_derechos = ?";
        $valores[] = $ano;
    }
    
    if (empty($campos)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No se proporcionaron campos para actualizar']);
        exit();
    }
    
    // Agregar cédula al final para el WHERE
    $valores[] = $cedula;
    
    // Ejecutar actualización
    $sql = "UPDATE funcionarios SET " . implode(', ', $campos) . " WHERE cedula = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($valores);
    
    echo json_encode([
        'success' => true,
        'mensaje' => 'Derechos actualizados correctamente'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Error al actualizar derechos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al actualizar los derechos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error inesperado al actualizar derechos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error inesperado: ' . $e->getMessage()
    ]);
}
?>
