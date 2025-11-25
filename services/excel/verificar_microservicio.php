<?php
/**
 * Script de Verificación del Microservicio Python
 * Sistema RRHH
 * 
 * Verifica que el microservicio esté funcionando correctamente
 */

require_once __DIR__ . '/../../config/constants.php';

// URLs del microservicio
define('MICROSERVICIO_URL', 'http://localhost:5000/api/read-excel');
define('MICROSERVICIO_HEALTH', 'http://localhost:5000/api/health');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación del Microservicio</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status.ok {
            background: #27ae60;
            color: white;
        }
        .status.fail {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Verificación del Microservicio Python</h1>
        
        <?php
        // Función para hacer peticiones HTTP
        function hacerPeticion($url, $timeout = 5) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            return [
                'response' => $response,
                'http_code' => $http_code,
                'error' => $curl_error,
                'success' => ($http_code === 200 && empty($curl_error))
            ];
        }
        
        // Test 1: Verificar endpoint de health
        echo '<div class="test-section">';
        echo '<h2>1. Verificación del Endpoint /api/health</h2>';
        $health = hacerPeticion(MICROSERVICIO_HEALTH);
        
        if ($health['success']) {
            echo '<p><span class="status ok">✓ CONECTADO</span></p>';
            echo '<p class="info">HTTP Status: ' . $health['http_code'] . '</p>';
            echo '<p class="info">Respuesta:</p>';
            echo '<pre>' . htmlspecialchars($health['response']) . '</pre>';
        } else {
            echo '<p><span class="status fail">✗ NO CONECTADO</span></p>';
            echo '<p class="error">HTTP Status: ' . ($health['http_code'] ?: 'Sin respuesta') . '</p>';
            if ($health['error']) {
                echo '<p class="error">Error cURL: ' . htmlspecialchars($health['error']) . '</p>';
            }
            echo '<p class="error">El microservicio no está disponible. Verifica que esté corriendo en http://localhost:5000</p>';
        }
        echo '</div>';
        
        // Test 2: Verificar endpoint principal
        echo '<div class="test-section">';
        echo '<h2>2. Verificación del Endpoint /api/read-excel</h2>';
        $read = hacerPeticion(MICROSERVICIO_URL);
        
        if ($read['http_code'] > 0 && $read['http_code'] < 500) {
            echo '<p><span class="status ok">✓ ENDPOINT ACCESIBLE</span></p>';
            echo '<p class="info">HTTP Status: ' . $read['http_code'] . '</p>';
            if ($read['http_code'] === 405) {
                echo '<p class="info">El endpoint existe pero requiere método POST (esto es correcto)</p>';
            }
        } else {
            echo '<p><span class="status fail">✗ ENDPOINT NO ACCESIBLE</span></p>';
            echo '<p class="error">HTTP Status: ' . ($read['http_code'] ?: 'Sin respuesta') . '</p>';
            if ($read['error']) {
                echo '<p class="error">Error: ' . htmlspecialchars($read['error']) . '</p>';
            }
        }
        echo '</div>';
        
        // Test 3: Verificar función verificarMicroservicio() de importar.php
        echo '<div class="test-section">';
        echo '<h2>3. Verificación de la Función verificarMicroservicio()</h2>';
        
        // Simular la función del archivo importar.php
        function verificarMicroservicio() {
            $ch = curl_init(MICROSERVICIO_HEALTH);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($http_code === 200) {
                return true;
            }
            
            if ($http_code === 0 || $http_code >= 400) {
                $ch2 = curl_init('http://localhost:5000/');
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 2);
                curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 1);
                curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch2);
                $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);
                
                return ($http_code2 > 0 && $http_code2 < 500);
            }
            
            return false;
        }
        
        $microservicio_disponible = verificarMicroservicio();
        
        if ($microservicio_disponible) {
            echo '<p><span class="status ok">✓ FUNCIÓN RETORNA: DISPONIBLE</span></p>';
        } else {
            echo '<p><span class="status fail">✗ FUNCIÓN RETORNA: NO DISPONIBLE</span></p>';
        }
        echo '</div>';
        
        // Resumen
        echo '<div class="test-section">';
        echo '<h2>📊 Resumen</h2>';
        
        $todos_ok = $health['success'] && $microservicio_disponible;
        
        if ($todos_ok) {
            echo '<p class="success">✓ El microservicio está funcionando correctamente</p>';
            echo '<p class="info">Puedes proceder con las pruebas de importación de Excel.</p>';
        } else {
            echo '<p class="error">✗ El microservicio no está disponible o tiene problemas</p>';
            echo '<p class="info"><strong>Instrucciones para iniciar el microservicio:</strong></p>';
            echo '<ol>';
            echo '<li>Abre una terminal</li>';
            echo '<li>Ve a: <code>C:\\AMPYME\\MICROSERVICIO LECTURA DE EXCEL</code></li>';
            echo '<li>Ejecuta: <code>python app.py</code> o usa <code>start.bat</code></li>';
            echo '<li>El servicio debe estar en: <code>http://localhost:5000</code></li>';
            echo '</ol>';
        }
        echo '</div>';
        ?>
        
        <div class="test-section">
            <h2>🔗 Enlaces Útiles</h2>
            <ul>
                <li><a href="<?php echo BASE_URL; ?>/services/excel/importar.php">Ir a Importar Excel</a></li>
                <li><a href="<?php echo BASE_URL; ?>/pages/index.php">Volver al Dashboard</a></li>
            </ul>
        </div>
    </div>
</body>
</html>

