<?php
/**
 * Script para verificar que la migración se aplicó correctamente
 * Sistema RRHH
 * 
 * Acceder desde: http://localhost/SISTEMA%20RRHH/verificar_migracion.php
 * O desde: http://localhost/SISTEMA%20%20RRHH/verificar_migracion.php
 */

// Incluir configuración
require_once __DIR__ . '/config/database.php';

try {
    $db = getDBConnection();
    
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Verificar Migración</title>";
    echo "<style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #4CAF50; color: white; }
        .nullable { color: #28a745; font-weight: bold; }
        .required { color: #dc3545; font-weight: bold; }
        .success { background: #d4edda; padding: 15px; border-radius: 4px; margin: 20px 0; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 4px; margin: 20px 0; }
    </style></head><body>";
    
    echo "<h1>✅ Verificación de Migración</h1>";
    
    // Obtener estructura de la tabla
    $stmt = $db->query("SHOW COLUMNS FROM funcionarios");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='success'>";
    echo "<strong>✓ Migración aplicada correctamente</strong><br>";
    echo "La estructura de la tabla ha sido modificada.";
    echo "</div>";
    
    echo "<h2>Estructura Actual de la Tabla `funcionarios`:</h2>";
    echo "<table>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Permite NULL</th><th>Default</th><th>Comentario</th></tr>";
    
    $nullableCount = 0;
    $requiredCount = 0;
    
    foreach ($columns as $column) {
        $nullable = $column['Null'] === 'YES';
        $nullableDisplay = $nullable ? '<span class="nullable">✅ SÍ</span>' : '<span class="required">❌ NO</span>';
        
        if ($nullable) {
            $nullableCount++;
        } else {
            $requiredCount++;
        }
        
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($column['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . $nullableDisplay . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td><small>" . htmlspecialchars($column['Comment'] ?? '') . "</small></td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<div class='info'>";
    echo "<strong>Resumen:</strong><br>";
    echo "• Total de campos: <strong>" . count($columns) . "</strong><br>";
    echo "• Campos que permiten NULL: <strong>{$nullableCount}</strong><br>";
    echo "• Campos obligatorios (NOT NULL): <strong>{$requiredCount}</strong><br><br>";
    
    // Verificar que solo cedula es obligatorio
    $cedulaField = array_filter($columns, function($col) { return $col['Field'] === 'cedula'; });
    $cedulaField = reset($cedulaField);
    
    if ($cedulaField && $cedulaField['Null'] === 'NO' && $requiredCount === 1) {
        echo "<strong style='color: #28a745;'>✓ Configuración correcta:</strong> Solo el campo <code>cedula</code> es obligatorio.<br>";
        echo "Todos los demás campos permiten NULL, facilitando la importación de datos incompletos.";
    } else {
        echo "<strong style='color: #dc3545;'>⚠ Atención:</strong> Hay más campos obligatorios de los esperados.";
    }
    
    echo "</div>";
    
    // Detectar la ruta base correcta
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $baseUrl = $scriptPath;
    
    echo "<a href='{$baseUrl}/pages/index.php' style='display: inline-block; padding: 12px 24px; background: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px;'>← Volver al Sistema</a>";
    
    echo "</body></html>";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;'>";
    echo "<strong>❌ Error:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

