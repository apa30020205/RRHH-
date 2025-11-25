<?php
/**
 * Script de Prueba de Funciones Auxiliares
 * Sistema RRHH
 * 
 * Prueba todas las funciones auxiliares con casos válidos e inválidos
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pruebas de Funciones Auxiliares</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 20px auto;
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
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border-left: 4px solid #3498db;
            background: #f8f9fa;
        }
        .test-case {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .success {
            color: #27ae60;
            font-weight: bold;
        }
        .error {
            color: #e74c3c;
            font-weight: bold;
        }
        .info {
            color: #3498db;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 0.9em;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #3498db;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Pruebas de Funciones Auxiliares</h1>
        
        <?php
        // Función para mostrar resultado de prueba
        function mostrarPrueba($nombre, $entrada, $esperado, $obtenido, $esValido) {
            echo '<div class="test-case">';
            echo '<strong>' . htmlspecialchars($nombre) . '</strong><br>';
            echo '<span class="info">Entrada:</span> <code>' . htmlspecialchars($entrada) . '</code><br>';
            echo '<span class="info">Esperado:</span> <code>' . htmlspecialchars($esperado) . '</code><br>';
            echo '<span class="info">Obtenido:</span> <code>' . htmlspecialchars($obtenido) . '</code><br>';
            if ($esValido) {
                echo '<span class="success">✓ PASÓ</span>';
            } else {
                echo '<span class="error">✗ FALLÓ</span>';
            }
            echo '</div>';
        }
        
        // 1. Pruebas de validarCedula()
        echo '<div class="test-section">';
        echo '<h2>1. Pruebas de validarCedula()</h2>';
        
        $casosCedula = [
            ['8-1234-5678', true, 'Cédula válida con guiones'],
            ['812345678', true, 'Cédula válida sin guiones'],
            ['8-12-34-5678', true, 'Cédula válida con múltiples guiones'],
            ['PE-123456-7', true, 'Cédula alfanumérica válida'],
            ['', false, 'Cédula vacía'],
            ['123', false, 'Cédula muy corta'],
            ['8-1234-5678-9-10', false, 'Cédula con demasiados guiones'],
            ['8@1234-5678', false, 'Cédula con caracteres inválidos'],
        ];
        
        $pasadosCedula = 0;
        foreach ($casosCedula as $caso) {
            $resultado = validarCedula($caso[0]);
            $esValido = ($resultado === $caso[1]);
            if ($esValido) $pasadosCedula++;
            mostrarPrueba(
                $caso[2],
                $caso[0],
                $caso[1] ? 'true' : 'false',
                $resultado ? 'true' : 'false',
                $esValido
            );
        }
        echo '<p><strong>Resultado:</strong> ' . $pasadosCedula . '/' . count($casosCedula) . ' pruebas pasaron</p>';
        echo '</div>';
        
        // 2. Pruebas de normalizarCedula()
        echo '<div class="test-section">';
        echo '<h2>2. Pruebas de normalizarCedula()</h2>';
        
        $casosNormalizar = [
            ['8-1234-5678', '812345678', 'Quitar guiones'],
            ['8 1234 5678', '812345678', 'Quitar espacios'],
            ['8-12-34-5678', '812345678', 'Quitar múltiples guiones'],
            ['PE-123456-7', 'PE1234567', 'Cédula alfanumérica'],
        ];
        
        $pasadosNormalizar = 0;
        foreach ($casosNormalizar as $caso) {
            $resultado = normalizarCedula($caso[0]);
            $esValido = ($resultado === $caso[1]);
            if ($esValido) $pasadosNormalizar++;
            mostrarPrueba(
                $caso[2],
                $caso[0],
                $caso[1],
                $resultado,
                $esValido
            );
        }
        echo '<p><strong>Resultado:</strong> ' . $pasadosNormalizar . '/' . count($casosNormalizar) . ' pruebas pasaron</p>';
        echo '</div>';
        
        // 3. Pruebas de formatearCedula()
        echo '<div class="test-section">';
        echo '<h2>3. Pruebas de formatearCedula()</h2>';
        
        $casosFormatear = [
            ['8-1234-5678', '8-1234-5678', 'Preservar guiones existentes'],
            ['812345678', '8-1234-5678', 'Agregar guiones a cédula numérica'],
            ['PE-123456-7', 'PE-123456-7', 'Preservar formato alfanumérico'],
        ];
        
        $pasadosFormatear = 0;
        foreach ($casosFormatear as $caso) {
            $resultado = formatearCedula($caso[0]);
            $esValido = ($resultado === $caso[1]);
            if ($esValido) $pasadosFormatear++;
            mostrarPrueba(
                $caso[2],
                $caso[0],
                $caso[1],
                $resultado,
                $esValido
            );
        }
        echo '<p><strong>Resultado:</strong> ' . $pasadosFormatear . '/' . count($casosFormatear) . ' pruebas pasaron</p>';
        echo '</div>';
        
        // 4. Pruebas de formatearFechaBD()
        echo '<div class="test-section">';
        echo '<h2>4. Pruebas de formatearFechaBD()</h2>';
        
        $casosFecha = [
            ['2024-01-15', '2024-01-15', 'Fecha en formato YYYY-MM-DD'],
            ['15/01/2024', '2024-01-15', 'Fecha en formato DD/MM/YYYY'],
            ['01/15/2024', '2024-01-15', 'Fecha en formato MM/DD/YYYY'],
            ['2024-1-5', '2024-01-05', 'Fecha sin ceros iniciales'],
            ['', null, 'Fecha vacía'],
            ['fecha-invalida', null, 'Fecha inválida'],
        ];
        
        $pasadosFecha = 0;
        foreach ($casosFecha as $caso) {
            $resultado = formatearFechaBD($caso[0]);
            $esValido = ($resultado === $caso[1]);
            if ($esValido) $pasadosFecha++;
            mostrarPrueba(
                $caso[2],
                $caso[0],
                $caso[1] ?? 'null',
                $resultado ?? 'null',
                $esValido
            );
        }
        echo '<p><strong>Resultado:</strong> ' . $pasadosFecha . '/' . count($casosFecha) . ' pruebas pasaron</p>';
        echo '</div>';
        
        // 5. Pruebas de sanitize()
        echo '<div class="test-section">';
        echo '<h2>5. Pruebas de sanitize()</h2>';
        
        $casosSanitize = [
            ['<script>alert("xss")</script>', '', 'Eliminar etiquetas HTML'],
            ['Texto normal', 'Texto normal', 'Preservar texto normal'],
            ['  Texto con espacios  ', 'Texto con espacios', 'Eliminar espacios al inicio y final'],
            ['Texto con "comillas"', 'Texto con "comillas"', 'Preservar comillas'],
        ];
        
        $pasadosSanitize = 0;
        foreach ($casosSanitize as $caso) {
            $resultado = sanitize($caso[0]);
            $esValido = ($resultado === $caso[1]);
            if ($esValido) $pasadosSanitize++;
            mostrarPrueba(
                $caso[2],
                $caso[0],
                $caso[1],
                $resultado,
                $esValido
            );
        }
        echo '<p><strong>Resultado:</strong> ' . $pasadosSanitize . '/' . count($casosSanitize) . ' pruebas pasaron</p>';
        echo '</div>';
        
        // Resumen
        $totalPruebas = count($casosCedula) + count($casosNormalizar) + count($casosFormatear) + count($casosFecha) + count($casosSanitize);
        $totalPasadas = $pasadosCedula + $pasadosNormalizar + $pasadosFormatear + $pasadosFecha + $pasadosSanitize;
        
        echo '<div class="test-section">';
        echo '<h2>📊 Resumen General</h2>';
        echo '<table>';
        echo '<tr><th>Función</th><th>Pruebas Pasadas</th><th>Total</th></tr>';
        echo '<tr><td>validarCedula()</td><td>' . $pasadosCedula . '</td><td>' . count($casosCedula) . '</td></tr>';
        echo '<tr><td>normalizarCedula()</td><td>' . $pasadosNormalizar . '</td><td>' . count($casosNormalizar) . '</td></tr>';
        echo '<tr><td>formatearCedula()</td><td>' . $pasadosFormatear . '</td><td>' . count($casosFormatear) . '</td></tr>';
        echo '<tr><td>formatearFechaBD()</td><td>' . $pasadosFecha . '</td><td>' . count($casosFecha) . '</td></tr>';
        echo '<tr><td>sanitize()</td><td>' . $pasadosSanitize . '</td><td>' . count($casosSanitize) . '</td></tr>';
        echo '<tr><th>TOTAL</th><th>' . $totalPasadas . '</th><th>' . $totalPruebas . '</th></tr>';
        echo '</table>';
        
        if ($totalPasadas === $totalPruebas) {
            echo '<p class="success">✓ Todas las pruebas pasaron correctamente</p>';
        } else {
            echo '<p class="error">✗ Algunas pruebas fallaron. Revisa los resultados arriba.</p>';
        }
        echo '</div>';
        ?>
    </div>
</body>
</html>

