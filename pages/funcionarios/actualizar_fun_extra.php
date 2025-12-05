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
    
    // Si se está marcando como "cesante", mover a ex_funcionarios y ex_marcaciones
    if ($fun_extra === 'cesante') {
        // Iniciar transacción
        $db->beginTransaction();
        
        try {
            // 1. Obtener todos los datos del funcionario
            $stmtFuncionario = $db->prepare("SELECT * FROM funcionarios WHERE cedula = ?");
            $stmtFuncionario->execute([$cedula]);
            $datosFuncionario = $stmtFuncionario->fetch(PDO::FETCH_ASSOC);
            
            if (!$datosFuncionario) {
                throw new Exception("No se pudieron obtener los datos del funcionario");
            }
            
            // 2. Verificar que no esté ya en ex_funcionarios
            $stmtCheckEx = $db->prepare("SELECT cedula FROM ex_funcionarios WHERE cedula = ?");
            $stmtCheckEx->execute([$cedula]);
            if ($stmtCheckEx->fetch()) {
                throw new Exception("Este funcionario ya está en la lista de ex-funcionarios");
            }
            
            // 3. Insertar en ex_funcionarios (copiar todos los campos)
            $stmtInsertEx = $db->prepare("
                INSERT INTO ex_funcionarios (
                    cedula, nombre, apellido, fecha_nacimiento, edad, sangre, 
                    no_posicion, posicion_funcional, fecha_inicio, sede_provincia, 
                    Direccion, fun_horario_especial, fun_extra
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsertEx->execute([
                $datosFuncionario['cedula'],
                $datosFuncionario['nombre'],
                $datosFuncionario['apellido'],
                $datosFuncionario['fecha_nacimiento'],
                $datosFuncionario['edad'],
                $datosFuncionario['sangre'],
                $datosFuncionario['no_posicion'],
                $datosFuncionario['posicion_funcional'],
                $datosFuncionario['fecha_inicio'],
                $datosFuncionario['sede_provincia'],
                $datosFuncionario['Direccion'],
                $datosFuncionario['fun_horario_especial'] ?? 0,
                'cesante' // Marcar como cesante en ex_funcionarios
            ]);
            
            // 4. Obtener todas las marcaciones del funcionario
            $stmtMarcaciones = $db->prepare("SELECT * FROM marcaciones WHERE cedula = ?");
            $stmtMarcaciones->execute([$cedula]);
            $marcaciones = $stmtMarcaciones->fetchAll(PDO::FETCH_ASSOC);
            
            // 5. Insertar marcaciones en ex_marcaciones
            if (count($marcaciones) > 0) {
                $stmtInsertMarcaciones = $db->prepare("
                    INSERT INTO ex_marcaciones (
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
            
            // 6. Eliminar marcaciones de marcaciones
            $stmtDeleteMarcaciones = $db->prepare("DELETE FROM marcaciones WHERE cedula = ?");
            $stmtDeleteMarcaciones->execute([$cedula]);
            
            // 7. Eliminar funcionario de funcionarios
            $stmtDeleteFuncionario = $db->prepare("DELETE FROM funcionarios WHERE cedula = ?");
            $stmtDeleteFuncionario->execute([$cedula]);
            
            // Confirmar transacción
            $db->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Funcionario movido a ex-funcionarios correctamente. Se movieron ' . count($marcaciones) . ' marcaciones.',
                'marcaciones_movidas' => count($marcaciones)
            ]);
            
        } catch (Exception $e) {
            // Rollback en caso de error
            $db->rollBack();
            throw $e;
        }
    } else {
        // Actualizar el campo fun_extra normalmente (no es cesante)
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

