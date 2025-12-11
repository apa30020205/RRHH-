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
    $sql = "SELECT m.*, f.nombre, f.apellido 
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
            "TIME_FORMAT(m.hora_entrada, '%H:%i') LIKE ?",
            "TIME_FORMAT(m.hora_salida, '%H:%i') LIKE ?"
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
        $stmtHorario = $db->prepare("SELECT h_entrada, h_salida FROM funcionarios WHERE cedula = ?");
        $stmtHorario->execute([$cedulaFiltro]);
        $horarioFunc = $stmtHorario->fetch();
        if ($horarioFunc) {
            $hEntradaFunc = $horarioFunc['h_entrada'];
            $hSalidaFunc = $horarioFunc['h_salida'];
        }
    }
    
    // Calcular horas contabilizadas para cada marcación
    foreach ($marcaciones as &$marcacion) {
        if (!empty($marcacion['hora_entrada']) && !empty($marcacion['hora_salida'])) {
            // Obtener horario del funcionario si no está filtrado por cédula
            if (empty($cedulaFiltro) && !$exFuncionario) {
                $stmtHorarioMarc = $db->prepare("SELECT h_entrada, h_salida FROM funcionarios WHERE cedula = ?");
                $stmtHorarioMarc->execute([$marcacion['cedula']]);
                $horarioMarc = $stmtHorarioMarc->fetch();
                $hEntradaMarc = $horarioMarc ? ($horarioMarc['h_entrada'] ?? null) : null;
                $hSalidaMarc = $horarioMarc ? ($horarioMarc['h_salida'] ?? null) : null;
            } else {
                $hEntradaMarc = $hEntradaFunc;
                $hSalidaMarc = $hSalidaFunc;
            }
            
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
    if (!empty($cedulaFiltro)) {
        // Para funcionarios activos, obtener de tabla funcionarios (incluye h_entrada y h_salida)
        // Para ex-funcionarios, solo obtener nombre y apellido
        if (!$exFuncionario) {
            $stmtFunc = $db->prepare("SELECT nombre, apellido, h_entrada, h_salida FROM funcionarios WHERE cedula = ?");
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

<div class="page-header" style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1rem;">
    <h2 style="margin: 0; flex-shrink: 0;">Marcaciones Biométricas<?php echo !empty($nombreFuncionario) ? ' - ' . htmlspecialchars($nombreCompleto) . ' - ' . htmlspecialchars($cedulaFiltro) : ''; ?></h2>
    <?php if (!empty($cedulaFiltro) && !$exFuncionario): 
        // Convertir horas a formato 12 horas para visualización
        $hEntradaFormato = $hEntrada ? date('g:i', strtotime($hEntrada)) : '08:00';
        $hEntradaAMPM = $hEntrada ? (date('H', strtotime($hEntrada)) < 12 ? 'a.m.' : 'p.m.') : 'a.m.';
        $hSalidaFormato = $hSalida ? date('g:i', strtotime($hSalida)) : '04:00';
        $hSalidaAMPM = $hSalida ? (date('H', strtotime($hSalida)) < 12 ? 'a.m.' : 'p.m.') : 'p.m.';
    ?>
    <form id="form-horario" style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; flex: 1; min-width: 0;">
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
                                $hora = new DateTime($marcacion['hora_entrada']);
                                $horaLimite = new DateTime('08:00:00');
                                if ($hora > $horaLimite) {
                                    echo 'background-color: #ffcccc; color: #721c24; font-weight: bold;';
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
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

