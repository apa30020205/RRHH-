<?php
/**
 * Página de Login
 * Módulo: roles_rrhh
 * Sistema RRHH
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../classes/Auth.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Iniciar Sesión - Sistema RRHH';

// Si ya está autenticado, redirigir
if (Auth::isAuthenticated()) {
    $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/pages/index.php';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit();
}

$error = '';
$mensaje = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, completa todos los campos';
    } else {
        $resultado = Auth::login($username, $password);
        
        if ($resultado['success']) {
            $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/pages/index.php';
            unset($_SESSION['redirect_after_login']);
            mostrarMensaje('Bienvenido, ' . $resultado['user']['nombre_completo'], 'success');
            header('Location: ' . $redirect);
            exit();
        } else {
            $error = $resultado['message'];
        }
    }
}

// Incluir header sin mostrar navegación en login
$pageTitle = 'Iniciar Sesión - Sistema RRHH';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body data-base-url="<?php echo BASE_URL; ?>">
<div class="login-container">
    <div class="login-box">
        <h2>
            <i class="fas fa-lock"></i>
            Iniciar Sesión
        </h2>
        <p class="login-subtitle">Sistema de Recursos Humanos</p>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="login-form">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i>
                    Usuario
                </label>
                <input type="text" id="username" name="username" required autofocus 
                       placeholder="Ingresa tu usuario" autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-key"></i>
                    Contraseña
                </label>
                <input type="password" id="password" name="password" required 
                       placeholder="Ingresa tu contraseña" autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
            </button>
        </form>
        
        <div class="login-info">
            <p><strong>Usuario por defecto:</strong></p>
            <p>Usuario: <code>admin</code></p>
            <p>Contraseña: <code>admin123</code></p>
            <p class="text-warning"><small>⚠️ Cambiar en producción</small></p>
        </div>
    </div>
</div>
</body>
</html>

