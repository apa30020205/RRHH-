<?php
/**
 * Script de verificación de campos de permisos
 * Verifica qué campos existen en la tabla funcionarios
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener todas las columnas de la tabla funcionarios
    $stmt = $db->query("SHOW COLUMNS FROM funcionarios");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Campos en la tabla funcionarios relacionados con permisos:</h2>\n";
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Default</th></tr>\n";
    
    $camposPermisos = [];
    foreach ($columnas as $columna) {
        $nombreCampo = $columna['Field'];
        if (strpos($nombreCampo, 'permisos') !== false || 
            strpos($nombreCampo, 'vacaciones') !== false ||
            strpos($nombreCampo, 'ano_derechos') !== false) {
            $camposPermisos[] = $columna;
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($nombreCampo) . "</strong></td>";
            echo "<td>" . htmlspecialchars($columna['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($columna['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($columna['Default'] ?? 'NULL') . "</td>";
            echo "</tr>\n";
        }
    }
    
    echo "</table>\n";
    
    // Verificar específicamente los campos TIME
    echo "<h3>Verificación específica:</h3>\n";
    echo "<ul>\n";
    
    $stmtTime = $db->query("SHOW COLUMNS FROM funcionarios LIKE 'permisos_justificados_acumulados'");
    $existeTime = $stmtTime->rowCount() > 0;
    echo "<li><strong>permisos_justificados_acumulados (TIME):</strong> " . ($existeTime ? "✅ EXISTE" : "❌ NO EXISTE") . "</li>\n";
    
    $stmtTime2 = $db->query("SHOW COLUMNS FROM funcionarios LIKE 'permisos_no_justificados_acumulados'");
    $existeTime2 = $stmtTime2->rowCount() > 0;
    echo "<li><strong>permisos_no_justificados_acumulados (TIME):</strong> " . ($existeTime2 ? "✅ EXISTE" : "❌ NO EXISTE") . "</li>\n";
    
    $stmtDecimal = $db->query("SHOW COLUMNS FROM funcionarios LIKE 'permisos_justificados_dias_acumulados'");
    $existeDecimal = $stmtDecimal->rowCount() > 0;
    echo "<li><strong>permisos_justificados_dias_acumulados (DECIMAL):</strong> " . ($existeDecimal ? "✅ EXISTE (antiguo)" : "❌ NO EXISTE") . "</li>\n";
    
    $stmtDecimal2 = $db->query("SHOW COLUMNS FROM funcionarios LIKE 'permisos_justificados_horas_acumuladas'");
    $existeDecimal2 = $stmtDecimal2->rowCount() > 0;
    echo "<li><strong>permisos_justificados_horas_acumuladas (DECIMAL):</strong> " . ($existeDecimal2 ? "✅ EXISTE (antiguo)" : "❌ NO EXISTE") . "</li>\n";
    
    echo "</ul>\n";
    
    // Conclusión
    echo "<h3>Estado:</h3>\n";
    if ($existeTime && $existeTime2 && !$existeDecimal && !$existeDecimal2) {
        echo "<p style='color: green;'><strong>✅ Migración completada correctamente. El sistema debería usar campos TIME.</strong></p>\n";
    } elseif (!$existeTime && !$existeTime2 && $existeDecimal && $existeDecimal2) {
        echo "<p style='color: orange;'><strong>⚠️ Migración NO ejecutada. El sistema está usando campos DECIMAL antiguos.</strong></p>\n";
        echo "<p>Necesitas ejecutar el script: <code>database/migrations/202501XX_migrar_permisos_a_time.sql</code></p>\n";
    } elseif ($existeTime && $existeDecimal) {
        echo "<p style='color: red;'><strong>❌ ERROR: Existen ambos tipos de campos. La migración está incompleta.</strong></p>\n";
        echo "<p>Necesitas completar la migración eliminando los campos DECIMAL antiguos.</p>\n";
    } else {
        echo "<p style='color: red;'><strong>❓ Estado desconocido. Revisa manualmente.</strong></p>\n";
    }
    
    // Mostrar algunos datos de ejemplo si existen los campos TIME
    if ($existeTime) {
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
            echo "<table border='1' cellpadding='5'>\n";
            echo "<tr><th>Cédula</th><th>Nombre</th><th>P. Justificados (TIME)</th><th>P. No Justificados (TIME)</th></tr>\n";
            foreach ($datos as $dato) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($dato['cedula']) . "</td>";
                echo "<td>" . htmlspecialchars($dato['nombre'] . ' ' . $dato['apellido']) . "</td>";
                echo "<td>" . htmlspecialchars($dato['permisos_justificados_acumulados'] ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($dato['permisos_no_justificados_acumulados'] ?? 'NULL') . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p>No hay datos de permisos en la base de datos.</p>\n";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
?>
