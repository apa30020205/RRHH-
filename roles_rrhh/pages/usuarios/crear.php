<?php
/**
 * Crear Usuario (Solo Administradores)
 * Módulo: roles_rrhh
 * Sistema RRHH
 */

require_once __DIR__ . '/../../../config/constants.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../classes/Database.php';
require_once __DIR__ . '/../../middleware/admin_middleware.php';

$pageTitle = 'Crear Usuario - Sistema RRHH';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];
        $nombre_completo = trim($_POST['nombre_completo']);
        $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
        $rol = $_POST['rol'];
        
        // Validaciones
        if (empty($username) || empty($password) || empty($nombre_completo)) {
            throw new Exception("Todos los campos obligatorios deben completarse");
        }
        
        if ($password !== $password_confirm) {
            throw new Exception("Las contraseñas no coinciden");
        }
        
        if (strlen($password) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres");
        }
        
        // Verificar si el usuario ya existe
        $stmtCheck = $db->prepare("SELECT id_usuario FROM usuarios WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetch()) {
            throw new Exception("El usuario ya existe");
        }
        
        // Hash de contraseña
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Obtener ID del administrador actual
        $currentUser = Auth::getCurrentUser();
        
        // Insertar usuario
        $stmt = $db->prepare("
            INSERT INTO usuarios (username, password_hash, nombre_completo, email, rol, creado_por)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $username,
            $password_hash,
            $nombre_completo,
            $email,
            $rol,
            $currentUser['id']
        ]);
        
        mostrarMensaje("Usuario creado exitosamente", 'success');
        redirect(BASE_URL . '/roles_rrhh/pages/usuarios/listar.php');
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            mostrarMensaje("El usuario o email ya existe", 'error');
        } else {
            mostrarMensaje("Error al crear usuario: " . $e->getMessage(), 'error');
        }
    } catch (Exception $e) {
        mostrarMensaje($e->getMessage(), 'error');
    }
}

include __DIR__ . '/../../../includes/header.php';
?>

<div class="page-header">
    <h2>Crear Nuevo Usuario</h2>
    <a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/usuarios/listar.php" class="btn">Volver</a>
</div>

<form method="POST" action="" data-validate>
    <div class="form-group">
        <label for="username">Usuario *</label>
        <input type="text" id="username" name="username" required maxlength="50" autocomplete="off">
        <small>Nombre de usuario único para iniciar sesión</small>
    </div>
    
    <div class="form-group">
        <label for="password">Contraseña *</label>
        <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
        <small>Mínimo 6 caracteres</small>
    </div>
    
    <div class="form-group">
        <label for="password_confirm">Confirmar Contraseña *</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="6" autocomplete="new-password">
    </div>
    
    <div class="form-group">
        <label for="nombre_completo">Nombre Completo *</label>
        <input type="text" id="nombre_completo" name="nombre_completo" required maxlength="100">
    </div>
    
    <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="100">
        <small>Opcional</small>
    </div>
    
    <div class="form-group">
        <label for="rol">Rol *</label>
        <select id="rol" name="rol" required>
            <option value="usuario">Usuario</option>
            <option value="administrador">Administrador</option>
        </select>
        <small>Los administradores pueden gestionar usuarios y modificar datos</small>
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Crear Usuario</button>
        <a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/usuarios/listar.php" class="btn">Cancelar</a>
    </div>
</form>

<script>
// Validar que las contraseñas coincidan
document.getElementById('password_confirm').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirm = this.value;
    
    if (password !== confirm) {
        this.setCustomValidity('Las contraseñas no coinciden');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

