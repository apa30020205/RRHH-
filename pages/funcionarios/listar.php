<?php
/**
 * Listar Funcionarios
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Listar Funcionarios - Sistema RRHH';

// Obtener parámetros de búsqueda y ordenamiento
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$ordenarPor = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'apellido';
$direccion = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'DESC' : 'ASC';

// Validar campo de ordenamiento (prevenir SQL injection)
$camposPermitidos = [
    'cedula', 'nombre', 'apellido', 'fecha_nacimiento', 
    'edad', 'sangre', 'no_posicion', 'posicion_funcional', 
    'fecha_inicio', 'sede_provincia', 'Direccion'
];

if (!in_array($ordenarPor, $camposPermitidos)) {
    $ordenarPor = 'apellido';
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Construir consulta con búsqueda y ordenamiento
    $sql = "SELECT * FROM funcionarios";
    $params = [];
    
    // Agregar condición de búsqueda si existe
    if (!empty($busqueda)) {
        $busquedaLimpia = '%' . $busqueda . '%';
        $sql .= " WHERE (
            cedula LIKE ? OR 
            nombre LIKE ? OR 
            apellido LIKE ? OR 
            CAST(edad AS CHAR) LIKE ? OR 
            CAST(no_posicion AS CHAR) LIKE ? OR 
            sangre LIKE ? OR 
            posicion_funcional LIKE ? OR 
            sede_provincia LIKE ? OR 
            Direccion LIKE ? OR
            DATE_FORMAT(fecha_nacimiento, '%d/%m/%Y') LIKE ? OR
            DATE_FORMAT(fecha_inicio, '%d/%m/%Y') LIKE ?
        )";
        // Agregar parámetros para cada campo de búsqueda
        for ($i = 0; $i < 11; $i++) {
            $params[] = $busquedaLimpia;
        }
    }
    
    // Agregar ordenamiento
    $sql .= " ORDER BY ";
    if ($ordenarPor === 'apellido' || $ordenarPor === 'nombre') {
        // Para apellido y nombre, usar COALESCE para manejar NULL
        $sql .= "COALESCE($ordenarPor, '') $direccion";
        if ($ordenarPor === 'apellido') {
            $sql .= ", COALESCE(nombre, '') $direccion, cedula $direccion";
        } else {
            $sql .= ", COALESCE(apellido, '') $direccion, cedula $direccion";
        }
    } else {
        // Para otros campos, ordenar directamente
        $sql .= "$ordenarPor $direccion";
        // Si el campo puede ser NULL, agregar ordenamiento secundario
        if (in_array($ordenarPor, ['fecha_nacimiento', 'fecha_inicio', 'edad', 'no_posicion'])) {
            $sql .= ", cedula ASC";
        }
    }
    
    // Ejecutar consulta
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $funcionarios = $stmt->fetchAll();
    
    // Contar total (con búsqueda si aplica)
    $sqlCount = "SELECT COUNT(*) as total FROM funcionarios";
    if (!empty($busqueda)) {
        $sqlCount .= " WHERE (
            cedula LIKE ? OR 
            nombre LIKE ? OR 
            apellido LIKE ? OR 
            CAST(edad AS CHAR) LIKE ? OR 
            CAST(no_posicion AS CHAR) LIKE ? OR 
            sangre LIKE ? OR 
            posicion_funcional LIKE ? OR 
            sede_provincia LIKE ? OR 
            Direccion LIKE ? OR
            DATE_FORMAT(fecha_nacimiento, '%d/%m/%Y') LIKE ? OR
            DATE_FORMAT(fecha_inicio, '%d/%m/%Y') LIKE ?
        )";
    }
    $stmtCount = $db->prepare($sqlCount);
    if (!empty($busqueda)) {
        $paramsCount = [];
        for ($i = 0; $i < 11; $i++) {
            $paramsCount[] = '%' . $busqueda . '%';
        }
        $stmtCount->execute($paramsCount);
    } else {
        $stmtCount->execute();
    }
    $total = $stmtCount->fetch()['total'];
    
    // Contar resultados de búsqueda
    $totalResultados = count($funcionarios);
} catch (Exception $e) {
    $funcionarios = [];
    $total = 0;
    $totalResultados = 0;
    mostrarMensaje("Error al cargar funcionarios: " . $e->getMessage(), 'error');
}

// Función para generar URL de ordenamiento
function urlOrdenar($campo, $busquedaActual = '') {
    $params = ['ordenar' => $campo];
    
    // Si ya está ordenando por este campo, cambiar dirección
    if (isset($_GET['ordenar']) && $_GET['ordenar'] === $campo) {
        $direccionActual = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
        $params['dir'] = $direccionActual === 'asc' ? 'desc' : 'asc';
    } else {
        $params['dir'] = 'asc';
    }
    
    if (!empty($busquedaActual)) {
        $params['buscar'] = $busquedaActual;
    }
    
    return '?' . http_build_query($params);
}

// Función para obtener icono de ordenamiento
function iconoOrdenamiento($campo) {
    if (!isset($_GET['ordenar']) || $_GET['ordenar'] !== $campo) {
        return '<i class="fas fa-sort" style="opacity: 0.3; margin-left: 5px;"></i>';
    }
    
    $direccion = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
    if ($direccion === 'asc') {
        return '<i class="fas fa-sort-up" style="margin-left: 5px;"></i>';
    } else {
        return '<i class="fas fa-sort-down" style="margin-left: 5px;"></i>';
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Lista de Funcionarios</h2>
    <div>
        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/crear.php" class="btn btn-primary">Nuevo Funcionario</a>
        <a href="<?php echo BASE_URL; ?>/services/excel/importar.php" class="btn btn-success">Importar Excel</a>
    </div>
</div>

<!-- Barra de búsqueda -->
<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px;">
    <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
        <label for="buscar" style="font-weight: bold; min-width: 100px;">Buscar:</label>
        <input type="text" 
               id="buscar" 
               name="buscar" 
               value="<?php echo htmlspecialchars($busqueda); ?>" 
               placeholder="Buscar por cédula, nombre, apellido, edad, posición, etc..."
               style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.95em;">
        <button type="submit" class="btn btn-primary" style="padding: 8px 20px;">
            <i class="fas fa-search"></i> Buscar
        </button>
        <?php if (!empty($busqueda)): ?>
        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/listar.php" class="btn" style="padding: 8px 20px;">
            <i class="fas fa-times"></i> Limpiar
        </a>
        <?php endif; ?>
        <?php if (!empty($busqueda)): ?>
            <input type="hidden" name="ordenar" value="<?php echo htmlspecialchars($ordenarPor); ?>">
            <input type="hidden" name="dir" value="<?php echo htmlspecialchars($direccion === 'DESC' ? 'desc' : 'asc'); ?>">
        <?php endif; ?>
    </form>
</div>

<?php if (isset($total) && $total > 0): ?>
    <div class="alert alert-<?php echo !empty($busqueda) ? 'info' : 'success'; ?>" style="margin-bottom: 20px;">
        <?php if (!empty($busqueda)): ?>
            <strong>Resultados de búsqueda:</strong> <?php echo number_format($totalResultados); ?> de <?php echo number_format($total); ?> funcionarios
        <?php else: ?>
            <strong>Total de funcionarios:</strong> <?php echo number_format($total); ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (empty($funcionarios)): ?>
    <div class="alert alert-info">
        <?php if (!empty($busqueda)): ?>
            No se encontraron funcionarios que coincidan con "<?php echo htmlspecialchars($busqueda); ?>".
            <a href="<?php echo BASE_URL; ?>/pages/funcionarios/listar.php">Ver todos los funcionarios</a>
        <?php else: ?>
            No hay funcionarios registrados. <a href="<?php echo BASE_URL; ?>/pages/funcionarios/crear.php">Crear el primero</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div style="overflow-x: auto; margin: 20px 0;">
        <table class="table-excel" style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
            <thead>
                <tr>
                    <th>
                        <a href="<?php echo urlOrdenar('cedula', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Cédula <?php echo iconoOrdenamiento('cedula'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('nombre', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Nombre <?php echo iconoOrdenamiento('nombre'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('apellido', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Apellido <?php echo iconoOrdenamiento('apellido'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('fecha_nacimiento', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Fecha Nac. <?php echo iconoOrdenamiento('fecha_nacimiento'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('edad', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Edad <?php echo iconoOrdenamiento('edad'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('sangre', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Sangre <?php echo iconoOrdenamiento('sangre'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('no_posicion', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            No. Posición <?php echo iconoOrdenamiento('no_posicion'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('posicion_funcional', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Posición Funcional <?php echo iconoOrdenamiento('posicion_funcional'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('fecha_inicio', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Fecha Inicio <?php echo iconoOrdenamiento('fecha_inicio'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('sede_provincia', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Sede/Provincia <?php echo iconoOrdenamiento('sede_provincia'); ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?php echo urlOrdenar('Direccion', $busqueda); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center;">
                            Dirección <?php echo iconoOrdenamiento('Direccion'); ?>
                        </a>
                    </th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($funcionarios as $func): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars(formatearCedula($func['cedula'])); ?></strong></td>
                    <td><?php echo htmlspecialchars($func['nombre'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($func['apellido'] ?? '-'); ?></td>
                    <td><?php echo $func['fecha_nacimiento'] ? formatearFecha($func['fecha_nacimiento'], 'd/m/Y') : '-'; ?></td>
                    <td style="text-align: center;"><?php echo $func['edad'] ? htmlspecialchars($func['edad']) : '-'; ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($func['sangre'] ?? '-'); ?></td>
                    <td style="text-align: center;"><?php echo $func['no_posicion'] ? htmlspecialchars($func['no_posicion']) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($func['posicion_funcional'] ?? '-'); ?></td>
                    <td><?php echo $func['fecha_inicio'] ? formatearFecha($func['fecha_inicio'], 'd/m/Y') : '-'; ?></td>
                    <td><?php echo htmlspecialchars($func['sede_provincia'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($func['Direccion'] ?? '-'); ?></td>
                    <td style="white-space: nowrap;">
                        <a href="<?php echo BASE_URL; ?>/pages/marcaciones/listar.php?cedula=<?php echo urlencode($func['cedula']); ?>" 
                           class="btn btn-info" 
                           style="padding: 4px 8px; font-size: 0.85em; margin: 2px;">
                            <i class="fas fa-clock"></i> Marcacion
                        </a>
                        <?php if (Auth::isAdmin()): ?>
                        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/editar.php?cedula=<?php echo urlencode($func['cedula']); ?>" 
                           class="btn btn-success" style="padding: 4px 8px; font-size: 0.85em; margin: 2px;">Editar</a>
                        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/eliminar.php?cedula=<?php echo urlencode($func['cedula']); ?>" 
                           class="btn btn-danger" 
                           style="padding: 4px 8px; font-size: 0.85em; margin: 2px;"
                           onclick="return confirm('¿Está seguro de eliminar este funcionario?')">Eliminar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <style>
        .table-excel {
            background: white;
            border: 1px solid #ddd;
        }
        .table-excel thead {
            background: #2c3e50;
            color: white;
        }
        .table-excel thead th {
            padding: 8px 6px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1a252f;
            font-size: 0.85em;
            position: relative;
        }
        
        .table-excel thead th a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            transition: opacity 0.2s;
        }
        
        .table-excel thead th a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }
        
        .table-excel thead th:last-child a {
            justify-content: flex-start;
            cursor: default;
        }
        
        .table-excel thead th:last-child a:hover {
            opacity: 1;
            text-decoration: none;
        }
        .table-excel tbody tr {
            border-bottom: 1px solid #ddd;
        }
        .table-excel tbody tr:hover {
            background-color: #f5f5f5;
        }
        .table-excel tbody td {
            padding: 6px 4px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        .table-excel tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table-excel tbody tr:nth-child(even):hover {
            background-color: #f0f0f0;
        }
        
        /* Anchos específicos para columnas */
        /* Cédula - aumentar ancho para mejor visualización */
        .table-excel thead th:nth-child(1),
        .table-excel tbody td:nth-child(1) {
            min-width: 150px;
            width: 15%;
            white-space: nowrap;
        }
        
        /* Nombre - aumentar 100% */
        .table-excel thead th:nth-child(2),
        .table-excel tbody td:nth-child(2) {
            min-width: 150px;
            width: 10%;
        }
        
        /* Apellido - aumentar 100% */
        .table-excel thead th:nth-child(3),
        .table-excel tbody td:nth-child(3) {
            min-width: 150px;
            width: 10%;
        }
    </style>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

