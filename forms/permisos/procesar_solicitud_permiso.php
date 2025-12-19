<?php
/**
 * Procesar Solicitud de Permiso
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mostrarMensaje("MÃ©todo no permitido", 'error');
    redirect(BASE_URL . '/forms/permisos/solicitud_permiso.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener usuario actual
    $usuarioId = $_SESSION['id_usuario'] ?? null;
    
    // Validar cÃ©dula
    if (empty($_POST['cedula'])) {
        throw new Exception("CÃ©dula no proporcionada");
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
    
    // Validar motivo
    if (empty($_POST['motivo'])) {
        throw new Exception("El motivo es obligatorio");
    }
    
    $motivo = trim($_POST['motivo']);
    $motivosValidos = ['Enfermedad', 'Duelo', 'Matrimonio', 'Nacimiento de hijos', 
                       'Enfermedad de parientes cercanos', 'Eventos acadÃ©micos puntuales', 
                       'Otros asuntos personales', 'Permiso InJustificado'];
    
    if (!in_array($motivo, $motivosValidos)) {
        throw new Exception("Motivo invÃ¡lido");
    }
    
    // Validar especifique si motivo es "Otros asuntos personales"
    $especifique = null;
    if ($motivo === 'Otros asuntos personales') {
        if (empty($_POST['especifique']) || trim($_POST['especifique']) === '') {
            throw new Exception("Debe especificar el motivo cuando selecciona 'Otros asuntos personales'");
        }
        $especifique = sanitize($_POST['especifique']);
    } else {
        // Si hay especifique pero el motivo no lo requiere, limpiarlo
        $especifique = null;
    }
    
    // Validar que hay al menos un perÃ­odo
    if (empty($_POST['periodos']) || !is_array($_POST['periodos'])) {
        throw new Exception("Debe agregar al menos un perÃ­odo");
    }
    
    $periodos = $_POST['periodos'];
    $registrosGuardados = 0;
    $errores = [];
    $minutosNuevosTotales = 0; // Suma de minutos totales de los permisos guardados exitosamente
    
    // Procesar cada perÃ­odo
    foreach ($periodos as $index => $periodoData) {
        // Validar campos requeridos
        if (empty($periodoData['fecha_desde']) || empty($periodoData['hora_desde']) || 
            empty($periodoData['fecha_hasta']) || empty($periodoData['hora_hasta'])) {
            $errores[] = "PerÃ­odo " . ($index + 1) . ": Todos los campos son obligatorios";
            continue;
        }
        
        $fechaDesde = trim($periodoData['fecha_desde']);
        $horaDesde = trim($periodoData['hora_desde']);
        $fechaHasta = trim($periodoData['fecha_hasta']);
        $horaHasta = trim($periodoData['hora_hasta']);
        
        // Validar formato de fechas
        $fechaDesdeObj = DateTime::createFromFormat('Y-m-d', $fechaDesde);
        $fechaHastaObj = DateTime::createFromFormat('Y-m-d', $fechaHasta);
        
        if (!$fechaDesdeObj || $fechaDesdeObj->format('Y-m-d') !== $fechaDesde) {
            $errores[] = "PerÃ­odo " . ($index + 1) . ": Fecha desde invÃ¡lida";
            continue;
        }
        
        if (!$fechaHastaObj || $fechaHastaObj->format('Y-m-d') !== $fechaHasta) {
            $errores[] = "PerÃ­odo " . ($index + 1) . ": Fecha hasta invÃ¡lida";
            continue;
        }
        
        // Validar que fecha_hasta >= fecha_desde
        if ($fechaHasta < $fechaDesde) {
            $errores[] = "PerÃ­odo " . ($index + 1) . ": La fecha hasta debe ser mayor o igual que la fecha desde";
            continue;
        }
        
        // Validar formato de horas
        $horaDesdeObj = DateTime::createFromFormat('H:i', $horaDesde);
        $horaHastaObj = DateTime::createFromFormat('H:i', $horaHasta);
        
        if (!$horaDesdeObj || !$horaHastaObj) {
            $errores[] = "PerÃ­odo " . ($index + 1) . ": Formato de hora invÃ¡lido";
            continue;
        }
        
        // Si las fechas son iguales, validar que hora_hasta > hora_desde
        if ($fechaDesde === $fechaHasta && $horaHasta <= $horaDesde) {
            $errores[] = "PerÃ­odo " . ($index + 1) . ": Cuando las fechas son iguales, la hora hasta debe ser mayor que la hora desde";
            continue;
        }
        
        // Agregar segundos si no los tiene
        if (strlen($horaDesde) == 5) {
            $horaDesde .= ':00';
        }
        if (strlen($horaHasta) == 5) {
            $horaHasta .= ':00';
        }
        
        try {
            // Insertar en la base de datos
            // Las horas_totales se calculan automÃ¡ticamente por la columna GENERATED
            $stmt = $db->prepare("
                INSERT INTO permisos 
                (cedula, motivo, especifique, fecha_desde, hora_desde, fecha_hasta, hora_hasta, usuario_registro, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activa')
            ");
            
            $stmt->execute([
                $cedulaParaGuardar,
                $motivo,
                $especifique,
                $fechaDesde,
                $horaDesde,
                $fechaHasta,
                $horaHasta,
                $usuarioId
            ]);
            
            $registrosGuardados++;
            
            // Calcular minutos de este permiso y sumar al total (sin redondear)
            // Crear DateTime para desde y hasta combinando fecha y hora
            $desdeCompleto = DateTime::createFromFormat('Y-m-d H:i:s', $fechaDesde . ' ' . $horaDesde);
            $hastaCompleto = DateTime::createFromFormat('Y-m-d H:i:s', $fechaHasta . ' ' . $horaHasta);
            
            if ($desdeCompleto && $hastaCompleto) {
                $diferencia = $hastaCompleto->diff($desdeCompleto);
                $dias = (int)$diferencia->format('%a');
                $horas = (int)$diferencia->format('%h');
                $minutos = (int)$diferencia->format('%i');
                
                // Calcular minutos totales (dÃ­as * 24 * 60 + horas * 60 + minutos)
                $minutosTotales = ($dias * 24 * 60) + ($horas * 60) + $minutos;
                $minutosNuevosTotales += $minutosTotales;
            }
            
        } catch (PDOException $e) {
            // Si es error de duplicado, continuar con el siguiente
            if ($e->getCode() == 23000) {
                $errores[] = "PerÃ­odo " . ($index + 1) . ": Ya existe un permiso para este perÃ­odo";
            } else {
                $errores[] = "PerÃ­odo " . ($index + 1) . ": Error al guardar - " . $e->getMessage();
            }
        }
    }
    
    // Actualizar permisos_acumulados si se guardaron permisos
    if ($registrosGuardados > 0 && $minutosNuevosTotales > 0) {
        try {
            // Obtener valor actual de permisos_acumulados
            $stmtPermisosActual = $db->prepare("
                SELECT permisos_acumulados 
                FROM funcionarios 
                WHERE cedula = ?
            ");
            $stmtPermisosActual->execute([$cedulaParaGuardar]);
            $resultado = $stmtPermisosActual->fetch();
            
            $minutosActuales = 0;
            if (!empty($resultado['permisos_acumulados'])) {
                // Convertir TIME a minutos totales
                $timeValue = $resultado['permisos_acumulados'];
                if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                    $partes = explode(':', $timeValue);
                    $horas = (int)($partes[0] ?? 0);
                    $minutos = (int)($partes[1] ?? 0);
                    $minutosActuales = ($horas * 60) + $minutos;
                } else {
                    // Si es un nÃºmero, asumir que son horas y convertir a minutos
                    $minutosActuales = (int)$timeValue * 60;
                }
            }
            
            // Sumar nuevos minutos a los existentes
            $minutosTotalesNuevos = $minutosActuales + $minutosNuevosTotales;
            
            // Convertir minutos totales a horas y minutos para guardar como TIME (HH:MM:00)
            $horasTotales = (int)($minutosTotalesNuevos / 60);
            $minutosRestantes = $minutosTotalesNuevos % 60;
            
            // Verificar si existe la columna antes de actualizar
            $stmtCheckCol = $db->query("
                SELECT COUNT(*) as existe
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'funcionarios'
                AND COLUMN_NAME = 'permisos_acumulados'
            ");
            $columnaExiste = $stmtCheckCol->fetch()['existe'] > 0;
            
            if ($columnaExiste) {
                // Actualizar campo (convertir a formato TIME HH:MM:00)
                $permisosTimeFormat = sprintf('%02d:%02d:00', $horasTotales, $minutosRestantes);
                $stmtUpdate = $db->prepare("
                    UPDATE funcionarios 
                    SET permisos_acumulados = ?
                    WHERE cedula = ?
                ");
                $stmtUpdate->execute([$permisosTimeFormat, $cedulaParaGuardar]);
            }
        } catch (Exception $e) {
            // Si falla la actualizaciÃ³n de permisos acumulados, no bloquear el proceso
            error_log("Error al actualizar permisos_acumulados: " . $e->getMessage());
        }
    }
    
    // Preparar mensaje de resultado
    if ($registrosGuardados > 0) {
        $mensaje = "Se guardaron " . $registrosGuardados . " permiso(s) exitosamente";
        if (count($errores) > 0) {
            $mensaje .= ". Errores: " . implode(", ", $errores);
        }
        mostrarMensaje($mensaje, count($errores) > 0 ? 'warning' : 'success');
    } else {
        mostrarMensaje("No se pudo guardar ningÃºn permiso. Errores: " . implode(", ", $errores), 'error');
    }
    
    // Redirigir de vuelta al formulario con la cÃ©dula
    redirect(BASE_URL . '/forms/permisos/solicitud_permiso.php?cedula=' . urlencode($cedula));
    
} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
    
    // Redirigir con cÃ©dula si estÃ¡ disponible
    $cedulaParam = isset($_POST['cedula']) ? '?cedula=' . urlencode($_POST['cedula']) : '';
    redirect(BASE_URL . '/forms/permisos/solicitud_permiso.php' . $cedulaParam);
}

