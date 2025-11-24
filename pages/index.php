<?php
/**
 * Página principal - Dashboard
 * Sistema RRHH
 */

require_once __DIR__ . '/../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../roles_rrhh/classes/Auth.php';

$pageTitle = 'Dashboard - Sistema RRHH';

// Aquí puedes agregar lógica para obtener estadísticas
// Por ejemplo: total funcionarios, permisos pendientes, etc.

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard">
    <h2>Bienvenido al Sistema de Recursos Humanos</h2>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Funcionarios</h3>
            <p class="stat-number">-</p>
            <a href="<?php echo BASE_URL; ?>/pages/funcionarios/listar.php">Ver todos</a>
        </div>
        
        <div class="stat-card">
            <h3>Permisos Pendientes</h3>
            <p class="stat-number">-</p>
            <a href="<?php echo BASE_URL; ?>/forms/permisos/index.php">Ver solicitudes</a>
        </div>
        
        <div class="stat-card">
            <h3>Asistencia</h3>
            <p class="stat-number">-</p>
            <a href="#">Ver reportes</a>
        </div>
    </div>
    
    <div class="quick-actions">
        <h3>Acciones Rápidas</h3>
        <ul>
            <li><a href="<?php echo BASE_URL; ?>/pages/funcionarios/crear.php">Nuevo Funcionario</a></li>
            <li><a href="<?php echo BASE_URL; ?>/forms/permisos/index.php">Solicitar Permiso</a></li>
            <li><a href="<?php echo BASE_URL; ?>/services/excel/importar.php">Importar Excel (Marcaciones)</a></li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

