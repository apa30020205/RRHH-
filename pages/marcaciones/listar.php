<?php
/**
 * Listar Marcaciones
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/funciones_calculo_horas.php';
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
    $sql = "SELECT m.id_marcacion, m.cedula, m.fecha, 
                   TIME_FORMAT(m.hora_entrada, '%H:%i:%s') as hora_entrada,
                   TIME_FORMAT(m.almuerzo_salida, '%H:%i:%s') as almuerzo_salida,
                   TIME_FORMAT(m.almuerzo_entrada, '%H:%i:%s') as almuerzo_entrada,
                   TIME_FORMAT(m.hora_salida, '%H:%i:%s') as hora_salida,
                   m.horas_trabajadas, m.tiempo_faltante, m.fecha_importacion,
                   f.nombre, f.apellido 
            FROM $tablaMarcaciones m
            LEFT JOIN $tablaFuncionarios f ON m.cedula = f.cedula";
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
            
            // Calcular horas contabilizadas
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
    if (!empty($cedulaFiltro)) {
        // Para funcionarios activos, obtener de tabla funcionarios (incluye h_entrada y h_salida)
        // Para ex-funcionarios, solo obtener nombre y apellido
        if (!$exFuncionario) {
            $stmtFunc = $db->prepare("SELECT nombre, apellido, h_entrada, h_salida, fun_extra FROM funcionarios WHERE cedula = ?");
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
                $mapeoValores = ['Jefe' => 'VIP', 'cesante' => 'Cesante'];
                if ($funExtraActual && isset($mapeoValores[$funExtraActual])) {
                    $funExtraActual = $mapeoValores[$funExtraActual];
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
            <!-- Primera fila: VIP, Manual, Cesante -->
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" 
                        class="btn-fun-extra <?php echo $funExtraActual === 'VIP' ? 'activo' : ''; ?>" 
                        data-valor="VIP"
                        data-cedula="<?php echo htmlspecialchars($cedulaFiltro, ENT_QUOTES); ?>">
                    VIP
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
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; background: white;">
            <thead>
                <tr style="background: #343a40; color: white;">
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('id_marcacion', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            ID <?php echo iconoOrdenamiento('id_marcacion'); ?>
                        </a>
                    </th>
                    <?php if (empty($cedulaFiltro)): ?>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('cedula', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Cédula <?php echo iconoOrdenamiento('cedula'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('nombre', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Nombre <?php echo iconoOrdenamiento('nombre'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('apellido', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Apellido <?php echo iconoOrdenamiento('apellido'); ?>
                        </a>
                    </th>
                    <?php endif; ?>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('fecha', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Fecha <?php echo iconoOrdenamiento('fecha'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('hora_entrada', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Hora Entrada <?php echo iconoOrdenamiento('hora_entrada'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Alm. Salida
                    </th>
                    <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6;">
                        Alm. Entrada
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('hora_salida', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Hora Salida <?php echo iconoOrdenamiento('hora_salida'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.5rem 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        Horas Trabajadas
                    </th>
                    <th style="padding: 0.5rem 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        Horas Dia.
                    </th>
                    <th style="padding: 0.5rem 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        Tardanza/Irregular
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('fecha_importacion', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta, $exFuncionario); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Fecha Importación <?php echo iconoOrdenamiento('fecha_importacion'); ?>
                        </a>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($marcaciones as $marcacion): ?>
                    <tr style="border-bottom: 1px solid #dee2e6; <?php echo (empty($marcacion['hora_entrada']) || empty($marcacion['hora_salida'])) ? 'background: #fff3cd;' : ''; ?>">
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($marcacion['id_marcacion']); ?></td>
                        <?php if (empty($cedulaFiltro)): ?>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; font-weight: bold;"><?php echo htmlspecialchars($marcacion['cedula']); ?></td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($marcacion['nombre'] ?? '-'); ?></td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($marcacion['apellido'] ?? '-'); ?></td>
                        <?php endif; ?>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;">
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
                                        echo 'background-color: #ffcccc; color: #721c24; font-weight: bold;';
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
                                        echo 'background-color: #ffcccc; color: #721c24; font-weight: bold;';
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
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php
                            // Validación visual: fondo rojo si es NULL o si excede 1 hora
                            $almuerzoSalida = $marcacion['almuerzo_salida'] ?? null;
                            $almuerzoEntrada = $marcacion['almuerzo_entrada'] ?? null;
                            $mostrarError = false;
                            
                            if (empty($almuerzoSalida) || empty($almuerzoEntrada)) {
                                $mostrarError = true;
                            } else {
                                // Calcular diferencia en minutos
                                $entrada = DateTime::createFromFormat('H:i:s', $almuerzoEntrada);
                                $salida = DateTime::createFromFormat('H:i:s', $almuerzoSalida);
                                if ($entrada && $salida) {
                                    $diff = $salida->getTimestamp() - $entrada->getTimestamp();
                                    $minutos = (int)($diff / 60);
                                    if ($minutos > 60) {
                                        $mostrarError = true;
                                    }
                                }
                            }
                            
                            if ($mostrarError) {
                                echo 'background-color: #ffcccc; color: #721c24; font-weight: bold;';
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
                        <!-- Columna Alm. Entrada -->
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php
                            if ($mostrarError) {
                                echo 'background-color: #ffcccc; color: #721c24; font-weight: bold;';
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
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center;">
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
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php 
                            // Fondo rojo solo si hay tiempo faltante calculado
                            if (isset($marcacion['tiempo_faltante_calc']) && $marcacion['tiempo_faltante_calc'] !== '00:00:00') {
                                echo 'background-color: #ffcccc; color: #721c24; font-weight: bold;';
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
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; background-color: #fff3cd; color: #856404; font-weight: bold;">
                            <?php 
                            // Calcular horas reales directamente desde hora_entrada y hora_salida del reloj biométrico (sin filtros)
                            if (!empty($marcacion['hora_entrada']) && !empty($marcacion['hora_salida'])) {
                                $entrada = DateTime::createFromFormat('H:i:s', $marcacion['hora_entrada']);
                                if (!$entrada) {
                                    $entrada = DateTime::createFromFormat('H:i', $marcacion['hora_entrada']);
                                }
                                
                                $salida = DateTime::createFromFormat('H:i:s', $marcacion['hora_salida']);
                                if (!$salida) {
                                    $salida = DateTime::createFromFormat('H:i', $marcacion['hora_salida']);
                                }
                                
                                if ($entrada && $salida) {
                                    // Calcular diferencia directa en minutos (sin filtros)
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
                        <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6;">
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>

