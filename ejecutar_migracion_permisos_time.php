<?php
/**
 * Ejecutar Migración: Cambiar campos de permisos a TIME
 * Sistema RRHH
 * 
 * Este script ejecuta automáticamente la migración para cambiar
 * los campos de permisos de DECIMAL (días y horas separados) 
 * a TIME (días y horas combinados)
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/roles_rrhh/classes/Auth.php';
require_once __DIR__ . '/roles_rrhh/middleware/auth_middleware.php';

// Solo administradores pueden ejecutar migraciones
if (!Auth::isAdmin()) {
    die('Error: Solo administradores pueden ejecutar migraciones.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Migración: Permisos a TIME</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: #0c5460; background: #d1ecf1; padding: 10px; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
    </style>
</head>
<body>
    <h1>Migración: Cambiar Campos de Permisos a TIME</h1>
    
<?php
try {
    $db = Database::getInstance()->getConnection();
    
    echo "<div class='info'><strong>Paso 1:</strong> Verificando estado actual de la base de datos...</div>\n";
    
    // Verificar qué campos existen
    $stmtCheckTime = $db->query("SHOW COLUMNS FROM funcionarios LIKE 'permisos_justificados_acumulados'");
    $existeTime = $stmtCheckTime->rowCount() > 0;
    
    $stmtCheckDecimal = $db->query("SHOW COLUMNS FROM funcionarios LIKE 'permisos_justificados_dias_acumulados'");
    $existeDecimal = $stmtCheckDecimal->rowCount() > 0;
    
    if ($existeTime && !$existeDecimal) {
        echo "<div class='success'><strong>✅ La migración ya fue ejecutada.</strong> Los campos TIME ya existen y los campos DECIMAL ya fueron eliminados.</div>\n";
        echo "<p>El sistema está usando los campos TIME correctamente.</p>\n";
        
        // Mostrar estructura actual
        echo "<h3>Estructura actual de campos de permisos:</h3>\n";
        $stmt = $db->query("SHOW COLUMNS FROM funcionarios WHERE Field LIKE '%permisos%' OR Field LIKE '%vacaciones%' OR Field LIKE '%ano_derechos%'");
        $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>\n";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>\n";
        foreach ($columnas as $col) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
    } elseif ($existeTime && $existeDecimal) {
        echo "<div class='warning'><strong>⚠️ Migración incompleta:</strong> Existen ambos tipos de campos. Se completará la migración eliminando los campos DECIMAL antiguos.</div>\n";
        
        // Paso 3: Eliminar campos antiguos
        echo "<div class='info'><strong>Paso 2:</strong> Eliminando campos DECIMAL antiguos...</div>\n";
        
        try {
            $db->exec("ALTER TABLE `funcionarios`
              DROP COLUMN `permisos_justificados_dias_acumulados`,
              DROP COLUMN `permisos_justificados_horas_acumuladas`,
              DROP COLUMN `permisos_no_justificados_dias_acumulados`,
              DROP COLUMN `permisos_no_justificados_horas_acumuladas`");
            
            echo "<div class='success'><strong>✅ Campos DECIMAL antiguos eliminados correctamente.</strong></div>\n";
        } catch (PDOException $e) {
            echo "<div class='error'><strong>❌ Error al eliminar campos antiguos:</strong> " . htmlspecialchars($e->getMessage()) . "</div>\n";
            throw $e;
        }
        
    } elseif (!$existeTime && $existeDecimal) {
        echo "<div class='info'><strong>Estado:</strong> Los campos DECIMAL antiguos existen. Iniciando migración...</div>\n";
        
        // Paso 1: Agregar nuevos campos TIME
        echo "<div class='info'><strong>Paso 1:</strong> Agregando nuevos campos TIME...</div>\n";
        
        try {
            $db->exec("ALTER TABLE `funcionarios`
              ADD COLUMN `permisos_justificados_acumulados` TIME NULL COMMENT 'Permisos justificados acumulados en formato DDD:HH:00:00 (días:horas)',
              ADD COLUMN `permisos_no_justificados_acumulados` TIME NULL COMMENT 'Permisos no justificados acumulados en formato DDD:HH:00:00 (días:horas)'");
            
            echo "<div class='success'><strong>✅ Campos TIME agregados correctamente.</strong></div>\n";
        } catch (PDOException $e) {
            echo "<div class='error'><strong>❌ Error al agregar campos TIME:</strong> " . htmlspecialchars($e->getMessage()) . "</div>\n";
            throw $e;
        }
        
        // Paso 2: Migrar datos
        echo "<div class='info'><strong>Paso 2:</strong> Migrando datos existentes de días y horas a formato TIME...</div>\n";
        
        try {
            $db->exec("UPDATE `funcionarios`
            SET 
              `permisos_justificados_acumulados` = CASE
                WHEN `permisos_justificados_dias_acumulados` IS NOT NULL 
                     OR `permisos_justificados_horas_acumuladas` IS NOT NULL
                THEN SEC_TO_TIME(
                  COALESCE(FLOOR(`permisos_justificados_dias_acumulados`), 0) * 86400 +  
                  COALESCE(FLOOR(`permisos_justificados_horas_acumuladas`), 0) * 3600    
                )
                ELSE NULL
              END,
              `permisos_no_justificados_acumulados` = CASE
                WHEN `permisos_no_justificados_dias_acumulados` IS NOT NULL 
                     OR `permisos_no_justificados_horas_acumuladas` IS NOT NULL
                THEN SEC_TO_TIME(
                  COALESCE(FLOOR(`permisos_no_justificados_dias_acumulados`), 0) * 86400 +  
                  COALESCE(FLOOR(`permisos_no_justificados_horas_acumuladas`), 0) * 3600    
                )
                ELSE NULL
              END");
            
            $filasAfectadas = $db->rowCount();
            echo "<div class='success'><strong>✅ Datos migrados correctamente.</strong> Filas actualizadas: " . $filasAfectadas . "</div>\n";
        } catch (PDOException $e) {
            echo "<div class='error'><strong>❌ Error al migrar datos:</strong> " . htmlspecialchars($e->getMessage()) . "</div>\n";
            throw $e;
        }
        
        // Paso 3: Eliminar campos antiguos
        echo "<div class='info'><strong>Paso 3:</strong> Eliminando campos DECIMAL antiguos...</div>\n";
        
        try {
            $db->exec("ALTER TABLE `funcionarios`
              DROP COLUMN `permisos_justificados_dias_acumulados`,
              DROP COLUMN `permisos_justificados_horas_acumuladas`,
              DROP COLUMN `permisos_no_justificados_dias_acumulados`,
              DROP COLUMN `permisos_no_justificados_horas_acumuladas`");
            
            echo "<div class='success'><strong>✅ Campos DECIMAL antiguos eliminados correctamente.</strong></div>\n";
        } catch (PDOException $e) {
            echo "<div class='error'><strong>❌ Error al eliminar campos antiguos:</strong> " . htmlspecialchars($e->getMessage()) . "</div>\n";
            throw $e;
        }
        
        echo "<div class='success'><h2>✅ Migración completada exitosamente!</h2></div>\n";
        
    } else {
        echo "<div class='warning'><strong>⚠️ No se encontraron campos de permisos en la base de datos.</strong></div>\n";
        echo "<p>Puede que necesites ejecutar primero la migración que crea los campos de derechos: <code>202501XX_agregar_derechos_funcionarios.sql</code></p>\n";
    }
    
    // Mostrar estructura final
    echo "<h3>Estructura final de campos de permisos:</h3>\n";
    $stmt = $db->query("SHOW COLUMNS FROM funcionarios WHERE Field LIKE '%permisos%' OR Field LIKE '%vacaciones%' OR Field LIKE '%ano_derechos%'");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($columnas) > 0) {
        echo "<table>\n";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>\n";
        foreach ($columnas as $col) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($col['Field']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Mostrar algunos datos de ejemplo
    if ($existeTime || ($existeTime && !$existeDecimal)) {
        echo "<h3>Datos de ejemplo (primeros 5 registros con permisos):</h3>\n";
        $stmtDatos = $db->query("SELECT cedula, nombre, apellido, 
                                        permisos_justificados_acumulados,
                                        permisos_no_justificados_acumulados
                                 FROM funcionarios 
                                 WHERE permisos_justificados_acumulados IS NOT NULL 
                                    OR permisos_no_justificados_acumulados IS NOT NULL
                                 LIMIT 5");
        $datos = $stmtDatos->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($datos) > 0) {
            echo "<table>\n";
            echo "<tr><th>Cédula</th><th>Nombre</th><th>P. Justificados (TIME)</th><th>P. No Justificados (TIME)</th></tr>\n";
            foreach ($datos as $dato) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($dato['cedula']) . "</td>";
                echo "<td>" . htmlspecialchars(trim($dato['nombre'] . ' ' . $dato['apellido'])) . "</td>";
                echo "<td>" . htmlspecialchars($dato['permisos_justificados_acumulados'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($dato['permisos_no_justificados_acumulados'] ?? 'NULL') . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p>No hay datos de permisos en la base de datos todavía.</p>\n";
        }
    }
    
    echo "<div class='info'><strong>Nota:</strong> El código PHP ya está actualizado para trabajar con los campos TIME. Después de esta migración, el sistema usará automáticamente los nuevos campos.</div>\n";
    
} catch (PDOException $e) {
    echo "<div class='error'><strong>❌ Error de base de datos:</strong> " . htmlspecialchars($e->getMessage()) . "</div>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
} catch (Exception $e) {
    echo "<div class='error'><strong>❌ Error inesperado:</strong> " . htmlspecialchars($e->getMessage()) . "</div>\n";
}
?>

</body>
</html>
