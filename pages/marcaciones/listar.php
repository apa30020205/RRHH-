<?php
/**
 * Listar Marcaciones
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/funciones_calculo_horas.php';
require_once __DIR__ . '/../../includes/funciones_deteccion_almuerzo.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Marcaciones - Sistema RRHH';

// Obtener parámetros de búsqueda, filtros y ordenamiento
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$cedulaFiltro = isset($_GET['cedula']) ? trim($_GET['cedula']) : '';
$fechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
$ordenarPor = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha';
$direccion = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'DESC' : 'ASC';
$exFuncionario = isset($_GET['ex_funcionario']) && intval($_GET['ex_funcionario']) === 1;

// Validar campo de ordenamiento (prevenir SQL injection)
$camposPermitidos = [
    'id_marcacion', 'cedula', 'fecha', 'hora_entrada', 'hora_salida', 'fecha_importacion',
    'nombre', 'apellido' // Para ordenar por nombre/apellido del funcionario
];

if (!in_array($ordenarPor, $camposPermitidos)) {
    $ordenarPor = 'fecha';
}

// Función para generar URL de ordenamiento
function urlOrdenar($campo, $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario = false) {
    $params = ['ordenar' => $campo];
    if (!empty($busqueda)) {
        $params['buscar'] = $busqueda;
    }
    if (!empty($cedulaFiltro)) {
        $params['cedula'] = $cedulaFiltro;
    }
    if (!empty($fechaDesde)) {
        $params['fecha_desde'] = $fechaDesde;
    }
    if (!empty($fechaHasta)) {
        $params['fecha_hasta'] = $fechaHasta;
    }
    if ($exFuncionario) {
        $params['ex_funcionario'] = '1';
    }
    
    // Determinar dirección del ordenamiento
    $dirActual = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
    $campoActual = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha';
    
    if ($campoActual === $campo) {
        $params['dir'] = $dirActual === 'asc' ? 'desc' : 'asc';
    } else {
        $params['dir'] = 'asc';
    }
    
    return BASE_URL . '/pages/marcaciones/listar.php?' . http_build_query($params);
}

// Función para mostrar icono de ordenamiento
function iconoOrdenamiento($campo) {
    $campoActual = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha';
    $dirActual = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
    
    if ($campoActual === $campo) {
        return $dirActual === 'desc' 
            ? '<i class="fas fa-sort-down"></i>' 
            : '<i class="fas fa-sort-up"></i>';
    }
    return '<i class="fas fa-sort"></i>';
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Determinar qué tablas usar según si es ex-funcionario o no
    $tablaMarcaciones = $exFuncionario ? 'ex_marcaciones' : 'marcaciones';
    $tablaFuncionarios = $exFuncionario ? 'ex_funcionarios' : 'funcionarios';
    
    // Construir consulta con JOIN a funcionarios/ex_funcionarios para obtener nombre y apellido
    // Usar TIME_FORMAT para normalizar el formato de hora_entrada y hora_salida
    // LEFT JOIN con jornada_extraordinaria para identificar fechas con horas extras
    $sql = "SELECT m.id_marcacion, m.cedula, m.fecha, 
                   TIME_FORMAT(m.hora_entrada, '%H:%i:%s') as hora_entrada,
                   TIME_FORMAT(m.almuerzo_salida, '%H:%i:%s') as almuerzo_salida,
                   TIME_FORMAT(m.almuerzo_entrada, '%H:%i:%s') as almuerzo_entrada,
                   TIME_FORMAT(m.hora_salida, '%H:%i:%s') as hora_salida,
                   m.horas_trabajadas, m.tiempo_faltante, m.fecha_importacion,
                   m.todas_marcaciones,
                   f.nombre, f.apellido,
                   j.id_jornada, j.hora_desde as jornada_hora_desde, 
                   j.hora_hasta as jornada_hora_hasta, j.horas_totales as jornada_horas_totales,
                   j.justificacion as jornada_justificacion
            FROM $tablaMarcaciones m
            LEFT JOIN $tablaFuncionarios f ON m.cedula = f.cedula
            LEFT JOIN jornada_extraordinaria j ON m.cedula = j.cedula AND m.fecha = j.fecha AND j.estado = 'activa'";
    $params = [];
    $condiciones = [];
    
    // Filtrar por cédula específica (si viene de la lista de funcionarios)
    if (!empty($cedulaFiltro)) {
        $condiciones[] = "m.cedula = ?";
        $params[] = $cedulaFiltro;
    }
    
    // Filtrar por rango de fechas
    if (!empty($fechaDesde)) {
        $condiciones[] = "m.fecha >= ?";
        $params[] = $fechaDesde;
    }
    if (!empty($fechaHasta)) {
        $condiciones[] = "m.fecha <= ?";
        $params[] = $fechaHasta;
    }
    
    // Agregar condición de búsqueda si existe
    if (!empty($busqueda)) {
        $busquedaLimpia = '%' . $busqueda . '%';
        $condicionesBusqueda = [
            "m.cedula LIKE ?",
            "f.nombre LIKE ?",
            "f.apellido LIKE ?",
            "DATE_FORMAT(m.fecha, '%d/%m/%Y') LIKE ?",
            "TIME_FORMAT(m.hora_entrada, '%H:%i:%s') LIKE ?",
            "TIME_FORMAT(m.hora_salida, '%H:%i:%s') LIKE ?"
        ];
        $condiciones[] = "(" . implode(" OR ", $condicionesBusqueda) . ")";
        // Agregar parámetros para cada campo de búsqueda
        for ($i = 0; $i < 6; $i++) {
            $params[] = $busquedaLimpia;
        }
    }
    
    // Agregar condiciones WHERE si existen
    if (!empty($condiciones)) {
        $sql .= " WHERE " . implode(" AND ", $condiciones);
    }
    
    // Agregar ordenamiento
    $sql .= " ORDER BY ";
    if ($ordenarPor === 'nombre' || $ordenarPor === 'apellido') {
        // Para nombre y apellido, usar COALESCE para manejar NULL
        $sql .= "COALESCE(f.$ordenarPor, '') $direccion";
        if ($ordenarPor === 'apellido') {
            $sql .= ", COALESCE(f.nombre, '') $direccion, m.cedula $direccion";
        } else {
            $sql .= ", COALESCE(f.apellido, '') $direccion, m.cedula $direccion";
        }
    } else {
        // Para otros campos, ordenar directamente
        $sql .= "m.$ordenarPor $direccion";
        // Agregar ordenamiento secundario
        $sql .= ", m.fecha DESC, m.fecha_importacion DESC";
    }
    
    // Ejecutar consulta
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $marcaciones = $stmt->fetchAll();
    
    // Contar total (con búsqueda y filtros si aplica)
    $sqlCount = "SELECT COUNT(*) as total 
                 FROM $tablaMarcaciones m
                 LEFT JOIN $tablaFuncionarios f ON m.cedula = f.cedula";
    if (!empty($condiciones)) {
        $sqlCount .= " WHERE " . implode(" AND ", $condiciones);
    }
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Calcular sumas totales de horas contabilizadas y tardanzas
    // IMPORTANTE: La BD guarda horas reales, pero aquí calculamos horas contabilizadas para visualización
    $totalHorasContabilizadas = 0; // En minutos
    $totalTardanzasMinutos = 0; // En minutos
    
    // Si hay un funcionario específico, obtener su horario
    $hEntradaFunc = null;
    $hSalidaFunc = null;
    if (!empty($cedulaFiltro) && !$exFuncionario) {
        $stmtHorario = $db->prepare("SELECT TIME_FORMAT(h_entrada, '%H:%i:%s') as h_entrada, TIME_FORMAT(h_salida, '%H:%i:%s') as h_salida FROM funcionarios WHERE cedula = ?");
        $stmtHorario->execute([$cedulaFiltro]);
        $horarioFunc = $stmtHorario->fetch();
        if ($horarioFunc) {
            $hEntradaFunc = $horarioFunc['h_entrada'];
            $hSalidaFunc = $horarioFunc['h_salida'];
            // Normalizar formato
            if ($hEntradaFunc) {
                $hEntradaFunc = trim($hEntradaFunc);
                if (strlen($hEntradaFunc) == 5) {
                    $hEntradaFunc .= ':00';
                }
            }
        }
    }
    
    // Calcular horas contabilizadas para cada marcación
    // Primero calcular almuerzo desde todas_marcaciones
    foreach ($marcaciones as &$marcacion) {
        // Calcular almuerzo desde todas_marcaciones si existe
        $almuerzoSalida = null;
        $almuerzoEntrada = null;
        
        if (!empty($marcacion['todas_marcaciones'])) {
            // Parsear todas las marcaciones (separadas por comas)
            $horasRegistro = explode(',', $marcacion['todas_marcaciones']);
            $horasRegistro = array_map('trim', $horasRegistro);
            $horasRegistro = array_filter($horasRegistro); // Eliminar vacíos
            
            if (!empty($horasRegistro)) {
                try {
                    $resultadoAlmuerzo = detectarHorarioAlmuerzo($horasRegistro);
                    if ($resultadoAlmuerzo && is_array($resultadoAlmuerzo)) {
                        // La función retorna: 'entrada' = primera hora (sale a almorzar), 'salida' = segunda hora (regresa)
                        // En BD: almuerzo_salida = cuando sale, almuerzo_entrada = cuando regresa
                        $almuerzoSalida = isset($resultadoAlmuerzo['entrada']) ? $resultadoAlmuerzo['entrada'] : null;
                        $almuerzoEntrada = isset($resultadoAlmuerzo['salida']) ? $resultadoAlmuerzo['salida'] : null;
                    }
                } catch (Exception $e) {
                    // Si hay error, usar valores de BD si existen
                    $almuerzoSalida = $marcacion['almuerzo_salida'] ?? null;
                    $almuerzoEntrada = $marcacion['almuerzo_entrada'] ?? null;
                }
            }
        } else {
            // Si no hay todas_marcaciones, usar valores de BD si existen
            $almuerzoSalida = $marcacion['almuerzo_salida'] ?? null;
            $almuerzoEntrada = $marcacion['almuerzo_entrada'] ?? null;
        }
        
        // Actualizar los valores calculados en la marcación para mostrar
        $marcacion['almuerzo_salida_calc'] = $almuerzoSalida;
        $marcacion['almuerzo_entrada_calc'] = $almuerzoEntrada;
    }
    unset($marcacion); // Liberar referencia
    
    // Ahora calcular horas trabajadas
    foreach ($marcaciones as &$marcacion) {
        if (!empty($marcacion['hora_entrada']) && !empty($marcacion['hora_salida'])) {
            // Obtener horario del funcionario si no está filtrado por cédula
            if (empty($cedulaFiltro) && !$exFuncionario) {
                $stmtHorarioMarc = $db->prepare("SELECT TIME_FORMAT(h_entrada, '%H:%i:%s') as h_entrada, TIME_FORMAT(h_salida, '%H:%i:%s') as h_salida FROM funcionarios WHERE cedula = ?");
                $stmtHorarioMarc->execute([$marcacion['cedula']]);
                $horarioMarc = $stmtHorarioMarc->fetch();
                $hEntradaMarc = $horarioMarc ? ($horarioMarc['h_entrada'] ?? null) : null;
                $hSalidaMarc = $horarioMarc ? ($horarioMarc['h_salida'] ?? null) : null;
            } else {
                $hEntradaMarc = $hEntradaFunc;
                $hSalidaMarc = $hSalidaFunc;
            }
            
            // Normalizar formato del horario (asegurar formato HH:MM:SS)
            if ($hEntradaMarc) {
                $hEntradaMarc = trim($hEntradaMarc);
                if (strlen($hEntradaMarc) == 5) {
                    $hEntradaMarc .= ':00';
                }
            }
            
            // Guardar horario de entrada y salida en la marcación para usarlo después en la visualización
            $marcacion['h_entrada_func'] = $hEntradaMarc;
            if ($hSalidaMarc) {
                $hSalidaMarc = trim($hSalidaMarc);
                if (strlen($hSalidaMarc) == 5) {
                    $hSalidaMarc .= ':00';
                }
            }
            $marcacion['h_salida_func'] = $hSalidaMarc;
            
            // Verificar si hay jornada extraordinaria aprobada para esta fecha
            $tieneJornadaExtra = !empty($marcacion['id_jornada']);
            
            // Calcular horas contabilizadas
            if ($tieneJornadaExtra) {
                // Si hay jornada extraordinaria aprobada, usar las horas reales del reloj (sin límite del horario regular)
                $entrada = DateTime::createFromFormat('H:i:s', $marcacion['hora_entrada']);
                if (!$entrada) {
                    $entrada = DateTime::createFromFormat('H:i', $marcacion['hora_entrada']);
                }
                
                $salida = DateTime::createFromFormat('H:i:s', $marcacion['hora_salida']);
                if (!$salida) {
                    $salida = DateTime::createFromFormat('H:i', $marcacion['hora_salida']);
                }
                
                if ($entrada && $salida) {
                    // Calcular diferencia en minutos (horas reales del reloj)
                    $minutosEntrada = $entrada->format('H') * 60 + $entrada->format('i');
                    $minutosSalida = $salida->format('H') * 60 + $salida->format('i');
                    $minutosTrabajados = $minutosSalida - $minutosEntrada;
                    
                    if ($minutosTrabajados > 0) {
                        // Convertir a formato HH:MM:SS
                        $horas = floor($minutosTrabajados / 60);
                        $minutos = $minutosTrabajados % 60;
                        $marcacion['horas_contabilizadas'] = sprintf('%02d:%02d:00', $horas, $minutos);
                        $marcacion['tiempo_faltante_calc'] = '00:00:00'; // No hay tiempo faltante con jornada extraordinaria
                        
                        // Sumar minutos contabilizados a total
                        $totalHorasContabilizadas += ($horas * 60) + $minutos;
                        // No sumar tardanzas para jornada extraordinaria
                    } else {
                        $marcacion['horas_contabilizadas'] = '00:00:00';
                        $marcacion['tiempo_faltante_calc'] = '00:00:00';
                    }
                } else {
                    $marcacion['horas_contabilizadas'] = '00:00:00';
                    $marcacion['tiempo_faltante_calc'] = '00:00:00';
                }
            } else {
                // Sin jornada extraordinaria: usar lógica normal (limitada al horario del funcionario)
                $resultado = calcularHorasTrabajadas($marcacion['hora_entrada'], $marcacion['hora_salida'], $hEntradaMarc, $hSalidaMarc);
                if ($resultado) {
                    $marcacion['horas_contabilizadas'] = $resultado['horas_contabilizadas'];
                    $marcacion['tiempo_faltante_calc'] = $resultado['tiempo_faltante'];
                    
                    // Sumar minutos contabilizados
                    $partes = explode(':', $resultado['horas_contabilizadas']);
                    $horasCont = (int)($partes[0] ?? 0);
                    $minutosCont = (int)($partes[1] ?? 0);
                    $totalHorasContabilizadas += ($horasCont * 60) + $minutosCont;
                    
                    // Sumar minutos de tardanza
                    $partesTard = explode(':', $resultado['tiempo_faltante']);
                    $horasTard = (int)($partesTard[0] ?? 0);
                    $minutosTard = (int)($partesTard[1] ?? 0);
                    $totalTardanzasMinutos += ($horasTard * 60) + $minutosTard;
                } else {
                    $marcacion['horas_contabilizadas'] = '00:00:00';
                    $marcacion['tiempo_faltante_calc'] = '00:00:00';
                }
            }
        } else {
            $marcacion['horas_contabilizadas'] = '00:00:00';
            $marcacion['tiempo_faltante_calc'] = '00:00:00';
            
            // Obtener horario del funcionario aunque no haya hora_entrada/salida para usarlo en la visualización
            if (empty($cedulaFiltro) && !$exFuncionario) {
                $stmtHorarioMarc = $db->prepare("SELECT TIME_FORMAT(h_entrada, '%H:%i:%s') as h_entrada FROM funcionarios WHERE cedula = ?");
                $stmtHorarioMarc->execute([$marcacion['cedula']]);
                $horarioMarc = $stmtHorarioMarc->fetch();
                $hEntradaMarc = $horarioMarc ? ($horarioMarc['h_entrada'] ?? null) : null;
                // Normalizar formato
                if ($hEntradaMarc) {
                    $hEntradaMarc = trim($hEntradaMarc);
                    if (strlen($hEntradaMarc) == 5) {
                        $hEntradaMarc .= ':00';
                    }
                }
            } else {
                $hEntradaMarc = $hEntradaFunc;
            }
            $marcacion['h_entrada_func'] = $hEntradaMarc;
        }
    }
    unset($marcacion); // Liberar referencia
    
    // Convertir minutos totales a formato HH:MM
    $horasTotal = floor($totalHorasContabilizadas / 60);
    $minutosTotal = $totalHorasContabilizadas % 60;
    $totalHorasTrabajadas = sprintf('%02d:%02d:00', $horasTotal, $minutosTotal);
    
    $horasTardTotal = floor($totalTardanzasMinutos / 60);
    $minutosTardTotal = $totalTardanzasMinutos % 60;
    $totalTardanzas = sprintf('%02d:%02d:00', $horasTardTotal, $minutosTardTotal);
    
    // Obtener nombre del funcionario y horario si hay filtro por cédula
    $nombreFuncionario = '';
    $nombreCompleto = '';
    $hEntrada = null;
    $hSalida = null;
    $funExtraActual = null;
    // Verificar si los campos de derechos existen en la tabla
    // Primero verificar campos nuevos (TIME), luego campos antiguos (DECIMAL)
    $camposDerechosExisten = false;
    $usarCamposTime = false; // Indica si usar campos TIME o DECIMAL
    if (!empty($cedulaFiltro) && !$exFuncionario) {
        try {
            // Verificar si existen los nuevos campos TIME
            $stmtCheckTime = $db->query("SHOW COLUMNS FROM funcionarios LIKE 'permisos_justificados_acumulados'");
            if ($stmtCheckTime->rowCount() > 0) {
                $usarCamposTime = true;
                $camposDerechosExisten = true;
            } else {
                // Verificar campos antiguos DECIMAL
                $stmtCheck = $db->query("SHOW COLUMNS FROM funcionarios LIKE 'vacaciones_dias_acumulados'");
                $camposDerechosExisten = $stmtCheck->rowCount() > 0;
            }
        } catch (PDOException $e) {
            $camposDerechosExisten = false;
            $usarCamposTime = false;
        }
    }
    
    if (!empty($cedulaFiltro)) {
        // Para funcionarios activos, obtener de tabla funcionarios (incluye h_entrada y h_salida)
        // Para ex-funcionarios, solo obtener nombre y apellido
        if (!$exFuncionario) {
            // Construir query dinámicamente según si los campos de derechos existen
            if ($camposDerechosExisten) {
                if ($usarCamposTime) {
                    // Usar nuevos campos TIME
                    $stmtFunc = $db->prepare("SELECT nombre, apellido, h_entrada, h_salida, fun_extra, 
                                                     vacaciones_dias_acumulados, 
                                                     permisos_justificados_acumulados,
                                                     permisos_no_justificados_acumulados,
                                                     ano_derechos
                                              FROM funcionarios WHERE cedula = ?");
                } else {
                    // Usar campos antiguos DECIMAL
                    $stmtFunc = $db->prepare("SELECT nombre, apellido, h_entrada, h_salida, fun_extra, 
                                                     vacaciones_dias_acumulados, 
                                                     permisos_justificados_dias_acumulados, 
                                                     permisos_justificados_horas_acumuladas,
                                                     permisos_no_justificados_dias_acumulados,
                                                     permisos_no_justificados_horas_acumuladas,
                                                     ano_derechos
                                              FROM funcionarios WHERE cedula = ?");
                }
            } else {
                $stmtFunc = $db->prepare("SELECT nombre, apellido, h_entrada, h_salida, fun_extra 
                                          FROM funcionarios WHERE cedula = ?");
            }
        } else {
            $stmtFunc = $db->prepare("SELECT nombre, apellido FROM ex_funcionarios WHERE cedula = ?");
        }
        $stmtFunc->execute([$cedulaFiltro]);
        $funcionario = $stmtFunc->fetch();
        if ($funcionario) {
            $nombreCompleto = trim(($funcionario['nombre'] ?? '') . ' ' . ($funcionario['apellido'] ?? ''));
            $prefijo = $exFuncionario ? ' (Ex-Funcionario)' : '';
            $nombreFuncionario = $nombreCompleto . ' - ' . $cedulaFiltro . $prefijo;
            
            // Obtener horarios solo para funcionarios activos
            if (!$exFuncionario) {
                $hEntrada = $funcionario['h_entrada'] ?? null;
                $hSalida = $funcionario['h_salida'] ?? null;
                // Si no tiene horario, usar valores por defecto
                if ($hEntrada === null) $hEntrada = '08:00:00';
                if ($hSalida === null) $hSalida = '16:00:00';
                
                // Obtener fun_extra y mapear valores antiguos a nuevos
                $funExtraActual = $funcionario['fun_extra'] ?? null;
                $mapeoValores = ['Jefe' => 'Director', 'VIP' => 'Director', 'cesante' => 'Cesante'];
                if ($funExtraActual && isset($mapeoValores[$funExtraActual])) {
                    $funExtraActual = $mapeoValores[$funExtraActual];
                }
                
                // Obtener datos de derechos solo si los campos existen
                if ($camposDerechosExisten) {
                    if ($usarCamposTime) {
                        // Convertir campos TIME a días y horas
                        $permisosJustificados = timeToDiasHoras($funcionario['permisos_justificados_acumulados'] ?? null);
                        $permisosNoJustificados = timeToDiasHoras($funcionario['permisos_no_justificados_acumulados'] ?? null);
                        
                        $derechosFuncionario = [
                            'vacaciones_dias' => (int)($funcionario['vacaciones_dias_acumulados'] ?? 0),
                            'permisos_justificados_dias' => $permisosJustificados['dias'],
                            'permisos_justificados_horas' => $permisosJustificados['horas'],
                            'permisos_no_justificados_dias' => $permisosNoJustificados['dias'],
                            'permisos_no_justificados_horas' => $permisosNoJustificados['horas'],
                            'ano_derechos' => $funcionario['ano_derechos'] ?? date('Y')
                        ];
                    } else {
                        // Usar campos antiguos DECIMAL
                        $derechosFuncionario = [
                            'vacaciones_dias' => (int)($funcionario['vacaciones_dias_acumulados'] ?? 0),
                            'permisos_justificados_dias' => (int)($funcionario['permisos_justificados_dias_acumulados'] ?? 0),
                            'permisos_justificados_horas' => (int)($funcionario['permisos_justificados_horas_acumuladas'] ?? 0),
                            'permisos_no_justificados_dias' => (int)($funcionario['permisos_no_justificados_dias_acumulados'] ?? 0),
                            'permisos_no_justificados_horas' => (int)($funcionario['permisos_no_justificados_horas_acumuladas'] ?? 0),
                            'ano_derechos' => $funcionario['ano_derechos'] ?? date('Y')
                        ];
                    }
                } else {
                    // Si los campos no existen, inicializar con valores por defecto
                    $derechosFuncionario = [
                        'vacaciones_dias' => 0,
                        'permisos_justificados_dias' => 0,
                        'permisos_justificados_horas' => 0,
                        'permisos_no_justificados_dias' => 0,
                        'permisos_no_justificados_horas' => 0,
                        'ano_derechos' => date('Y')
                    ];
                }
            }
        }
    }
    
} catch (PDOException $e) {
    mostrarMensaje("Error al cargar marcaciones: " . $e->getMessage(), 'error');
    $marcaciones = [];
    $totalRegistros = 0;
    $totalHorasTrabajadas = '00:00:00';
    $totalTardanzas = '00:00:00';
    $nombreFuncionario = '';
    $nombreCompleto = '';
    $hEntrada = '08:00:00';
    $hSalida = '16:00:00';
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header" style="display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem;">
    <h2 style="margin: 0; flex-shrink: 0;">Marcaciones Biométricas<?php echo !empty($nombreFuncionario) ? ' - ' . htmlspecialchars($nombreCompleto) . ' - ' . htmlspecialchars($cedulaFiltro) : ''; ?></h2>
    <?php if (!empty($cedulaFiltro) && !$exFuncionario): 
        // Convertir horas a formato 12 horas para visualización
        $hEntradaFormato = $hEntrada ? date('g:i', strtotime($hEntrada)) : '08:00';
        $hEntradaAMPM = $hEntrada ? (date('H', strtotime($hEntrada)) < 12 ? 'a.m.' : 'p.m.') : 'a.m.';
        $hSalidaFormato = $hSalida ? date('g:i', strtotime($hSalida)) : '04:00';
        $hSalidaAMPM = $hSalida ? (date('H', strtotime($hSalida)) < 12 ? 'a.m.' : 'p.m.') : 'p.m.';
    ?>
    <form id="form-horario" style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: nowrap; margin-left: auto;">
        <span style="font-weight: bold; font-size: 1.05em;">Horario de:</span>
        <input type="time" 
               id="h_entrada" 
               name="h_entrada" 
               value="<?php echo $hEntrada ? date('H:i', strtotime($hEntrada)) : '08:00'; ?>" 
               style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; width: 140px; font-size: 1.1em;"
               required>
        <span style="font-weight: bold; font-size: 1.05em;">hasta</span>
        <input type="time" 
               id="h_salida" 
               name="h_salida" 
               value="<?php echo $hSalida ? date('H:i', strtotime($hSalida)) : '16:00'; ?>" 
               style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; width: 140px; font-size: 1.1em;"
               required>
        <button type="button" 
                id="btn-guardar-horario" 
                class="btn btn-primary" 
                style="margin-left: 0.6rem; padding: 0.5rem 1.2rem; font-size: 1.05em;">
            Guardar
        </button>
        <span id="mensaje-horario" style="margin-left: 0.6rem; color: #28a745; font-weight: bold; font-size: 1.05em; display: none;"></span>
    </form>
    <?php endif; ?>
</div>

    <?php if (empty($cedulaFiltro)): ?>
    <div style="flex-shrink: 0;">
        <form method="GET" action="" style="display: flex; gap: 0.5rem; align-items: center;">
            <input type="text" name="buscar" placeholder="Buscar por cédula, nombre, apellido..." 
                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                   style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; min-width: 250px;">
            <?php if (!empty($fechaDesde)): ?>
            <input type="hidden" name="fecha_desde" value="<?php echo htmlspecialchars($fechaDesde); ?>">
            <?php endif; ?>
            <?php if (!empty($fechaHasta)): ?>
            <input type="hidden" name="fecha_hasta" value="<?php echo htmlspecialchars($fechaHasta); ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Formulario de filtros de fecha -->
<?php if (!empty($cedulaFiltro) || !empty($fechaDesde) || !empty($fechaHasta)): ?>
<form method="GET" action="" class="search-form" style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem;">
    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; justify-content: space-between;">
        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
            <?php if (!empty($cedulaFiltro)): ?>
            <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($cedulaFiltro); ?>">
            <?php endif; ?>
            <?php if ($exFuncionario): ?>
            <input type="hidden" name="ex_funcionario" value="1">
            <?php endif; ?>
            <?php if (empty($cedulaFiltro)): ?>
            <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>">
            <?php endif; ?>
            <div style="min-width: 150px;">
                <label style="display: block; font-size: 0.9em; margin-bottom: 0.25rem; color: #666;">Desde:</label>
                <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fechaDesde); ?>" 
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px;">
            </div>
            <div style="min-width: 150px;">
                <label style="display: block; font-size: 0.9em; margin-bottom: 0.25rem; color: #666;">Hasta:</label>
                <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fechaHasta); ?>" 
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px;">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Buscar</button>
                <?php if (!empty($busqueda) || !empty($fechaDesde) || !empty($fechaHasta)): ?>
                    <a href="<?php echo BASE_URL; ?>/pages/marcaciones/listar.php<?php 
                        $params = [];
                        if (!empty($cedulaFiltro)) $params['cedula'] = $cedulaFiltro;
                        if ($exFuncionario) $params['ex_funcionario'] = '1';
                        echo !empty($params) ? '?' . http_build_query($params) : '';
                    ?>" class="btn btn-secondary" style="font-weight: bold; color: #17a2b8;">Limpiar</a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Panel de botones fun_extra (dentro del bloque gris, alineado a la derecha) -->
        <?php if (!empty($cedulaFiltro) && !$exFuncionario && Auth::isAdmin()): ?>
        <div class="botones-fun-extra" style="display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-end;">
            <!-- Primera fila: Director, Manual, Cesante -->
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" 
                        class="btn-fun-extra <?php echo ($funExtraActual === 'Director' || $funExtraActual === 'VIP') ? 'activo' : ''; ?>" 
                        data-valor="Director"
                        data-cedula="<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>">
                    Director
                </button>
                <button type="button" 
                        class="btn-fun-extra <?php echo $funExtraActual === 'Manual' ? 'activo' : ''; ?>" 
                        data-valor="Manual"
                        data-cedula="<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>">
                    Manual
                </button>
                <button type="button" 
                        class="btn-fun-extra <?php echo $funExtraActual === 'Cesante' ? 'activo' : ''; ?>" 
                        data-valor="Cesante"
                        data-cedula="<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>">
                    Cesante
                </button>
            </div>
            <!-- Segunda fila: Préstamo, Lic. Sueldo, Lic. Sin Sueldo -->
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" 
                        class="btn-fun-extra <?php echo $funExtraActual === 'Préstamo' ? 'activo' : ''; ?>" 
                        data-valor="Préstamo"
                        data-cedula="<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>">
                    Préstamo
                </button>
                <button type="button" 
                        class="btn-fun-extra <?php echo $funExtraActual === 'Lic. Sueldo' ? 'activo' : ''; ?>" 
                        data-valor="Lic. Sueldo"
                        data-cedula="<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>">
                    Lic. Sueldo
                </button>
                <button type="button" 
                        class="btn-fun-extra <?php echo $funExtraActual === 'Lic. Sin Sueldo' ? 'activo' : ''; ?>" 
                        data-valor="Lic. Sin Sueldo"
                        data-cedula="<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>">
                    Lic. Sin Sueldo
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>

<!-- Tabla de marcaciones -->
<?php if (count($marcaciones) > 0): ?>
    <style>
        /* Reducir interlineado de la tabla de marcaciones */
        .data-table th,
        .data-table td {
            padding: 0.4rem 0.5rem !important;
            line-height: 1.3;
        }
        .data-table th {
            padding: 0.5rem 0.5rem !important;
            text-align: center !important;
        }
        .data-table th a {
            justify-content: center !important;
        }
    </style>
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; background: white;">
            <thead>
                <tr style="background: #343a40; color: white;">
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        ID
                    </th>
                    <?php if (empty($cedulaFiltro)): ?>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('cedula', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                            Cédula <?php echo iconoOrdenamiento('cedula'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('nombre', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                            Nombre <?php echo iconoOrdenamiento('nombre'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('apellido', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                            Apellido <?php echo iconoOrdenamiento('apellido'); ?>
                        </a>
                    </th>
                    <?php endif; ?>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Fecha
                    </th>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Hora Entrada
                    </th>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Alm. Salida
                    </th>
                    <th style="padding: 0.5rem 0.75rem; text-align: center; border: 1px solid #dee2e6; width: 80px; min-width: 80px;">
                        Alm.
                    </th>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Alm. Entrada
                    </th>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('hora_salida', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                            Hora Salida <?php echo iconoOrdenamiento('hora_salida'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.5rem 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Horas Trabajadas
                    </th>
                    <th style="padding: 0.5rem 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Horas Dia.
                    </th>
                    <th style="padding: 0.5rem 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Tardanza/Irregular
                    </th>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Fecha Importación
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($marcaciones as $marcacion): 
                    // Determinar estilo de la fila
                    $estiloFila = '';
                    $tieneJornadaExtra = !empty($marcacion['id_jornada']);
                    
                    if ($tieneJornadaExtra) {
                        // Fondo azul con letras blancas para jornada extraordinaria
                        $estiloFila = 'background-color: #2196F3 !important; color: white;';
                    } elseif (empty($marcacion['hora_entrada']) || empty($marcacion['hora_salida'])) {
                        // Fondo amarillo para marcaciones incompletas
                        $estiloFila = 'background: #fff3cd;';
                    }
                ?>
                    <tr style="border-bottom: 1px solid #dee2e6; <?php echo $estiloFila; ?>" <?php echo $tieneJornadaExtra ? 'class="fila-jornada-extra"' : ''; ?>>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; <?php echo $tieneJornadaExtra ? 'color: white !important;' : ''; ?>"><?php echo htmlspecialchars($marcacion['id_marcacion']); ?></td>
                        <?php if (empty($cedulaFiltro)): ?>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; font-weight: bold; <?php echo $tieneJornadaExtra ? 'color: white !important;' : ''; ?>"><?php echo htmlspecialchars($marcacion['cedula']); ?></td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; <?php echo $tieneJornadaExtra ? 'color: white !important;' : ''; ?>"><?php echo htmlspecialchars($marcacion['nombre'] ?? '-'); ?></td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; <?php echo $tieneJornadaExtra ? 'color: white !important;' : ''; ?>"><?php echo htmlspecialchars($marcacion['apellido'] ?? '-'); ?></td>
                        <?php endif; ?>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; <?php echo $tieneJornadaExtra ? 'color: white !important;' : ''; ?>">
                            <?php 
                            if ($marcacion['fecha']) {
                                $fecha = new DateTime($marcacion['fecha']);
                                echo $fecha->format('d/m/Y');
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php 
                            // Si tiene jornada extraordinaria, usar fondo azul, sino aplicar lógica de tardanza
                            if ($tieneJornadaExtra) {
                                echo 'background-color: #2196F3 !important; color: white !important;';
                            } else {
                                echo 'background-color: #BBDEFB;';
                                if ($marcacion['hora_entrada']) {
                                    // Usar el horario del funcionario ya obtenido en el bucle anterior
                                    $horaLimiteFunc = $marcacion['h_entrada_func'] ?? null;
                                    
                                    // Si hay horario del funcionario, comparar con él
                                    if ($horaLimiteFunc) {
                                        // Limpiar y normalizar formatos de hora (eliminar espacios, asegurar formato H:i:s)
                                        $horaEntrada = trim($marcacion['hora_entrada']);
                                        $horaLimiteFunc = trim($horaLimiteFunc);
                                        
                                        // Normalizar formato: asegurar que tenga segundos
                                        if (strlen($horaEntrada) == 5) {
                                            $horaEntrada .= ':00'; // Agregar segundos si no los tiene
                                        }
                                        if (strlen($horaLimiteFunc) == 5) {
                                            $horaLimiteFunc .= ':00'; // Agregar segundos si no los tiene
                                        }
                                        
                                        // Convertir a segundos desde medianoche usando comparación directa
                                        // Dividir en horas, minutos y segundos
                                        $partesEntrada = explode(':', $horaEntrada);
                                        $partesLimite = explode(':', $horaLimiteFunc);
                                        
                                        $hE = isset($partesEntrada[0]) ? (int)trim($partesEntrada[0]) : 0;
                                        $mE = isset($partesEntrada[1]) ? (int)trim($partesEntrada[1]) : 0;
                                        // IGNORAR segundos - solo comparar horas y minutos
                                        
                                        $hL = isset($partesLimite[0]) ? (int)trim($partesLimite[0]) : 0;
                                        $mL = isset($partesLimite[1]) ? (int)trim($partesLimite[1]) : 0;
                                        // IGNORAR segundos - solo comparar horas y minutos
                                        
                                        // Convertir a MINUTOS desde medianoche (ignorando segundos)
                                        // Esto permite que cualquier segundo dentro del mismo minuto sea puntual
                                        // Ejemplo: Si horario es 8:00, entonces 8:00:00, 8:00:10, 8:00:59 → PUNTUAL
                                        $minutosEntrada = (int)($hE * 60 + $mE);
                                        $minutosLimite = (int)($hL * 60 + $mL);
                                        
                                        // Solo es tarde si es DESPUÉS del minuto exacto
                                        // Ejemplo: Si horario es 8:00, entonces:
                                        // - 8:00:00, 8:00:10, 8:00:59 → PUNTUAL (mismo minuto)
                                        // - 8:01:00 en adelante → TARDE (siguiente minuto)
                                        if ($minutosEntrada > $minutosLimite) {
                                            echo 'background-color: #ffcccc !important; color: #721c24; font-weight: bold;';
                                        }
                                    } else {
                                        // Si no hay horario, usar 08:00:00 por defecto
                                        $horaEntrada = trim($marcacion['hora_entrada']);
                                        if (strlen($horaEntrada) == 5) {
                                            $horaEntrada .= ':00';
                                        }
                                        $partesEntrada = explode(':', $horaEntrada);
                                        $hE = isset($partesEntrada[0]) ? (int)trim($partesEntrada[0]) : 0;
                                        $mE = isset($partesEntrada[1]) ? (int)trim($partesEntrada[1]) : 0;
                                        // IGNORAR segundos - solo comparar horas y minutos
                                        $minutosEntrada = (int)($hE * 60 + $mE);
                                        $minutosLimite = 8 * 60 + 0; // 08:00 = 480 minutos
                                        // Solo es tarde si es DESPUÉS del minuto 8:00 (8:01 en adelante)
                                        if ($minutosEntrada > $minutosLimite) {
                                            echo 'background-color: #ffcccc !important; color: #721c24; font-weight: bold;';
                                        }
                                    }
                                }
                            }
                        ?>">
                            <?php 
                            if ($marcacion['hora_entrada']) {
                                $hora = new DateTime($marcacion['hora_entrada']);
                                // Formato 12 horas con a.m./p.m.
                                $horaFormato = $hora->format('g:i');
                                $ampm = strtolower($hora->format('A')); // am o pm
                                $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                echo $horaFormato . ' ' . $ampm;
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <!-- Columna Alm. Salida -->
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php echo $tieneJornadaExtra ? 'background-color: #2196F3 !important; color: white !important;' : 'background-color: #E3F2FD;'; ?> <?php
                            // Usar valores calculados si existen, sino usar valores de BD
                            $almuerzoSalida = $marcacion['almuerzo_salida_calc'] ?? $marcacion['almuerzo_salida'] ?? null;
                            $almuerzoEntrada = $marcacion['almuerzo_entrada_calc'] ?? $marcacion['almuerzo_entrada'] ?? null;
                            $mostrarError = false;
                            
                            if (empty($almuerzoSalida) || empty($almuerzoEntrada)) {
                                $mostrarError = true;
                            } else {
                                // Calcular diferencia en minutos
                                // almuerzo_salida = cuando sale (más temprano), almuerzo_entrada = cuando regresa (más tarde)
                                $salida = DateTime::createFromFormat('H:i:s', $almuerzoSalida);
                                $entrada = DateTime::createFromFormat('H:i:s', $almuerzoEntrada);
                                if ($entrada && $salida) {
                                    $diff = $entrada->getTimestamp() - $salida->getTimestamp(); // entrada - salida (positivo)
                                    $minutos = (int)($diff / 60);
                                    if ($minutos > 60 || $minutos < 0) {
                                        $mostrarError = true;
                                    }
                                }
                            }
                            
                            if ($mostrarError) {
                                echo 'background-color: #ffcccc !important; color: #721c24; font-weight: bold;';
                            }
                        ?>">
                            <?php 
                            if ($almuerzoSalida) {
                                $hora = new DateTime($almuerzoSalida);
                                $horaFormato = $hora->format('g:i');
                                $ampm = strtolower($hora->format('A'));
                                $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                echo $horaFormato . ' ' . $ampm;
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <!-- Columna Alm. (Diferencia) -->
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php echo $tieneJornadaExtra ? 'background-color: #2196F3 !important; color: white !important;' : 'background-color: #fff3cd; color: #856404;'; ?> font-weight: bold; width: 80px; min-width: 80px;">
                            <?php 
                            // Calcular diferencia entre Alm. Entrada - Alm. Salida
                            if ($almuerzoSalida && $almuerzoEntrada) {
                                $salida = DateTime::createFromFormat('H:i:s', $almuerzoSalida);
                                $entrada = DateTime::createFromFormat('H:i:s', $almuerzoEntrada);
                                if ($entrada && $salida) {
                                    $diff = $entrada->getTimestamp() - $salida->getTimestamp();
                                    $minutos = (int)($diff / 60);
                                    if ($minutos > 0) {
                                        $horas = floor($minutos / 60);
                                        $mins = $minutos % 60;
                                        if ($horas > 0) {
                                            echo sprintf('%d:%02d', $horas, $mins);
                                        } else {
                                            echo sprintf('%d min', $mins);
                                        }
                                    } else {
                                        echo '-';
                                    }
                                } else {
                                    echo '-';
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <!-- Columna Alm. Entrada -->
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php echo $tieneJornadaExtra ? 'background-color: #2196F3 !important; color: white !important;' : 'background-color: #E3F2FD;'; ?> <?php
                            // Solo aplicar error si no tiene jornada extraordinaria
                            if ($mostrarError && !$tieneJornadaExtra) {
                                echo 'background-color: #ffcccc !important; color: #721c24; font-weight: bold;';
                            }
                        ?>">
                            <?php 
                            if ($almuerzoEntrada) {
                                $hora = new DateTime($almuerzoEntrada);
                                $horaFormato = $hora->format('g:i');
                                $ampm = strtolower($hora->format('A'));
                                $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                echo $horaFormato . ' ' . $ampm;
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php echo $tieneJornadaExtra ? 'background-color: #2196F3 !important; color: white !important;' : 'background-color: #BBDEFB;'; ?>">
                            <?php 
                            if ($marcacion['hora_salida']) {
                                $hora = new DateTime($marcacion['hora_salida']);
                                // Formato 12 horas con a.m./p.m.
                                $horaFormato = $hora->format('g:i');
                                $ampm = strtolower($hora->format('A')); // am o pm
                                $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                echo $horaFormato . ' ' . $ampm;
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; background-color: #90CAF9; <?php 
                            // Fondo rojo solo si hay tiempo faltante calculado
                            if (isset($marcacion['tiempo_faltante_calc']) && $marcacion['tiempo_faltante_calc'] !== '00:00:00') {
                                echo 'background-color: #ffcccc !important; color: #721c24; font-weight: bold;';
                            }
                        ?>">
                            <?php 
                            // Mostrar horas contabilizadas (para visualización)
                            if (isset($marcacion['horas_contabilizadas']) && $marcacion['horas_contabilizadas'] !== '00:00:00') {
                                $partes = explode(':', $marcacion['horas_contabilizadas']);
                                echo sprintf('%d:%02d', (int)($partes[0] ?? 0), (int)($partes[1] ?? 0));
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php echo $tieneJornadaExtra ? 'background-color: #2196F3 !important; color: white !important;' : 'background-color: #fff3cd; color: #856404;'; ?> font-weight: bold;" valign="top">
                            <?php 
                            // Calcular horas trabajadas según si hay jornada extraordinaria o no
                            if (!empty($marcacion['hora_entrada']) && !empty($marcacion['hora_salida'])) {
                                // #region agent log
                                $logFile = __DIR__ . '/../../.cursor/debug.log';
                                $logEntry = json_encode([
                                    'sessionId' => 'debug-session',
                                    'runId' => 'run1',
                                    'hypothesisId' => 'A',
                                    'location' => 'listar.php:918',
                                    'message' => 'Calculando Horas/Trabajo',
                                    'data' => [
                                        'id_marcacion' => $marcacion['id_marcacion'],
                                        'tieneJornadaExtra' => $tieneJornadaExtra,
                                        'hora_entrada' => $marcacion['hora_entrada'],
                                        'hora_salida' => $marcacion['hora_salida'],
                                        'h_entrada_func' => $marcacion['h_entrada_func'] ?? null,
                                        'h_salida_func' => $marcacion['h_salida_func'] ?? null
                                    ],
                                    'timestamp' => time() * 1000
                                ]) . "\n";
                                file_put_contents($logFile, $logEntry, FILE_APPEND);
                                // #endregion agent log
                                
                                if ($tieneJornadaExtra) {
                                    // CON jornada extraordinaria: verificar si se superpone con horario regular
                                    $hEntradaFunc = !empty($marcacion['h_entrada_func']) ? trim($marcacion['h_entrada_func']) : null;
                                    $hSalidaFunc = !empty($marcacion['h_salida_func']) ? trim($marcacion['h_salida_func']) : null;
                                    
                                    // Normalizar formato del horario
                                    if ($hEntradaFunc && strlen($hEntradaFunc) == 5) {
                                        $hEntradaFunc .= ':00';
                                    }
                                    if ($hSalidaFunc && strlen($hSalidaFunc) == 5) {
                                        $hSalidaFunc .= ':00';
                                    }
                                    
                                    // Verificar si la jornada extraordinaria se superpone con el horario regular
                                    $seSuperponen = false;
                                    if ($hEntradaFunc && $hSalidaFunc && !empty($marcacion['jornada_hora_desde']) && !empty($marcacion['jornada_hora_hasta'])) {
                                        $horaDesdeExtra = trim($marcacion['jornada_hora_desde']);
                                        $horaHastaExtra = trim($marcacion['jornada_hora_hasta']);
                                        if (strlen($horaDesdeExtra) == 5) {
                                            $horaDesdeExtra .= ':00';
                                        }
                                        if (strlen($horaHastaExtra) == 5) {
                                            $horaHastaExtra .= ':00';
                                        }
                                        
                                        $seSuperponen = rangosTiempoSeSuperponen(
                                            $hEntradaFunc,
                                            $hSalidaFunc,
                                            $horaDesdeExtra,
                                            $horaHastaExtra
                                        );
                                    }
                                    
                                    // #region agent log
                                    $logEntry = json_encode([
                                        'sessionId' => 'debug-session',
                                        'runId' => 'run1',
                                        'hypothesisId' => 'B',
                                        'location' => 'listar.php:965',
                                        'message' => 'Verificacion superposicion',
                                        'data' => [
                                            'id_marcacion' => $marcacion['id_marcacion'],
                                            'seSuperponen' => $seSuperponen,
                                            'hEntradaFunc' => $hEntradaFunc,
                                            'hSalidaFunc' => $hSalidaFunc,
                                            'horaDesdeExtra' => $horaDesdeExtra ?? null,
                                            'horaHastaExtra' => $horaHastaExtra ?? null
                                        ],
                                        'timestamp' => time() * 1000
                                    ]) . "\n";
                                    file_put_contents($logFile, $logEntry, FILE_APPEND);
                                    // #endregion agent log
                                    
                                    if (!$seSuperponen && !empty($hEntradaFunc) && !empty($hSalidaFunc)) {
                                        // NO se superponen: Sumar horas normales (limitadas al horario regular) + horas aprobadas
                                        $resultadoHorasNormales = calcularHorasTrabajadas(
                                            $marcacion['hora_entrada'], 
                                            $marcacion['hora_salida'], 
                                            $hEntradaFunc, 
                                            $hSalidaFunc
                                        );
                                        
                                        // #region agent log
                                        $logEntry = json_encode([
                                            'sessionId' => 'debug-session',
                                            'runId' => 'run1',
                                            'hypothesisId' => 'C',
                                            'location' => 'listar.php:985',
                                            'message' => 'Calculo horas normales',
                                            'data' => [
                                                'id_marcacion' => $marcacion['id_marcacion'],
                                                'horas_contabilizadas' => $resultadoHorasNormales ? $resultadoHorasNormales['horas_contabilizadas'] : null,
                                                'jornada_horas_totales' => $marcacion['jornada_horas_totales'] ?? null
                                            ],
                                            'timestamp' => time() * 1000
                                        ]) . "\n";
                                        file_put_contents($logFile, $logEntry, FILE_APPEND);
                                        // #endregion agent log
                                        
                                        if ($resultadoHorasNormales && $resultadoHorasNormales['horas_contabilizadas'] !== '00:00:00') {
                                            $partesNormales = explode(':', $resultadoHorasNormales['horas_contabilizadas']);
                                            $horasNormales = (int)($partesNormales[0] ?? 0);
                                            $minutosNormales = (int)($partesNormales[1] ?? 0);
                                            $minutosTotalesNormales = ($horasNormales * 60) + $minutosNormales;
                                            
                                            $horasTotalesExtra = $marcacion['jornada_horas_totales'] ?? '00:00:00';
                                            $partesExtra = explode(':', $horasTotalesExtra);
                                            $horasExtra = (int)($partesExtra[0] ?? 0);
                                            $minutosExtra = (int)($partesExtra[1] ?? 0);
                                            $minutosTotalesExtra = ($horasExtra * 60) + $minutosExtra;
                                            
                                            $minutosTotalesTrabajados = $minutosTotalesNormales + $minutosTotalesExtra;
                                            
                                            // #region agent log
                                            $logEntry = json_encode([
                                                'sessionId' => 'debug-session',
                                                'runId' => 'run1',
                                                'hypothesisId' => 'D',
                                                'location' => 'listar.php:1007',
                                                'message' => 'Suma final',
                                                'data' => [
                                                    'id_marcacion' => $marcacion['id_marcacion'],
                                                    'minutosTotalesNormales' => $minutosTotalesNormales,
                                                    'minutosTotalesExtra' => $minutosTotalesExtra,
                                                    'minutosTotalesTrabajados' => $minutosTotalesTrabajados
                                                ],
                                                'timestamp' => time() * 1000
                                            ]) . "\n";
                                            file_put_contents($logFile, $logEntry, FILE_APPEND);
                                            // #endregion agent log
                                            
                                            if ($minutosTotalesTrabajados > 0) {
                                                $horasFinales = floor($minutosTotalesTrabajados / 60);
                                                $minutosFinales = $minutosTotalesTrabajados % 60;
                                                echo sprintf('%d:%02d', $horasFinales, $minutosFinales);
                                            } else {
                                                echo '<span style="color: #856404;">-</span>';
                                            }
                                        } else {
                                            // Si no hay horas normales, mostrar solo horas aprobadas
                                            $horasTotalesExtra = $marcacion['jornada_horas_totales'] ?? '00:00:00';
                                            $partesExtra = explode(':', $horasTotalesExtra);
                                            $horasExtra = (int)($partesExtra[0] ?? 0);
                                            $minutosExtra = (int)($partesExtra[1] ?? 0);
                                            if ($horasExtra > 0 || $minutosExtra > 0) {
                                                echo sprintf('%d:%02d', $horasExtra, $minutosExtra);
                                            } else {
                                                echo '<span style="color: #856404;">-</span>';
                                            }
                                        }
                                    } else {
                                        // Se superponen: Mostrar horas reales del biométrico
                                        $entrada = DateTime::createFromFormat('H:i:s', $marcacion['hora_entrada']);
                                        if (!$entrada) {
                                            $entrada = DateTime::createFromFormat('H:i', $marcacion['hora_entrada']);
                                        }
                                        
                                        $salida = DateTime::createFromFormat('H:i:s', $marcacion['hora_salida']);
                                        if (!$salida) {
                                            $salida = DateTime::createFromFormat('H:i', $marcacion['hora_salida']);
                                        }
                                        
                                        if ($entrada && $salida) {
                                            $minutosEntrada = $entrada->format('H') * 60 + $entrada->format('i');
                                            $minutosSalida = $salida->format('H') * 60 + $salida->format('i');
                                            $minutosTrabajados = $minutosSalida - $minutosEntrada;
                                            
                                            if ($minutosTrabajados > 0) {
                                                $horas = floor($minutosTrabajados / 60);
                                                $minutos = $minutosTrabajados % 60;
                                                echo sprintf('%d:%02d', $horas, $minutos);
                                            } else {
                                                echo '<span style="color: #856404;">-</span>';
                                            }
                                        } else {
                                            echo '<span style="color: #856404;">-</span>';
                                        }
                                    }
                                } else {
                                    // SIN jornada extraordinaria: usar calcularHorasTrabajadas() limitado al horario regular
                                    $hEntradaFunc = !empty($marcacion['h_entrada_func']) ? $marcacion['h_entrada_func'] : null;
                                    $hSalidaFunc = !empty($marcacion['h_salida_func']) ? $marcacion['h_salida_func'] : null;
                                    
                                    $resultado = calcularHorasTrabajadas(
                                        $marcacion['hora_entrada'], 
                                        $marcacion['hora_salida'], 
                                        $hEntradaFunc, 
                                        $hSalidaFunc
                                    );
                                    
                                    // #region agent log
                                    $logEntry = json_encode([
                                        'sessionId' => 'debug-session',
                                        'runId' => 'run1',
                                        'hypothesisId' => 'E',
                                        'location' => 'listar.php:1063',
                                        'message' => 'Sin jornada extra - calculo',
                                        'data' => [
                                            'id_marcacion' => $marcacion['id_marcacion'],
                                            'horas_contabilizadas' => $resultado ? $resultado['horas_contabilizadas'] : null
                                        ],
                                        'timestamp' => time() * 1000
                                    ]) . "\n";
                                    file_put_contents($logFile, $logEntry, FILE_APPEND);
                                    // #endregion agent log
                                    
                                    if ($resultado && $resultado['horas_contabilizadas'] !== '00:00:00') {
                                        $partes = explode(':', $resultado['horas_contabilizadas']);
                                        $horas = (int)($partes[0] ?? 0);
                                        $minutos = (int)($partes[1] ?? 0);
                                        echo sprintf('%d:%02d', $horas, $minutos);
                                    } else {
                                        echo '<span style="color: #856404;">-</span>';
                                    }
                                }
                            } else {
                                echo '<span style="color: #856404;">-</span>';
                            }
                            ?>
                        </td>
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php 
                            // Fondo rojo siempre que haya tiempo faltante calculado
                            if (isset($marcacion['tiempo_faltante_calc']) && $marcacion['tiempo_faltante_calc'] !== '00:00:00') {
                                echo 'background-color: #ffcccc; color: #721c24; font-weight: bold;';
                            }
                        ?>">
                            <?php 
                            if (isset($marcacion['tiempo_faltante_calc']) && $marcacion['tiempo_faltante_calc'] !== '00:00:00') {
                                $partes = explode(':', $marcacion['tiempo_faltante_calc']);
                                echo sprintf('%d:%02d', (int)($partes[0] ?? 0), (int)($partes[1] ?? 0));
                            } else {
                                echo '00:00';
                            }
                            ?>
                        </td>
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; <?php echo $tieneJornadaExtra ? 'color: white !important;' : ''; ?>">
                            <?php 
                            if ($marcacion['fecha_importacion']) {
                                $fecha = new DateTime($marcacion['fecha_importacion']);
                                echo $fecha->format('d/m/Y H:i');
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 1rem; color: #666; display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
        <div>
            <strong>Total de registros:</strong> <strong><?php echo $totalRegistros; ?></strong>
        </div>
        <div>
            <strong>Total Horas Trabajadas:</strong> 
            <span style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem;">
                <?php 
                // Mostrar el valor formateado (ya viene en formato HH:MM:SS)
                if (!empty($totalHorasTrabajadas)) {
                    // Extraer horas y minutos del string
                    $partes = explode(':', $totalHorasTrabajadas);
                    $horasInt = (int)($partes[0] ?? 0);
                    $minutosInt = (int)($partes[1] ?? 0);
                    // Mostrar en formato HH:MM (puede ser más de 24 horas)
                    echo sprintf('%d:%02d', $horasInt, $minutosInt);
                } else {
                    echo '00:00';
                }
                ?>
            </span>
        </div>
        <div>
            <strong>Total Tardanza/Irregular:</strong> 
            <span style="font-size: 1.1em; font-weight: bold; color: #721c24; margin-left: 0.5rem;">
                <?php 
                // Mostrar el valor formateado (ya viene en formato HH:MM:SS)
                if (!empty($totalTardanzas)) {
                    // Extraer horas y minutos del string
                    $partes = explode(':', $totalTardanzas);
                    $horasInt = (int)($partes[0] ?? 0);
                    $minutosInt = (int)($partes[1] ?? 0);
                    // Mostrar en formato HH:MM
                    echo sprintf('%d:%02d', $horasInt, $minutosInt);
                } else {
                    echo '00:00';
                }
                ?>
            </span>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info" style="padding: 1rem; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; color: #0c5460;">
        <i class="fas fa-info-circle"></i> No se encontraron marcaciones<?php echo !empty($busqueda) || !empty($fechaDesde) || !empty($fechaHasta) ? ' que coincidan con los filtros' : ''; ?>.
    </div>
<?php endif; ?>

<!-- Sección de Derechos/Permisos/Vacaciones -->
<?php if (!empty($cedulaFiltro) && !$exFuncionario && $derechosFuncionario !== null && Auth::isAdmin()): ?>
<div style="margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
    <h3 style="margin-top: 0; margin-bottom: 1rem; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 0.5rem;">
        <i class="fas fa-calendar-check"></i> Permisos/Vacaciones
    </h3>
    
    <form id="form-derechos-funcionario" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label for="ano_derechos" style="font-weight: bold; color: #555; white-space: nowrap;">
                Año:
            </label>
            <input type="number" 
                   id="ano_derechos" 
                   name="ano_derechos" 
                   value="<?php echo htmlspecialchars($derechosFuncionario['ano_derechos']); ?>" 
                   min="2000" 
                   max="2100"
                   style="width: 80px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px;">
        </div>
        
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label for="vacaciones_dias" style="font-weight: bold; color: #555; white-space: nowrap;">
                Vacaciones:
                <i class="fas fa-info-circle" style="color: #17a2b8; margin-left: 0.25rem;" 
                   title="Días de vacaciones acumulados. Se toman por día completo. No afecta horas trabajadas del día."></i>
            </label>
            <input type="number" 
                   id="vacaciones_dias" 
                   name="vacaciones_dias" 
                   value="<?php echo sprintf('%02d', (int)$derechosFuncionario['vacaciones_dias']); ?>" 
                   step="1" 
                   min="0"
                   max="99"
                   style="width: 60px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; text-align: center;">
        </div>
        
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label for="permisos_justificados_dias" style="font-weight: bold; color: #555; white-space: nowrap;">
                Permisos Justificados - Días:
                <i class="fas fa-clock" style="color: #ff9800; margin-left: 0.25rem;" 
                   title="Días de permisos justificados. Pueden tomarse por día completo o por horas. Afecta horas trabajadas."></i>
            </label>
            <input type="number" 
                   id="permisos_justificados_dias" 
                   name="permisos_justificados_dias" 
                   value="<?php echo sprintf('%02d', (int)$derechosFuncionario['permisos_justificados_dias']); ?>" 
                   step="1" 
                   min="0"
                   max="99"
                   style="width: 60px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; text-align: center;">
            <label for="permisos_justificados_horas" style="font-weight: bold; color: #555; white-space: nowrap; margin-left: 0.5rem;">
                Horas:
            </label>
            <input type="number" 
                   id="permisos_justificados_horas" 
                   name="permisos_justificados_horas" 
                   value="<?php echo sprintf('%02d', (int)$derechosFuncionario['permisos_justificados_horas']); ?>" 
                   step="1" 
                   min="0"
                   max="23"
                   style="width: 60px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; text-align: center;">
        </div>
        
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <label for="permisos_no_justificados_dias" style="font-weight: bold; color: #555; white-space: nowrap;">
                Permisos No Justificados - Días:
                <i class="fas fa-clock" style="color: #dc3545; margin-left: 0.25rem;" 
                   title="Días de permisos no justificados. Pueden tomarse por día completo o por horas. Afecta horas trabajadas."></i>
            </label>
            <input type="number" 
                   id="permisos_no_justificados_dias" 
                   name="permisos_no_justificados_dias" 
                   value="<?php echo sprintf('%02d', (int)$derechosFuncionario['permisos_no_justificados_dias']); ?>" 
                   step="1" 
                   min="0"
                   max="99"
                   style="width: 60px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; text-align: center;">
            <label for="permisos_no_justificados_horas" style="font-weight: bold; color: #555; white-space: nowrap; margin-left: 0.5rem;">
                Horas:
            </label>
            <input type="number" 
                   id="permisos_no_justificados_horas" 
                   name="permisos_no_justificados_horas" 
                   value="<?php echo sprintf('%02d', (int)$derechosFuncionario['permisos_no_justificados_horas']); ?>" 
                   step="1" 
                   min="0"
                   max="23"
                   style="width: 60px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; text-align: center;">
        </div>
        
        <div style="margin-left: auto;">
            <button type="button" 
                    id="btn-guardar-derechos" 
                    class="btn btn-primary" 
                    style="padding: 0.75rem 2rem; font-size: 1.05em;">
                <i class="fas fa-save"></i> Guardar
            </button>
            <span id="mensaje-derechos" style="margin-left: 1rem; color: #28a745; font-weight: bold; font-size: 1.05em; display: none;"></span>
        </div>
    </form>
    
    <div style="margin-top: 1rem; padding: 0.75rem; background: #e7f3ff; border-left: 4px solid #2196F3; border-radius: 3px;">
        <strong style="color: #1976d2;">Nota:</strong>
        <ul style="margin: 0.5rem 0 0 1.5rem; color: #555;">
            <li><strong>Vacaciones:</strong> Se toman por día completo. No afectan las horas trabajadas del día.</li>
            <li><strong>Permisos Justificados y No Justificados:</strong> Pueden tomarse por día completo o por horas. Estos campos <strong>afectan el cálculo de horas trabajadas</strong>.</li>
            <li>En Panamá, 1 día laboral = 7 horas.</li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnGuardarDerechos = document.getElementById('btn-guardar-derechos');
    const mensajeDerechos = document.getElementById('mensaje-derechos');
    const formDerechos = document.getElementById('form-derechos-funcionario');
    
    if (btnGuardarDerechos) {
        btnGuardarDerechos.addEventListener('click', function() {
            // Obtener valores del formulario (convertir a enteros)
            const datos = {
                cedula: '<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>',
                ano: parseInt(document.getElementById('ano_derechos').value) || null,
                vacaciones_dias: parseInt(document.getElementById('vacaciones_dias').value) || 0,
                permisos_justificados_dias: parseInt(document.getElementById('permisos_justificados_dias').value) || 0,
                permisos_justificados_horas: parseInt(document.getElementById('permisos_justificados_horas').value) || 0,
                permisos_no_justificados_dias: parseInt(document.getElementById('permisos_no_justificados_dias').value) || 0,
                permisos_no_justificados_horas: parseInt(document.getElementById('permisos_no_justificados_horas').value) || 0
            };
            
            // Deshabilitar botón mientras se procesa
            btnGuardarDerechos.disabled = true;
            btnGuardarDerechos.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            mensajeDerechos.style.display = 'none';
            
            // Enviar petición AJAX
            fetch('<?php echo BASE_URL; ?>/pages/funcionarios/actualizar_derechos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(datos)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mensajeDerechos.textContent = '✓ ' + (data.mensaje || 'Derechos actualizados correctamente');
                    mensajeDerechos.style.color = '#28a745';
                    mensajeDerechos.style.display = 'inline';
                    
                    // Ocultar mensaje después de 3 segundos
                    setTimeout(() => {
                        mensajeDerechos.style.display = 'none';
                    }, 3000);
                } else {
                    mensajeDerechos.textContent = '✗ Error: ' + (data.error || 'No se pudo actualizar');
                    mensajeDerechos.style.color = '#dc3545';
                    mensajeDerechos.style.display = 'inline';
                    alert('Error: ' + (data.error || 'No se pudo actualizar los derechos'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mensajeDerechos.textContent = '✗ Error al comunicarse con el servidor';
                mensajeDerechos.style.color = '#dc3545';
                mensajeDerechos.style.display = 'inline';
                alert('Error al comunicarse con el servidor');
            })
            .finally(() => {
                // Rehabilitar botón
                btnGuardarDerechos.disabled = false;
                btnGuardarDerechos.innerHTML = '<i class="fas fa-save"></i> Guardar Derechos';
            });
        });
        
        // Formatear inputs para mostrar siempre dos dígitos al perder el foco
        const inputsDerechos = ['vacaciones_dias', 'permisos_justificados_dias', 'permisos_justificados_horas', 
                                'permisos_no_justificados_dias', 'permisos_no_justificados_horas'];
        inputsDerechos.forEach(function(inputId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('blur', function() {
                    const valor = parseInt(this.value) || 0;
                    // Para horas, limitar a 23 máximo
                    if (inputId.includes('horas')) {
                        const valorFinal = Math.min(valor, 23);
                        this.value = String(valorFinal).padStart(2, '0');
                    } else {
                        this.value = String(valor).padStart(2, '0');
                    }
                });
            }
        });
    }
});
</script>
<?php endif; ?>

<?php if (!empty($cedulaFiltro) && !$exFuncionario): ?>
<style>
.panel-fun-extra {
    margin-top: 1rem;
    display: flex;
    justify-content: flex-end;
    width: 100%;
}

.botones-fun-extra {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-end;
}

.btn-fun-extra {
    padding: 0.5rem 1rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: white;
    color: #333;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9em;
    font-weight: 500;
    min-width: 90px;
}

.btn-fun-extra:hover {
    background: #f0f0f0;
    border-color: #999;
}

.btn-fun-extra.activo {
    background: #28a745;
    color: white;
    border-color: #1e7e34;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
    transform: translateY(1px);
}

.btn-fun-extra:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<script>
// Manejar guardado de horario
document.addEventListener('DOMContentLoaded', function() {
    const btnGuardar = document.getElementById('btn-guardar-horario');
    const hEntrada = document.getElementById('h_entrada');
    const hSalida = document.getElementById('h_salida');
    const mensajeHorario = document.getElementById('mensaje-horario');
    
    if (btnGuardar) {
        btnGuardar.addEventListener('click', function() {
            const entrada = hEntrada.value;
            const salida = hSalida.value;
            const cedula = '<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>';
            
            // Validar que las horas sean válidas
            if (!entrada || !salida) {
                alert('Por favor, complete ambos campos de horario');
                return;
            }
            
            // Validar que la salida sea después de la entrada
            if (entrada >= salida) {
                alert('La hora de salida debe ser posterior a la hora de entrada');
                return;
            }
            
            // Deshabilitar botón mientras se procesa
            btnGuardar.disabled = true;
            btnGuardar.textContent = 'Guardando...';
            mensajeHorario.style.display = 'none';
            
            // Hacer petición AJAX
            fetch('<?php echo BASE_URL; ?>/pages/marcaciones/actualizar_horario.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cedula: cedula,
                    h_entrada: entrada + ':00',
                    h_salida: salida + ':00'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mensajeHorario.textContent = 'Horario actualizado correctamente';
                    mensajeHorario.style.display = 'inline';
                    mensajeHorario.style.color = '#28a745';
                    
                    // Recargar la página después de 1 segundo para actualizar visualización
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    alert('Error: ' + (data.error || 'No se pudo actualizar el horario'));
                    btnGuardar.disabled = false;
                    btnGuardar.textContent = 'Guardar';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al comunicarse con el servidor');
                btnGuardar.disabled = false;
                btnGuardar.textContent = 'Guardar';
            });
        });
    }
    
    // Manejar clicks en botones fun_extra
    const botonesFunExtra = document.querySelectorAll('.btn-fun-extra');
    botonesFunExtra.forEach(function(boton) {
        boton.addEventListener('click', function() {
            const cedula = this.getAttribute('data-cedula');
            const valor = this.getAttribute('data-valor');
            const estaActivo = this.classList.contains('activo');
            
            // Si ya está activo, desactivarlo (enviar null)
            if (estaActivo) {
                actualizarFunExtra(cedula, null, this);
            } else {
                // Si es Cesante, pedir confirmación
                if (valor === 'Cesante') {
                    const confirmar = confirm('Este funcionario ya no estará trabajando en la entidad. ¿Sí o No?');
                    if (!confirmar) {
                        // Si el usuario cancela, no hacer nada
                        return;
                    }
                }
                
                // Desactivar todos los botones primero
                botonesFunExtra.forEach(function(btn) {
                    btn.classList.remove('activo');
                });
                
                // Activar el botón seleccionado
                this.classList.add('activo');
                
                // Enviar actualización
                actualizarFunExtra(cedula, valor, this);
            }
        });
    });
    
    function actualizarFunExtra(cedula, valor, boton) {
        // Deshabilitar botón mientras se procesa
        boton.disabled = true;
        
        // Hacer petición AJAX
        fetch('<?php echo BASE_URL; ?>/pages/funcionarios/actualizar_fun_extra.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cedula: cedula,
                fun_extra: valor
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Si se marcó como Cesante, recargar la página (el funcionario ya no existe en funcionarios)
                if (valor === 'Cesante') {
                    alert('Funcionario movido a ex-funcionarios. La página se recargará.');
                    window.location.reload();
                }
                // Si se desactivó (valor null), remover clase activo de todos
                if (valor === null) {
                    botonesFunExtra.forEach(function(btn) {
                        btn.classList.remove('activo');
                    });
                }
            } else {
                // Revertir cambios visuales en caso de error
                if (valor === null) {
                    // Si se intentó desactivar, volver a activar
                    boton.classList.add('activo');
                } else {
                    // Si se intentó activar, desactivar y reactivar el que estaba antes
                    boton.classList.remove('activo');
                }
                alert('Error: ' + (data.message || 'No se pudo actualizar el campo'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Revertir cambios visuales
            if (valor === null) {
                boton.classList.add('activo');
            } else {
                boton.classList.remove('activo');
            }
            alert('Error al comunicarse con el servidor');
        })
        .finally(() => {
            // Rehabilitar botón
            boton.disabled = false;
        });
    }
});
</script>
<?php endif; ?>

<?php 
// Sección de Jornadas Extraordinarias
// Solo mostrar si hay filtro por cédula o por fechas
if (!empty($cedulaFiltro) || !empty($fechaDesde) || !empty($fechaHasta)): 
    try {
        $sqlJornadas = "SELECT j.id_jornada, j.cedula, j.fecha, j.hora_desde, j.hora_hasta, 
                               j.horas_totales, j.justificacion, j.fecha_registro,
                               f.nombre, f.apellido,
                               TIME_FORMAT(f.h_entrada, '%H:%i:%s') as h_entrada_funcionario,
                               TIME_FORMAT(f.h_salida, '%H:%i:%s') as h_salida_funcionario,
                               CASE WHEN m.id_marcacion IS NOT NULL THEN 1 ELSE 0 END as tiene_marcacion,
                               TIME_FORMAT(m.hora_entrada, '%H:%i:%s') as hora_entrada_marcacion,
                               TIME_FORMAT(m.hora_salida, '%H:%i:%s') as hora_salida_marcacion
                        FROM jornada_extraordinaria j
                        LEFT JOIN funcionarios f ON j.cedula = f.cedula
                        LEFT JOIN marcaciones m ON j.cedula = m.cedula AND j.fecha = m.fecha
                        WHERE j.estado = 'activa'";
        
        $paramsJornadas = [];
        $condicionesJornadas = [];
        
        if (!empty($cedulaFiltro)) {
            $condicionesJornadas[] = "j.cedula = ?";
            $paramsJornadas[] = $cedulaFiltro;
        }
        
        if (!empty($fechaDesde)) {
            $condicionesJornadas[] = "j.fecha >= ?";
            $paramsJornadas[] = $fechaDesde;
        }
        
        if (!empty($fechaHasta)) {
            $condicionesJornadas[] = "j.fecha <= ?";
            $paramsJornadas[] = $fechaHasta;
        }
        
        if (!empty($condicionesJornadas)) {
            $sqlJornadas .= " AND " . implode(" AND ", $condicionesJornadas);
        }
        
        $sqlJornadas .= " ORDER BY j.fecha DESC, j.fecha_registro DESC";
        
        $stmtJornadas = $db->prepare($sqlJornadas);
        $stmtJornadas->execute($paramsJornadas);
        $jornadasExtraordinarias = $stmtJornadas->fetchAll();
        
        if (count($jornadasExtraordinarias) > 0):
?>
<style>
.jornadas-extraordinarias-section {
    margin-top: 2rem;
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.jornadas-extraordinarias-section h3 {
    color: #2196F3;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #2196F3;
}

.jornadas-extras-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.jornadas-extras-table th {
    background: #2196F3;
    color: white;
    padding: 0.75rem;
    text-align: left;
    border: 1px solid #1976D2;
}

.jornadas-extras-table td {
    padding: 0.75rem;
    border: 1px solid #dee2e6;
}

.jornadas-extras-table tr.jornada-con-marcacion {
    background-color: #E3F2FD;
}

.jornadas-extras-table tr.jornada-sin-marcacion {
    background-color: #ffcccc;
    color: #721c24;
}

.jornadas-extras-table tr.jornada-con-marcacion td {
    color: #1976D2;
    font-weight: 500;
}

.jornadas-extras-table tr.jornada-sin-marcacion td {
    color: #721c24;
}
</style>

<div class="jornadas-extraordinarias-section">
    <h3><i class="fas fa-clock"></i> Jornadas Extraordinarias del Período</h3>
    <table class="jornadas-extras-table">
        <thead>
            <tr>
                <?php if (empty($cedulaFiltro)): ?>
                <th>Cédula</th>
                <th>Nombre</th>
                <?php endif; ?>
                <th>Fecha</th>
                <th>Hora Desde</th>
                <th>Hora Hasta</th>
                <th>Horas/J.Extra.</th>
                <th>Horas Trabajadas</th>
                <th>Justificación</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jornadasExtraordinarias as $jornada):
                $claseFila = $jornada['tiene_marcacion'] ? 'jornada-con-marcacion' : 'jornada-sin-marcacion';
                
                // Obtener horario del funcionario si no está en el resultado del JOIN
                $hEntradaFuncJornada = !empty($jornada['h_entrada_funcionario']) ? trim($jornada['h_entrada_funcionario']) : null;
                $hSalidaFuncJornada = !empty($jornada['h_salida_funcionario']) ? trim($jornada['h_salida_funcionario']) : null;
                
                // #region agent log
                $logFile = __DIR__ . '/../../.cursor/debug.log';
                $logEntry = json_encode([
                    'sessionId' => 'debug-session',
                    'runId' => 'run1',
                    'hypothesisId' => 'F',
                    'location' => 'listar.php:1760',
                    'message' => 'Obteniendo horario funcionario - antes de consulta',
                    'data' => [
                        'id_jornada' => $jornada['id_jornada'],
                        'cedula' => $jornada['cedula'],
                        'hEntradaFuncJornada_antes' => $hEntradaFuncJornada,
                        'hSalidaFuncJornada_antes' => $hSalidaFuncJornada
                    ],
                    'timestamp' => time() * 1000
                ]) . "\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
                // #endregion agent log
                
                // Si no tenemos el horario del JOIN, intentar obtenerlo
                if (empty($hEntradaFuncJornada) || empty($hSalidaFuncJornada)) {
                    // Si estamos filtrados por cédula, usar el horario que ya se obtuvo al inicio
                    if (!empty($cedulaFiltro) && !$exFuncionario && isset($hEntradaFunc) && isset($hSalidaFunc) && !empty($hEntradaFunc) && !empty($hSalidaFunc)) {
                        $hEntradaFuncJornada = $hEntradaFunc;
                        $hSalidaFuncJornada = $hSalidaFunc;
                    } else {
                        // Buscar el horario usando la misma lógica que en la tabla principal
                        if (!empty($jornada['cedula'])) {
                            $stmtHorarioJornada = $db->prepare("SELECT TIME_FORMAT(h_entrada, '%H:%i:%s') as h_entrada, TIME_FORMAT(h_salida, '%H:%i:%s') as h_salida FROM funcionarios WHERE cedula = ? LIMIT 1");
                            $stmtHorarioJornada->execute([$jornada['cedula']]);
                            $horarioJornada = $stmtHorarioJornada->fetch();
                            
                            // #region agent log
                            $logEntry = json_encode([
                                'sessionId' => 'debug-session',
                                'runId' => 'run1',
                                'hypothesisId' => 'G',
                                'location' => 'listar.php:1787',
                                'message' => 'Resultado consulta horario funcionario',
                                'data' => [
                                    'id_jornada' => $jornada['id_jornada'],
                                    'cedula' => $jornada['cedula'],
                                    'cedulaFiltro' => $cedulaFiltro ?? null,
                                    'hEntradaFunc_global' => $hEntradaFunc ?? null,
                                    'hSalidaFunc_global' => $hSalidaFunc ?? null,
                                    'horarioJornada_fetch' => $horarioJornada !== false,
                                    'horarioJornada' => $horarioJornada,
                                    'h_entrada' => $horarioJornada ? ($horarioJornada['h_entrada'] ?? null) : null,
                                    'h_salida' => $horarioJornada ? ($horarioJornada['h_salida'] ?? null) : null
                                ],
                                'timestamp' => time() * 1000
                            ]) . "\n";
                            file_put_contents($logFile, $logEntry, FILE_APPEND);
                            // #endregion agent log
                            
                            if ($horarioJornada && !empty($horarioJornada['h_entrada']) && !empty($horarioJornada['h_salida'])) {
                                $hEntradaFuncJornada = $horarioJornada['h_entrada'];
                                $hSalidaFuncJornada = $horarioJornada['h_salida'];
                            } else {
                                // Si aún no encontramos, intentar buscar por la cédula normalizada
                                // La cédula podría estar en formato diferente (con/sin guiones)
                                $cedulaSinGuiones = str_replace('-', '', $jornada['cedula']);
                                $stmtHorarioJornada2 = $db->prepare("SELECT TIME_FORMAT(h_entrada, '%H:%i:%s') as h_entrada, TIME_FORMAT(h_salida, '%H:%i:%s') as h_salida FROM funcionarios WHERE REPLACE(cedula, '-', '') = ? LIMIT 1");
                                $stmtHorarioJornada2->execute([$cedulaSinGuiones]);
                                $horarioJornada2 = $stmtHorarioJornada2->fetch();
                                
                                if ($horarioJornada2 && !empty($horarioJornada2['h_entrada']) && !empty($horarioJornada2['h_salida'])) {
                                    $hEntradaFuncJornada = $horarioJornada2['h_entrada'];
                                    $hSalidaFuncJornada = $horarioJornada2['h_salida'];
                                }
                            }
                        }
                    }
                }
                
                // Guardar en el array para usar después
                $jornada['h_entrada_funcionario'] = $hEntradaFuncJornada;
                $jornada['h_salida_funcionario'] = $hSalidaFuncJornada;
            ?>
                <tr class="<?php echo $claseFila; ?>">
                    <?php if (empty($cedulaFiltro)): ?>
                    <td><?php echo htmlspecialchars($jornada['cedula']); ?></td>
                    <td><?php echo htmlspecialchars(($jornada['nombre'] ?? '') . ' ' . ($jornada['apellido'] ?? '')); ?></td>
                    <?php endif; ?>
                    <td><?php echo date('d/m/Y', strtotime($jornada['fecha'])); ?></td>
<<<<<<< Updated upstream
                    <td>
                        <?php 
                        if ($jornada['hora_desde']) {
                            $hora = new DateTime($jornada['hora_desde']);
                            // Formato 12 horas con a.m./p.m.
                            $horaFormato = $hora->format('g:i');
                            $ampm = strtolower($hora->format('A')); // am o pm
                            $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                            echo $horaFormato . ' ' . $ampm;
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php 
                        if ($jornada['hora_hasta']) {
                            $hora = new DateTime($jornada['hora_hasta']);
                            // Formato 12 horas con a.m./p.m.
                            $horaFormato = $hora->format('g:i');
                            $ampm = strtolower($hora->format('A')); // am o pm
                            $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                            echo $horaFormato . ' ' . $ampm;
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
=======
                    <td><?php 
                        $horaDesde = DateTime::createFromFormat('H:i:s', $jornada['hora_desde']);
                        if (!$horaDesde) {
                            $horaDesde = DateTime::createFromFormat('H:i', $jornada['hora_desde']);
                        }
                        echo $horaDesde ? $horaDesde->format('g:i a') : $jornada['hora_desde'];
                    ?></td>
                    <td><?php 
                        $horaHasta = DateTime::createFromFormat('H:i:s', $jornada['hora_hasta']);
                        if (!$horaHasta) {
                            $horaHasta = DateTime::createFromFormat('H:i', $jornada['hora_hasta']);
                        }
                        echo $horaHasta ? $horaHasta->format('g:i a') : $jornada['hora_hasta'];
                    ?></td>
>>>>>>> Stashed changes
                    <td><strong><?php echo $jornada['horas_totales'] ?? '-'; ?></strong></td>
                    <td>
                        <?php 
                        // Buscar la marcación correspondiente en el array ya procesado para obtener horas_contabilizadas
                        $marcacionCorrespondiente = null;
                        foreach ($marcaciones as $marc) {
                            if ($marc['cedula'] === $jornada['cedula'] && $marc['fecha'] === $jornada['fecha']) {
                                $marcacionCorrespondiente = $marc;
                                break;
                            }
                        }
                        
                        // Calcular horas trabajadas según si se superpone o no con horario regular
                        if (!empty($jornada['hora_entrada_marcacion']) && !empty($jornada['hora_salida_marcacion'])) {
                            // #region agent log
                            $logFile = __DIR__ . '/../../.cursor/debug.log';
                            $logEntry = json_encode([
                                'sessionId' => 'debug-session',
                                'runId' => 'run1',
                                'hypothesisId' => 'A',
                                'location' => 'listar.php:1770',
                                'message' => 'Calculando Horas Trabajadas - Jornadas Extra',
                                'data' => [
                                    'id_jornada' => $jornada['id_jornada'],
                                    'fecha' => $jornada['fecha'],
                                    'hora_entrada_marcacion' => $jornada['hora_entrada_marcacion'],
                                    'hora_salida_marcacion' => $jornada['hora_salida_marcacion'],
                                    'h_entrada_funcionario' => $jornada['h_entrada_funcionario'] ?? null,
                                    'h_salida_funcionario' => $jornada['h_salida_funcionario'] ?? null,
                                    'hora_desde' => $jornada['hora_desde'] ?? null,
                                    'hora_hasta' => $jornada['hora_hasta'] ?? null,
                                    'horas_totales' => $jornada['horas_totales'] ?? null
                                ],
                                'timestamp' => time() * 1000
                            ]) . "\n";
                            file_put_contents($logFile, $logEntry, FILE_APPEND);
                            // #endregion agent log
                            
                            // Obtener horario del funcionario (usar variables locales que ya obtuvimos)
                            $hEntradaFunc = !empty($hEntradaFuncJornada) ? trim($hEntradaFuncJornada) : null;
                            $hSalidaFunc = !empty($hSalidaFuncJornada) ? trim($hSalidaFuncJornada) : null;
                            
                            // #region agent log
                            $logEntry = json_encode([
                                'sessionId' => 'debug-session',
                                'runId' => 'run1',
                                'hypothesisId' => 'H',
                                'location' => 'listar.php:1875',
                                'message' => 'Variables locales antes de calcular',
                                'data' => [
                                    'id_jornada' => $jornada['id_jornada'],
                                    'fecha' => $jornada['fecha'],
                                    'hEntradaFuncJornada' => $hEntradaFuncJornada,
                                    'hSalidaFuncJornada' => $hSalidaFuncJornada,
                                    'hEntradaFunc' => $hEntradaFunc,
                                    'hSalidaFunc' => $hSalidaFunc
                                ],
                                'timestamp' => time() * 1000
                            ]) . "\n";
                            file_put_contents($logFile, $logEntry, FILE_APPEND);
                            // #endregion agent log
                            
                            // Normalizar formato del horario (asegurar formato HH:MM:SS)
                            if ($hEntradaFunc && strlen($hEntradaFunc) == 5) {
                                $hEntradaFunc .= ':00';
                            }
                            if ($hSalidaFunc && strlen($hSalidaFunc) == 5) {
                                $hSalidaFunc .= ':00';
                            }
                            
                            // Verificar si la jornada extraordinaria se superpone con el horario regular
                            $seSuperponen = false;
                            $debugSuperpone = 'NO_HORARIO';
                            if ($hEntradaFunc && $hSalidaFunc && !empty($jornada['hora_desde']) && !empty($jornada['hora_hasta'])) {
                                // Normalizar formato de jornada extraordinaria
                                $horaDesdeExtra = trim($jornada['hora_desde']);
                                $horaHastaExtra = trim($jornada['hora_hasta']);
                                if (strlen($horaDesdeExtra) == 5) {
                                    $horaDesdeExtra .= ':00';
                                }
                                if (strlen($horaHastaExtra) == 5) {
                                    $horaHastaExtra .= ':00';
                                }
                                
                                // Comparar rangos: horario regular vs jornada extraordinaria
                                $seSuperponen = rangosTiempoSeSuperponen(
                                    $hEntradaFunc,
                                    $hSalidaFunc,
                                    $horaDesdeExtra,
                                    $horaHastaExtra
                                );
                                // DEBUG: Guardar valores para mostrar en el debug
                                $debugSuperpone = $seSuperponen ? 'SI' : 'NO';
                            }
                            
                            // #region agent log
                            $logEntry = json_encode([
                                'sessionId' => 'debug-session',
                                'runId' => 'run1',
                                'hypothesisId' => 'B',
                                'location' => 'listar.php:1815',
                                'message' => 'Verificacion superposicion - Jornadas Extra',
                                'data' => [
                                    'id_jornada' => $jornada['id_jornada'],
                                    'fecha' => $jornada['fecha'],
                                    'seSuperponen' => $seSuperponen,
                                    'debugSuperpone' => $debugSuperpone,
                                    'hEntradaFunc' => $hEntradaFunc,
                                    'hSalidaFunc' => $hSalidaFunc,
                                    'horaDesdeExtra' => $horaDesdeExtra ?? null,
                                    'horaHastaExtra' => $horaHastaExtra ?? null
                                ],
                                'timestamp' => time() * 1000
                            ]) . "\n";
                            file_put_contents($logFile, $logEntry, FILE_APPEND);
                            // #endregion agent log
                            
                            // Verificar si NO se superponen
                            if (!$seSuperponen) {
                                // Usar horas_contabilizadas de la marcación correspondiente si está disponible
                                $minutosTotalesNormales = 0;
                                if ($marcacionCorrespondiente && !empty($marcacionCorrespondiente['horas_contabilizadas']) && $marcacionCorrespondiente['horas_contabilizadas'] !== '00:00:00') {
                                    // Usar el valor ya calculado de la marcación
                                    $partesNormales = explode(':', $marcacionCorrespondiente['horas_contabilizadas']);
                                    $horasNormales = (int)($partesNormales[0] ?? 0);
                                    $minutosNormales = (int)($partesNormales[1] ?? 0);
                                    $minutosTotalesNormales = ($horasNormales * 60) + $minutosNormales;
                                } else {
                                    // Si no tenemos la marcación, intentar calcular con el horario si está disponible
                                    $resultadoHorasNormales = null;
                                    if (!empty($hEntradaFunc) && !empty($hSalidaFunc)) {
                                        $resultadoHorasNormales = calcularHorasTrabajadas(
                                            $jornada['hora_entrada_marcacion'], 
                                            $jornada['hora_salida_marcacion'], 
                                            $hEntradaFunc, 
                                            $hSalidaFunc
                                        );
                                    }
                                    
                                    if ($resultadoHorasNormales && $resultadoHorasNormales['horas_contabilizadas'] !== '00:00:00') {
                                        $partesNormales = explode(':', $resultadoHorasNormales['horas_contabilizadas']);
                                        $horasNormales = (int)($partesNormales[0] ?? 0);
                                        $minutosNormales = (int)($partesNormales[1] ?? 0);
                                        $minutosTotalesNormales = ($horasNormales * 60) + $minutosNormales;
                                    }
                                }
                                
                                // #region agent log
                                $logEntry = json_encode([
                                    'sessionId' => 'debug-session',
                                    'runId' => 'run1',
                                    'hypothesisId' => 'C',
                                    'location' => 'listar.php:1900',
                                    'message' => 'Calculo horas normales - Jornadas Extra',
                                    'data' => [
                                        'id_jornada' => $jornada['id_jornada'],
                                        'fecha' => $jornada['fecha'],
                                        'tieneMarcacionCorrespondiente' => $marcacionCorrespondiente !== null,
                                        'horas_contabilizadas_marcacion' => $marcacionCorrespondiente ? ($marcacionCorrespondiente['horas_contabilizadas'] ?? null) : null,
                                        'minutosTotalesNormales' => $minutosTotalesNormales,
                                        'horas_totales_extra' => $jornada['horas_totales'] ?? null
                                    ],
                                    'timestamp' => time() * 1000
                                ]) . "\n";
                                file_put_contents($logFile, $logEntry, FILE_APPEND);
                                // #endregion agent log
                                
                                // Si aún no tenemos horas normales, no podemos continuar
                                if ($minutosTotalesNormales === 0) {
                                    // Mostrar horas reales del reloj como fallback
                                    $entrada = DateTime::createFromFormat('H:i:s', $jornada['hora_entrada_marcacion']);
                                    if (!$entrada) {
                                        $entrada = DateTime::createFromFormat('H:i', $jornada['hora_entrada_marcacion']);
                                    }
                                    
                                    $salida = DateTime::createFromFormat('H:i:s', $jornada['hora_salida_marcacion']);
                                    if (!$salida) {
                                        $salida = DateTime::createFromFormat('H:i', $jornada['hora_salida_marcacion']);
                                    }
                                    
                                    if ($entrada && $salida) {
                                        $minutosEntrada = $entrada->format('H') * 60 + $entrada->format('i');
                                        $minutosSalida = $salida->format('H') * 60 + $salida->format('i');
                                        $minutosTrabajados = $minutosSalida - $minutosEntrada;
                                        
                                        if ($minutosTrabajados > 0) {
                                            $horas = floor($minutosTrabajados / 60);
                                            $minutos = $minutosTrabajados % 60;
                                            echo sprintf('%d:%02d', $horas, $minutos);
                                        } else {
                                            echo '-';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    continue; // Saltar al siguiente registro
                                }
                                
                                // Convertir horas aprobadas de jornada extraordinaria a minutos
                                $horasTotalesExtra = $jornada['horas_totales'] ?? '00:00:00';
                                $partesExtra = explode(':', $horasTotalesExtra);
                                $horasExtra = (int)($partesExtra[0] ?? 0);
                                $minutosExtra = (int)($partesExtra[1] ?? 0);
                                $minutosTotalesExtra = ($horasExtra * 60) + $minutosExtra;
                                
                                // Sumar horas normales (limitadas al horario) + horas aprobadas
                                $minutosTotalesTrabajados = $minutosTotalesNormales + $minutosTotalesExtra;
                                
                                // #region agent log
                                $logEntry = json_encode([
                                    'sessionId' => 'debug-session',
                                    'runId' => 'run1',
                                    'hypothesisId' => 'D',
                                    'location' => 'listar.php:1942',
                                    'message' => 'Suma final - Jornadas Extra',
                                    'data' => [
                                        'id_jornada' => $jornada['id_jornada'],
                                        'fecha' => $jornada['fecha'],
                                        'minutosTotalesNormales' => $minutosTotalesNormales,
                                        'minutosTotalesExtra' => $minutosTotalesExtra,
                                        'minutosTotalesTrabajados' => $minutosTotalesTrabajados
                                    ],
                                    'timestamp' => time() * 1000
                                ]) . "\n";
                                file_put_contents($logFile, $logEntry, FILE_APPEND);
                                // #endregion agent log
                                
                                if ($minutosTotalesTrabajados > 0) {
                                    $horasFinales = floor($minutosTotalesTrabajados / 60);
                                    $minutosFinales = $minutosTotalesTrabajados % 60;
                                    echo sprintf('%d:%02d', $horasFinales, $minutosFinales);
                                } else {
                                    echo '-';
                                }
                            } else {
                                // Se superponen: Mostrar horas reales del biométrico
                                $entrada = DateTime::createFromFormat('H:i:s', $jornada['hora_entrada_marcacion']);
                                if (!$entrada) {
                                    $entrada = DateTime::createFromFormat('H:i', $jornada['hora_entrada_marcacion']);
                                }
                                
                                $salida = DateTime::createFromFormat('H:i:s', $jornada['hora_salida_marcacion']);
                                if (!$salida) {
                                    $salida = DateTime::createFromFormat('H:i', $jornada['hora_salida_marcacion']);
                                }
                                
                                if ($entrada && $salida) {
                                    // Calcular diferencia directa en minutos (horas reales del reloj)
                                    $minutosEntrada = $entrada->format('H') * 60 + $entrada->format('i');
                                    $minutosSalida = $salida->format('H') * 60 + $salida->format('i');
                                    $minutosTrabajados = $minutosSalida - $minutosEntrada;
                                    
                                    if ($minutosTrabajados > 0) {
                                        $horas = floor($minutosTrabajados / 60);
                                        $minutos = $minutosTrabajados % 60;
                                        echo sprintf('%d:%02d', $horas, $minutos);
                                    } else {
                                        echo '-';
                                    }
                                } else {
                                    echo '-';
                                }
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars(substr($jornada['justificacion'], 0, 50)); ?><?php echo strlen($jornada['justificacion']) > 50 ? '...' : ''; ?></td>
                    <td>
                        <?php if ($jornada['tiene_marcacion']): ?>
                            <span style="color: #1976D2;"><i class="fas fa-check-circle"></i> Coincide con marcación</span>
                        <?php else: ?>
                            <span style="color: #721c24;"><i class="fas fa-exclamation-triangle"></i> Sin marcación (ajustar horario)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php 
        endif;
    } catch (Exception $e) {
        // Error al obtener jornadas, no mostrar sección
    }
endif;
?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

