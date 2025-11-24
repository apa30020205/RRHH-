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

try {
    $db = Database::getInstance()->getConnection();
    // Ordenar por cedula si apellido o nombre son NULL
    $stmt = $db->query("SELECT * FROM funcionarios ORDER BY 
        COALESCE(apellido, '') ASC, 
        COALESCE(nombre, '') ASC, 
        cedula ASC");
    $funcionarios = $stmt->fetchAll();
    
    // Contar total
    $stmtCount = $db->query("SELECT COUNT(*) as total FROM funcionarios");
    $total = $stmtCount->fetch()['total'];
} catch (Exception $e) {
    $funcionarios = [];
    $total = 0;
    mostrarMensaje("Error al cargar funcionarios: " . $e->getMessage(), 'error');
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

<?php if (isset($total) && $total > 0): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;">
        <strong>Total de funcionarios:</strong> <?php echo number_format($total); ?>
    </div>
<?php endif; ?>

<?php if (empty($funcionarios)): ?>
    <div class="alert alert-info">
        No hay funcionarios registrados. <a href="<?php echo BASE_URL; ?>/pages/funcionarios/crear.php">Crear el primero</a>
    </div>
<?php else: ?>
    <div style="overflow-x: auto; margin: 20px 0;">
        <table class="table-excel" style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
            <thead>
                <tr>
                    <th>Cédula</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Fecha Nac.</th>
                    <th>Edad</th>
                    <th>Sangre</th>
                    <th>No. Posición</th>
                    <th>Posición Funcional</th>
                    <th>Fecha Inicio</th>
                    <th>Sede/Provincia</th>
                    <th>Dirección</th>
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
                        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/ver.php?cedula=<?php echo urlencode($func['cedula']); ?>" 
                           class="btn btn-primary" style="padding: 4px 8px; font-size: 0.85em; margin: 2px;">Ver</a>
                        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/editar.php?cedula=<?php echo urlencode($func['cedula']); ?>" 
                           class="btn btn-success" style="padding: 4px 8px; font-size: 0.85em; margin: 2px;">Editar</a>
                        <?php if (Auth::isAdmin()): ?>
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
        /* Cédula - aumentar 50% */
        .table-excel thead th:nth-child(1),
        .table-excel tbody td:nth-child(1) {
            min-width: 120px;
            width: 12%;
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

