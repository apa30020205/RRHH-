<?php
/**
 * Módulo de Mantenimiento
 * Sistema RRHH
 * Solo para Administradores
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Solo administradores pueden acceder
if (!Auth::isAdmin()) {
    mostrarMensaje("No tienes permisos para acceder a esta sección", 'error');
    redirect(BASE_URL . '/pages/index.php');
}

$pageTitle = 'Módulo de Mantenimiento - Sistema RRHH';

include __DIR__ . '/../../includes/header_mantenimiento.php';
?>

<!-- Sección 1: Importar Excel -->
<div id="importar-excel" class="seccion-mantenimiento" style="display: block;">
    <h2><i class="fas fa-file-excel"></i> Importar Excel</h2>
    <?php
    // Verificar microservicio
    if (!defined('MICROSERVICIO_URL')) {
        define('MICROSERVICIO_URL', 'http://localhost:5000/api/read-excel');
        define('MICROSERVICIO_HEALTH', 'http://localhost:5000/api/health');
    }
    
    function verificarMicroservicioMantenimiento() {
        $ch = curl_init(MICROSERVICIO_HEALTH);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
    
    $microservicio_disponible = verificarMicroservicioMantenimiento();
    ?>
    
    <!-- Estado del Microservicio -->
    <?php if (!$microservicio_disponible): ?>
    <div class="alert alert-error no-auto-hide" data-persist="true">
        <strong>⚠️ Microservicio no disponible:</strong> El microservicio Python no está corriendo.
        <p class="mt-2">Para iniciarlo:</p>
        <ol class="ml-4 mt-2">
            <li>1. Abre una terminal</li>
            <li>2. Ve a: <code>C:\AMPYME\MICROSERVICIO LECTURA DE EXCEL</code></li>
            <li>3. Ejecuta: <code>python app.py</code> o usa <code>start.bat</code></li>
            <li>4. El servicio debe estar en: <code>http://localhost:5000</code></li>
        </ol>
    </div>
    <?php else: ?>
    <div class="alert alert-success no-auto-hide" data-persist="true">
        <strong>✓ Microservicio conectado:</strong> El servicio está disponible y funcionando.
    </div>
    <?php endif; ?>
    
    <?php
    // Incluir los contenedores directamente desde importar.php
    // Usar una variable para indicar que solo queremos los contenedores (sin header/footer)
    $_SOLO_CONTENEDORES = true;
    
    // Definir constantes necesarias
    if (!defined('MICROSERVICIO_URL')) {
        define('MICROSERVICIO_URL', 'http://localhost:5000/api/read-excel');
        define('MICROSERVICIO_HEALTH', 'http://localhost:5000/api/health');
    }
    
    // Incluir importar.php (no mostrará header ni footer debido a $_SOLO_CONTENEDORES)
    include __DIR__ . '/../../services/excel/importar.php';
    ?>
</div>

<!-- Sección 2: Crear/Editar -->
<div id="crear-editar" class="seccion-mantenimiento" style="display: none;">
    <h2><i class="fas fa-user-edit"></i> Crear/Editar Funcionario</h2>
    <?php include __DIR__ . '/crear_editar.php'; ?>
</div>

<!-- Sección 3: Horario Manual -->
<div id="horario-manual" class="seccion-mantenimiento" style="display: none;">
    <h2><i class="fas fa-clock"></i> Horario Manual</h2>
    <?php include __DIR__ . '/horario_manual.php'; ?>
</div>

<!-- Sección 4: EX/Funcionario -->
<div id="cesante" class="seccion-mantenimiento" style="display: none;">
    <h2><i class="fas fa-user-times"></i> EX/Funcionarios</h2>
    <?php include __DIR__ . '/cesante.php'; ?>
</div>

<!-- Sección 5: Regionales Especiales -->
<div id="regionales-especiales" class="seccion-mantenimiento" style="display: none;">
    <h2><i class="fas fa-building"></i> Regionales Especiales</h2>
    <?php include __DIR__ . '/regionales_especiales.php'; ?>
</div>

<?php include __DIR__ . '/../../includes/footer_mantenimiento.php'; ?>

