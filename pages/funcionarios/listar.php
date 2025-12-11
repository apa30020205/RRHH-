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
    <h2>
        Lista de Funcionarios
        <?php if (isset($totalResultados)): ?>
            - <?php echo number_format($totalResultados); ?>
            <?php if (!empty($busqueda) && isset($total)): ?>
                <span style="font-size: 0.7em; font-weight: normal; color: #666;">
                    (de <?php echo number_format($total); ?> totales)
                </span>
            <?php endif; ?>
        <?php endif; ?>
    </h2>
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
                            No. Pos. <?php echo iconoOrdenamiento('no_posicion'); ?>
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
                    <td style="white-space: nowrap; display: flex; align-items: center; gap: 4px;">
                        <a href="<?php echo BASE_URL; ?>/pages/marcaciones/listar.php?cedula=<?php echo urlencode($func['cedula']); ?>" 
                           class="btn btn-info btn-action-icon" 
                           title="Ver Marcaciones">
                            <i class="fas fa-stopwatch"></i>
                        </a>
                        <?php if (Auth::isAdmin()): ?>
                        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/editar.php?cedula=<?php echo urlencode($func['cedula']); ?>" 
                           class="btn btn-success btn-action-icon" 
                           title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/eliminar.php?cedula=<?php echo urlencode($func['cedula']); ?>" 
                           class="btn btn-danger btn-action-icon" 
                           title="Eliminar"
                           onclick="return confirm('¿Está seguro de eliminar este funcionario?')">
                            <i class="fas fa-times"></i>
                        </a>
                        <select class="select-fun-extra" 
                                data-cedula="<?php echo htmlspecialchars($func['cedula']); ?>"
                                onchange="actualizarFunExtra('<?php echo htmlspecialchars($func['cedula']); ?>', this.value)">
                            <option value="">---</option>
                            <option value="Jefe" <?php echo (isset($func['fun_extra']) && $func['fun_extra'] === 'Jefe') ? 'selected' : ''; ?>>Jefe</option>
                            <option value="Manual" <?php echo (isset($func['fun_extra']) && $func['fun_extra'] === 'Manual') ? 'selected' : ''; ?>>Manual</option>
                            <option value="cesante" <?php echo (isset($func['fun_extra']) && $func['fun_extra'] === 'cesante') ? 'selected' : ''; ?>>cesante</option>
                            <option value="Préstamo" <?php echo (isset($func['fun_extra']) && $func['fun_extra'] === 'Préstamo') ? 'selected' : ''; ?>>Préstamo</option>
                            <option value="Lic. Sueldo" <?php echo (isset($func['fun_extra']) && $func['fun_extra'] === 'Lic. Sueldo') ? 'selected' : ''; ?>>Lic. Sueldo</option>
                            <option value="Lic. Sin Sueldo" <?php echo (isset($func['fun_extra']) && $func['fun_extra'] === 'Lic. Sin Sueldo') ? 'selected' : ''; ?>>Lic. Sin Sueldo</option>
                            <option value="otro" <?php echo (isset($func['fun_extra']) && $func['fun_extra'] === 'otro') ? 'selected' : ''; ?>>otro</option>
                        </select>
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
            font-size: 1em;
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
            min-width: 100px;
            width: 8%;
            white-space: nowrap;
        }
        
        /* Nombre - aumentar 100% */
        .table-excel thead th:nth-child(2),
        .table-excel tbody td:nth-child(2) {
            min-width: 100px;
            width: 10%;
        }
        
        /* Apellido - aumentar 100% */
        .table-excel thead th:nth-child(3),
        .table-excel tbody td:nth-child(3) {
            min-width: 100px;
            width: 10%;
        }
        
        /* Edad - reducir ancho */
        .table-excel thead th:nth-child(5),
        .table-excel tbody td:nth-child(5) {
            min-width: 50px;
            width: 4%;
        }
        
        /* Sangre - reducir ancho */
        .table-excel thead th:nth-child(6),
        .table-excel tbody td:nth-child(6) {
            min-width: 60px;
            width: 5%;
        }
        
        /* No. Pos. - reducir ancho */
        .table-excel thead th:nth-child(7),
        .table-excel tbody td:nth-child(7) {
            min-width: 70px;
            width: 5%;
        }
        
        /* Acciones - aumentar ancho para dropdown */
        .table-excel thead th:last-child,
        .table-excel tbody td:last-child {
            min-width: 220px;
            width: 15%;
        }
        
        /* Contenedor de botones de acción - alinear verticalmente */
        .table-excel tbody td:last-child {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: nowrap;
        }
        
        /* Botones de acción - solo iconos */
        .btn-action-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            text-decoration: none;
            margin: 0;
            transition: all 0.2s;
            font-size: 1em;
            flex-shrink: 0;
            vertical-align: middle;
        }
        
        /* Botón del reloj (Ver Marcaciones) - ajustado */
        .btn-action-icon.btn-info {
            width: 32px;
            height: 32px;
            font-size: 1.1em;
            background-color: #17a2b8 !important;
            border-color: #17a2b8 !important;
            color: white !important;
        }
        
        .btn-action-icon i {
            margin: 0;
        }
        
        .btn-action-icon:hover {
            transform: scale(1.1);
            opacity: 0.9;
        }
        
        /* Mantener color azul en hover del botón del reloj */
        .btn-action-icon.btn-info:hover {
            background-color: #138496 !important;
            border-color: #117a8b !important;
            color: white !important;
        }
        
        .btn-action-icon:active {
            transform: scale(0.95);
        }
        
        /* Mantener color azul en el botón del reloj al hacer clic - TODOS los estados */
        .btn-action-icon.btn-info:active,
        .btn-action-icon.btn-info:focus,
        .btn-action-icon.btn-info:focus-visible,
        .btn-action-icon.btn-info:visited,
        .btn-action-icon.btn-info:link {
            background-color: #17a2b8 !important;
            border-color: #17a2b8 !important;
            color: white !important;
            outline: none !important;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.5) !important;
        }
        
        /* Estilos para dropdown fun_extra */
        .select-fun-extra {
            height: 32px;
            padding: 4px 6px;
            font-size: 0.85em;
            border-radius: 4px;
            width: 90px;
            border: 1px solid #ccc;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .select-fun-extra:hover {
            border-color: #999;
        }
        
        .select-fun-extra:focus {
            outline: none;
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }
    </style>
    
    <script>
    // Función para actualizar fun_extra
        function actualizarFunExtra(cedula, valor) {
            try {
                // Si el valor está vacío, enviar null para borrar
                const valorEnviar = (valor === '' || valor === null) ? null : valor;
                
                // Validar que el valor no exceda 20 caracteres
                if (valorEnviar && valorEnviar.length > 20) {
                    alert('Error: El valor no puede exceder 20 caracteres');
                    return;
                }
                
                // Obtener el select para deshabilitarlo durante la petición
                const select = document.querySelector('.select-fun-extra[data-cedula="' + cedula + '"]');
                if (select) {
                    select.disabled = true;
                    select.style.opacity = '0.6';
                }
                
                // Hacer petición AJAX
                fetch('<?php echo BASE_URL; ?>/pages/funcionarios/actualizar_fun_extra.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        cedula: cedula,
                        fun_extra: valorEnviar
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error de red: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Actualización exitosa - no mostrar mensaje
                        console.log('fun_extra actualizado correctamente');
                    } else {
                        // Mostrar error
                        alert('Error: ' + (data.message || 'No se pudo actualizar el campo'));
                        // Revertir el valor del select
                        if (select) {
                            const valorAnterior = select.getAttribute('data-valor-anterior') || '';
                            select.value = valorAnterior;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al comunicarse con el servidor: ' + error.message);
                    // Revertir el valor del select
                    if (select) {
                        const valorAnterior = select.getAttribute('data-valor-anterior') || '';
                        select.value = valorAnterior;
                    }
                })
                .finally(() => {
                    // Rehabilitar select
                    if (select) {
                        select.disabled = false;
                        select.style.opacity = '1';
                        // Guardar el nuevo valor como valor anterior
                        select.setAttribute('data-valor-anterior', select.value);
                    }
                });
            } catch (error) {
                console.error('Error en actualizarFunExtra:', error);
                alert('Error de codificación: ' + error.message);
            }
        }
        
        // Guardar valores iniciales de los selects al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const selects = document.querySelectorAll('.select-fun-extra');
            selects.forEach(function(select) {
                select.setAttribute('data-valor-anterior', select.value);
            });
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

