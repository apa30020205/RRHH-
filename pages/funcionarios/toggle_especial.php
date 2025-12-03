<?php
/**
 * Toggle Estado Funcionario Especial
 * Sistema RRHH
 * 
 * Endpoint AJAX para cambiar el estado de fun_horario_especial
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

if (!$data || !isset($data['cedula']) || !isset($data['estado'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit();
}

$cedula = sanitize($data['cedula']);
$estado = intval($data['estado']) === 1 ? 1 : 0;

// Validar cédula
if (empty($cedula)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cédula inválida']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el funcionario existe
    $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);
    
    if (!$stmtCheck->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Funcionario no encontrado']);
        exit();
    }
    
    // Actualizar estado
    $stmt = $db->prepare("
        UPDATE funcionarios 
        SET fun_horario_especial = ?
        WHERE cedula = ?
    ");
    
    $stmt->execute([$estado, $cedula]);
    
    if ($stmt->rowCount() > 0) {
        // Recalcular todas las marcaciones de este funcionario
        // Asegurar que $estado sea 0 o 1 (no NULL)
        $estadoCalculo = intval($estado) === 1 ? 1 : 0;
        $marcacionesRecalculadas = recalcularMarcacionesFuncionario($db, $cedula, $estadoCalculo);
        
        echo json_encode([
            'success' => true,
            'mensaje' => $estado ? 'Funcionario marcado como especial' : 'Funcionario marcado como normal',
            'estado' => $estado,
            'marcaciones_recalculadas' => $marcacionesRecalculadas
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'No se pudo actualizar el estado']);
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

/**
 * Recalcula todas las marcaciones de un funcionario
 * @param PDO $db Conexión a la base de datos
 * @param string $cedula Cédula del funcionario
 * @param int $esEspecial 1 si es especial, 0 si no
 * @return int Número de marcaciones recalculadas
 */
function recalcularMarcacionesFuncionario($db, $cedula, $esEspecial) {
    // Obtener todas las marcaciones del funcionario
    $stmt = $db->prepare("
        SELECT id_marcacion, hora_entrada, hora_salida, fecha
        FROM marcaciones
        WHERE cedula = ? AND hora_entrada IS NOT NULL AND hora_salida IS NOT NULL
    ");
    $stmt->execute([$cedula]);
    $marcaciones = $stmt->fetchAll();
    
    $contador = 0;
    $stmtUpdate = $db->prepare("
        UPDATE marcaciones
        SET horas_trabajadas = ?, tiempo_faltante = ?
        WHERE id_marcacion = ?
    ");
    
    foreach ($marcaciones as $marcacion) {
        $horaEntrada = $marcacion['hora_entrada'];
        $horaSalida = $marcacion['hora_salida'];
        
        // Calcular horas trabajadas y tiempo faltante
        $resultado = calcularHorasTrabajadas($horaEntrada, $horaSalida, $esEspecial);
        
        if ($resultado) {
            $stmtUpdate->execute([
                $resultado['horas_trabajadas'],
                $resultado['tiempo_faltante'],
                $marcacion['id_marcacion']
            ]);
            $contador++;
        }
    }
    
    return $contador;
}

/**
 * Calcula horas trabajadas y tiempo faltante
 * @param string $horaEntrada Hora de entrada (formato H:i:s o H:i)
 * @param string $horaSalida Hora de salida (formato H:i:s o H:i)
 * @param int $esEspecial 1 si es funcionario especial, 0 si no
 * @return array|null Array con 'horas_trabajadas' y 'tiempo_faltante' o null si hay error
 */
function calcularHorasTrabajadas($horaEntrada, $horaSalida, $esEspecial) {
    // Asegurar que $esEspecial sea 0 o 1 (tratar NULL como 0)
    $esEspecial = intval($esEspecial) === 1 ? 1 : 0;
    if (empty($horaEntrada) || empty($horaSalida)) {
        return null;
    }
    
    // Convertir horas a objetos DateTime
    $entrada = DateTime::createFromFormat('H:i:s', $horaEntrada);
    if (!$entrada) {
        $entrada = DateTime::createFromFormat('H:i', $horaEntrada);
    }
    
    $salida = DateTime::createFromFormat('H:i:s', $horaSalida);
    if (!$salida) {
        $salida = DateTime::createFromFormat('H:i', $horaSalida);
    }
    
    if (!$entrada || !$salida) {
        return null;
    }
    
    // Si NO es funcionario especial y llega antes de las 8:00, usar 8:00 como hora de entrada
    $horaLimiteEntrada = DateTime::createFromFormat('H:i:s', '08:00:00');
    if (!$esEspecial && $entrada < $horaLimiteEntrada) {
        $entrada = clone $horaLimiteEntrada;
    }
    
    // Si la salida es después de las 4:00 PM, usar 4:00 PM como límite
    $horaLimiteSalida = DateTime::createFromFormat('H:i:s', '16:00:00');
    if ($salida > $horaLimiteSalida) {
        $salida = clone $horaLimiteSalida;
    }
    
    // Calcular diferencia en minutos
    $minutosEntrada = $entrada->format('H') * 60 + $entrada->format('i');
    $minutosSalida = $salida->format('H') * 60 + $salida->format('i');
    $minutosTrabajados = $minutosSalida - $minutosEntrada;
    
    // Si los minutos trabajados son negativos o cero, retornar null
    if ($minutosTrabajados <= 0) {
        return [
            'horas_trabajadas' => '00:00:00',
            'tiempo_faltante' => '08:00:00'
        ];
    }
    
    // Convertir minutos a horas:minutos
    $horas = floor($minutosTrabajados / 60);
    $minutos = $minutosTrabajados % 60;
    $horasTrabajadas = sprintf('%02d:%02d:00', $horas, $minutos);
    
    // Calcular tiempo faltante
    $minutosRequeridos = 480; // 8 horas * 60 minutos
    $tiempoFaltante = '00:00:00';
    
    if ($minutosTrabajados < $minutosRequeridos) {
        // Si sale antes de las 4:00 PM, la tardanza es desde la hora de salida hasta las 4:00 PM
        $salidaOriginal = DateTime::createFromFormat('H:i:s', $horaSalida);
        if (!$salidaOriginal) {
            $salidaOriginal = DateTime::createFromFormat('H:i', $horaSalida);
        }
        $horaLimiteSalida = DateTime::createFromFormat('H:i:s', '16:00:00');
        
        if ($salidaOriginal && $salidaOriginal < $horaLimiteSalida) {
            // Tardanza = desde hora de salida hasta 4:00 PM
            $minutosSalidaOriginal = $salidaOriginal->format('H') * 60 + $salidaOriginal->format('i');
            $minutosHasta4PM = (16 * 60) - $minutosSalidaOriginal; // 960 minutos (4 PM) - minutos de salida
            $horasFaltantes = floor($minutosHasta4PM / 60);
            $minutosFaltantesRestantes = $minutosHasta4PM % 60;
            $tiempoFaltante = sprintf('%02d:%02d:00', $horasFaltantes, $minutosFaltantesRestantes);
        } else {
            // Si sale después de las 4:00 PM, tardanza = 8 horas - horas trabajadas
            $minutosFaltantes = $minutosRequeridos - $minutosTrabajados;
            $horasFaltantes = floor($minutosFaltantes / 60);
            $minutosFaltantesRestantes = $minutosFaltantes % 60;
            $tiempoFaltante = sprintf('%02d:%02d:00', $horasFaltantes, $minutosFaltantesRestantes);
        }
    }
    
    return [
        'horas_trabajadas' => $horasTrabajadas,
        'tiempo_faltante' => $tiempoFaltante
    ];
}
?>

