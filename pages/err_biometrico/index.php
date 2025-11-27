<?php
/**
 * Err-Biométrico - Reportes de Errores
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Err-Biométrico - Reportes de Errores - Sistema RRHH';

// Solo administradores pueden acceder
if (!Auth::isAdmin()) {
    mostrarMensaje("No tienes permisos para acceder a esta sección", 'error');
    redirect(BASE_URL . '/pages/index.php');
}

// Obtener parámetros de búsqueda y ordenamiento
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$ordenarPor = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha_importacion';
$direccion = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'DESC' : 'ASC';
$mostrarResueltos = isset($_GET['resueltos']) && $_GET['resueltos'] === '1' ? true : false;

// Validar campo de ordenamiento (prevenir SQL injection)
$camposPermitidos = [
    'id_error', 'id_excel', 'nombre_excel', 'apellido_excel', 
    'fila_excel', 'fecha_importacion', 'resuelto', 'fecha_resolucion'
];

if (!in_array($ordenarPor, $camposPermitidos)) {
    $ordenarPor = 'fecha_importacion';
}

// Función para generar URL de ordenamiento
function urlOrdenar($campo, $busqueda, $resueltos) {
    $params = ['ordenar' => $campo];
    if (!empty($busqueda)) {
        $params['buscar'] = $busqueda;
    }
    if ($resueltos) {
        $params['resueltos'] = '1';
    }
    
    // Determinar dirección del ordenamiento
    $dirActual = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
    $campoActual = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha_importacion';
    
    if ($campoActual === $campo) {
        $params['dir'] = $dirActual === 'asc' ? 'desc' : 'asc';
    } else {
        $params['dir'] = 'asc';
    }
    
    return BASE_URL . '/pages/err_biometrico/index.php?' . http_build_query($params);
}

// Función para mostrar icono de ordenamiento
function iconoOrdenamiento($campo) {
    $campoActual = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'fecha_importacion';
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
    
    // Construir consulta con búsqueda, filtro de resueltos y ordenamiento
    $sql = "SELECT * FROM errores_importacion_biometrico";
    $params = [];
    $condiciones = [];
    
    // Filtrar por resueltos/pendientes
    if (!$mostrarResueltos) {
        $condiciones[] = "resuelto = 0";
    }
    
    // Agregar condición de búsqueda si existe
    if (!empty($busqueda)) {
        $busquedaLimpia = '%' . $busqueda . '%';
        $condicionesBusqueda = [
            "id_excel LIKE ?",
            "nombre_excel LIKE ?",
            "apellido_excel LIKE ?",
            "CAST(fila_excel AS CHAR) LIKE ?",
            "DATE_FORMAT(fecha_importacion, '%d/%m/%Y %H:%i') LIKE ?"
        ];
        $condiciones[] = "(" . implode(" OR ", $condicionesBusqueda) . ")";
        // Agregar parámetros para cada campo de búsqueda
        for ($i = 0; $i < 5; $i++) {
            $params[] = $busquedaLimpia;
        }
    }
    
    // Agregar condiciones WHERE si existen
    if (!empty($condiciones)) {
        $sql .= " WHERE " . implode(" AND ", $condiciones);
    }
    
    // Agregar ordenamiento
    $sql .= " ORDER BY ";
    if ($ordenarPor === 'nombre_excel' || $ordenarPor === 'apellido_excel') {
        // Para nombre y apellido, usar COALESCE para manejar NULL
        $sql .= "COALESCE($ordenarPor, '') $direccion";
        if ($ordenarPor === 'apellido_excel') {
            $sql .= ", COALESCE(nombre_excel, '') $direccion, id_excel $direccion";
        } else {
            $sql .= ", COALESCE(apellido_excel, '') $direccion, id_excel $direccion";
        }
    } else {
        // Para otros campos, ordenar directamente
        $sql .= "$ordenarPor $direccion";
        // Agregar ordenamiento secundario
        $sql .= ", fecha_importacion DESC";
    }
    
    // Ejecutar consulta
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $errores = $stmt->fetchAll();
    
    // Contar total (con búsqueda y filtro si aplica)
    $sqlCount = "SELECT COUNT(*) as total FROM errores_importacion_biometrico";
    if (!empty($condiciones)) {
        $sqlCount .= " WHERE " . implode(" AND ", $condiciones);
    }
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRegistros = $stmtCount->fetch()['total'];
    
    // Contar resueltos y pendientes
    $stmtResueltos = $db->query("SELECT COUNT(*) as total FROM errores_importacion_biometrico WHERE resuelto = 1");
    $totalResueltos = $stmtResueltos->fetch()['total'];
    
    $stmtPendientes = $db->query("SELECT COUNT(*) as total FROM errores_importacion_biometrico WHERE resuelto = 0");
    $totalPendientes = $stmtPendientes->fetch()['total'];
    
} catch (PDOException $e) {
    mostrarMensaje("Error al cargar errores: " . $e->getMessage(), 'error');
    $errores = [];
    $totalRegistros = 0;
    $totalResueltos = 0;
    $totalPendientes = 0;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Err-Biométrico - Errores de Importación Biométrica</h2>
    <a href="<?php echo BASE_URL; ?>/pages/index.php" class="btn">Volver al Inicio</a>
</div>

<!-- Estadísticas -->
<div class="stats-container" style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <div class="stat-card" style="background: #fff3cd; padding: 1rem; border-radius: 5px; border: 1px solid #ffc107; flex: 1; min-width: 200px;">
        <h3 style="margin: 0 0 0.5rem 0; color: #856404;">Total de Errores</h3>
        <p style="font-size: 2rem; margin: 0; font-weight: bold; color: #856404;"><?php echo $totalRegistros; ?></p>
    </div>
    <div class="stat-card" style="background: #d1ecf1; padding: 1rem; border-radius: 5px; border: 1px solid #0c5460; flex: 1; min-width: 200px;">
        <h3 style="margin: 0 0 0.5rem 0; color: #0c5460;">Pendientes</h3>
        <p style="font-size: 2rem; margin: 0; font-weight: bold; color: #0c5460;"><?php echo $totalPendientes; ?></p>
    </div>
    <div class="stat-card" style="background: #d4edda; padding: 1rem; border-radius: 5px; border: 1px solid #155724; flex: 1; min-width: 200px;">
        <h3 style="margin: 0 0 0.5rem 0; color: #155724;">Resueltos</h3>
        <p style="font-size: 2rem; margin: 0; font-weight: bold; color: #155724;"><?php echo $totalResueltos; ?></p>
    </div>
</div>

<!-- Formulario de búsqueda y filtros -->
<form method="GET" action="" class="search-form" style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1.5rem;">
    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <input type="text" name="buscar" placeholder="Buscar por ID, Nombre, Apellido, Fila..." 
                   value="<?php echo htmlspecialchars($busqueda); ?>" 
                   style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px;">
        </div>
        <div>
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" name="resueltos" value="1" <?php echo $mostrarResueltos ? 'checked' : ''; ?>>
                <span>Mostrar resueltos</span>
            </label>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            <?php if (!empty($busqueda) || $mostrarResueltos): ?>
                <a href="<?php echo BASE_URL; ?>/pages/err_biometrico/index.php" class="btn btn-secondary">Limpiar</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Tabla de errores -->
<?php if (count($errores) > 0): ?>
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse; background: white;">
            <thead>
                <tr style="background: #343a40; color: white;">
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('id_error', $busqueda, $mostrarResueltos); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            ID <?php echo iconoOrdenamiento('id_error'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('id_excel', $busqueda, $mostrarResueltos); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            ID Excel <?php echo iconoOrdenamiento('id_excel'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('nombre_excel', $busqueda, $mostrarResueltos); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Nombre <?php echo iconoOrdenamiento('nombre_excel'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('apellido_excel', $busqueda, $mostrarResueltos); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Apellido <?php echo iconoOrdenamiento('apellido_excel'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('fila_excel', $busqueda, $mostrarResueltos); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Fila Excel <?php echo iconoOrdenamiento('fila_excel'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('fecha_importacion', $busqueda, $mostrarResueltos); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Fecha Importación <?php echo iconoOrdenamiento('fecha_importacion'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">
                        <a href="<?php echo urlOrdenar('resuelto', $busqueda, $mostrarResueltos); ?>" 
                           style="color: white; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                            Estado <?php echo iconoOrdenamiento('resuelto'); ?>
                        </a>
                    </th>
                    <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errores as $error): ?>
                    <tr style="border-bottom: 1px solid #dee2e6; <?php echo $error['resuelto'] ? 'background: #f0f0f0;' : ''; ?>">
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($error['id_error']); ?></td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; font-weight: bold;"><?php echo htmlspecialchars($error['id_excel']); ?></td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($error['nombre_excel'] ?: '-'); ?></td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($error['apellido_excel'] ?: '-'); ?></td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; text-align: center;"><?php echo htmlspecialchars($error['fila_excel'] ?: '-'); ?></td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;">
                            <?php 
                            if ($error['fecha_importacion']) {
                                $fecha = new DateTime($error['fecha_importacion']);
                                echo $fecha->format('d/m/Y H:i');
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6; text-align: center;">
                            <?php if ($error['resuelto']): ?>
                                <span style="background: #28a745; color: white; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85em;">
                                    <i class="fas fa-check"></i> Resuelto
                                </span>
                            <?php else: ?>
                                <span style="background: #ffc107; color: #856404; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.85em;">
                                    <i class="fas fa-clock"></i> Pendiente
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 0.75rem; border: 1px solid #dee2e6;">
                            <?php if (!$error['resuelto']): ?>
                                <a href="#" onclick="marcarResuelto(<?php echo $error['id_error']; ?>); return false;" 
                                   class="btn btn-success" style="padding: 4px 8px; font-size: 0.85em;">
                                    <i class="fas fa-check"></i> Marcar Resuelto
                                </a>
                            <?php else: ?>
                                <a href="#" onclick="marcarPendiente(<?php echo $error['id_error']; ?>); return false;" 
                                   class="btn btn-warning" style="padding: 4px 8px; font-size: 0.85em;">
                                    <i class="fas fa-undo"></i> Marcar Pendiente
                                </a>
                            <?php endif; ?>
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
        <i class="fas fa-info-circle"></i> No se encontraron errores<?php echo !empty($busqueda) ? ' que coincidan con la búsqueda' : ''; ?>.
    </div>
<?php endif; ?>

<script>
function marcarResuelto(idError) {
    if (!confirm('¿Desea marcar este error como resuelto?')) {
        return;
    }
    
    fetch('<?php echo BASE_URL; ?>/pages/err_biometrico/marcar_resuelto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id_error: idError,
            resuelto: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo actualizar el estado'));
        }
    })
    .catch(error => {
        alert('Error al procesar la solicitud');
        console.error('Error:', error);
    });
}

function marcarPendiente(idError) {
    if (!confirm('¿Desea marcar este error como pendiente?')) {
        return;
    }
    
    fetch('<?php echo BASE_URL; ?>/pages/err_biometrico/marcar_resuelto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id_error: idError,
            resuelto: 0
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo actualizar el estado'));
        }
    })
    .catch(error => {
        alert('Error al procesar la solicitud');
        console.error('Error:', error);
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

