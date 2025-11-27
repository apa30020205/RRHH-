<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Sistema RRHH'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body data-base-url="<?php echo BASE_URL; ?>">
    <header>
        <div class="container">
            <h1>Sistema de Recursos Humanos</h1>
            <nav>
                <ul>
                    <li><a href="<?php echo BASE_URL; ?>/pages/index.php">Inicio</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/funcionarios/listar.php">Funcionarios</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/forms/permisos/index.php">Permisos Pendientes</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/services/excel/importar.php">Importar Marcaciones</a></li>
                    <?php
                    // Mostrar opciones según rol
                    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador'):
                    ?>
                    <li><a href="<?php echo BASE_URL; ?>/pages/err_biometrico/index.php">Err-Biométrico</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/pages/err_rrhh/index.php">Err-RRHH</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/usuarios/listar.php">Usuarios</a></li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['autenticado']) && $_SESSION['autenticado']): ?>
                    <li style="margin-left: auto;"><a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/logout.php" class="btn-logout">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a></li>
                    <?php else: ?>
                    <li style="margin-left: auto;"><a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/login.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </a></li>
                    <?php endif; ?>
                </ul>
                <div class="user-menu">
                    <?php if (isset($_SESSION['autenticado']) && $_SESSION['autenticado']): ?>
                        <span class="user-info">
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['username']); ?>
                            <span class="badge badge-<?php echo $_SESSION['rol'] === 'administrador' ? 'admin' : 'user'; ?>">
                                <?php echo $_SESSION['rol'] === 'administrador' ? 'Admin' : 'Usuario'; ?>
                            </span>
                        </span>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>
    <main class="container">
        <?php
        // Mostrar mensajes si existen
        $mensaje = obtenerMensaje();
        if ($mensaje):
        ?>
        <div class="alert alert-<?php echo $mensaje['tipo']; ?>">
            <?php echo htmlspecialchars($mensaje['texto']); ?>
        </div>
        <?php endif; ?>

