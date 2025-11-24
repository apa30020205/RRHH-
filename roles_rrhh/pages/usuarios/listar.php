<?php
/**
 * Listar Usuarios (Solo Administradores)
 * Módulo: roles_rrhh
 * Sistema RRHH
 */

require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../middleware/admin_middleware.php';

$pageTitle = 'Gestión de Usuarios - Sistema RRHH';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT u.*, 
               creador.username as creado_por_username
        FROM usuarios u
        LEFT JOIN usuarios creador ON u.creado_por = creador.id_usuario
        ORDER BY u.fecha_creacion DESC
    ");
    $usuarios = $stmt->fetchAll();
} catch (Exception $e) {
    $usuarios = [];
    mostrarMensaje("Error al cargar usuarios: " . $e->getMessage(), 'error');
}

include __DIR__ . '/../../../includes/header.php';
?>

<div class="page-header">
    <h2>Gestión de Usuarios</h2>
    <a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/usuarios/crear.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Nuevo Usuario
    </a>
</div>

<?php if (empty($usuarios)): ?>
    <div class="alert alert-info">
        No hay usuarios registrados.
    </div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre Completo</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Creado Por</th>
                <th>Último Acceso</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usuario): ?>
            <tr>
                <td><?php echo htmlspecialchars($usuario['username']); ?></td>
                <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                <td><?php echo htmlspecialchars($usuario['email'] ?? '-'); ?></td>
                <td>
                    <span class="badge badge-<?php echo $usuario['rol'] === 'administrador' ? 'admin' : 'user'; ?>">
                        <?php echo $usuario['rol'] === 'administrador' ? 'Administrador' : 'Usuario'; ?>
                    </span>
                </td>
                <td>
                    <span class="badge badge-<?php echo $usuario['activo'] ? 'success' : 'danger'; ?>">
                        <?php echo $usuario['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </td>
                <td><?php echo htmlspecialchars($usuario['creado_por_username'] ?? 'Sistema'); ?></td>
                <td><?php echo $usuario['ultimo_acceso'] ? formatearFecha($usuario['ultimo_acceso'], 'd/m/Y H:i') : 'Nunca'; ?></td>
                <td>
                    <a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/usuarios/editar.php?id=<?php echo $usuario['id_usuario']; ?>" 
                       class="btn btn-success">Editar</a>
                    <?php if ($usuario['id_usuario'] != Auth::getCurrentUser()['id']): ?>
                    <a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/usuarios/eliminar.php?id=<?php echo $usuario['id_usuario']; ?>" 
                       class="btn btn-danger"
                       onclick="return confirmDelete('¿Está seguro de eliminar este usuario?')">Eliminar</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

