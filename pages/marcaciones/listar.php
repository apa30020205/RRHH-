<?php
/**
 * Listar Marcaciones
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
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

// Validar campo de ordenamiento (prevenir SQL injection)
$camposPermitidos = [
    'id_marcacion', 'cedula', 'fecha', 'hora_entrada', 'hora_salida', 'fecha_importacion',
    'nombre', 'apellido' // Para ordenar por nombre/apellido del funcionario
];

if (!in_array($ordenarPor, $camposPermitidos)) {
    $ordenarPor = 'fecha';
}

// Función para generar URL de ordenamiento
function urlOrdenar($campo, $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta) {
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
    
    // Construir consulta con JOIN a funcionarios para obtener nombre y apellido
    $sql = "SELECT m.*, f.nombre, f.apellido 
            FROM marcaciones m
            LEFT JOIN funcionarios f ON m.cedula = f.cedula";
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
                 FROM marcaciones m
                 LEFT JOIN funcionarios f ON m.cedula = f.cedula";
    if (!empty($condiciones)) {
        $sqlCount .= " WHERE " . implode(" AND ", $condiciones);
    }
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Obtener nombre del funcionario si hay filtro por cédula
    $nombreFuncionario = '';
    if (!empty($cedulaFiltro)) {
        $stmtFunc = $db->prepare("SELECT nombre, apellido FROM funcionarios WHERE cedula = ?");
        $stmtFunc->execute([$cedulaFiltro]);
        $funcionario = $stmtFunc->fetch();
        if ($funcionario) {
            $nombreFuncionario = trim(($funcionario['nombre'] ?? '') . ' ' . ($funcionario['apellido'] ?? '') . ' - ' . $cedulaFiltro);
        }
    }
    
} catch (PDOException $e) {
    mostrarMensaje("Error al cargar marcaciones: " . $e->getMessage(), 'error');
    $marcaciones = [];
    $totalRegistros = 0;
    $nombreFuncionario = '';
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Marcaciones Biométricas<?php echo !empty($nombreFuncionario) ? ' - ' . htmlspecialchars($nombreFuncionario) : ''; ?></h2>
    <a href="<?php echo BASE_URL; ?>/pages/index.php" class="btn">Volver al Inicio</a>
    <?php if (!empty($cedulaFiltro)): ?>
        <a href="<?php echo BASE_URL; ?>/pages/marcaciones/listar.php" class="btn btn-secondary">Ver Todas las Marcaciones</a>
    <?php endif; ?>
</div>

<!-- Formulario de búsqueda y filtros -->
<form method="GET" action="" class="search-form" style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem;">
    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <?php if (empty($cedulaFiltro)): ?>
        <div style="flex: 1; min-width: 200px;">
            <input type="text" name="buscar" placeholder="Buscar por cédula, nombre, apellido, fecha, hora..." 
                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                   style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px;">
        </div>
        <?php else: ?>
        <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($cedulaFiltro); ?>">
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
                <a href="<?php echo BASE_URL; ?>/pages/marcaciones/listar.php<?php echo !empty($cedulaFiltro) ? '?cedula=' . urlencode($cedulaFiltro) : ''; ?>" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Tabla de marcaciones -->
<?php if (count($marcaciones) > 0): ?>
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; background: white;">
            <thead>
                <tr style="background: #343a40; color: white;">
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('id_marcacion', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            ID <?php echo iconoOrdenamiento('id_marcacion'); ?>
                        </a>
                    </th>
                    <?php if (empty($cedulaFiltro)): ?>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('cedula', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Cédula <?php echo iconoOrdenamiento('cedula'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('nombre', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Nombre <?php echo iconoOrdenamiento('nombre'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('apellido', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Apellido <?php echo iconoOrdenamiento('apellido'); ?>
                        </a>
                    </th>
                    <?php endif; ?>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('fecha', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Fecha <?php echo iconoOrdenamiento('fecha'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('hora_entrada', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Hora Entrada <?php echo iconoOrdenamiento('hora_entrada'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('hora_salida', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Hora Salida <?php echo iconoOrdenamiento('hora_salida'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('fecha_importacion', $busqueda, $cedulaFiltro, $fechaDesde, $fechaHasta); ?>" 
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
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; text-align: center;">
                            <?php 
                            if ($marcacion['hora_entrada']) {
                                $hora = new DateTime($marcacion['hora_entrada']);
                                echo $hora->format('H:i');
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; text-align: center;">
                            <?php 
                            if ($marcacion['hora_salida']) {
                                $hora = new DateTime($marcacion['hora_salida']);
                                echo $hora->format('H:i');
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;">
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
    
    <div style="margin-top: 1rem; color: #666;">
        <p>Total de registros: <strong><?php echo $totalRegistros; ?></strong></p>
    </div>
<?php else: ?>
    <div class="alert alert-info" style="padding: 1rem; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; color: #0c5460;">
        <i class="fas fa-info-circle"></i> No se encontraron marcaciones<?php echo !empty($busqueda) || !empty($fechaDesde) || !empty($fechaHasta) ? ' que coincidan con los filtros' : ''; ?>.
    </div>
<?php endif; ?>

<style>
    /* Reducir interlineado en tabla de marcaciones para que quepan más líneas */
    .data-table tbody tr {
        line-height: 1.2;
    }
    
    .data-table tbody td {
        padding: 0.5rem 0.75rem;
        line-height: 1.2;
    }
    
    .data-table thead th {
        padding: 0.5rem 0.75rem;
        line-height: 1.2;
    }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

