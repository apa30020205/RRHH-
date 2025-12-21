<?php
/**
 * Procesar Solicitud de Vacaciones
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mostrarMensaje("Método no permitido", 'error');
    redirect(BASE_URL . '/forms/permisos/vacaciones.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener usuario actual
    $usuarioId = $_SESSION['id_usuario'] ?? null;
    
    // Validar cédula
    if (empty($_POST['cedula'])) {
        throw new Exception("Cédula no proporcionada");
    }
    
    $cedula = trim($_POST['cedula']);
    
    // Verificar que el funcionario existe
    $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);
    $funcionarioBD = $stmtCheck->fetch();
    
    // Si no se encuentra, intentar normalizada
    if (!$funcionarioBD) {
        $cedulaNormalizada = normalizarCedula($cedula);
        $stmtCheck->execute([$cedulaNormalizada]);
        $funcionarioBD = $stmtCheck->fetch();
        if ($funcionarioBD) {
            $cedula = $funcionarioBD['cedula'];
        }
    } else {
        $cedula = $funcionarioBD['cedula'];
    }
    
    if (!$funcionarioBD) {
        throw new Exception("Funcionario no encontrado");
    }
    
    $cedulaParaGuardar = $cedula;
    
    // Validar campos de la declaración
    $diasSolicitados = !empty($_POST['dias_solicitados']) ? (int)$_POST['dias_solicitados'] : null;
    $fechaInicio = !empty($_POST['fecha_inicio']) ? trim($_POST['fecha_inicio']) : null;
    $fechaRetorno = !empty($_POST['fecha_retorno']) ? trim($_POST['fecha_retorno']) : null;
    $observaciones = !empty($_POST['observaciones']) ? sanitize($_POST['observaciones']) : null;
    
    // Validar fechas si se proporcionaron
    if ($fechaInicio) {
        $fechaInicioObj = DateTime::createFromFormat('Y-m-d', $fechaInicio);
        if (!$fechaInicioObj || $fechaInicioObj->format('Y-m-d') !== $fechaInicio) {
            throw new Exception("Fecha de inicio inválida");
        }
    }
    
    if ($fechaRetorno) {
        $fechaRetornoObj = DateTime::createFromFormat('Y-m-d', $fechaRetorno);
        if (!$fechaRetornoObj || $fechaRetornoObj->format('Y-m-d') !== $fechaRetorno) {
            throw new Exception("Fecha de retorno inválida");
        }
    }
    
    // Validar que hay al menos un período
    if (empty($_POST['periodos']) || !is_array($_POST['periodos'])) {
        throw new Exception("Debe agregar al menos un período de vacaciones");
    }
    
    $periodos = $_POST['periodos'];
    $registrosGuardados = 0;
    $errores = [];
    
    // Procesar cada período (línea de la tabla)
    foreach ($periodos as $index => $periodoData) {
        // Validar campos requeridos de cada línea
        if (empty($periodoData['fecha_resolucion']) || empty($periodoData['dias_vacacion'])) {
            $errores[] = "Línea " . ($index + 1) . ": Fecha y días son obligatorios";
            continue;
        }
        
        $resolucion = !empty($periodoData['resolucion']) ? sanitize($periodoData['resolucion']) : null;
        $fechaResolucion = trim($periodoData['fecha_resolucion']);
        $diasVacacion = (int)$periodoData['dias_vacacion'];
        
        // Validar formato de fecha de resolución
        $fechaResolucionObj = DateTime::createFromFormat('Y-m-d', $fechaResolucion);
        if (!$fechaResolucionObj || $fechaResolucionObj->format('Y-m-d') !== $fechaResolucion) {
            $errores[] = "Línea " . ($index + 1) . ": Fecha de resolución inválida";
            continue;
        }
        
        // Validar días
        if ($diasVacacion <= 0) {
            $errores[] = "Línea " . ($index + 1) . ": Los días deben ser mayor a 0";
            continue;
        }
        
        try {
            // Insertar en la base de datos
            // Cada línea se guarda como un registro separado, pero comparten los datos generales
            $stmt = $db->prepare("
                INSERT INTO solicitud_vacaciones 
                (cedula, dias_solicitados, fecha_inicio, fecha_retorno, resolucion, fecha_resolucion, dias_vacacion, observaciones, usuario_registro, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'activa')
            ");
            
            $stmt->execute([
                $cedulaParaGuardar,
                $diasSolicitados,
                $fechaInicio,
                $fechaRetorno,
                $resolucion,
                $fechaResolucion,
                $diasVacacion,
                $observaciones,
                $usuarioId
            ]);
            
            $registrosGuardados++;
            
        } catch (PDOException $e) {
            // Si es error de duplicado, continuar con el siguiente
            if ($e->getCode() == 23000) {
                $errores[] = "Línea " . ($index + 1) . ": Ya existe un registro similar";
            } else {
                $errores[] = "Línea " . ($index + 1) . ": Error al guardar - " . $e->getMessage();
            }
        }
    }
    
    // Preparar mensaje de resultado
    if ($registrosGuardados > 0) {
        $mensaje = "Se guardaron " . $registrosGuardados . " registro(s) de vacaciones exitosamente";
        if (count($errores) > 0) {
            $mensaje .= ". Errores: " . implode(", ", $errores);
        }
        mostrarMensaje($mensaje, count($errores) > 0 ? 'warning' : 'success');
    } else {
        mostrarMensaje("No se pudo guardar ningún registro. Errores: " . implode(", ", $errores), 'error');
    }
    
    // Redirigir de vuelta al formulario con la cédula
    redirect(BASE_URL . '/forms/permisos/vacaciones.php?cedula=' . urlencode($cedula));
    
} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
    
    // Redirigir con cédula si está disponible
    $cedulaParam = isset($_POST['cedula']) ? '?cedula=' . urlencode($_POST['cedula']) : '';
    redirect(BASE_URL . '/forms/permisos/vacaciones.php' . $cedulaParam);
}
