<?php
/**
 * Reintegrar EX/Funcionario
 * Sistema RRHH
 * 
 * Endpoint AJAX para reintegrar un ex-funcionario de vuelta a funcionarios activos
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

// Validar que se recibió la cédula
if (!isset($data['cedula']) || empty($data['cedula'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cédula no proporcionada']);
    exit();
}

$cedula = sanitize($data['cedula']);

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el ex-funcionario existe
    $stmt = $db->prepare("SELECT * FROM ex_funcionarios WHERE cedula = ?");
    $stmt->execute([$cedula]);
    $exFuncionario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$exFuncionario) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Ex-funcionario no encontrado']);
        exit();
    }
    
    // Verificar que no esté ya en funcionarios activos
    $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);
    if ($stmtCheck->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Este funcionario ya está en la lista de funcionarios activos']);
        exit();
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // 1. Insertar en funcionarios (copiar todos los campos, excepto fun_extra que será NULL o el valor que tenía)
        $stmtInsertFuncionario = $db->prepare("
            INSERT INTO funcionarios (
                cedula, nombre, apellido, fecha_nacimiento, edad, sangre, 
                no_posicion, posicion_funcional, fecha_inicio, sede_provincia, 
                Direccion, fun_horario_especial, fun_extra
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsertFuncionario->execute([
            $exFuncionario['cedula'],
            $exFuncionario['nombre'],
            $exFuncionario['apellido'],
            $exFuncionario['fecha_nacimiento'],
            $exFuncionario['edad'],
            $exFuncionario['sangre'],
            $exFuncionario['no_posicion'],
            $exFuncionario['posicion_funcional'],
            $exFuncionario['fecha_inicio'],
            $exFuncionario['sede_provincia'],
            $exFuncionario['Direccion'],
            $exFuncionario['fun_horario_especial'] ?? 0,
            NULL // fun_extra se deja NULL al reintegrar (se puede configurar después)
        ]);
        
        // 2. Obtener todas las marcaciones del ex-funcionario
        $stmtMarcaciones = $db->prepare("SELECT * FROM ex_marcaciones WHERE cedula = ?");
        $stmtMarcaciones->execute([$cedula]);
        $marcaciones = $stmtMarcaciones->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Insertar marcaciones en marcaciones (incluyendo campos opcionales si existen)
        if (count($marcaciones) > 0) {
            // Verificar qué columnas tiene la tabla marcaciones usando INFORMATION_SCHEMA
            $stmtColumns = $db->query("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'marcaciones' 
                AND COLUMN_NAME IN ('almuerzo_salida', 'almuerzo_entrada', 'todas_marcaciones')
            ");
            $existingColumns = $stmtColumns->fetchAll(PDO::FETCH_COLUMN);
            $tieneAlmuerzo = in_array('almuerzo_entrada', $existingColumns) && in_array('almuerzo_salida', $existingColumns);
            $tieneTodasMarcaciones = in_array('todas_marcaciones', $existingColumns);
            
            if ($tieneAlmuerzo && $tieneTodasMarcaciones) {
                $stmtInsertMarcaciones = $db->prepare("
                    INSERT INTO marcaciones (
                        cedula, fecha, hora_entrada, almuerzo_salida, almuerzo_entrada, hora_salida,
                        todas_marcaciones, horas_trabajadas, tiempo_faltante, fecha_importacion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($marcaciones as $marcacion) {
                    $stmtInsertMarcaciones->execute([
                        $marcacion['cedula'],
                        $marcacion['fecha'],
                        $marcacion['hora_entrada'],
                        $marcacion['almuerzo_salida'] ?? null,
                        $marcacion['almuerzo_entrada'] ?? null,
                        $marcacion['hora_salida'],
                        $marcacion['todas_marcaciones'] ?? null,
                        $marcacion['horas_trabajadas'],
                        $marcacion['tiempo_faltante'],
                        $marcacion['fecha_importacion']
                    ]);
                }
            } else {
                // Versión sin campos opcionales (compatibilidad con tablas antiguas)
                $stmtInsertMarcaciones = $db->prepare("
                    INSERT INTO marcaciones (
                        cedula, fecha, hora_entrada, hora_salida, 
                        horas_trabajadas, tiempo_faltante, fecha_importacion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($marcaciones as $marcacion) {
                    $stmtInsertMarcaciones->execute([
                        $marcacion['cedula'],
                        $marcacion['fecha'],
                        $marcacion['hora_entrada'],
                        $marcacion['hora_salida'],
                        $marcacion['horas_trabajadas'],
                        $marcacion['tiempo_faltante'],
                        $marcacion['fecha_importacion']
                    ]);
                }
            }
        }
        
        // 4. Eliminar marcaciones de ex_marcaciones
        $stmtDeleteMarcaciones = $db->prepare("DELETE FROM ex_marcaciones WHERE cedula = ?");
        $stmtDeleteMarcaciones->execute([$cedula]);
        
        // 5. Eliminar ex-funcionario de ex_funcionarios
        $stmtDeleteExFuncionario = $db->prepare("DELETE FROM ex_funcionarios WHERE cedula = ?");
        $stmtDeleteExFuncionario->execute([$cedula]);
        
        // Confirmar transacción
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Ex-funcionario reintegrado correctamente. Se movieron ' . count($marcaciones) . ' marcaciones.',
            'marcaciones_movidas' => count($marcaciones)
        ]);
        
    } catch (Exception $e) {
        // Rollback en caso de error
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Error al reintegrar ex-funcionario: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error general al reintegrar ex-funcionario: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>


