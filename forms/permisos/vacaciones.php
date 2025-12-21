<?php
/**
 * Formulario de Solicitud de Vacaciones
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Solicitud de Vacaciones - Sistema RRHH';

// Variables para mostrar datos
$funcionario = null;
$busqueda = '';
$vacaciones = [];

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
            SELECT cedula, nombre, apellido FROM funcionarios 
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
        
        // Cargar vacaciones para la tabla (aplicar filtro de fechas si existe)
        // Agrupar por los datos comunes (dias_solicitados, fecha_inicio, fecha_retorno, observaciones)
        // y mostrar las líneas individuales
        $sql = "SELECT id_vacacion, dias_solicitados, fecha_inicio, fecha_retorno, 
                       resolucion, fecha_resolucion, dias_vacacion, observaciones, 
                       fecha_registro, estado
                FROM solicitud_vacaciones
                WHERE cedula = ? AND estado = 'activa'";
        $params = [$cedulaBD];
        
        if (!empty($fechaDesdeFiltro)) {
            $sql .= " AND fecha_inicio >= ?";
            $params[] = $fechaDesdeFiltro;
        }
        
        if (!empty($fechaHastaFiltro)) {
            $sql .= " AND fecha_inicio <= ?";
            $params[] = $fechaHastaFiltro;
        }
        
        $sql .= " ORDER BY fecha_inicio DESC, fecha_registro DESC";
        
        $stmtVacaciones = $db->prepare($sql);
        $stmtVacaciones->execute($params);
        $vacaciones = $stmtVacaciones->fetchAll();
    } catch (Exception $e) {
        mostrarMensaje("Error al procesar vacaciones: " . $e->getMessage(), 'error');
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Solicitud de Vacaciones</h2>
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
    .vacaciones-container {
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
        color: #e91e63;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e91e63;
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
    
    .declaracion-section {
        background: #fce4ec;
        padding: 1.5rem;
        border-radius: 8px;
        margin-top: 1rem;
        border-left: 4px solid #e91e63;
    }
    
    .declaracion-section p {
        margin-bottom: 1rem;
        color: #555;
        line-height: 1.6;
    }
    
    .declaracion-section input[type="number"] {
        width: 80px;
        display: inline-block;
        margin: 0 0.25rem;
        text-align: center;
        font-weight: bold;
        border-color: #e91e63;
    }
    
    .fechas-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .vacaciones-table-container {
        margin-top: 1.5rem;
    }
    
    .vacaciones-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        margin-top: 1rem;
    }
    
    .vacaciones-table th {
        background: #e91e63;
        color: white;
        padding: 0.75rem;
        text-align: left;
        border: 1px solid #c2185b;
    }
    
    .vacaciones-table td {
        padding: 0.75rem;
        border: 1px solid #dee2e6;
    }
    
    .vacaciones-table tr:nth-child(even) {
        background: #f8f9fa;
    }
    
    .btn-agregar-vacaciones {
        background: #e91e63;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        margin-top: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .btn-agregar-vacaciones:hover {
        background: #c2185b;
    }
    
    .vacaciones-list {
        margin-top: 2rem;
    }
    
    .vacaciones-list h3 {
        color: #e91e63;
        margin-bottom: 1rem;
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
        background: #fce4ec;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #e91e63;
    }
    
    .funcionario-info strong {
        color: #c2185b;
    }
    
    .btn-primary {
        background: #e91e63;
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
        background: #c2185b;
    }
    
    .btn {
        background: #e91e63;
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
        background: #c2185b;
    }
    
    .btn-eliminar-fila {
        color: #dc3545;
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 0.25rem 0.5rem;
        font-size: 1rem;
    }
    
    .btn-eliminar-fila:hover {
        color: #c82333;
    }
</style>

<!-- Sección de Búsqueda de Funcionario -->
<div class="vacaciones-container">
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
<div class="vacaciones-container">
    <form method="POST" action="<?php echo BASE_URL; ?>/forms/permisos/procesar_vacaciones.php" id="formVacaciones">
        <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
        
        <div class="form-section">
            <div class="declaracion-section">
                <h4 style="color: #e91e63; margin-bottom: 1rem; font-weight: bold;">Declaración</h4>
                <p>
                    Por este medio informo a usted que haré uso de 
                    <input type="number" id="dias_solicitados" name="dias_solicitados" 
                           min="1" max="365" value="0" required 
                           style="width: 80px; display: inline-block; margin: 0 0.25rem; text-align: center; font-weight: bold; border-color: #e91e63;">
                    días de vacaciones a las que tengo derecho, según el Artículo 95 del Texto Único de 2008, que contiene la Ley N°9 del 20 de junio de 1994.
                </p>
                
                <div class="fechas-row">
                    <div class="form-group">
                        <label for="fecha_inicio">Las mismas se harán efectivas</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" required>
                    </div>
                    <div class="form-group">
                        <label for="fecha_retorno">Retornando a mis labores en día</label>
                        <input type="date" id="fecha_retorno" name="fecha_retorno" required>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Vacaciones correspondientes a:</h3>
            <p style="margin-bottom: 1rem; color: #666; font-size: 0.95em;">
                Puede agregar varias líneas de vacaciones. Cada línea corresponde a un periodo solicitado.
            </p>
            
            <div class="vacaciones-table-container">
                <table class="vacaciones-table" id="tabla-vacaciones">
                    <thead>
                        <tr>
                            <th>RESOLUCIÓN</th>
                            <th>FECHA</th>
                            <th>DÍAS</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-vacaciones">
                        <!-- Las filas se agregarán dinámicamente con JavaScript -->
                    </tbody>
                </table>
                
                <button type="button" class="btn-agregar-vacaciones" onclick="agregarFilaVacacion()">
                    <i class="fas fa-plus"></i> Agregar vacaciones
                </button>
            </div>
        </div>
        
        <div class="form-section">
            <div class="form-group">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="4" 
                          placeholder="Observaciones..."></textarea>
            </div>
        </div>
        
        <div class="form-actions" style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Solicitud de Vacaciones
            </button>
            <button type="reset" class="btn" onclick="limpiarFormulario()">Limpiar</button>
        </div>
    </form>
</div>

<!-- Listado de Vacaciones Registradas -->
<div class="vacaciones-container vacaciones-list">
    <h3>Vacaciones Registradas</h3>
    
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
    
    <?php if (count($vacaciones) > 0): ?>
        <table class="vacaciones-table">
            <thead>
                <tr>
                    <th>Fecha Inicio</th>
                    <th>Fecha Retorno</th>
                    <th>Resolución</th>
                    <th>Fecha Resolución</th>
                    <th>Días</th>
                    <th>Fecha Registro</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vacaciones as $vacacion): ?>
                    <tr>
                        <td><?php echo $vacacion['fecha_inicio'] ? date('d/m/Y', strtotime($vacacion['fecha_inicio'])) : '-'; ?></td>
                        <td><?php echo $vacacion['fecha_retorno'] ? date('d/m/Y', strtotime($vacacion['fecha_retorno'])) : '-'; ?></td>
                        <td><?php echo htmlspecialchars($vacacion['resolucion'] ?? '-'); ?></td>
                        <td><?php echo $vacacion['fecha_resolucion'] ? date('d/m/Y', strtotime($vacacion['fecha_resolucion'])) : '-'; ?></td>
                        <td><strong><?php echo $vacacion['dias_vacacion']; ?></strong> días</td>
                        <td><?php echo date('d/m/Y H:i', strtotime($vacacion['fecha_registro'])); ?></td>
                        <td>
                            <?php if (Auth::isAdmin()): ?>
                            <a href="<?php echo BASE_URL; ?>/forms/permisos/eliminar_vacacion.php?id_vacacion=<?php echo $vacacion['id_vacacion']; ?>&cedula=<?php echo urlencode($funcionario['cedula'] ?? ''); ?>&fecha_desde=<?php echo urlencode($fechaDesdeFiltro ?? ''); ?>&fecha_hasta=<?php echo urlencode($fechaHastaFiltro ?? ''); ?>" 
                               style="color: #dc3545; text-decoration: none; display: inline-block; padding: 0.5rem 1rem; border: 1px solid #dc3545; border-radius: 4px; background: white;"
                               onmouseover="this.style.background='#f8d7da';"
                               onmouseout="this.style.background='white';"
                               onclick="return confirm('¿Eliminarás registro sí o no?')">
                                <i class="fas fa-trash" style="color: #dc3545;"></i> <span style="color: #dc3545;">Eliminar</span>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #666; padding: 1rem;">No hay vacaciones registradas.</p>
    <?php endif; ?>
</div>

<script>
let contadorFilas = 0;

function agregarFilaVacacion() {
    contadorFilas++;
    const tbody = document.getElementById('tbody-vacaciones');
    const nuevaFila = document.createElement('tr');
    nuevaFila.id = 'fila-vacacion-' + contadorFilas;
    
    nuevaFila.innerHTML = `
        <td>
            <input type="text" name="periodos[${contadorFilas}][resolucion]" 
                   placeholder="Resolución" value="0" 
                   style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px;">
        </td>
        <td>
            <input type="date" name="periodos[${contadorFilas}][fecha_resolucion]" 
                   style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px;">
        </td>
        <td>
            <input type="number" name="periodos[${contadorFilas}][dias_vacacion]" 
                   min="1" max="365" value="0" required
                   style="width: 80px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 3px; text-align: center;">
            <span style="margin-left: 0.5rem;">días</span>
        </td>
        <td>
            <button type="button" class="btn-eliminar-fila" onclick="eliminarFilaVacacion(${contadorFilas})">
                <i class="fas fa-trash" style="color: #dc3545;"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(nuevaFila);
}

function eliminarFilaVacacion(idFila) {
    const fila = document.getElementById('fila-vacacion-' + idFila);
    if (fila) {
        fila.remove();
    }
}

function limpiarFormulario() {
    document.getElementById('tbody-vacaciones').innerHTML = '';
    contadorFilas = 0;
}

// Validar que haya al menos una fila antes de enviar
document.getElementById('formVacaciones').addEventListener('submit', function(e) {
    const filas = document.querySelectorAll('#tbody-vacaciones tr');
    if (filas.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos una línea de vacaciones.');
        return false;
    }
    
    // Validar que todas las filas tengan fecha y días
    let todasValidas = true;
    filas.forEach(function(fila) {
        const fechaInput = fila.querySelector('input[type="date"]');
        const diasInput = fila.querySelector('input[type="number"]');
        
        if (!fechaInput.value || !diasInput.value || parseInt(diasInput.value) <= 0) {
            todasValidas = false;
        }
    });
    
    if (!todasValidas) {
        e.preventDefault();
        alert('Todas las líneas de vacaciones deben tener fecha y días válidos.');
        return false;
    }
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
