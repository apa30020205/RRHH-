<?php
/**
 * Eliminar Solicitud de Permiso (Solo Administradores)
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/admin_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Obtener parámetros
$idPermiso = isset($_GET['id_permiso']) ? intval($_GET['id_permiso']) : 0;
$cedulaFiltro = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';
$fechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

if ($idPermiso <= 0) {
    mostrarMensaje("ID de permiso no proporcionado o inválido", 'error');
    // Construir URL de redirección con parámetros (redirigir a solicitud_permiso.php)
    $redirectUrl = BASE_URL . '/forms/permisos/solicitud_permiso.php';
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
    
    // Verificar si existe la columna permiso_justificado para determinar si es injustificado
    $stmtCheckCol = $db->query("
        SELECT COUNT(*) as existe
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'permisos'
        AND COLUMN_NAME = 'permiso_justificado'
    ");
    $columnaPermisoJustificadoExiste = $stmtCheckCol->fetch()['existe'] > 0;
    
    // Verificar que el permiso existe y obtener sus datos para restar del acumulado
    $sql = "SELECT id_permiso, cedula, motivo, horas_totales";
    if ($columnaPermisoJustificadoExiste) {
        $sql .= ", permiso_justificado";
    }
    $sql .= " FROM permisos WHERE id_permiso = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$idPermiso]);
    $permiso = $stmt->fetch();
    
    if (!$permiso) {
        mostrarMensaje("Solicitud de permiso no encontrada", 'error');
    } else {
        // Determinar si es injustificado (por motivo o por columna permiso_justificado)
        $esInjustificado = false;
        if ($columnaPermisoJustificadoExiste && isset($permiso['permiso_justificado'])) {
            $esInjustificado = ($permiso['permiso_justificado'] == 0);
        } else {
            // Si no existe la columna, verificar por motivo
            $esInjustificado = ($permiso['motivo'] === 'Permiso InJustificado');
        }
        
        // Calcular minutos a restar del acumulado
        $minutosARestar = 0;
        if (!empty($permiso['horas_totales'])) {
            // Parsear horas_totales (formato HH:MM:SS o HH:MM)
            $horasTotales = $permiso['horas_totales'];
            $partes = explode(':', $horasTotales);
            $horas = (int)($partes[0] ?? 0);
            $minutos = (int)($partes[1] ?? 0);
            $minutosARestar = ($horas * 60) + $minutos;
        }
        
        // Eliminar permiso
        $stmt = $db->prepare("DELETE FROM permisos WHERE id_permiso = ?");
        $stmt->execute([$idPermiso]);
        
        // Restar horas del acumulado si hay minutos a restar
        if ($minutosARestar > 0) {
            try {
                // Restar de permisos_acumulados (siempre)
                $stmtPermisosActual = $db->prepare("
                    SELECT permisos_acumulados 
                    FROM funcionarios 
                    WHERE cedula = ?
                ");
                $stmtPermisosActual->execute([$permiso['cedula']]);
                $resultado = $stmtPermisosActual->fetch();
                
                $minutosActuales = 0;
                if (!empty($resultado['permisos_acumulados'])) {
                    // Parsear tiempo acumulado actual
                    $timeValue = $resultado['permisos_acumulados'];
                    if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                        $partes = explode(':', $timeValue);
                        $horasActuales = (int)($partes[0] ?? 0);
                        $minutosActualesParte = (int)($partes[1] ?? 0);
                        $minutosActuales = ($horasActuales * 60) + $minutosActualesParte;
                    }
                }
                
                // Restar minutos de permisos_acumulados
                $minutosTotales = max(0, $minutosActuales - $minutosARestar);
                $horasTotales = floor($minutosTotales / 60);
                $minutosRestantes = $minutosTotales % 60;
                
                // Formatear como TIME (HH:MM:SS)
                $nuevoTiempoAcumulado = sprintf('%02d:%02d:00', $horasTotales, $minutosRestantes);
                
                // Actualizar permisos_acumulados en funcionarios
                $stmtUpdate = $db->prepare("
                    UPDATE funcionarios 
                    SET permisos_acumulados = ? 
                    WHERE cedula = ?
                ");
                $stmtUpdate->execute([$nuevoTiempoAcumulado, $permiso['cedula']]);
                
                // Si es injustificado, también restar de permisos_injustificados_acumulados
                if ($esInjustificado) {
                    // Verificar si existe la columna permisos_injustificados_acumulados
                    $stmtCheckColInjustAcum = $db->query("
                        SELECT COUNT(*) as existe
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'funcionarios'
                        AND COLUMN_NAME = 'permisos_injustificados_acumulados'
                    ");
                    $columnaInjustAcumExiste = $stmtCheckColInjustAcum->fetch()['existe'] > 0;
                    
                    if ($columnaInjustAcumExiste) {
                        // Obtener valor actual de permisos_injustificados_acumulados
                        $stmtPermisosInjustActual = $db->prepare("
                            SELECT permisos_injustificados_acumulados 
                            FROM funcionarios 
                            WHERE cedula = ?
                        ");
                        $stmtPermisosInjustActual->execute([$permiso['cedula']]);
                        $resultadoInjust = $stmtPermisosInjustActual->fetch();
                        
                        $minutosActualesInjust = 0;
                        if (!empty($resultadoInjust['permisos_injustificados_acumulados'])) {
                            // Parsear tiempo acumulado actual
                            $timeValueInjust = $resultadoInjust['permisos_injustificados_acumulados'];
                            if (is_string($timeValueInjust) && strpos($timeValueInjust, ':') !== false) {
                                $partesInjust = explode(':', $timeValueInjust);
                                $horasActualesInjust = (int)($partesInjust[0] ?? 0);
                                $minutosActualesParteInjust = (int)($partesInjust[1] ?? 0);
                                $minutosActualesInjust = ($horasActualesInjust * 60) + $minutosActualesParteInjust;
                            }
                        }
                        
                        // Restar minutos de permisos_injustificados_acumulados
                        $minutosTotalesInjust = max(0, $minutosActualesInjust - $minutosARestar);
                        $horasTotalesInjust = floor($minutosTotalesInjust / 60);
                        $minutosRestantesInjust = $minutosTotalesInjust % 60;
                        
                        // Formatear como TIME (HH:MM:SS)
                        $nuevoTiempoAcumuladoInjust = sprintf('%02d:%02d:00', $horasTotalesInjust, $minutosRestantesInjust);
                        
                        // Actualizar permisos_injustificados_acumulados en funcionarios
                        $stmtUpdateInjust = $db->prepare("
                            UPDATE funcionarios 
                            SET permisos_injustificados_acumulados = ? 
                            WHERE cedula = ?
                        ");
                        $stmtUpdateInjust->execute([$nuevoTiempoAcumuladoInjust, $permiso['cedula']]);
                    }
                }
                
            } catch (Exception $e) {
                // Log del error pero no fallar la eliminación
                error_log("Error al actualizar permisos_acumulados al eliminar: " . $e->getMessage());
            }
        }
        
        mostrarMensaje("Solicitud de permiso eliminada exitosamente", 'success');
    }

} catch (Exception $e) {
    mostrarMensaje("Error al eliminar solicitud de permiso: " . $e->getMessage(), 'error');
}

// Construir URL de redirección con parámetros (redirigir a solicitud_permiso.php)
$redirectUrl = BASE_URL . '/forms/permisos/solicitud_permiso.php';
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

