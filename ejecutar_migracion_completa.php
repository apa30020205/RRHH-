<?php
/**
 * Script web para ejecutar la migración completa
 * Sistema RRHH
 * 
 * Acceder desde: http://localhost/SISTEMA%20RRHH/ejecutar_migracion_completa.php
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
    <title>Ejecutar Migración Completa - Sistema RRHH</title>
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
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #17a2b8;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .field-nullable {
            color: #28a745;
            font-weight: bold;
        }
        .field-required {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Ejecutar Migración Completa</h1>
        
        <div class="info">
            <strong>Archivo:</strong> database/migrations/20251121_permitir_null_todos_campos.sql<br>
            <strong>Descripción:</strong> Permite NULL en todos los campos excepto `cedula`<br>
            <strong>Objetivo:</strong> Facilitar la importación de datos incompletos desde Excel
        </div>

        <h2>Contenido de la Migración:</h2>
        <div class="sql-code"><?php
            $sql = file_get_contents(__DIR__ . '/database/migrations/20251121_permitir_null_todos_campos.sql');
            echo htmlspecialchars($sql);
        ?></div>

        <?php if (!$confirmar): ?>
            <div class="warning">
                <strong>⚠️ ADVERTENCIA IMPORTANTE:</strong>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Esta operación modificará la estructura de la tabla <code>`funcionarios`</code></li>
                    <li>Todos los campos (excepto <code>cedula</code>) permitirán valores NULL</li>
                    <li><strong>Haz un respaldo de la base de datos antes de continuar</strong></li>
                    <li>Los datos existentes no se perderán, solo se cambiarán las restricciones</li>
                </ul>
            </div>
            <a href="?confirmar=si" class="btn">✅ Ejecutar Migración</a>
            <a href="../pages/index.php" class="btn btn-danger">❌ Cancelar</a>
        <?php else: ?>
            <?php
            try {
                $db = getDBConnection();
                
                echo '<div class="info"><strong>Ejecutando migración...</strong></div>';
                
                // Leer y ejecutar la migración
                $sql = file_get_contents(__DIR__ . '/database/migrations/20251121_permitir_null_todos_campos.sql');
                
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
                            echo '<strong>Ejecutando:</strong> ' . htmlspecialchars(substr($command, 0, 100)) . '...<br>';
                            $db->exec($command);
                            echo '<span style="color: #28a745;">✓ Comando ejecutado correctamente</span>';
                            echo '</div>';
                        }
                        $currentCommand = '';
                    }
                }
                
                echo '<div class="success">';
                echo '<strong>✓ Migración ejecutada correctamente</strong><br><br>';
                
                // Verificar los cambios - mostrar todos los campos
                echo '<strong>Estado actual de todos los campos:</strong><br>';
                $stmt = $db->query("SHOW COLUMNS FROM funcionarios");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo '<table>';
                echo '<tr><th>Campo</th><th>Tipo</th><th>Permite NULL</th><th>Default</th><th>Comentario</th></tr>';
                foreach ($columns as $column) {
                    $nullable = $column['Null'] === 'YES' ? '<span class="field-nullable">✅ SÍ</span>' : '<span class="field-required">❌ NO</span>';
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($column['Field']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
                    echo '<td>' . $nullable . '</td>';
                    echo '<td>' . htmlspecialchars($column['Default'] ?? 'NULL') . '</td>';
                    echo '<td><small>' . htmlspecialchars($column['Comment'] ?? '') . '</small></td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                // Resumen
                $nullableCount = count(array_filter($columns, function($col) { return $col['Null'] === 'YES'; }));
                $requiredCount = count($columns) - $nullableCount;
                
                echo '<div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 4px;">';
                echo '<strong>Resumen:</strong><br>';
                echo "• Total de campos: " . count($columns) . "<br>";
                echo "• Campos que permiten NULL: <strong>{$nullableCount}</strong><br>";
                echo "• Campos obligatorios (NOT NULL): <strong>{$requiredCount}</strong> (solo <code>cedula</code>)<br>";
                echo '</div>';
                
                echo '</div>';
                
                echo '<div class="info" style="margin-top: 20px;">';
                echo '<strong>✓ La migración se completó exitosamente.</strong><br>';
                echo 'Ahora puedes importar datos desde Excel sin que todos los campos sean obligatorios.<br>';
                echo 'Solo el campo <code>cedula</code> sigue siendo obligatorio.';
                echo '</div>';
                
                echo '<a href="../pages/index.php" class="btn">← Volver al Sistema</a>';
                
            } catch (PDOException $e) {
                echo '<div class="error">';
                echo '<strong>❌ Error al ejecutar la migración:</strong><br>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                echo '</div>';
                echo '<a href="?" class="btn">Intentar de nuevo</a>';
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<strong>❌ Error:</strong><br>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                echo '</div>';
                echo '<a href="?" class="btn">Intentar de nuevo</a>';
            }
            ?>
        <?php endif; ?>
    </div>
</body>
</html>

