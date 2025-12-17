<?php
/**
 * EX/Funcionarios
 * Módulo de Mantenimiento
 * Lista funcionarios marcados como "EX/Funcionario" en fun_extra
 */

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Parámetros de búsqueda y ordenamiento
    $busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
    $ordenarPor = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'nombre';
    $direccion = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'DESC' : 'ASC';
    
    // Validar campo de ordenamiento
    $camposPermitidos = ['cedula', 'nombre', 'apellido', 'fecha_inicio', 'posicion_funcional'];
    if (!in_array($ordenarPor, $camposPermitidos)) {
        $ordenarPor = 'nombre';
    }
    
    // Construir consulta - leer de ex_funcionarios
    $sql = "SELECT * FROM ex_funcionarios";
    $params = [];
    
    if (!empty($busqueda)) {
        $sql .= " WHERE (cedula LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR posicion_funcional LIKE ?)";
        $busquedaLimpia = '%' . $busqueda . '%';
        $params = array_merge($params, [$busquedaLimpia, $busquedaLimpia, $busquedaLimpia, $busquedaLimpia]);
    }
    
    $sql .= " ORDER BY $ordenarPor $direccion";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $funcionarios = $stmt->fetchAll();
    
    $totalRegistros = count($funcionarios);
    
} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
    $funcionarios = [];
    $totalRegistros = 0;
}

// Función para generar URL de ordenamiento
function urlOrdenar($campo, $busqueda) {
    $params = ['ordenar' => $campo];
    if (!empty($busqueda)) {
        $params['buscar'] = $busqueda;
    }
    
    $dirActual = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
    $campoActual = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'nombre';
    
    if ($campoActual === $campo) {
        $params['dir'] = $dirActual === 'asc' ? 'desc' : 'asc';
    } else {
        $params['dir'] = 'asc';
    }
    
    return BASE_URL . '/pages/mantenimiento/index.php#cesante&' . http_build_query($params);
}

// Función para mostrar icono de ordenamiento
function iconoOrdenamiento($campo) {
    $campoActual = isset($_GET['ordenar']) ? $_GET['ordenar'] : 'nombre';
    $dirActual = isset($_GET['dir']) && $_GET['dir'] === 'desc' ? 'desc' : 'asc';
    
    if ($campoActual === $campo) {
        return $dirActual === 'desc' 
            ? '<i class="fas fa-sort-down"></i>' 
            : '<i class="fas fa-sort-up"></i>';
    }
    return '<i class="fas fa-sort"></i>';
}
?>
<style>
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

    .btn-action-icon.btn-info:hover {
        background-color: #138496 !important;
        border-color: #117a8b !important;
        color: white !important;
    }

    /* Contenedor de botones de acción - alinear verticalmente */
    .table-excel tbody td:last-child {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: nowrap;
    }
</style>

<div class="page-content">
    <div class="info-section" style="margin-bottom: 2rem; padding: 1rem; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
        <p><strong>Nota:</strong> Esta sección muestra ex-funcionarios que han sido cesados. 
        Una vez que un funcionario es marcado como "cesante" en la <a href="<?php echo BASE_URL; ?>/pages/funcionarios/listar.php">Lista de Funcionarios</a>, 
        sus datos se mueven automáticamente a esta tabla y ya no aparecen en el listado de funcionarios activos. Esta acción no se puede revertir.</p>
    </div>
    
    <!-- Búsqueda -->
    <div class="search-section" style="margin-bottom: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
        <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end;">
            <input type="hidden" name="seccion" value="cesante">
            <div style="flex: 1;">
                <label for="buscar">Buscar:</label>
                <input type="text" 
                       id="buscar" 
                       name="buscar" 
                       value="<?php echo htmlspecialchars($busqueda); ?>" 
                       placeholder="Cédula, Nombre, Apellido o Posición Funcional"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
            <?php if (!empty($busqueda)): ?>
            <a href="<?php echo BASE_URL; ?>/pages/mantenimiento/index.php#cesante" class="btn">
                Limpiar
            </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Lista de EX/Funcionarios -->
    <div class="funcionarios-section">
        <h3>EX/Funcionarios - Total: <?php echo $totalRegistros; ?></h3>
        
        <?php if ($totalRegistros === 0): ?>
        <div class="alert alert-info">
            No hay ex-funcionarios registrados.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table-excel" style="width: 100%;">
                <thead>
                    <tr>
                        <th>
                            <a href="<?php echo urlOrdenar('cedula', $busqueda); ?>" style="color: inherit; text-decoration: none;">
                                Cédula <?php echo iconoOrdenamiento('cedula'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo urlOrdenar('nombre', $busqueda); ?>" style="color: inherit; text-decoration: none;">
                                Nombre <?php echo iconoOrdenamiento('nombre'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo urlOrdenar('apellido', $busqueda); ?>" style="color: inherit; text-decoration: none;">
                                Apellido <?php echo iconoOrdenamiento('apellido'); ?>
                            </a>
                        </th>
                        <th>Fecha Nacimiento</th>
                        <th>Edad</th>
                        <th>Sangre</th>
                        <th>No. Pos.</th>
                        <th>
                            <a href="<?php echo urlOrdenar('posicion_funcional', $busqueda); ?>" style="color: inherit; text-decoration: none;">
                                Posición Funcional <?php echo iconoOrdenamiento('posicion_funcional'); ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo urlOrdenar('fecha_inicio', $busqueda); ?>" style="color: inherit; text-decoration: none;">
                                Fecha Inicio <?php echo iconoOrdenamiento('fecha_inicio'); ?>
                            </a>
                        </th>
                        <th>Sede/Provincia</th>
                        <th>Dirección</th>
                        <th>Especial</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $func): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($func['cedula']); ?></td>
                        <td><?php echo htmlspecialchars($func['nombre'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($func['apellido'] ?? ''); ?></td>
                        <td><?php echo $func['fecha_nacimiento'] ? htmlspecialchars($func['fecha_nacimiento']) : '-'; ?></td>
                        <td><?php echo $func['edad'] ? htmlspecialchars($func['edad']) : '-'; ?></td>
                        <td><?php echo $func['sangre'] ? htmlspecialchars($func['sangre']) : '-'; ?></td>
                        <td><?php echo $func['no_posicion'] ? htmlspecialchars($func['no_posicion']) : '-'; ?></td>
                        <td><?php echo $func['posicion_funcional'] ? htmlspecialchars($func['posicion_funcional']) : '-'; ?></td>
                        <td><?php echo $func['fecha_inicio'] ? htmlspecialchars($func['fecha_inicio']) : '-'; ?></td>
                        <td><?php echo $func['sede_provincia'] ? htmlspecialchars($func['sede_provincia']) : '-'; ?></td>
                        <td><?php echo $func['Direccion'] ? htmlspecialchars($func['Direccion']) : '-'; ?></td>
                        <td style="text-align: center;">
                            <?php if (isset($func['fun_horario_especial']) && intval($func['fun_horario_especial']) === 1): ?>
                                <span style="background-color: #28a745; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem; font-weight: bold;">
                                    <i class="fas fa-check-circle"></i> Sí
                                </span>
                            <?php else: ?>
                                <span style="color: #999;">No</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap; display: flex; align-items: center; gap: 4px;">
                            <a href="<?php echo BASE_URL; ?>/pages/marcaciones/listar.php?cedula=<?php echo urlencode($func['cedula']); ?>&ex_funcionario=1" 
                               class="btn btn-info btn-action-icon" 
                               title="Ver Marcaciones">
                                <i class="fas fa-stopwatch"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

