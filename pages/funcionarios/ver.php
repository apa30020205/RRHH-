<?php
/**
 * Ver Funcionario
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Ver Funcionario - Sistema RRHH';

if (!isset($_GET['cedula'])) {
    mostrarMensaje("Cédula no proporcionada", 'error');
    redirect(BASE_URL . '/pages/funcionarios/listar.php');
}

$cedula = sanitize($_GET['cedula']);
// La cédula en la BD tiene guiones, usarla tal cual (NO normalizar)

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM funcionarios WHERE cedula = ?");
    $stmt->execute([$cedula]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        mostrarMensaje("Funcionario no encontrado", 'error');
        redirect(BASE_URL . '/pages/funcionarios/listar.php');
    }
} catch (Exception $e) {
    mostrarMensaje("Error al cargar funcionario: " . $e->getMessage(), 'error');
    redirect(BASE_URL . '/pages/funcionarios/listar.php');
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Información del Funcionario</h2>
    <div>
        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/editar.php?cedula=<?php echo urlencode($funcionario['cedula']); ?>" class="btn btn-success">Editar</a>
        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/listar.php" class="btn">Volver</a>
    </div>
</div>

<div class="funcionario-detail">
    <table>
        <tr>
            <th>Cédula</th>
            <td><?php echo htmlspecialchars(formatearCedula($funcionario['cedula'])); ?></td>
        </tr>
        <tr>
            <th>Nombre</th>
            <td><?php echo htmlspecialchars($funcionario['nombre'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th>Apellido</th>
            <td><?php echo htmlspecialchars($funcionario['apellido'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th>Fecha de Nacimiento</th>
            <td><?php echo $funcionario['fecha_nacimiento'] ? formatearFecha($funcionario['fecha_nacimiento']) : '-'; ?></td>
        </tr>
        <tr>
            <th>Edad</th>
            <td><?php echo $funcionario['edad'] ? htmlspecialchars($funcionario['edad']) . ' años' : '-'; ?></td>
        </tr>
        <tr>
            <th>Tipo de Sangre</th>
            <td><?php echo htmlspecialchars($funcionario['sangre'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th>Número de Posición</th>
            <td><?php echo htmlspecialchars($funcionario['no_posicion'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th>Posición Funcional</th>
            <td><?php echo htmlspecialchars($funcionario['posicion_funcional'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th>Fecha de Inicio</th>
            <td><?php echo $funcionario['fecha_inicio'] ? formatearFecha($funcionario['fecha_inicio']) : '-'; ?></td>
        </tr>
        <tr>
            <th>Sede/Provincia</th>
            <td><?php echo htmlspecialchars($funcionario['sede_provincia'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th>Dirección</th>
            <td><?php echo htmlspecialchars($funcionario['Direccion'] ?? '-'); ?></td>
        </tr>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

