<?php
/**
 * Guardar Marcación por AJAX
 * Módulo de Mantenimiento
 * Guarda o actualiza una marcación sin recargar la página
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/funciones_calculo_horas.php';
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
$fecha = sanitize($data['fecha']);
$hora_entrada = !empty($data['hora_entrada']) ? sanitize($data['hora_entrada']) : null;
$hora_salida = !empty($data['hora_salida']) ? sanitize($data['hora_salida']) : null;

if (empty($cedula) || empty($fecha)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cédula y fecha son obligatorios']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar si existe marcación para esa fecha
    $stmtCheck = $db->prepare("SELECT id_marcacion FROM marcaciones WHERE cedula = ? AND fecha = ?");
    $stmtCheck->execute([$cedula, $fecha]);
    $existe = $stmtCheck->fetch();
    
    if ($existe) {
        // Actualizar
        $stmtUpdate = $db->prepare("
            UPDATE marcaciones 
            SET hora_entrada = ?, hora_salida = ?, horas_trabajadas = NULL, tiempo_faltante = NULL
            WHERE cedula = ? AND fecha = ?
        ");
        $stmtUpdate->execute([$hora_entrada, $hora_salida, $cedula, $fecha]);
        
        $accion = 'actualizada';
    } else {
        // Crear nueva
        $stmtInsert = $db->prepare("
            INSERT INTO marcaciones (cedula, fecha, hora_entrada, hora_salida, horas_trabajadas, tiempo_faltante)
            VALUES (?, ?, ?, ?, NULL, NULL)
        ");
        $stmtInsert->execute([$cedula, $fecha, $hora_entrada, $hora_salida]);
        
        $accion = 'creada';
    }
    
    // Recalcular horas trabajadas
    if ($hora_entrada && $hora_salida) {
        // Obtener si es funcionario especial
        $stmtEspecial = $db->prepare("SELECT fun_horario_especial FROM funcionarios WHERE cedula = ?");
        $stmtEspecial->execute([$cedula]);
        $func = $stmtEspecial->fetch();
        $esEspecial = intval($func['fun_horario_especial'] ?? 0) === 1;
        
        // Calcular horas trabajadas
        $resultado = calcularHorasTrabajadas($hora_entrada, $hora_salida, $esEspecial);
        
        if ($resultado) {
            $stmtRecalc = $db->prepare("
                UPDATE marcaciones 
                SET horas_trabajadas = ?, tiempo_faltante = ?
                WHERE cedula = ? AND fecha = ?
            ");
            $stmtRecalc->execute([
                $resultado['horas_trabajadas'],
                $resultado['tiempo_faltante'],
                $cedula,
                $fecha
            ]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Marcación {$accion} exitosamente",
        'accion' => $accion
    ]);
    
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

