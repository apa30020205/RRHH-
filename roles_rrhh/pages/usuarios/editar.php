<?php
/**
 * Editar Usuario (Solo Administradores)
 * Módulo: roles_rrhh
 * Sistema RRHH
 */

require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../middleware/admin_middleware.php';

$pageTitle = 'Editar Usuario - Sistema RRHH';

if (!isset($_GET['id'])) {
    mostrarMensaje("ID de usuario no proporcionado", 'error');
    redirect(BASE_URL . '/roles_rrhh/pages/usuarios/listar.php');
}

$id_usuario = intval($_GET['id']);

try {
    $db = Database::getInstance()->getConnection();
    
    // Cargar datos del usuario
    $stmt = $db->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
    $stmt->execute([$id_usuario]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        mostrarMensaje("Usuario no encontrado", 'error');
        redirect(BASE_URL . '/roles_rrhh/pages/usuarios/listar.php');
    }
    
    // Procesar actualización
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre_completo = trim($_POST['nombre_completo']);
        $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
        $rol = $_POST['rol'];
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        // Si se proporciona nueva contraseña
        $updatePassword = false;
        if (!empty($_POST['password'])) {
            if ($_POST['password'] !== $_POST['password_confirm']) {
                throw new Exception("Las contraseñas no coinciden");
            }
            if (strlen($_POST['password']) < 6) {
                throw new Exception("La contraseña debe tener al menos 6 caracteres");
            }
            $password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $updatePassword = true;
        }
        
        if ($updatePassword) {
            $stmt = $db->prepare("
                UPDATE usuarios SET
                    nombre_completo = ?, email = ?, rol = ?, activo = ?, password_hash = ?
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nombre_completo, $email, $rol, $activo, $password_hash, $id_usuario]);
        } else {
            $stmt = $db->prepare("
                UPDATE usuarios SET
                    nombre_completo = ?, email = ?, rol = ?, activo = ?
                WHERE id_usuario = ?
            ");
            $stmt->execute([$nombre_completo, $email, $rol, $activo, $id_usuario]);
        }
        
        mostrarMensaje("Usuario actualizado exitosamente", 'success');
        redirect(BASE_URL . '/roles_rrhh/pages/usuarios/listar.php');
    }
    
} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
}

include __DIR__ . '/../../../includes/header.php';
?>

<div class="page-header">
    <h2>Editar Usuario</h2>
    <a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/usuarios/listar.php" class="btn">Volver</a>
</div>

<form method="POST" action="">
    <div class="form-group">
        <label for="username">Usuario</label>
        <input type="text" id="username" value="<?php echo htmlspecialchars($usuario['username']); ?>" disabled>
        <small>El usuario no se puede modificar</small>
    </div>
    
    <div class="form-group">
        <label for="nombre_completo">Nombre Completo *</label>
        <input type="text" id="nombre_completo" name="nombre_completo" 
               value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required maxlength="100">
    </div>
    
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" 
               value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>" maxlength="100">
    </div>
    
    <div class="form-group">
        <label for="rol">Rol *</label>
        <select id="rol" name="rol" required>
            <option value="usuario" <?php echo $usuario['rol'] === 'usuario' ? 'selected' : ''; ?>>Usuario</option>
            <option value="administrador" <?php echo $usuario['rol'] === 'administrador' ? 'selected' : ''; ?>>Administrador</option>
        </select>
    </div>
    
    <div class="form-group">
        <label>
            <input type="checkbox" name="activo" value="1" <?php echo $usuario['activo'] ? 'checked' : ''; ?>>
            Usuario activo
        </label>
        <small>Los usuarios inactivos no pueden iniciar sesión</small>
    </div>
    
    <div class="form-group">
        <label for="password">Nueva Contraseña</label>
        <input type="password" id="password" name="password" minlength="6" autocomplete="new-password">
        <small>Dejar vacío para mantener la contraseña actual</small>
    </div>
    
    <div class="form-group">
        <label for="password_confirm">Confirmar Nueva Contraseña</label>
        <input type="password" id="password_confirm" name="password_confirm" minlength="6" autocomplete="new-password">
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
        <a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/usuarios/listar.php" class="btn">Cancelar</a>
    </div>
</form>

<script>
// Validar contraseñas solo si se ingresa una nueva
const passwordInput = document.getElementById('password');
const passwordConfirmInput = document.getElementById('password_confirm');

passwordInput.addEventListener('input', function() {
    if (this.value.length > 0) {
        passwordConfirmInput.required = true;
    } else {
        passwordConfirmInput.required = false;
        passwordConfirmInput.value = '';
    }
});

passwordConfirmInput.addEventListener('input', function() {
    const password = passwordInput.value;
    const confirm = this.value;
    
    if (password.length > 0 && password !== confirm) {
        this.setCustomValidity('Las contraseñas no coinciden');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

