<?php
/**
 * Guardar Múltiples Marcaciones por AJAX
 * Módulo de Mantenimiento
 * Guarda o actualiza múltiples marcaciones en una sola operación
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

if (!$data || !isset($data['cedula']) || !isset($data['marcaciones']) || !is_array($data['marcaciones'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

$cedula = sanitize($data['cedula']);
$marcaciones = $data['marcaciones'];

if (empty($cedula)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cédula es obligatoria']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();
    
    // Obtener si es funcionario especial (una sola vez)
    $stmtEspecial = $db->prepare("SELECT fun_horario_especial FROM funcionarios WHERE cedula = ?");
    $stmtEspecial->execute([$cedula]);
    $func = $stmtEspecial->fetch();
    $esEspecial = intval($func['fun_horario_especial'] ?? 0) === 1;
    
    $stmtCheck = $db->prepare("SELECT id_marcacion FROM marcaciones WHERE cedula = ? AND fecha = ?");
    $stmtUpdate = $db->prepare("
        UPDATE marcaciones 
        SET hora_entrada = ?, hora_salida = ?, horas_trabajadas = NULL, tiempo_faltante = NULL
        WHERE cedula = ? AND fecha = ?
    ");
    $stmtInsert = $db->prepare("
        INSERT INTO marcaciones (cedula, fecha, hora_entrada, hora_salida, horas_trabajadas, tiempo_faltante)
        VALUES (?, ?, ?, ?, NULL, NULL)
    ");
    $stmtRecalc = $db->prepare("
        UPDATE marcaciones 
        SET horas_trabajadas = ?, tiempo_faltante = ?
        WHERE cedula = ? AND fecha = ?
    ");
    
    $creadas = 0;
    $actualizadas = 0;
    $errores = [];
    
    foreach ($marcaciones as $marcacion) {
        $fecha = sanitize($marcacion['fecha'] ?? '');
        $hora_entrada = !empty($marcacion['hora_entrada']) ? sanitize($marcacion['hora_entrada']) : null;
        $hora_salida = !empty($marcacion['hora_salida']) ? sanitize($marcacion['hora_salida']) : null;
        
        if (empty($fecha)) {
            $errores[] = "Fecha vacía en una marcación";
            continue;
        }
        
        // Verificar si existe
        $stmtCheck->execute([$cedula, $fecha]);
        $existe = $stmtCheck->fetch();
        
        if ($existe) {
            // Actualizar
            $stmtUpdate->execute([$hora_entrada, $hora_salida, $cedula, $fecha]);
            $actualizadas++;
        } else {
            // Crear nueva
            $stmtInsert->execute([$cedula, $fecha, $hora_entrada, $hora_salida]);
            $creadas++;
        }
        
        // Recalcular horas trabajadas si hay entrada y salida
        if ($hora_entrada && $hora_salida) {
            $resultado = calcularHorasTrabajadas($hora_entrada, $hora_salida, $esEspecial);
            
            if ($resultado) {
                $stmtRecalc->execute([
                    $resultado['horas_trabajadas'],
                    $resultado['tiempo_faltante'],
                    $cedula,
                    $fecha
                ]);
            }
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Marcaciones guardadas: {$creadas} creadas, {$actualizadas} actualizadas",
        'creadas' => $creadas,
        'actualizadas' => $actualizadas,
        'errores' => $errores
    ]);
    
} catch (PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>

