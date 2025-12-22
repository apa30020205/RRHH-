<?php
/**
 * Formulario de Reincorporación
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Reincorporación - Sistema RRHH';

// Variables para mostrar datos
$funcionario = null;
$busqueda = '';
$reincorporaciones = [];

// Procesar búsqueda de funcionario
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Si viene cédula por GET, usarla como búsqueda
if (empty($busqueda) && isset($_GET['cedula']) && !empty($_GET['cedula'])) {
    $busqueda = trim($_GET['cedula']);
}

if (!empty($busqueda)) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar funcionario - por cédula, nombre o apellido
        $stmt = $db->prepare("
            SELECT cedula, nombre, apellido, no_posicion, posicion_funcional, sede_provincia, Direccion
            FROM funcionarios 
            WHERE cedula LIKE ? OR nombre LIKE ? OR apellido LIKE ?
            LIMIT 1
        ");
        $busquedaLike = '%' . $busqueda . '%';
        $stmt->execute([$busquedaLike, $busquedaLike, $busquedaLike]);
        $funcionario = $stmt->fetch();
        
        if ($funcionario) {
            $cedulaBD = $funcionario['cedula'];
        }
    } catch (Exception $e) {
        mostrarMensaje("Error al buscar funcionario: " . $e->getMessage(), 'error');
    }
}

// Procesar filtro de fechas para listado
$fechaDesdeFiltro = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fechaHastaFiltro = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

if ($funcionario) {
    try {
        $db = Database::getInstance()->getConnection();
        $cedulaBD = $funcionario['cedula'];
        
        // Cargar reincorporaciones para la tabla (aplicar filtro de fechas si existe)
        $sql = "SELECT id_reincorporacion, motivo_ausencia, puesto, no_posicion, 
                       unidad_administrativa, fecha_reincorporacion, fecha_registro, estado
                FROM reincorporacion
                WHERE cedula = ? AND estado = 'activa'";
        $params = [$cedulaBD];
        
        if (!empty($fechaDesdeFiltro)) {
            $sql .= " AND fecha_reincorporacion >= ?";
            $params[] = $fechaDesdeFiltro;
        }
        
        if (!empty($fechaHastaFiltro)) {
            $sql .= " AND fecha_reincorporacion <= ?";
            $params[] = $fechaHastaFiltro;
        }
        
        $sql .= " ORDER BY fecha_reincorporacion DESC, fecha_registro DESC";
        
        $stmtReincorporaciones = $db->prepare($sql);
        $stmtReincorporaciones->execute($params);
        $reincorporaciones = $stmtReincorporaciones->fetchAll();
    } catch (Exception $e) {
        mostrarMensaje("Error al procesar reincorporaciones: " . $e->getMessage(), 'error');
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Reincorporación</h2>
    <a href="<?php echo BASE_URL; ?>/forms/permisos/index.php" class="btn">Volver</a>
</div>

<?php
// Mostrar mensajes
$mensaje = obtenerMensaje();
if ($mensaje): ?>
    <div class="alert alert-<?php echo $mensaje['tipo']; ?>">
        <?php echo htmlspecialchars($mensaje['texto']); ?>
    </div>
<?php endif; ?>

<style>
    .reincorporacion-container {
        background: white;
        padding: 2rem;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        margin-bottom: 2rem;
    }
    
    .search-section {
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .form-section {
        margin-bottom: 2rem;
    }
    
    .form-section h3 {
        color: #9c27b0;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #9c27b0;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #333;
    }
    
    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group input[type="date"],
    .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
    }
    
    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .radio-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    
    .radio-option {
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .radio-option:hover {
        background: #f8f9fa;
        border-color: #9c27b0;
    }
    
    .radio-option input[type="radio"] {
        margin-right: 0.5rem;
    }
    
    .detalles-section {
        background: #f3e5f5;
        padding: 1.5rem;
        border-radius: 8px;
        margin-top: 1rem;
        border-left: 4px solid #9c27b0;
    }
    
    .detalles-section h4 {
        color: #7b1fa2;
        margin-bottom: 1rem;
        font-weight: bold;
    }
    
    .puesto-row {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .reincorporaciones-list {
        margin-top: 2rem;
    }
    
    .reincorporaciones-list h3 {
        color: #9c27b0;
        margin-bottom: 1rem;
    }
    
    .reincorporaciones-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        margin-top: 1rem;
    }
    
    .reincorporaciones-table th {
        background: #9c27b0;
        color: white;
        padding: 0.75rem;
        text-align: left;
        border: 1px solid #7b1fa2;
    }
    
    .reincorporaciones-table td {
        padding: 0.75rem;
        border: 1px solid #dee2e6;
    }
    
    .reincorporaciones-table tr:nth-child(even) {
        background: #f8f9fa;
    }
    
    .filter-section {
        margin-bottom: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 6px;
    }
    
    .filter-section .form-group {
        display: inline-block;
        margin-right: 1rem;
        margin-bottom: 0;
    }
    
    .filter-section label {
        margin-right: 0.5rem;
    }
    
    .funcionario-info {
        background: #f3e5f5;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #9c27b0;
    }
    
    .funcionario-info strong {
        color: #7b1fa2;
    }
    
    .btn-primary {
        background: #9c27b0;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-primary:hover {
        background: #7b1fa2;
    }
    
    .btn {
        background: #9c27b0;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn:hover {
        background: #7b1fa2;
    }
    
    .mensaje-recordatorio {
        background: #fff3cd;
        padding: 1rem;
        border-left: 4px solid #ffc107;
        border-radius: 4px;
        margin-top: 1rem;
        font-weight: bold;
        color: #856404;
    }
</style>

<!-- Sección de Búsqueda de Funcionario -->
<div class="reincorporacion-container">
    <div class="search-section">
        <h3>Buscar Funcionario</h3>
        <form method="GET" action="">
            <div class="form-group" style="max-width: 500px;">
                <label for="buscar_funcionario">Buscar por Cédula, Nombre o Apellido:</label>
                <input type="text" id="buscar_funcionario" name="buscar" 
                       value="<?php echo htmlspecialchars($busqueda); ?>" 
                       placeholder="Ej: 8-1234-5678 o José o Aguirre" required>
                <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
        </form>
        
        <?php if ($funcionario): ?>
            <div class="funcionario-info">
                <strong>Funcionario encontrado:</strong><br>
                <strong>Cédula:</strong> <?php echo htmlspecialchars(formatearCedula($funcionario['cedula'])); ?><br>
                <strong>Nombre:</strong> <?php echo htmlspecialchars($funcionario['nombre'] . ' ' . $funcionario['apellido']); ?>
            </div>
        <?php elseif (!empty($busqueda)): ?>
            <div class="alert alert-error">
                Funcionario no encontrado. Verifique la búsqueda (cédula, nombre o apellido).
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($funcionario): ?>
<!-- Formulario de Captura -->
<div class="reincorporacion-container">
    <form method="POST" action="<?php echo BASE_URL; ?>/forms/permisos/procesar_reincorporacion.php" id="formReincorporacion">
        <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
        
        <div class="form-section">
            <h3>Estuve ausente por motivo de: *</h3>
            <div class="radio-group">
                <label class="radio-option">
                    <input type="radio" name="motivo_ausencia" value="Licencia con sueldo" required>
                    Licencia con sueldo
                </label>
                <label class="radio-option">
                    <input type="radio" name="motivo_ausencia" value="Licencia sin sueldo" required>
                    Licencia sin sueldo
                </label>
                <label class="radio-option">
                    <input type="radio" name="motivo_ausencia" value="Licencia especial" required>
                    Licencia especial
                </label>
                <label class="radio-option">
                    <input type="radio" name="motivo_ausencia" value="Vacaciones" required>
                    Vacaciones
                </label>
                <label class="radio-option">
                    <input type="radio" name="motivo_ausencia" value="Prestando funciones en otra Institución" required>
                    Prestando funciones en otra Institución
                </label>
            </div>
        </div>
        
        <div class="form-section">
            <div class="detalles-section">
                <h4>Detalles de Reincorporación</h4>
                <p style="margin-bottom: 1rem; color: #555;">Me estoy reincorporando formalmente al puesto de:</p>
                
                <div class="puesto-row">
                    <div class="form-group">
                        <label for="puesto">Puesto *</label>
                        <input type="text" id="puesto" name="puesto" 
                               value="<?php echo htmlspecialchars($funcionario['posicion_funcional'] ?? ''); ?>" 
                               required placeholder="Nombre del puesto">
                    </div>
                    <div class="form-group">
                        <label for="no_posicion">Posición N°</label>
                        <input type="number" id="no_posicion" name="no_posicion" 
                               value="<?php echo htmlspecialchars($funcionario['no_posicion'] ?? ''); ?>" 
                               placeholder="Número de posición">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="unidad_administrativa">Unidad Administrativa</label>
                    <textarea id="unidad_administrativa" name="unidad_administrativa" rows="2" 
                              placeholder="Dirección de Desarrollo Empresarial: Departamento de Capacitación y Asistencia Técnica"><?php 
                        $unidadAdmin = '';
                        if (!empty($funcionario['sede_provincia'])) {
                            $unidadAdmin .= $funcionario['sede_provincia'];
                        }
                        if (!empty($funcionario['Direccion'])) {
                            if (!empty($unidadAdmin)) $unidadAdmin .= ': ';
                            $unidadAdmin .= $funcionario['Direccion'];
                        }
                        echo htmlspecialchars($unidadAdmin);
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="fecha_reincorporacion">A partir del *</label>
                    <input type="date" id="fecha_reincorporacion" name="fecha_reincorporacion" required>
                </div>
            </div>
        </div>
        
        <div class="form-actions" style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Reincorporación
            </button>
            <button type="reset" class="btn">Limpiar</button>
        </div>
    </form>
    
    <?php
    // Mostrar mensaje de recordatorio si hay reincorporaciones registradas o se acaba de guardar
    if (count($reincorporaciones) > 0 || (isset($mensaje) && $mensaje['tipo'] === 'success')): ?>
        <div class="mensaje-recordatorio" style="margin-top: 1.5rem;">
            <strong>Recuerde Incorporar al Funcionario desde EX/Funcionario</strong>
        </div>
    <?php endif; ?>
</div>

<!-- Listado de Reincorporaciones Registradas -->
<div class="reincorporacion-container reincorporaciones-list">
    <h3>Reincorporaciones Registradas</h3>
    
    <!-- Filtro de fechas -->
    <div class="filter-section">
        <form method="GET" action="">
            <input type="hidden" name="buscar" value="<?php echo htmlspecialchars($funcionario ? $funcionario['cedula'] : $busqueda); ?>">
            <div class="form-group">
                <label>Fecha Desde:</label>
                <input type="date" name="fecha_desde" value="<?php echo htmlspecialchars($fechaDesdeFiltro); ?>">
            </div>
            <div class="form-group">
                <label>Fecha Hasta:</label>
                <input type="date" name="fecha_hasta" value="<?php echo htmlspecialchars($fechaHastaFiltro); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <?php if (!empty($fechaDesdeFiltro) || !empty($fechaHastaFiltro)): ?>
                <a href="?buscar=<?php echo urlencode($funcionario ? $funcionario['cedula'] : $busqueda); ?>" class="btn">Limpiar Filtro</a>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if (count($reincorporaciones) > 0): ?>
        <table class="reincorporaciones-table">
            <thead>
                <tr>
                    <th>Fecha Reincorporación</th>
                    <th>Motivo Ausencia</th>
                    <th>Puesto</th>
                    <th>Posición N°</th>
                    <th>Unidad Administrativa</th>
                    <th>Fecha Registro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reincorporaciones as $reincorporacion): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($reincorporacion['fecha_reincorporacion'])); ?></td>
                        <td><?php echo htmlspecialchars($reincorporacion['motivo_ausencia']); ?></td>
                        <td><?php echo htmlspecialchars($reincorporacion['puesto']); ?></td>
                        <td><?php echo htmlspecialchars($reincorporacion['no_posicion'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($reincorporacion['unidad_administrativa'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($reincorporacion['fecha_registro'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #666; padding: 1rem;">No hay reincorporaciones registradas.</p>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

