<?php
/**
 * Script de Prueba de Seguridad
 * Sistema RRHH
 * 
 * Verifica que las medidas de seguridad estén implementadas correctamente
 */

require_once __DIR__ . '/../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../roles_rrhh/classes/Auth.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pruebas de Seguridad</title>
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
        .warning {
            color: #f39c12;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 0.9em;
        }
        code {
            background: #ecf0f1;
            padding: 2px 6px;
            border-radius: 3px;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 Pruebas de Seguridad del Módulo de Importación</h1>
        
        <?php
        // Verificación 1: Permisos de administrador
        echo '<div class="test-section">';
        echo '<h2>1. Verificación de Permisos</h2>';
        
        $esAdmin = Auth::isAdmin();
        $usuarioAutenticado = isset($_SESSION['autenticado']) && $_SESSION['autenticado'];
        $rol = $_SESSION['rol'] ?? 'no definido';
        
        if ($esAdmin) {
            echo '<p class="success">✓ Usuario actual es administrador</p>';
        } else {
            echo '<p class="error">✗ Usuario actual NO es administrador</p>';
        }
        
        echo '<p class="info">Usuario autenticado: ' . ($usuarioAutenticado ? 'Sí' : 'No') . '</p>';
        echo '<p class="info">Rol: ' . htmlspecialchars($rol) . '</p>';
        
        // Verificar que procesar_rrhh.php requiere admin
        $archivo = __DIR__ . '/../services/excel/procesar_rrhh.php';
        $contenido = file_get_contents($archivo);
        
        if (strpos($contenido, 'Auth::isAdmin()') !== false) {
            echo '<p class="success">✓ procesar_rrhh.php verifica permisos de administrador</p>';
        } else {
            echo '<p class="error">✗ procesar_rrhh.php NO verifica permisos de administrador</p>';
        }
        
        if (strpos($contenido, 'http_response_code(403)') !== false) {
            echo '<p class="success">✓ Retorna código 403 cuando no hay permisos</p>';
        } else {
            echo '<p class="warning">⚠ No se encontró código 403 explícito</p>';
        }
        
        echo '</div>';
        
        // Verificación 2: Validación de método HTTP
        echo '<div class="test-section">';
        echo '<h2>2. Validación de Método HTTP</h2>';
        
        if (strpos($contenido, "REQUEST_METHOD !== 'POST'") !== false) {
            echo '<p class="success">✓ Solo acepta método POST</p>';
        } else {
            echo '<p class="error">✗ No se valida el método HTTP</p>';
        }
        
        if (strpos($contenido, 'http_response_code(405)') !== false) {
            echo '<p class="success">✓ Retorna código 405 (Method Not Allowed) para métodos incorrectos</p>';
        } else {
            echo '<p class="warning">⚠ No se encontró código 405 explícito</p>';
        }
        
        echo '</div>';
        
        // Verificación 3: Validación de JSON
        echo '<div class="test-section">';
        echo '<h2>3. Validación de Datos JSON</h2>';
        
        if (strpos($contenido, 'json_decode') !== false) {
            echo '<p class="success">✓ Valida y decodifica JSON recibido</p>';
        } else {
            echo '<p class="error">✗ No se valida el JSON recibido</p>';
        }
        
        if (strpos($contenido, "isset(\$data['excel_data'])") !== false) {
            echo '<p class="success">✓ Verifica que exista la clave excel_data</p>';
        } else {
            echo '<p class="error">✗ No se verifica la estructura del JSON</p>';
        }
        
        if (strpos($contenido, 'http_response_code(400)') !== false) {
            echo '<p class="success">✓ Retorna código 400 (Bad Request) para datos inválidos</p>';
        } else {
            echo '<p class="warning">⚠ No se encontró código 400 explícito</p>';
        }
        
        echo '</div>';
        
        // Verificación 4: Sanitización de datos
        echo '<div class="test-section">';
        echo '<h2>4. Sanitización de Datos</h2>';
        
        if (strpos($contenido, 'sanitize(') !== false) {
            echo '<p class="success">✓ Utiliza función sanitize() para limpiar datos</p>';
            $numSanitize = substr_count($contenido, 'sanitize(');
            echo '<p class="info">Número de usos de sanitize(): ' . $numSanitize . '</p>';
        } else {
            echo '<p class="error">✗ No se utiliza sanitización de datos</p>';
        }
        
        // Verificar que se sanitizan campos críticos
        $camposCriticos = ['cedula', 'sangre', 'posicion_funcional', 'sede_provincia', 'Direccion'];
        $camposSanitizados = 0;
        foreach ($camposCriticos as $campo) {
            if (stripos($contenido, "sanitize(\$" . $campo) !== false) {
                $camposSanitizados++;
            }
        }
        
        if ($camposSanitizados > 0) {
            echo '<p class="success">✓ Se sanitizan ' . $camposSanitizados . ' campos críticos</p>';
        } else {
            echo '<p class="warning">⚠ No se encontraron campos críticos siendo sanitizados</p>';
        }
        
        echo '</div>';
        
        // Verificación 5: Uso de Prepared Statements
        echo '<div class="test-section">';
        echo '<h2>5. Protección contra SQL Injection</h2>';
        
        if (strpos($contenido, 'prepare(') !== false) {
            echo '<p class="success">✓ Utiliza prepared statements</p>';
            $numPrepare = substr_count($contenido, 'prepare(');
            echo '<p class="info">Número de prepared statements: ' . $numPrepare . '</p>';
        } else {
            echo '<p class="error">✗ No se utilizan prepared statements</p>';
        }
        
        if (strpos($contenido, 'execute([') !== false) {
            echo '<p class="success">✓ Utiliza parámetros enlazados (bind parameters)</p>';
        } else {
            echo '<p class="error">✗ No se utilizan parámetros enlazados</p>';
        }
        
        // Verificar que no hay concatenación directa de variables en SQL
        $patronesPeligrosos = [
            'SELECT.*\$',
            'INSERT.*\$',
            'UPDATE.*\$',
            'DELETE.*\$'
        ];
        $encontrados = false;
        foreach ($patronesPeligrosos as $patron) {
            if (preg_match('/' . $patron . '/i', $contenido)) {
                $encontrados = true;
                break;
            }
        }
        
        if (!$encontrados) {
            echo '<p class="success">✓ No se encontró concatenación directa de variables en SQL</p>';
        } else {
            echo '<p class="warning">⚠ Se encontraron posibles concatenaciones directas (revisar manualmente)</p>';
        }
        
        echo '</div>';
        
        // Verificación 6: Validación de entrada
        echo '<div class="test-section">';
        echo '<h2>6. Validación de Entrada</h2>';
        
        if (strpos($contenido, 'validarCedula(') !== false) {
            echo '<p class="success">✓ Valida formato de cédula</p>';
        } else {
            echo '<p class="error">✗ No se valida el formato de cédula</p>';
        }
        
        if (strpos($contenido, 'empty($cedula)') !== false) {
            echo '<p class="success">✓ Valida que la cédula no esté vacía</p>';
        } else {
            echo '<p class="warning">⚠ No se valida que la cédula no esté vacía</p>';
        }
        
        if (strpos($contenido, 'formatearFechaBD(') !== false) {
            echo '<p class="success">✓ Valida y formatea fechas</p>';
        } else {
            echo '<p class="warning">⚠ No se valida formato de fechas</p>';
        }
        
        echo '</div>';
        
        // Resumen
        echo '<div class="test-section">';
        echo '<h2>📊 Resumen de Seguridad</h2>';
        
        $verificaciones = [
            'Permisos de administrador' => $esAdmin && strpos($contenido, 'Auth::isAdmin()') !== false,
            'Validación de método POST' => strpos($contenido, "REQUEST_METHOD !== 'POST'") !== false,
            'Validación de JSON' => strpos($contenido, 'json_decode') !== false && strpos($contenido, "isset(\$data['excel_data'])") !== false,
            'Sanitización de datos' => strpos($contenido, 'sanitize(') !== false,
            'Prepared statements' => strpos($contenido, 'prepare(') !== false,
            'Validación de cédula' => strpos($contenido, 'validarCedula(') !== false,
        ];
        
        $pasadas = 0;
        foreach ($verificaciones as $nombre => $paso) {
            if ($paso) {
                echo '<p class="success">✓ ' . $nombre . '</p>';
                $pasadas++;
            } else {
                echo '<p class="error">✗ ' . $nombre . '</p>';
            }
        }
        
        $total = count($verificaciones);
        echo '<p><strong>Resultado:</strong> ' . $pasadas . '/' . $total . ' verificaciones pasaron</p>';
        
        if ($pasadas === $total) {
            echo '<p class="success">✓ Todas las medidas de seguridad están implementadas</p>';
        } else {
            echo '<p class="error">✗ Algunas medidas de seguridad faltan o están incompletas</p>';
        }
        
        echo '</div>';
        ?>
    </div>
</body>
</html>

