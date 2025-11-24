<?php
/**
 * Script web para limpiar la tabla funcionarios
 * Sistema RRHH
 * 
 * Acceder desde: http://localhost/SISTEMA%20RRHH/limpiar_funcionarios.php
 * O desde: http://localhost/SISTEMA%20%20RRHH/limpiar_funcionarios.php
 * 
 * ⚠️ ADVERTENCIA: Este script elimina TODOS los registros de la tabla funcionarios
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/roles_rrhh/classes/Auth.php';

// Solo administradores pueden limpiar la base de datos
if (!Auth::isAuthenticated() || !Auth::isAdmin()) {
    die('Acceso denegado. Solo administradores pueden ejecutar esta acción.');
}

// Solo permitir ejecución si hay un parámetro de confirmación
$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === 'si';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpiar Tabla Funcionarios - Sistema RRHH</title>
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
            border-bottom: 2px solid #dc3545;
            padding-bottom: 10px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .danger {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Limpiar Tabla Funcionarios</h1>
        
        <div class="danger">
            <strong>⚠️ ADVERTENCIA CRÍTICA:</strong>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Este script eliminará <strong>TODOS</strong> los registros de la tabla <code>funcionarios</code></li>
                <li>Esta acción <strong>NO se puede deshacer</strong></li>
                <li>Haz un backup de la base de datos antes de continuar</li>
                <li>Solo usar en ambiente de desarrollo/pruebas</li>
            </ul>
        </div>

        <?php if (!$confirmar): ?>
            <?php
            try {
                $db = getDBConnection();
                $stmt = $db->query("SELECT COUNT(*) as total FROM funcionarios");
                $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
                $total = $resultado['total'];
            } catch (Exception $e) {
                $total = 0;
            }
            ?>
            
            <div class="info">
                <strong>Estado actual:</strong><br>
                Total de registros en la tabla: <strong><?php echo number_format($total); ?></strong>
            </div>
            
            <div class="warning">
                <strong>¿Estás seguro de continuar?</strong><br>
                Se eliminarán <strong><?php echo number_format($total); ?></strong> registros permanentemente.
            </div>
            
            <a href="?confirmar=si" class="btn">🗑️ Sí, Eliminar Todos los Registros</a>
            <a href="../pages/index.php" class="btn btn-secondary">❌ Cancelar</a>
            
        <?php else: ?>
            <?php
            try {
                $db = getDBConnection();
                
                // Contar registros antes
                $stmtBefore = $db->query("SELECT COUNT(*) as total FROM funcionarios");
                $before = $stmtBefore->fetch(PDO::FETCH_ASSOC)['total'];
                
                echo '<div class="info"><strong>Eliminando registros...</strong></div>';
                
                // Eliminar todos los registros
                $db->exec("DELETE FROM funcionarios");
                
                // Contar registros después
                $stmtAfter = $db->query("SELECT COUNT(*) as total FROM funcionarios");
                $after = $stmtAfter->fetch(PDO::FETCH_ASSOC)['total'];
                
                echo '<div class="success">';
                echo '<strong>✓ Tabla limpiada exitosamente</strong><br><br>';
                echo "Registros eliminados: <strong>" . number_format($before) . "</strong><br>";
                echo "Registros restantes: <strong>" . number_format($after) . "</strong>";
                echo '</div>';
                
                echo '<div class="info" style="margin-top: 20px;">';
                echo '<strong>✓ La tabla está lista para nuevas importaciones.</strong><br>';
                echo 'Ahora puedes importar el Excel nuevamente para probar la corrección de "POSICIÓN FUNCIONAL".';
                echo '</div>';
                
                $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
                echo '<div style="margin-top: 20px;">';
                echo '<a href="' . $scriptPath . '/services/excel/importar.php" class="btn" style="background: #28a745;">📥 Ir a Importar Excel</a> ';
                echo '<a href="' . $scriptPath . '/pages/index.php" class="btn btn-secondary">← Volver al Sistema</a>';
                echo '</div>';
                
            } catch (PDOException $e) {
                echo '<div class="danger">';
                echo '<strong>❌ Error al limpiar la tabla:</strong><br>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                echo '</div>';
                echo '<a href="?" class="btn btn-secondary">Intentar de nuevo</a>';
            } catch (Exception $e) {
                echo '<div class="danger">';
                echo '<strong>❌ Error:</strong><br>';
                echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
                echo '</div>';
                echo '<a href="?" class="btn btn-secondary">Intentar de nuevo</a>';
            }
            ?>
        <?php endif; ?>
    </div>
</body>
</html>

