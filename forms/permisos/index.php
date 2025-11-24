<?php
/**
 * Índice de Formularios de Permisos
 * Sistema RRHH
 * 
 * Aquí se migrarán los 6 formularios de permisos
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Formularios de Permisos - Sistema RRHH';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Formularios de Permisos</h2>
</div>

<div class="permisos-grid">
    <div class="permiso-card">
        <h3>Vacaciones</h3>
        <p>Solicitar días de vacaciones</p>
        <a href="<?php echo BASE_URL; ?>/forms/permisos/vacaciones.php" class="btn btn-primary">Solicitar</a>
    </div>
    
    <div class="permiso-card">
        <h3>Permiso Médico</h3>
        <p>Solicitar permiso por razones médicas</p>
        <a href="<?php echo BASE_URL; ?>/forms/permisos/medico.php" class="btn btn-primary">Solicitar</a>
    </div>
    
    <div class="permiso-card">
        <h3>Permiso Personal</h3>
        <p>Solicitar permiso por asuntos personales</p>
        <a href="<?php echo BASE_URL; ?>/forms/permisos/personal.php" class="btn btn-primary">Solicitar</a>
    </div>
    
    <div class="permiso-card">
        <h3>Licencia de Maternidad</h3>
        <p>Solicitar licencia de maternidad</p>
        <a href="<?php echo BASE_URL; ?>/forms/permisos/maternidad.php" class="btn btn-primary">Solicitar</a>
    </div>
    
    <div class="permiso-card">
        <h3>Licencia de Paternidad</h3>
        <p>Solicitar licencia de paternidad</p>
        <a href="<?php echo BASE_URL; ?>/forms/permisos/paternidad.php" class="btn btn-primary">Solicitar</a>
    </div>
    
    <div class="permiso-card">
        <h3>Día Compensatorio</h3>
        <p>Solicitar día compensatorio</p>
        <a href="<?php echo BASE_URL; ?>/forms/permisos/compensatorio.php" class="btn btn-primary">Solicitar</a>
    </div>
</div>

<div class="info-box">
    <p><strong>Nota:</strong> Los formularios se migrarán desde el sistema anterior. Revisar y adaptar según sea necesario.</p>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

