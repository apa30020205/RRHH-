<?php
/**
 * Script de verificación de migración de permisos
 * Verifica que la tabla permisos y la columna permisos_acumulados se crearon correctamente
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = getDBConnection();
    
    echo "<h2>Verificación de Migración: Módulo de Permisos</h2>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .section { margin: 30px 0; padding: 20px; background: #f9f9f9; border-left: 4px solid #4CAF50; }
    </style>";
    
    // ============================================
    // Verificar tabla permisos
    // ============================================
    echo "<div class='section'>";
    echo "<h3>1. Verificación de la tabla 'permisos'</h3>";
    
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'permisos'");
        $existe = $stmt->fetch();
        
        if ($existe) {
            echo "<p class='success'>✓ La tabla 'permisos' existe</p>";
            
            // Mostrar estructura de la tabla
            $stmt = $db->query("DESCRIBE permisos");
            $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h4>Estructura de la tabla:</h4>";
            echo "<table>";
            echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Por Defecto</th><th>Extra</th></tr>";
            foreach ($columnas as $columna) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($columna['Field']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($columna['Type']) . "</td>";
                echo "<td>" . htmlspecialchars($columna['Null']) . "</td>";
                echo "<td>" . htmlspecialchars($columna['Key']) . "</td>";
                echo "<td>" . htmlspecialchars($columna['Default'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($columna['Extra'] ?? '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
        } else {
            echo "<p class='error'>✗ La tabla 'permisos' NO existe</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error al verificar la tabla: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // ============================================
    // Verificar columna permisos_acumulados
    // ============================================
    echo "<div class='section'>";
    echo "<h3>2. Verificación de la columna 'permisos_acumulados' en 'funcionarios'</h3>";
    
    try {
        $stmt = $db->query("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'funcionarios'
            AND COLUMN_NAME = 'permisos_acumulados'
        ");
        $columna = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($columna) {
            echo "<p class='success'>✓ La columna 'permisos_acumulados' existe en la tabla 'funcionarios'</p>";
            
            echo "<h4>Detalles de la columna:</h4>";
            echo "<table>";
            echo "<tr><th>Propiedad</th><th>Valor</th></tr>";
            echo "<tr><td><strong>Nombre</strong></td><td>" . htmlspecialchars($columna['COLUMN_NAME']) . "</td></tr>";
            echo "<tr><td><strong>Tipo de dato</strong></td><td>" . htmlspecialchars($columna['DATA_TYPE']) . "</td></tr>";
            echo "<tr><td><strong>Permite NULL</strong></td><td>" . htmlspecialchars($columna['IS_NULLABLE']) . "</td></tr>";
            echo "<tr><td><strong>Valor por defecto</strong></td><td>" . htmlspecialchars($columna['COLUMN_DEFAULT'] ?? 'NULL') . "</td></tr>";
            echo "<tr><td><strong>Comentario</strong></td><td>" . htmlspecialchars($columna['COLUMN_COMMENT'] ?? '') . "</td></tr>";
            echo "</table>";
            
        } else {
            echo "<p class='error'>✗ La columna 'permisos_acumulados' NO existe en la tabla 'funcionarios'</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error al verificar la columna: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // ============================================
    // Verificar constraints y relaciones
    // ============================================
    echo "<div class='section'>";
    echo "<h3>3. Verificación de relaciones (Foreign Keys)</h3>";
    
    try {
        $stmt = $db->query("
            SELECT 
                CONSTRAINT_NAME,
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'permisos'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($constraints) > 0) {
            echo "<p class='success'>✓ Se encontraron " . count($constraints) . " relación(es) definida(s)</p>";
            echo "<table>";
            echo "<tr><th>Constraint</th><th>Tabla</th><th>Columna</th><th>Tabla Referenciada</th><th>Columna Referenciada</th></tr>";
            foreach ($constraints as $constraint) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($constraint['CONSTRAINT_NAME']) . "</td>";
                echo "<td>" . htmlspecialchars($constraint['TABLE_NAME']) . "</td>";
                echo "<td>" . htmlspecialchars($constraint['COLUMN_NAME']) . "</td>";
                echo "<td>" . htmlspecialchars($constraint['REFERENCED_TABLE_NAME']) . "</td>";
                echo "<td>" . htmlspecialchars($constraint['REFERENCED_COLUMN_NAME']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>✗ No se encontraron relaciones definidas</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error al verificar relaciones: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    echo "</div>";
    
    // ============================================
    // Resumen final
    // ============================================
    echo "<div class='section'>";
    echo "<h3>Resumen</h3>";
    
    $tablaOk = false;
    $columnaOk = false;
    
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'permisos'");
        $tablaOk = $stmt->fetch() !== false;
    } catch (PDOException $e) {}
    
    try {
        $stmt = $db->query("
            SELECT COUNT(*) as existe
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'funcionarios'
            AND COLUMN_NAME = 'permisos_acumulados'
        ");
        $columnaOk = $stmt->fetch()['existe'] > 0;
    } catch (PDOException $e) {}
    
    if ($tablaOk && $columnaOk) {
        echo "<p class='success' style='font-size: 1.2em;'>✓ Migración completada exitosamente. Todo está en orden.</p>";
    } else {
        echo "<p class='error' style='font-size: 1.2em;'>✗ La migración está incompleta. Verifique los errores arriba.</p>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p class='error'>Error general: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
