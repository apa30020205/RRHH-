<?php
/**
 * Procesar Jornada Extraordinaria
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
    redirect(BASE_URL . '/forms/permisos/jornada_extraordinaria.php');
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
    
    // Verificar que el funcionario existe - buscar con la cédula tal cual (la BD tiene guiones)
    $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);
    $funcionarioBD = $stmtCheck->fetch();
    
    // Si no se encuentra, intentar normalizada
    if (!$funcionarioBD) {
        $cedulaNormalizada = normalizarCedula($cedula);
        $stmtCheck->execute([$cedulaNormalizada]);
        $funcionarioBD = $stmtCheck->fetch();
        if ($funcionarioBD) {
            $cedula = $funcionarioBD['cedula']; // Usar la cédula exacta de la BD
        }
    } else {
        $cedula = $funcionarioBD['cedula']; // Usar la cédula exacta de la BD
    }
    
    if (!$funcionarioBD) {
        throw new Exception("Funcionario no encontrado");
    }
    
    // Normalizar solo para guardar en jornada_extraordinaria (si se requiere normalización)
    // Pero primero verificar si la BD de jornada_extraordinaria usa cédula normalizada o con guiones
    // Por ahora, usaremos la cédula tal cual está en funcionarios
    $cedulaParaGuardar = $cedula;
    
    // Validar justificación
    if (empty($_POST['justificacion'])) {
        throw new Exception("La justificación es obligatoria");
    }
    
    $justificacion = sanitize($_POST['justificacion']);
    
    // Validar que hay al menos una fecha
    if (empty($_POST['fechas']) || !is_array($_POST['fechas'])) {
        throw new Exception("Debe agregar al menos una fecha con sus horas");
    }
    
    $fechas = $_POST['fechas'];
    $registrosGuardados = 0;
    $errores = [];
    $minutosNuevosTotales = 0; // Suma de minutos totales de las jornadas guardadas exitosamente (sin redondear)
    
    // Procesar cada fecha
    foreach ($fechas as $index => $fechaData) {
        // Validar campos requeridos
        if (empty($fechaData['fecha']) || empty($fechaData['hora_desde']) || empty($fechaData['hora_hasta'])) {
            $errores[] = "Fila " . ($index + 1) . ": Todos los campos son obligatorios";
            continue;
        }
        
        $fecha = trim($fechaData['fecha']);
        $horaDesde = trim($fechaData['hora_desde']);
        $horaHasta = trim($fechaData['hora_hasta']);
        
        // Validar formato de fecha
        $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            $errores[] = "Fila " . ($index + 1) . ": Fecha inválida";
            continue;
        }
        
        // Validar que hora_hasta > hora_desde
        $horaDesdeObj = DateTime::createFromFormat('H:i', $horaDesde);
        $horaHastaObj = DateTime::createFromFormat('H:i', $horaHasta);
        
        if (!$horaDesdeObj || !$horaHastaObj) {
            $errores[] = "Fila " . ($index + 1) . ": Formato de hora inválido";
            continue;
        }
        
        // Validar que hora_hasta sea mayor que hora_desde
        // Nota: Permitir que sea al día siguiente (ej: desde 17:00 hasta 02:00)
        // Para simplificar, solo validamos que no sean iguales
        if ($horaDesde === $horaHasta) {
            $errores[] = "Fila " . ($index + 1) . ": La hora hasta debe ser diferente a la hora desde";
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
            // Las horas_totales se calculan automáticamente por la columna GENERATED
            $stmt = $db->prepare("
                INSERT INTO jornada_extraordinaria 
                (cedula, justificacion, fecha, hora_desde, hora_hasta, usuario_registro, estado)
                VALUES (?, ?, ?, ?, ?, ?, 'activa')
            ");
            
            $stmt->execute([
                $cedulaParaGuardar,
                $justificacion,
                $fecha,
                $horaDesde,
                $horaHasta,
                $usuarioId
            ]);
            
            $registrosGuardados++;
            
            // Calcular minutos de esta jornada y sumar al total (sin redondear)
            $horaDesdeObj = DateTime::createFromFormat('H:i:s', $horaDesde);
            $horaHastaObj = DateTime::createFromFormat('H:i:s', $horaHasta);
            
            if ($horaDesdeObj && $horaHastaObj) {
                // Si hora_hasta es menor que hora_desde, asumir que es al día siguiente
                if ($horaHastaObj < $horaDesdeObj) {
                    $horaHastaObj->modify('+1 day');
                }
                
                $diferencia = $horaHastaObj->diff($horaDesdeObj);
                $horas = (int)$diferencia->format('%h');
                $minutos = (int)$diferencia->format('%i');
                // Acumular minutos totales (sin redondear)
                $minutosNuevosTotales += ($horas * 60) + $minutos;
            }
            
        } catch (PDOException $e) {
            // Si es error de duplicado, continuar con el siguiente
            if ($e->getCode() == 23000) {
                $errores[] = "Fila " . ($index + 1) . ": Ya existe una jornada extraordinaria para esta fecha";
            } else {
                $errores[] = "Fila " . ($index + 1) . ": Error al guardar - " . $e->getMessage();
            }
        }
    }
    
    // Actualizar horas_extraordinarias_acumuladas si se guardaron jornadas
    if ($registrosGuardados > 0 && $minutosNuevosTotales > 0) {
        try {
            // Obtener valor actual de horas_extraordinarias_acumuladas
            $stmtHorasActual = $db->prepare("
                SELECT horas_extraordinarias_acumuladas 
                FROM funcionarios 
                WHERE cedula = ?
            ");
            $stmtHorasActual->execute([$cedulaParaGuardar]);
            $resultado = $stmtHorasActual->fetch();
            
            $minutosActuales = 0;
            if (!empty($resultado['horas_extraordinarias_acumuladas'])) {
                // Convertir TIME a minutos totales
                $timeValue = $resultado['horas_extraordinarias_acumuladas'];
                if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                    $partes = explode(':', $timeValue);
                    $horas = (int)($partes[0] ?? 0);
                    $minutos = (int)($partes[1] ?? 0);
                    $minutosActuales = ($horas * 60) + $minutos;
                } else {
                    // Si es un número, asumir que son horas y convertir a minutos
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
                AND COLUMN_NAME = 'horas_extraordinarias_acumuladas'
            ");
            $columnaExiste = $stmtCheckCol->fetch()['existe'] > 0;
            
            if ($columnaExiste) {
                // Actualizar campo (convertir a formato TIME HH:MM:00)
                $horasTimeFormat = sprintf('%02d:%02d:00', $horasTotales, $minutosRestantes);
                $stmtUpdate = $db->prepare("
                    UPDATE funcionarios 
                    SET horas_extraordinarias_acumuladas = ?
                    WHERE cedula = ?
                ");
                $stmtUpdate->execute([$horasTimeFormat, $cedulaParaGuardar]);
            }
        } catch (Exception $e) {
            // Si falla la actualización de horas acumuladas, no bloquear el proceso
            // Solo registrar en errores (opcional) o ignorar silenciosamente
            error_log("Error al actualizar horas_extraordinarias_acumuladas: " . $e->getMessage());
        }
    }
    
    // Preparar mensaje de resultado
    if ($registrosGuardados > 0) {
        $mensaje = "Se guardaron " . $registrosGuardados . " jornada(s) extraordinaria(s) exitosamente";
        if (count($errores) > 0) {
            $mensaje .= ". Errores: " . implode(", ", $errores);
        }
        mostrarMensaje($mensaje, count($errores) > 0 ? 'warning' : 'success');
    } else {
        mostrarMensaje("No se pudo guardar ninguna jornada. Errores: " . implode(", ", $errores), 'error');
    }
    
    // Redirigir de vuelta al formulario con la cédula
    redirect(BASE_URL . '/forms/permisos/jornada_extraordinaria.php?cedula=' . urlencode($cedula));
    
} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
    
    // Redirigir con cédula si está disponible
    $cedulaParam = isset($_POST['cedula']) ? '?cedula=' . urlencode($_POST['cedula']) : '';
    redirect(BASE_URL . '/forms/permisos/jornada_extraordinaria.php' . $cedulaParam);
}


