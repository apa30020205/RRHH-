<?php
/**
 * Eliminar Tiempo Compensatorio (Solo Administradores)
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/admin_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Obtener parámetros
$idTiempoComp = isset($_GET['id_tiempo_comp']) ? intval($_GET['id_tiempo_comp']) : 0;
$cedulaFiltro = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';
$fechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

if ($idTiempoComp <= 0) {
    mostrarMensaje("ID de tiempo compensatorio no proporcionado o inválido", 'error');
    // Construir URL de redirección con parámetros
    $redirectUrl = BASE_URL . '/forms/permisos/tiempo_compensatorio.php';
    $params = [];
    if (!empty($cedulaFiltro)) {
        $params['buscar'] = $cedulaFiltro;
    }
    if (!empty($fechaDesde)) {
        $params['fecha_desde'] = $fechaDesde;
    }
    if (!empty($fechaHasta)) {
        $params['fecha_hasta'] = $fechaHasta;
    }
    if (!empty($params)) {
        $redirectUrl .= '?' . http_build_query($params);
    }
    redirect($redirectUrl);
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que el tiempo compensatorio existe y obtener sus datos para restar del acumulado
    $stmt = $db->prepare("SELECT id_tiempo_comp, cedula, horas, dias FROM tiempo_compensatorio WHERE id_tiempo_comp = ?");
    $stmt->execute([$idTiempoComp]);
    $tiempoComp = $stmt->fetch();
    
    if (!$tiempoComp) {
        mostrarMensaje("Tiempo compensatorio no encontrado", 'error');
    } else {
        $horasARestar = (int)($tiempoComp['horas'] ?? 0);
        $diasARestar = (int)($tiempoComp['dias'] ?? 0);
        
        // Eliminar tiempo compensatorio
        $stmt = $db->prepare("DELETE FROM tiempo_compensatorio WHERE id_tiempo_comp = ?");
        $stmt->execute([$idTiempoComp]);
        
        // Restar horas y días del acumulado si hay valores a restar
        if ($horasARestar > 0 || $diasARestar > 0) {
            try {
                // Obtener valores actuales de acumulados
                $stmtAcum = $db->prepare("
                    SELECT tiempo_compensatorio_horas_acumuladas, tiempo_compensatorio_dias_acumulados
                    FROM funcionarios 
                    WHERE cedula = ?
                ");
                $stmtAcum->execute([$tiempoComp['cedula']]);
                $resultado = $stmtAcum->fetch();
                
                // Calcular nuevas horas acumuladas
                $horasActuales = 0;
                if (!empty($resultado['tiempo_compensatorio_horas_acumuladas'])) {
                    $timeValue = $resultado['tiempo_compensatorio_horas_acumuladas'];
                    // MySQL TIME puede venir como string en formato 'HH:MM:SS' o '-HH:MM:SS' para negativos
                    if (is_string($timeValue)) {
                        // Detectar si es negativo (comienza con '-')
                        $esNegativo = (strpos($timeValue, '-') === 0);
                        $timeValueSinSigno = $esNegativo ? substr($timeValue, 1) : $timeValue;
                        
                        if (strpos($timeValueSinSigno, ':') !== false) {
                            $partes = explode(':', $timeValueSinSigno);
                            $horasActuales = (int)($partes[0] ?? 0);
                            if ($esNegativo) {
                                $horasActuales = -$horasActuales;
                            }
                        } else {
                            $horasActuales = $esNegativo ? -(int)$timeValueSinSigno : (int)$timeValueSinSigno;
                        }
                    } elseif (is_object($timeValue) && method_exists($timeValue, 'format')) {
                        // Si viene como objeto DateTime/Time
                        $horas = (int)$timeValue->format('H');
                        $horasActuales = $horas;
                    }
                }
                
                // SUMAR las horas (al eliminar, se revierte la resta, por lo tanto se suma)
                $nuevasHoras = $horasActuales + $horasARestar;
                // Permitir valores negativos
                if ($nuevasHoras > 838) {
                    $nuevasHoras = 838;
                } elseif ($nuevasHoras < -838) {
                    $nuevasHoras = -838;
                }
                // Formatear para TIME: MySQL TIME puede almacenar negativos con formato '-HH:MM:SS'
                if ($nuevasHoras < 0) {
                    $nuevasHorasTime = sprintf('-%02d:00:00', abs($nuevasHoras));
                } else {
                    $nuevasHorasTime = sprintf('%02d:00:00', $nuevasHoras);
                }
                
                // Calcular nuevos días acumulados (SUMAR al eliminar, revierte la resta)
                $diasActuales = (int)($resultado['tiempo_compensatorio_dias_acumulados'] ?? 0);
                // SUMAR los días (al eliminar, se revierte la resta, por lo tanto se suma)
                $nuevosDias = $diasActuales + $diasARestar;
                
                // Actualizar acumulados en funcionarios
                $stmtUpdate = $db->prepare("
                    UPDATE funcionarios 
                    SET tiempo_compensatorio_horas_acumuladas = ?, 
                        tiempo_compensatorio_dias_acumulados = ?
                    WHERE cedula = ?
                ");
                $stmtUpdate->execute([
                    $nuevasHorasTime,
                    $nuevosDias,
                    $tiempoComp['cedula']
                ]);
                
            } catch (Exception $e) {
                // Log del error pero no fallar la eliminación
                error_log("Error al actualizar tiempo_compensatorio acumulados al eliminar: " . $e->getMessage());
            }
        }
        
        mostrarMensaje("Tiempo compensatorio eliminado exitosamente", 'success');
    }

} catch (Exception $e) {
    mostrarMensaje("Error al eliminar tiempo compensatorio: " . $e->getMessage(), 'error');
}

// Construir URL de redirección con parámetros
$redirectUrl = BASE_URL . '/forms/permisos/tiempo_compensatorio.php';
$params = [];
if (!empty($cedulaFiltro)) {
    $params['buscar'] = $cedulaFiltro;
}
if (!empty($fechaDesde)) {
    $params['fecha_desde'] = $fechaDesde;
}
if (!empty($fechaHasta)) {
    $params['fecha_hasta'] = $fechaHasta;
}
if (!empty($params)) {
    $redirectUrl .= '?' . http_build_query($params);
}

redirect($redirectUrl);
?>

