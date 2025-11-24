<?php
/**
 * Script simple para verificar migración
 * Acceder desde cualquier URL que funcione
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = getDBConnection();
    
    // Obtener estructura
    $stmt = $db->query("SHOW COLUMNS FROM funcionarios");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $nullableCount = 0;
    $requiredCount = 0;
    
    foreach ($columns as $column) {
        if ($column['Null'] === 'YES') {
            $nullableCount++;
        } else {
            $requiredCount++;
        }
    }
    
    // Verificar que solo cedula es obligatorio
    $cedulaField = array_filter($columns, function($col) { return $col['Field'] === 'cedula'; });
    $cedulaField = reset($cedulaField);
    
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Verificar Migración</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #4CAF50; color: white; }
        .nullable { color: #28a745; font-weight: bold; }
        .required { color: #dc3545; font-weight: bold; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #28a745; }
        .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #17a2b8; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #ffc107; }
    </style></head><body>";
    
    echo "<div class='container'>";
    echo "<h1>✅ Verificación de Migración</h1>";
    
    if ($cedulaField && $cedulaField['Null'] === 'NO' && $requiredCount === 1) {
        echo "<div class='success'>";
        echo "<strong>✓ Migración aplicada correctamente</strong><br>";
        echo "Solo el campo <code>cedula</code> es obligatorio. Todos los demás campos permiten NULL.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠ Atención:</strong> La migración puede no haberse aplicado completamente.<br>";
        echo "Hay <strong>{$requiredCount}</strong> campos obligatorios (se esperaba solo 1: cedula).";
        echo "</div>";
    }
    
    echo "<h2>Estructura de la Tabla `funcionarios`:</h2>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Permite NULL</th><th>Default</th></tr>";
    
    foreach ($columns as $column) {
        $nullable = $column['Null'] === 'YES';
        $nullableDisplay = $nullable ? '<span class="nullable">✅ SÍ</span>' : '<span class="required">❌ NO</span>';
        
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($column['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . $nullableDisplay . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<div class='info'>";
    echo "<strong>Resumen:</strong><br>";
    echo "• Total de campos: <strong>" . count($columns) . "</strong><br>";
    echo "• Campos que permiten NULL: <strong>{$nullableCount}</strong><br>";
    echo "• Campos obligatorios (NOT NULL): <strong>{$requiredCount}</strong><br>";
    echo "</div>";
    
    echo "</div></body></html>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;'>";
    echo "<strong>❌ Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

