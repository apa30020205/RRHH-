<?php
/**
 * Script web para ejecutar la migración de tabla de errores
 * Sistema RRHH
 * 
 * Acceder desde: http://localhost/SISTEMA%20RRHH/ejecutar_migracion_errores.php
 */

require_once __DIR__ . '/config/database.php';

// Solo permitir ejecución si hay un parámetro de confirmación
$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === 'si';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ejecutar Migración - Tabla de Errores</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .sql-code {
            background: #f4f4f4;
            padding: 15px;
            border-left: 4px solid #4CAF50;
            margin: 20px 0;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn-danger {
            background: #f44336;
        }
        .btn-danger:hover {
            background: #da190b;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border: 1px solid #bee5eb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Ejecutar Migración: Tabla de Errores de Importación Biométrica</h1>
        
        <?php if (!$confirmar): ?>
            <div class="warning">
                <strong>⚠️ Advertencia:</strong> Este script creará la tabla <code>errores_importacion_biometrico</code> en la base de datos.
            </div>
            
            <div class="info">
                <strong>📋 Descripción:</strong><br>
                Esta migración crea una tabla para almacenar los errores de importación del archivo "personal biométrico"
                cuando el ID del Excel no existe como cédula en la tabla funcionarios.
            </div>
            
            <h2>SQL que se ejecutará:</h2>
            <div class="sql-code"><?php 
                $sql = file_get_contents(__DIR__ . '/database/migrations/20251121_crear_tabla_errores_importacion.sql');
                echo htmlspecialchars($sql);
            ?></div>
            
            <div style="margin-top: 30px;">
                <a href="?confirmar=si" class="btn">✅ Ejecutar Migración</a>
                <a href="pages/index.php" class="btn btn-danger">❌ Cancelar</a>
            </div>
        <?php else: ?>
            <?php
            try {
                $db = getDBConnection();
                
                echo '<div class="info"><strong>Ejecutando migración...</strong></div>';
                
                // Leer y ejecutar la migración
                $sql = file_get_contents(__DIR__ . '/database/migrations/20251121_crear_tabla_errores_importacion.sql');
                
                // Extraer comandos SQL (ignorar comentarios)
                $lines = explode("\n", $sql);
                $currentCommand = '';
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Ignorar líneas vacías y comentarios
                    if (empty($line) || strpos($line, '--') === 0) {
                        continue;
                    }
                    $currentCommand .= $line . ' ';
                    // Si la línea termina con punto y coma, es el final del comando
                    if (substr(rtrim($line), -1) === ';') {
                        $command = trim($currentCommand);
                        if (!empty($command)) {
                            echo '<div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
                            echo '<strong>Ejecutando:</strong> ' . htmlspecialchars(substr($command, 0, 150)) . '...<br>';
                            $db->exec($command);
                            echo '<span style="color: #28a745;">✓ Comando ejecutado correctamente</span>';
                            echo '</div>';
                        }
                        $currentCommand = '';
                    }
                }
                
                echo '<div class="success">';
                echo '<strong>✓ Migración ejecutada correctamente</strong><br><br>';
                
                // Verificar que la tabla se creó
                $stmt = $db->query("SHOW TABLES LIKE 'errores_importacion_biometrico'");
                $tablaExiste = $stmt->rowCount() > 0;
                
                if ($tablaExiste) {
                    echo '<strong>✓ Tabla creada exitosamente</strong><br><br>';
                    
                    // Mostrar estructura de la tabla
                    $stmt = $db->query("DESCRIBE errores_importacion_biometrico");
                    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo '<strong>Estructura de la tabla:</strong><br>';
                    echo '<table style="width: 100%; border-collapse: collapse; margin-top: 10px;">';
                    echo '<tr style="background: #f8f9fa;"><th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Campo</th><th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Tipo</th><th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Nulo</th><th style="padding: 8px; border: 1px solid #dee2e6; text-align: left;">Default</th></tr>';
                    foreach ($columnas as $col) {
                        echo '<tr>';
                        echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($col['Field']) . '</td>';
                        echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($col['Type']) . '</td>';
                        echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($col['Null']) . '</td>';
                        echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($col['Default'] ?? 'NULL') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    
                    // Contar registros (debería ser 0)
                    $stmt = $db->query("SELECT COUNT(*) as total FROM errores_importacion_biometrico");
                    $total = $stmt->fetch()['total'];
                    echo '<br><strong>Total de registros en la tabla:</strong> ' . $total;
                    
                } else {
                    echo '<div class="error">';
                    echo '<strong>⚠️ Error:</strong> La tabla no se pudo verificar. Por favor, revisa manualmente en phpMyAdmin.';
                    echo '</div>';
                }
                
                echo '</div>';
                
                echo '<div style="margin-top: 30px;">';
                echo '<a href="pages/mantenimiento/index.php" class="btn">📋 Ir a Mantenimiento</a>';
                echo '<a href="pages/index.php" class="btn">🏠 Volver al Inicio</a>';
                echo '</div>';
                
            } catch (PDOException $e) {
                echo '<div class="error">';
                echo '<strong>❌ Error al ejecutar la migración:</strong><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';
                
                echo '<div style="margin-top: 30px;">';
                echo '<a href="?" class="btn">🔄 Intentar de nuevo</a>';
                echo '<a href="pages/index.php" class="btn btn-danger">❌ Cancelar</a>';
                echo '</div>';
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<strong>❌ Error inesperado:</strong><br>';
                echo htmlspecialchars($e->getMessage());
                echo '</div>';
            }
            ?>
        <?php endif; ?>
    </div>
</body>
</html>

