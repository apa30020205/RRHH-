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
    // Verificar si las columnas de almuerzo y todas_marcaciones existen en la tabla
    $stmtCheckColumns = $db->query("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = '$tablaMarcaciones' 
        AND COLUMN_NAME IN ('almuerzo_salida', 'almuerzo_entrada', 'todas_marcaciones')
    ");
    $existingColumns = $stmtCheckColumns->fetchAll(PDO::FETCH_COLUMN);
    $tieneAlmuerzoSalida = in_array('almuerzo_salida', $existingColumns);
    $tieneAlmuerzoEntrada = in_array('almuerzo_entrada', $existingColumns);
    $tieneTodasMarcaciones = in_array('todas_marcaciones', $existingColumns);
    
    // Verificar si la columna permiso_justificado existe (debe estar disponible en todo el archivo)
    $columnaPermisoJustificadoExiste = false;
    try {
        $stmtCheckColPermisoJustificado = $db->query("
            SELECT COUNT(*) as existe
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'permisos'
            AND COLUMN_NAME = 'permiso_justificado'
        ");
        $result = $stmtCheckColPermisoJustificado->fetch();
        $columnaPermisoJustificadoExiste = ($result && isset($result['existe']) && $result['existe'] > 0);
    } catch (Exception $e) {
        // Si hay error, asumir que la columna no existe
        $columnaPermisoJustificadoExiste = false;
    }
    
    // Construir SELECT dinámicamente según las columnas disponibles
    $camposAlmuerzo = '';
    if ($tieneAlmuerzoSalida && $tieneAlmuerzoEntrada) {
        $camposAlmuerzo = "TIME_FORMAT(m.almuerzo_salida, '%H:%i:%s') as almuerzo_salida,
                           TIME_FORMAT(m.almuerzo_entrada, '%H:%i:%s') as almuerzo_entrada,";
    } else {
        $camposAlmuerzo = "NULL as almuerzo_salida,
                           NULL as almuerzo_entrada,";
    }
    
    $campoTodasMarcaciones = $tieneTodasMarcaciones ? 'm.todas_marcaciones,' : 'NULL as todas_marcaciones,';
    
    // Construir consulta base para marcaciones con jornadas extraordinarias
    $sqlBase = "SELECT m.id_marcacion, m.cedula, m.fecha, 
                   TIME_FORMAT(m.hora_entrada, '%H:%i:%s') as hora_entrada,
                   $camposAlmuerzo
                   TIME_FORMAT(m.hora_salida, '%H:%i:%s') as hora_salida,
                   m.horas_trabajadas, m.tiempo_faltante, m.fecha_importacion,
                   $campoTodasMarcaciones
                   f.nombre, f.apellido,
                   j.id_jornada, j.hora_desde as jornada_hora_desde, 
                   j.hora_hasta as jornada_hora_hasta, j.horas_totales as jornada_horas_totales,
                   j.justificacion as jornada_justificacion
            FROM $tablaMarcaciones m
            LEFT JOIN $tablaFuncionarios f ON m.cedula = f.cedula
            LEFT JOIN jornada_extraordinaria j ON m.cedula = j.cedula AND m.fecha = j.fecha AND j.estado = 'activa'";
    
    // Construir consulta para jornadas extraordinarias sin marcaciones
    $camposAlmuerzoJornada = "NULL as almuerzo_salida, NULL as almuerzo_entrada,";
    $sqlJornadasSinMarcaciones = "SELECT NULL as id_marcacion, j.cedula, j.fecha,
                   NULL as hora_entrada,
                   $camposAlmuerzoJornada
                   NULL as hora_salida,
                   NULL as horas_trabajadas, NULL as tiempo_faltante, NULL as fecha_importacion,
                   NULL as todas_marcaciones,
                   f.nombre, f.apellido,
                   j.id_jornada, j.hora_desde as jornada_hora_desde,
                   j.hora_hasta as jornada_hora_hasta, j.horas_totales as jornada_horas_totales,
                   j.justificacion as jornada_justificacion
            FROM jornada_extraordinaria j
            LEFT JOIN $tablaFuncionarios f ON j.cedula = f.cedula
            WHERE j.estado = 'activa'
            AND NOT EXISTS (
                SELECT 1 FROM $tablaMarcaciones m2 
                WHERE m2.cedula = j.cedula 
                AND m2.fecha = j.fecha
            )";
    
    // Combinar ambas consultas con UNION
    $sql = "($sqlBase) UNION ($sqlJornadasSinMarcaciones)";
    $params = [];
    $condiciones = [];
    
    // Construir condiciones para la primera consulta (marcaciones)
    $condicionesMarcaciones = [];
    $condicionesJornadas = [];
    $paramsMarcaciones = [];
    $paramsJornadas = [];
    
    // Filtrar por cédula específica (si viene de la lista de funcionarios)
    if (!empty($cedulaFiltro)) {
        $condicionesMarcaciones[] = "m.cedula = ?";
        $condicionesJornadas[] = "j.cedula = ?";
        $paramsMarcaciones[] = $cedulaFiltro;
        $paramsJornadas[] = $cedulaFiltro;
    }
    
    // Filtrar por rango de fechas
    if (!empty($fechaDesde)) {
        $condicionesMarcaciones[] = "m.fecha >= ?";
        $condicionesJornadas[] = "j.fecha >= ?";
        $paramsMarcaciones[] = $fechaDesde;
        $paramsJornadas[] = $fechaDesde;
    }
    if (!empty($fechaHasta)) {
        $condicionesMarcaciones[] = "m.fecha <= ?";
        $condicionesJornadas[] = "j.fecha <= ?";
        $paramsMarcaciones[] = $fechaHasta;
        $paramsJornadas[] = $fechaHasta;
    }
    
    // Agregar condición de búsqueda si existe (solo para marcaciones, ya que jornadas sin marcaciones no tienen esos campos)
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
        $condicionesMarcaciones[] = "(" . implode(" OR ", $condicionesBusqueda) . ")";
        // Agregar parámetros para cada campo de búsqueda
        for ($i = 0; $i < 6; $i++) {
            $paramsMarcaciones[] = $busquedaLimpia;
        }
    }
    
    // Aplicar condiciones a la primera consulta (marcaciones)
    if (!empty($condicionesMarcaciones)) {
        $sqlBase .= " WHERE " . implode(" AND ", $condicionesMarcaciones);
    }
    
    // Aplicar condiciones a la segunda consulta (jornadas sin marcaciones)
    if (!empty($condicionesJornadas)) {
        $sqlJornadasSinMarcaciones .= " AND " . implode(" AND ", $condicionesJornadas);
    }
    
    // Reconstruir SQL completo con condiciones aplicadas
    $sql = "($sqlBase) UNION ($sqlJornadasSinMarcaciones)";
    $params = array_merge($paramsMarcaciones, $paramsJornadas);
    
    // Agregar ordenamiento (usar alias de columnas para UNION)
    $sql .= " ORDER BY ";
    if ($ordenarPor === 'nombre' || $ordenarPor === 'apellido') {
        // Para nombre y apellido, usar COALESCE para manejar NULL
        $sql .= "COALESCE(nombre, '') $direccion";
        if ($ordenarPor === 'apellido') {
            $sql .= ", COALESCE(nombre, '') $direccion, cedula $direccion";
        } else {
            $sql .= ", COALESCE(apellido, '') $direccion, cedula $direccion";
        }
    } else {
        // Mapear campos de marcaciones a alias de UNION
        $campoOrden = $ordenarPor;
        if ($ordenarPor === 'fecha') {
            $campoOrden = 'fecha';
        } elseif ($ordenarPor === 'fecha_importacion') {
            $campoOrden = 'fecha_importacion';
        } elseif ($ordenarPor === 'id_marcacion') {
            $campoOrden = 'id_marcacion';
        } else {
            $campoOrden = $ordenarPor;
        }
        $sql .= "$campoOrden $direccion";
        // Agregar ordenamiento secundario
        $sql .= ", fecha DESC, fecha_importacion DESC";
    }
    
    // Ejecutar consulta
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $marcaciones = $stmt->fetchAll();
    
    // Contar total (incluyendo jornadas sin marcaciones)
    $sqlCount = "SELECT COUNT(*) as total FROM (
        SELECT m.id_marcacion
        FROM $tablaMarcaciones m
        LEFT JOIN $tablaFuncionarios f ON m.cedula = f.cedula";
    if (!empty($condicionesMarcaciones)) {
        $sqlCount .= " WHERE " . implode(" AND ", $condicionesMarcaciones);
    }
    $sqlCount .= "
        UNION
        SELECT j.id_jornada as id_marcacion
        FROM jornada_extraordinaria j
        LEFT JOIN $tablaFuncionarios f ON j.cedula = f.cedula
        WHERE j.estado = 'activa'
        AND NOT EXISTS (
            SELECT 1 FROM $tablaMarcaciones m2 
            WHERE m2.cedula = j.cedula 
            AND m2.fecha = j.fecha
        )";
    if (!empty($condicionesJornadas)) {
        $sqlCount .= " AND " . implode(" AND ", $condicionesJornadas);
    }
    $sqlCount .= ") as total_union";
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
            
            // Guardar horario de entrada en la marcación para usarlo después en la visualización
            $marcacion['h_entrada_func'] = $hEntradaMarc;
            
            // Verificar si hay jornada extraordinaria aprobada para esta fecha
            $tieneJornadaExtra = !empty($marcacion['id_jornada']);

            // Calcular horas contabilizadas - TODOS los días usan la misma lógica (limitada al horario regular)
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
    $totalJornadasExtra = '00:00:00';
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
            <!-- Primera fila: Director, Manual, EX/Funcionario -->
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
                        class="btn-fun-extra <?php echo $funExtraActual === 'EX/Funcionario' ? 'activo' : ''; ?>"
                        data-valor="EX/Funcionario"
                        data-cedula="<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>">
                    EX/Funcionario
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
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; <?php echo $tieneJornadaExtra ? 'color: white !important;' : ''; ?>"><?php echo $marcacion['id_marcacion'] ? htmlspecialchars($marcacion['id_marcacion']) : '-'; ?></td>
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
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php 
                            if ($tieneJornadaExtra) {
                                echo 'background-color: #1565C0 !important; color: white !important;';
                            } else {
                                echo 'background-color: #fff3cd; color: #856404;';
                            }
                        ?> font-weight: bold;">
                            <?php 
                            if ($tieneJornadaExtra && !empty($marcacion['jornada_horas_totales'])) {
                                // Hay jornada extraordinaria aprobada
                                $horasTotalesExtra = $marcacion['jornada_horas_totales'] ?? '00:00:00';
                                $partesExtra = explode(':', $horasTotalesExtra);
                                $horasExtra = (int)($partesExtra[0] ?? 0);
                                $minutosExtra = (int)($partesExtra[1] ?? 0);
                                $minutosTotalesExtra = ($horasExtra * 60) + $minutosExtra;
                                
                                // Verificar si hay marcaciones biométricas (horas contabilizadas)
                                if (!empty($marcacion['horas_contabilizadas']) && $marcacion['horas_contabilizadas'] !== '00:00:00') {
                                    // Con marcaciones biométricas: sumar horas normales (limitadas al horario) + horas extraordinarias aprobadas
                                    $partesNormales = explode(':', $marcacion['horas_contabilizadas']);
                                    $horasNormales = (int)($partesNormales[0] ?? 0);
                                    $minutosNormales = (int)($partesNormales[1] ?? 0);
                                    $minutosTotalesNormales = ($horasNormales * 60) + $minutosNormales;
                                    
                                    // Sumar total
                                    $minutosTotales = $minutosTotalesNormales + $minutosTotalesExtra;
                                    $horasFinales = floor($minutosTotales / 60);
                                    $minutosFinales = $minutosTotales % 60;
                                    echo sprintf('%d:%02d', $horasFinales, $minutosFinales);
                                } else {
                                    // Sin marcaciones biométricas: mostrar solo las horas extraordinarias aprobadas (formato HH:MM)
                                    $horasTotalesExtra = $marcacion['jornada_horas_totales'] ?? '00:00:00';
                                    if (strlen($horasTotalesExtra) >= 5) {
                                        echo substr($horasTotalesExtra, 0, 5); // Toma solo HH:MM
                                    } else {
                                        echo sprintf('%d:%02d', $horasExtra, $minutosExtra);
                                    }
                                }
                            } else {
                                // Sin jornada extraordinaria: mostrar horas contabilizadas normales
                                if (isset($marcacion['horas_contabilizadas']) && $marcacion['horas_contabilizadas'] !== '00:00:00') {
                                    $partes = explode(':', $marcacion['horas_contabilizadas']);
                                    echo sprintf('%d:%02d', (int)($partes[0] ?? 0), (int)($partes[1] ?? 0));
                                } else {
                                    echo '<span style="color: #856404;">-</span>';
                                }
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
                Permisos InJustificados - Días:
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
    background: #1e7e34;
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
                // Si se marcó como EX/Funcionario, Préstamo, Lic. Sueldo o Lic. Sin Sueldo, recargar la página (el funcionario ya no existe en funcionarios)
                const valoresQueMuevenAEx = ['EX/Funcionario', 'Préstamo', 'Lic. Sueldo', 'Lic. Sin Sueldo'];
                if (valoresQueMuevenAEx.includes(valor)) {
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
        
        // Calcular total de jornadas extraordinarias del período
        $totalJornadasExtraMinutos = 0;
        foreach ($jornadasExtraordinarias as $jornada) {
            if (!empty($jornada['horas_totales'])) {
                // Parsear horas extraordinarias (formato HH:MM:SS o HH:MM)
                $horasTotalesExtra = $jornada['horas_totales'];
                $partesExtra = explode(':', $horasTotalesExtra);
                $horasExtra = (int)($partesExtra[0] ?? 0);
                $minutosExtra = (int)($partesExtra[1] ?? 0);
                $totalJornadasExtraMinutos += ($horasExtra * 60) + $minutosExtra;
            }
        }
        // Convertir minutos totales a formato HH:MM:00
        $horasExtraTotal = floor($totalJornadasExtraMinutos / 60);
        $minutosExtraTotal = $totalJornadasExtraMinutos % 60;
        $totalJornadasExtra = sprintf('%02d:%02d:00', $horasExtraTotal, $minutosExtraTotal);
        
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
    background-color: #E3F2FD;
    color: #1976D2;
}

.jornadas-extras-table tr.jornada-con-marcacion td {
    color: #1976D2;
    font-weight: 500;
}

.jornadas-extras-table tr.jornada-sin-marcacion td {
    color: #1976D2;
    font-weight: 500;
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
                <th>Justificación</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jornadasExtraordinarias as $jornada): 
                $claseFila = $jornada['tiene_marcacion'] ? 'jornada-con-marcacion' : 'jornada-sin-marcacion';
            ?>
                <tr class="<?php echo $claseFila; ?>">
                    <?php if (empty($cedulaFiltro)): ?>
                    <td><?php echo htmlspecialchars($jornada['cedula']); ?></td>
                    <td><?php echo htmlspecialchars(($jornada['nombre'] ?? '') . ' ' . ($jornada['apellido'] ?? '')); ?></td>
                    <?php endif; ?>
                    <td><?php echo date('d/m/Y', strtotime($jornada['fecha'])); ?></td>
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
                    <td><strong>
                        <?php 
                        if (!empty($jornada['horas_totales'])) {
                            // Formatear para mostrar solo HH:MM (sin segundos)
                            $horasTotales = $jornada['horas_totales'];
                            if (strlen($horasTotales) >= 5) {
                                echo substr($horasTotales, 0, 5); // Toma solo HH:MM
                            } else {
                                echo $horasTotales;
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </strong></td>
                    <td><?php echo htmlspecialchars(substr($jornada['justificacion'], 0, 50)); ?><?php echo strlen($jornada['justificacion']) > 50 ? '...' : ''; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Sumatoria de Horas Extraordinarias del Período - Debajo de la tabla -->
    <div style="margin-top: 1rem; color: #666; display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
        <div>
            <strong>Total Jornadas Extraordinarias del Período:</strong>
            <span style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; color: #1976D2;">
                <?php 
                // Mostrar el valor formateado (ya viene en formato HH:MM:SS)
                if (!empty($totalJornadasExtra)) {
                    // Extraer horas y minutos del string
                    $partes = explode(':', $totalJornadasExtra);
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
</div>

<?php 
        endif;
    } catch (Exception $e) {
        // Error al obtener jornadas, no mostrar sección
    }
endif;

// Inicializar variables de permisos (deben estar disponibles en todo el archivo)
$totalPermisosInjustificados = '00:00:00';
$totalPermisosInjustificadosMinutos = 0;
$permisosPeriodo = [];

// Obtener permisos del período (solo si hay marcaciones y el período está definido)
if (!empty($marcaciones) && (!empty($fechaDesde) || !empty($fechaHasta) || !empty($cedulaFiltro))):
    try {
        // $columnaPermisoJustificadoExiste ya está inicializada al inicio del archivo
        
        $sqlPermisos = "SELECT p.id_permiso, p.cedula, p.motivo, p.especifique, 
                               p.fecha_desde, p.hora_desde, p.fecha_hasta, p.hora_hasta,
                               p.horas_totales, p.fecha_registro";
        if ($columnaPermisoJustificadoExiste) {
            $sqlPermisos .= ", p.permiso_justificado";
        }
        $sqlPermisos .= ", f.nombre, f.apellido,
                               CASE WHEN m.id_marcacion IS NOT NULL THEN 1 ELSE 0 END as tiene_marcacion
                        FROM permisos p
                        LEFT JOIN funcionarios f ON p.cedula = f.cedula
                        LEFT JOIN marcaciones m ON p.cedula = m.cedula 
                            AND m.fecha >= p.fecha_desde AND m.fecha <= p.fecha_hasta
                        WHERE p.estado = 'activa'";
        
        $paramsPermisos = [];
        $condicionesPermisos = [];
        
        if (!empty($cedulaFiltro)) {
            $condicionesPermisos[] = "p.cedula = ?";
            $paramsPermisos[] = $cedulaFiltro;
        }
        
        if (!empty($fechaDesde)) {
            $condicionesPermisos[] = "p.fecha_hasta >= ?";
            $paramsPermisos[] = $fechaDesde;
        }
        
        if (!empty($fechaHasta)) {
            $condicionesPermisos[] = "p.fecha_desde <= ?";
            $paramsPermisos[] = $fechaHasta;
        }
        
        if (!empty($condicionesPermisos)) {
            $sqlPermisos .= " AND " . implode(" AND ", $condicionesPermisos);
        }
        
        $sqlPermisos .= " ORDER BY p.fecha_desde DESC, p.fecha_registro DESC";
        
        $stmtPermisos = $db->prepare($sqlPermisos);
        $stmtPermisos->execute($paramsPermisos);
        $permisosPeriodo = $stmtPermisos->fetchAll();
        
        // Calcular total de permisos del período
        $totalPermisosMinutos = 0;
        $totalPermisosInjustificadosMinutos = 0;
        foreach ($permisosPeriodo as $permiso) {
            if (!empty($permiso['horas_totales'])) {
                $horasTotalesPermiso = $permiso['horas_totales'];
                $partesPermiso = explode(':', $horasTotalesPermiso);
                $horasPermiso = (int)($partesPermiso[0] ?? 0);
                $minutosPermiso = (int)($partesPermiso[1] ?? 0);
                $minutosTotales = ($horasPermiso * 60) + $minutosPermiso;
                
                // Acumular según si es justificado o no (solo si la columna existe)
                if ($columnaPermisoJustificadoExiste && isset($permiso['permiso_justificado']) && $permiso['permiso_justificado'] == 0) {
                    $totalPermisosInjustificadosMinutos += $minutosTotales;
                } else {
                    $totalPermisosMinutos += $minutosTotales;
                }
            }
        }
        // Convertir minutos totales a formato HH:MM:00
        $horasPermisosTotal = floor($totalPermisosMinutos / 60);
        $minutosPermisosTotal = $totalPermisosMinutos % 60;
        $totalPermisos = sprintf('%02d:%02d:00', $horasPermisosTotal, $minutosPermisosTotal);
        
        // Convertir minutos injustificados a formato HH:MM:00
        $horasPermisosInjustificadosTotal = floor($totalPermisosInjustificadosMinutos / 60);
        $minutosPermisosInjustificadosTotal = $totalPermisosInjustificadosMinutos % 60;
        $totalPermisosInjustificados = sprintf('%02d:%02d:00', $horasPermisosInjustificadosTotal, $minutosPermisosInjustificadosTotal);
        
        if (count($permisosPeriodo) > 0):
?>
<style>
.permisos-periodo-section {
    margin-top: 2rem;
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.permisos-periodo-section h3 {
    color: #4caf50;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #4caf50;
}

.permisos-periodo-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.permisos-periodo-table th {
    background: #4caf50;
    color: white;
    padding: 0.75rem;
    text-align: left;
    border: 1px solid #45a049;
}

.permisos-periodo-table td {
    padding: 0.75rem;
    border: 1px solid #dee2e6;
}

.permisos-periodo-table tr.permiso-con-marcacion {
    background-color: #c8e6c9;
}

.permisos-periodo-table tr.permiso-sin-marcacion {
    background-color: #c8e6c9;
    color: #2e7d32;
}

.permisos-periodo-table tr.permiso-con-marcacion td {
    color: #2e7d32;
    font-weight: 500;
}

.permisos-periodo-table tr.permiso-sin-marcacion td {
    color: #2e7d32;
    font-weight: 500;
}

.permisos-periodo-table tr.permiso-injustificado {
    background-color: #ffcccc !important;
}

.permisos-periodo-table tr.permiso-injustificado td {
    color: #721c24;
    font-weight: 500;
}
</style>

<div class="permisos-periodo-section">
    <h3><i class="fas fa-calendar-check"></i> Permisos del Período</h3>
    <table class="permisos-periodo-table">
        <thead>
            <tr>
                <?php if (empty($cedulaFiltro)): ?>
                <th>Cédula</th>
                <th>Nombre</th>
                <?php endif; ?>
                <th>Fecha Desde</th>
                <th>Hora Desde</th>
                <th>Fecha Hasta</th>
                <th>Hora Hasta</th>
                <th>Horas Totales</th>
                <th>Motivo</th>
                <th>Especifique</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($permisosPeriodo as $permiso):
                // Determinar clase según si tiene marcación Y si es injustificado
                $claseFila = $permiso['tiene_marcacion'] ? 'permiso-con-marcacion' : 'permiso-sin-marcacion';
                if ($columnaPermisoJustificadoExiste && isset($permiso['permiso_justificado']) && $permiso['permiso_justificado'] == 0) {
                    $claseFila = 'permiso-injustificado';
                }
            ?>
                <tr class="<?php echo $claseFila; ?>">
                    <?php if (empty($cedulaFiltro)): ?>
                    <td><?php echo htmlspecialchars($permiso['cedula']); ?></td>
                    <td><?php echo htmlspecialchars(($permiso['nombre'] ?? '') . ' ' . ($permiso['apellido'] ?? '')); ?></td>
                    <?php endif; ?>
                    <td><?php echo date('d/m/Y', strtotime($permiso['fecha_desde'])); ?></td>
                    <td>
                        <?php
                        if ($permiso['hora_desde']) {
                            $hora = new DateTime($permiso['hora_desde']);
                            $horaFormato = $hora->format('g:i');
                            $ampm = strtolower($hora->format('A'));
                            $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                            echo $horaFormato . ' ' . $ampm;
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($permiso['fecha_hasta'])); ?></td>
                    <td>
                        <?php
                        if ($permiso['hora_hasta']) {
                            $hora = new DateTime($permiso['hora_hasta']);
                            $horaFormato = $hora->format('g:i');
                            $ampm = strtolower($hora->format('A'));
                            $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                            echo $horaFormato . ' ' . $ampm;
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><strong>
                        <?php
                        if (!empty($permiso['horas_totales'])) {
                            $horasTotales = $permiso['horas_totales'];
                            if (strlen($horasTotales) >= 5) {
                                echo substr($horasTotales, 0, 5);
                            } else {
                                echo $horasTotales;
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </strong></td>
                    <td><?php 
                            $motivoTexto = htmlspecialchars($permiso['motivo']);
                            if (isset($permiso['motivo']) && $permiso['motivo'] === 'Permiso InJustificado') {
                                echo '<span style="color: #dc3545; font-weight: bold;">' . $motivoTexto . '</span>';
                            } else {
                                echo $motivoTexto;
                            }
                        ?></td>
                    <td><?php echo htmlspecialchars(substr($permiso['especifique'] ?? '', 0, 50)); ?><?php echo strlen($permiso['especifique'] ?? '') > 50 ? '...' : ''; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- Sumatoria de Permisos del Período - Debajo de la tabla -->
    <div style="margin-top: 1rem; color: #666; display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
        <div>
            <strong>Total Permisos del Período:</strong>
            <span style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; color: #2e7d32;">
                <?php
                if (!empty($totalPermisos)) {
                    $partes = explode(':', $totalPermisos);
                    $horasInt = (int)($partes[0] ?? 0);
                    $minutosInt = (int)($partes[1] ?? 0);
                    echo sprintf('%d:%02d', $horasInt, $minutosInt);
                } else {
                    echo '00:00';
                }
                ?>
            </span>
        </div>
        
        <?php if ($columnaPermisoJustificadoExiste && isset($totalPermisosInjustificados)): ?>
        <div>
            <strong style="color: #dc3545;">Total Permisos Injustificados del Período:</strong>
            <span style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; color: #dc3545;">
                <?php
                if (!empty($totalPermisosInjustificados)) {
                    $partesInjust = explode(':', $totalPermisosInjustificados);
                    $horasIntInjust = (int)($partesInjust[0] ?? 0);
                    $minutosIntInjust = (int)($partesInjust[1] ?? 0);
                    echo sprintf('%d:%02d', $horasIntInjust, $minutosIntInjust);
                } else {
                    echo '00:00';
                }
                ?>
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
        endif;
    } catch (Exception $e) {
        // Error al obtener permisos, no mostrar sección
    }
endif;
?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

