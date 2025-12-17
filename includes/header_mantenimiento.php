<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Mantenimiento - Sistema RRHH'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <style>
        /* Header de Mantenimiento */
        .header-mantenimiento {
            background-color: #2c3e50;
            color: white;
            padding: 2rem 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .header-mantenimiento .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header-mantenimiento h1 {
            margin-bottom: 1.5rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            padding-bottom: 0.5rem;
        }
        
        .nav-mantenimiento {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .nav-mantenimiento ul {
            list-style: none;
            display: flex;
            gap: 1rem;
            margin: 0;
            padding: 0;
            flex-wrap: wrap;
        }
        
        .nav-mantenimiento a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            background-color: #e74c3c !important;  /* Naranja/Rojo igual que btn-logout */
            border: none !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .nav-mantenimiento a:hover {
            background-color: #c0392b !important;  /* Naranja/Rojo más oscuro */
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.4) !important;
        }
        
        .nav-mantenimiento a.active {
            background-color: #c0392b !important;
            font-weight: bold;
        }
        
        .btn-volver-mantenimiento {
            background-color: #6c757d !important;
            color: white !important;
        }
        
        .btn-volver-mantenimiento:hover {
            background-color: #5a6268 !important;
        }
    </style>
</head>
<body data-base-url="<?php echo BASE_URL; ?>">
    <header class="header-mantenimiento">
        <div class="container">
            <h1>Módulo de Mantenimiento</h1>
            <nav class="nav-mantenimiento">
                <ul>
                    <li><a href="#importar-excel" class="nav-mant-link" data-section="importar-excel">
                        <i class="fas fa-file-excel"></i> Importar Excel
                    </a></li>
                    <li><a href="#crear-editar" class="nav-mant-link" data-section="crear-editar">
                        <i class="fas fa-user-edit"></i> Crear/Editar
                    </a></li>
                    <li><a href="#horario-manual" class="nav-mant-link" data-section="horario-manual">
                        <i class="fas fa-clock"></i> Horario Manual
                    </a></li>
                    <li><a href="#cesante" class="nav-mant-link" data-section="cesante">
                        <i class="fas fa-user-times"></i> EX/Funcionario
                    </a></li>
                    <li><a href="#regionales-especiales" class="nav-mant-link" data-section="regionales-especiales">
                        <i class="fas fa-building"></i> Regionales Especiales
                    </a></li>
                </ul>
                <a href="<?php echo BASE_URL; ?>/pages/index.php" class="btn-volver-mantenimiento">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
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

