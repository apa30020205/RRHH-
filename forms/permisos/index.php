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

$pageTitle = 'Listado Permisos/Vacaciones - Sistema RRHH';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Listado Permisos/Vacaciones</h2>
</div>

<style>
    .permisos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }
    
    .permiso-card {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        cursor: pointer;
        color: #333;
        text-align: left;
        position: relative;
        border-left: 5px solid #667eea;
    }
    
    .permiso-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    /* Colores de borde izquierdo para cada card */
    .permiso-card:nth-child(1) {
        border-left-color: #2196F3; /* Azul */
    }
    
    .permiso-card:nth-child(2) {
        border-left-color: #f44336; /* Rojo */
    }
    
    .permiso-card:nth-child(3) {
        border-left-color: #9c27b0; /* Morado */
    }
    
    .permiso-card:nth-child(4) {
        border-left-color: #ff9800; /* Naranja */
    }
    
    .permiso-card:nth-child(5) {
        border-left-color: #4caf50; /* Verde */
    }
    
    .permiso-card:nth-child(6) {
        border-left-color: #e91e63; /* Rosa */
    }
    
    .permiso-card .icon-container {
        margin-bottom: 1rem;
    }
    
    .permiso-card i {
        font-size: 2.5rem;
        margin-bottom: 0;
    }
    
    /* Colores de iconos */
    .permiso-card:nth-child(1) i {
        color: #2196F3; /* Azul */
    }
    
    .permiso-card:nth-child(2) i {
        color: #f44336; /* Rojo */
    }
    
    .permiso-card:nth-child(3) i {
        color: #9c27b0; /* Morado */
    }
    
    .permiso-card:nth-child(4) i {
        color: #ff9800; /* Naranja */
    }
    
    .permiso-card:nth-child(5) i {
        color: #4caf50; /* Verde */
    }
    
    .permiso-card:nth-child(6) i {
        color: #e91e63; /* Rosa */
    }
    
    .permiso-card h3 {
        margin: 0 0 0.5rem 0;
        font-size: 1.25rem;
        font-weight: bold;
        color: #333;
    }
    
    .permiso-card p {
        margin: 0 0 1.5rem 0;
        font-size: 0.9rem;
        color: #666;
        line-height: 1.5;
    }
    
    .permiso-card .btn {
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 6px;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }
    
    /* Colores de botones */
    .permiso-card:nth-child(1) .btn {
        background: #2196F3;
        color: white;
    }
    
    .permiso-card:nth-child(2) .btn {
        background: #f44336;
        color: white;
    }
    
    .permiso-card:nth-child(3) .btn {
        background: #9c27b0;
        color: white;
    }
    
    .permiso-card:nth-child(4) .btn {
        background: #ff9800;
        color: white;
    }
    
    .permiso-card:nth-child(5) .btn {
        background: #4caf50;
        color: white;
    }
    
    .permiso-card:nth-child(6) .btn {
        background: #e91e63;
        color: white;
    }
    
    .permiso-card .btn:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }
    
    .permiso-card .btn i {
        font-size: 1rem;
        color: white;
    }
    
    /* Modal de construcción */
    .modal-construccion {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s ease;
    }
    
    .modal-construccion.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .modal-content-construccion {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        text-align: center;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
    }
    
    @keyframes slideUp {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .modal-content-construccion i {
        font-size: 5rem;
        color: #ffc107;
        margin-bottom: 1rem;
    }
    
    .modal-content-construccion h2 {
        color: #333;
        margin-bottom: 1rem;
    }
    
    .modal-content-construccion p {
        color: #666;
        margin-bottom: 2rem;
    }
    
    .modal-content-construccion .btn-cerrar {
        background: #667eea;
        color: white;
        border: none;
        padding: 0.75rem 2rem;
        border-radius: 25px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    
    .modal-content-construccion .btn-cerrar:hover {
        background: #5568d3;
    }
</style>

<div class="permisos-grid">
    <div class="permiso-card" onclick="mostrarConstruccion()">
        <div class="icon-container">
            <i class="fas fa-umbrella-beach"></i>
        </div>
        <h3>Vacaciones</h3>
        <p>Solicitar días de vacaciones</p>
        <span class="btn"><i class="fas fa-file-alt"></i> Llenar Formulario</span>
    </div>
    
    <div class="permiso-card" onclick="mostrarConstruccion()">
        <div class="icon-container">
            <i class="fas fa-heartbeat"></i>
        </div>
        <h3>Permiso Médico</h3>
        <p>Solicitar permiso por razones médicas</p>
        <span class="btn"><i class="fas fa-file-alt"></i> Llenar Formulario</span>
    </div>
    
    <div class="permiso-card" onclick="mostrarConstruccion()">
        <div class="icon-container">
            <i class="fas fa-user-clock"></i>
        </div>
        <h3>Permiso Personal</h3>
        <p>Solicitar permiso por asuntos personales</p>
        <span class="btn"><i class="fas fa-file-alt"></i> Llenar Formulario</span>
    </div>
    
    <div class="permiso-card" onclick="mostrarConstruccion()">
        <div class="icon-container">
            <i class="fas fa-baby"></i>
        </div>
        <h3>Licencia de Maternidad</h3>
        <p>Solicitar licencia de maternidad</p>
        <span class="btn"><i class="fas fa-file-alt"></i> Llenar Formulario</span>
    </div>
    
    <div class="permiso-card" onclick="mostrarConstruccion()">
        <div class="icon-container">
            <i class="fas fa-child"></i>
        </div>
        <h3>Licencia de Paternidad</h3>
        <p>Solicitar licencia de paternidad</p>
        <span class="btn"><i class="fas fa-file-alt"></i> Llenar Formulario</span>
    </div>
    
    <div class="permiso-card" onclick="mostrarConstruccion()">
        <div class="icon-container">
            <i class="fas fa-calendar-check"></i>
        </div>
        <h3>Día Compensatorio</h3>
        <p>Solicitar día compensatorio</p>
        <span class="btn"><i class="fas fa-file-alt"></i> Llenar Formulario</span>
    </div>
</div>

<!-- Modal de Construcción -->
<div id="modalConstruccion" class="modal-construccion" onclick="cerrarConstruccion(event)">
    <div class="modal-content-construccion" onclick="event.stopPropagation()">
        <i class="fas fa-tools"></i>
        <h2>En Construcción</h2>
        <p>Esta funcionalidad está en desarrollo. Estará disponible próximamente.</p>
        <button class="btn-cerrar" onclick="cerrarConstruccion()">Cerrar</button>
    </div>
</div>

<script>
function mostrarConstruccion() {
    document.getElementById('modalConstruccion').classList.add('show');
}

function cerrarConstruccion(event) {
    // Si se hace clic fuera del modal o en el botón cerrar
    if (!event || event.target.classList.contains('modal-construccion') || event.target.classList.contains('btn-cerrar')) {
        document.getElementById('modalConstruccion').classList.remove('show');
    }
}

// Cerrar modal con tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.getElementById('modalConstruccion').classList.remove('show');
    }
});
</script>

<div class="info-box" style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
    <p><strong>Nota:</strong> Los formularios se migrarán desde el sistema anterior. Revisar y adaptar según sea necesario.</p>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

