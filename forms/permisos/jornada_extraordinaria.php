<?php
/**
 * Formulario de Jornada Extraordinaria
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Jornada Extraordinaria - Sistema RRHH';

// Obtener usuario actual para registro
$usuarioId = $_SESSION['id_usuario'] ?? null;

// Variables para mostrar datos
$funcionario = null;
$cedulaBuscada = '';
$jornadas = [];

// Procesar búsqueda de funcionario
// La cédula en la BD tiene guiones, usarla tal cual (NO normalizar)
if (isset($_GET['cedula']) && !empty($_GET['cedula'])) {
    $cedulaBuscada = trim($_GET['cedula']);
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar funcionario - usar búsqueda flexible como en horario_manual
        // La cédula en la BD tiene guiones, pero permitimos buscar con o sin guiones
        $cedulaLimpia = preg_replace('/[^0-9A-Za-z]/', '', $cedulaBuscada);
        $stmt = $db->prepare("
            SELECT cedula, nombre, apellido FROM funcionarios 
            WHERE cedula = ? OR cedula LIKE ? OR REPLACE(REPLACE(cedula, '-', ''), ' ', '') LIKE ?
            LIMIT 1
        ");
        $busquedaLike = '%' . $cedulaBuscada . '%';
        $cedulaLimpiaLike = '%' . $cedulaLimpia . '%';
        $stmt->execute([$cedulaBuscada, $busquedaLike, $cedulaLimpiaLike]);
        $funcionario = $stmt->fetch();
        
        // Si se encontró, cargar jornadas registradas usando la cédula exacta de la BD
        if ($funcionario) {
            $cedulaBD = $funcionario['cedula']; // Usar la cédula exacta de la BD
            
            // Obtener jornadas del funcionario usando la cédula de la BD
            $stmtJornadas = $db->prepare("
                SELECT id_jornada, fecha, hora_desde, hora_hasta, horas_totales, 
                       justificacion, fecha_registro, estado
                FROM jornada_extraordinaria
                WHERE cedula = ? AND estado = 'activa'
                ORDER BY fecha DESC, fecha_registro DESC
            ");
            $stmtJornadas->execute([$cedulaBD]);
            $jornadas = $stmtJornadas->fetchAll();
        }
    } catch (Exception $e) {
        mostrarMensaje("Error al buscar funcionario: " . $e->getMessage(), 'error');
    }
}

// Procesar filtro de fechas para listado
$fechaDesdeFiltro = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fechaHastaFiltro = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

if ($funcionario && (!empty($fechaDesdeFiltro) || !empty($fechaHastaFiltro))) {
    try {
        $db = Database::getInstance()->getConnection();
        // Usar la cédula exacta de la BD (con guiones)
        $cedulaBD = $funcionario['cedula'];
        
        $sql = "SELECT id_jornada, fecha, hora_desde, hora_hasta, horas_totales, 
                       justificacion, fecha_registro, estado
                FROM jornada_extraordinaria
                WHERE cedula = ? AND estado = 'activa'";
        $params = [$cedulaBD];
        
        if (!empty($fechaDesdeFiltro)) {
            $sql .= " AND fecha >= ?";
            $params[] = $fechaDesdeFiltro;
        }
        
        if (!empty($fechaHastaFiltro)) {
            $sql .= " AND fecha <= ?";
            $params[] = $fechaHastaFiltro;
        }
        
        $sql .= " ORDER BY fecha DESC, fecha_registro DESC";
        
        $stmtJornadas = $db->prepare($sql);
        $stmtJornadas->execute($params);
        $jornadas = $stmtJornadas->fetchAll();
    } catch (Exception $e) {
        mostrarMensaje("Error al filtrar jornadas: " . $e->getMessage(), 'error');
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Jornada Extraordinaria</h2>
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
    .jornada-container {
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
        color: #2196F3;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #2196F3;
    }
    
    .fecha-hora-group {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr auto;
        gap: 1rem;
        align-items: end;
        margin-bottom: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 6px;
        border-left: 4px solid #2196F3;
    }
    
    .fecha-hora-item {
        display: contents;
    }
    
    .fecha-hora-group .form-group {
        margin-bottom: 0;
    }
    
    .btn-remove-fecha {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        cursor: pointer;
        height: fit-content;
    }
    
    .btn-remove-fecha:hover {
        background: #c0392b;
    }
    
    .btn-add-fecha {
        background: #2196F3;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 1rem;
    }
    
    .btn-add-fecha:hover {
        background: #1976D2;
    }
    
    .jornadas-list {
        margin-top: 2rem;
    }
    
    .jornadas-list h3 {
        color: #2196F3;
        margin-bottom: 1rem;
    }
    
    .jornadas-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        margin-top: 1rem;
    }
    
    .jornadas-table th {
        background: #2196F3;
        color: white;
        padding: 0.75rem;
        text-align: left;
        border: 1px solid #1976D2;
    }
    
    .jornadas-table td {
        padding: 0.75rem;
        border: 1px solid #dee2e6;
    }
    
    .jornadas-table tr:nth-child(even) {
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
        background: #e3f2fd;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #2196F3;
    }
    
    .funcionario-info strong {
        color: #1976D2;
    }
</style>

<!-- Sección de Búsqueda de Funcionario -->
<div class="jornada-container">
    <div class="search-section">
        <h3>Buscar Funcionario</h3>
        <form method="GET" action="">
            <div class="form-group" style="max-width: 400px;">
                <label for="cedula_buscar">Cédula del Funcionario</label>
                <input type="text" id="cedula_buscar" name="cedula" 
                       value="<?php echo htmlspecialchars($cedulaBuscada); ?>" 
                       placeholder="8-1234-5678" required>
                <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">Buscar</button>
            </div>
        </form>
        
        <?php if ($funcionario): ?>
            <div class="funcionario-info">
                <strong>Funcionario encontrado:</strong><br>
                <strong>Cédula:</strong> <?php echo htmlspecialchars(formatearCedula($funcionario['cedula'])); ?><br>
                <strong>Nombre:</strong> <?php echo htmlspecialchars($funcionario['nombre'] . ' ' . $funcionario['apellido']); ?>
            </div>
        <?php elseif (!empty($cedulaBuscada)): ?>
            <div class="alert alert-error">
                Funcionario no encontrado. Verifique la cédula.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($funcionario): ?>
<!-- Formulario de Captura -->
<div class="jornada-container">
    <form method="POST" action="<?php echo BASE_URL; ?>/forms/permisos/procesar_jornada_extraordinaria.php" id="formJornada">
        <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
        
        <div class="form-section">
            <h3>Justificación de Jornada Extraordinaria</h3>
            <div class="form-group">
                <label for="justificacion">Justificación *</label>
                <textarea id="justificacion" name="justificacion" rows="4" required 
                          placeholder="Describa la razón de la jornada extraordinaria..."></textarea>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Fechas y Horas</h3>
            <div id="fechas-container">
                <!-- Se agregarán dinámicamente -->
                <div class="fecha-hora-group" data-index="0">
                    <div class="form-group">
                        <label>Fecha *</label>
                        <input type="date" name="fechas[0][fecha]" required class="fecha-input">
                    </div>
                    <div class="form-group">
                        <label>Hora Desde *</label>
                        <input type="time" name="fechas[0][hora_desde]" required class="hora-desde-input">
                    </div>
                    <div class="form-group">
                        <label>Hora Hasta *</label>
                        <input type="time" name="fechas[0][hora_hasta]" required class="hora-hasta-input">
                    </div>
                    <div class="form-group">
                        <label>Horas Totales</label>
                        <input type="text" readonly class="horas-totales-input" placeholder="Se calcula automáticamente">
                    </div>
                    <button type="button" class="btn-remove-fecha" onclick="removeFecha(this)" style="display: none;">
                        <i class="fas fa-times"></i> Eliminar
                    </button>
                </div>
            </div>
            
            <button type="button" class="btn-add-fecha" onclick="addFecha()">
                <i class="fas fa-plus"></i> Agregar Otra Fecha
            </button>
        </div>
        
        <div class="form-actions" style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Jornada Extraordinaria
            </button>
            <button type="reset" class="btn">Limpiar</button>
        </div>
    </form>
</div>

<!-- Listado de Jornadas Registradas -->
<div class="jornada-container jornadas-list">
    <h3>Jornadas Extraordinarias Registradas</h3>
    
    <!-- Filtro de fechas -->
    <div class="filter-section">
        <form method="GET" action="">
            <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($funcionario ? $funcionario['cedula'] : $cedulaBuscada); ?>">
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
                <a href="?cedula=<?php echo urlencode($funcionario ? $funcionario['cedula'] : $cedulaBuscada); ?>" class="btn">Limpiar Filtro</a>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if (count($jornadas) > 0): ?>
        <table class="jornadas-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora Desde</th>
                    <th>Hora Hasta</th>
                    <th>Horas Totales</th>
                    <th>Justificación</th>
                    <th>Fecha Registro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jornadas as $jornada): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($jornada['fecha'])); ?></td>
                        <td><?php echo date('H:i', strtotime($jornada['hora_desde'])); ?></td>
                        <td><?php echo date('H:i', strtotime($jornada['hora_hasta'])); ?></td>
                        <td><strong><?php echo $jornada['horas_totales'] ?? '-'; ?></strong></td>
                        <td><?php echo htmlspecialchars(substr($jornada['justificacion'], 0, 50)); ?><?php echo strlen($jornada['justificacion']) > 50 ? '...' : ''; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($jornada['fecha_registro'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay jornadas extraordinarias registradas para este funcionario.</p>
    <?php endif; ?>
</div>

<script>
let fechaIndex = 1;

function addFecha() {
    const container = document.getElementById('fechas-container');
    const newGroup = document.createElement('div');
    newGroup.className = 'fecha-hora-group';
    newGroup.setAttribute('data-index', fechaIndex);
    
    newGroup.innerHTML = `
        <div class="form-group">
            <label>Fecha *</label>
            <input type="date" name="fechas[${fechaIndex}][fecha]" required class="fecha-input">
        </div>
        <div class="form-group">
            <label>Hora Desde *</label>
            <input type="time" name="fechas[${fechaIndex}][hora_desde]" required class="hora-desde-input">
        </div>
        <div class="form-group">
            <label>Hora Hasta *</label>
            <input type="time" name="fechas[${fechaIndex}][hora_hasta]" required class="hora-hasta-input">
        </div>
        <div class="form-group">
            <label>Horas Totales</label>
            <input type="text" readonly class="horas-totales-input" placeholder="Se calcula automáticamente">
        </div>
        <button type="button" class="btn-remove-fecha" onclick="removeFecha(this)">
            <i class="fas fa-times"></i> Eliminar
        </button>
    `;
    
    container.appendChild(newGroup);
    fechaIndex++;
    
    // Agregar listeners para cálculo automático
    setupCalculoHoras(newGroup);
    
    // Mostrar botones de eliminar si hay más de uno
    updateRemoveButtons();
}

function removeFecha(button) {
    const group = button.closest('.fecha-hora-group');
    group.remove();
    updateRemoveButtons();
}

function updateRemoveButtons() {
    const groups = document.querySelectorAll('.fecha-hora-group');
    groups.forEach(group => {
        const btnRemove = group.querySelector('.btn-remove-fecha');
        if (btnRemove) {
            btnRemove.style.display = groups.length > 1 ? 'block' : 'none';
        }
    });
}

function calcularHoras(horaDesde, horaHasta) {
    if (!horaDesde || !horaHasta) return '';
    
    const [h1, m1] = horaDesde.split(':').map(Number);
    const [h2, m2] = horaHasta.split(':').map(Number);
    
    let minutosDesde = h1 * 60 + m1;
    let minutosHasta = h2 * 60 + m2;
    
    // Si la hora hasta es menor que desde, asumir que es al día siguiente
    if (minutosHasta < minutosDesde) {
        minutosHasta += 24 * 60;
    }
    
    const diffMinutos = minutosHasta - minutosDesde;
    const horas = Math.floor(diffMinutos / 60);
    const minutos = diffMinutos % 60;
    
    return String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0') + ':00';
}

function setupCalculoHoras(group) {
    const horaDesde = group.querySelector('.hora-desde-input');
    const horaHasta = group.querySelector('.hora-hasta-input');
    const horasTotales = group.querySelector('.horas-totales-input');
    
    function actualizar() {
        horasTotales.value = calcularHoras(horaDesde.value, horaHasta.value);
    }
    
    horaDesde.addEventListener('change', actualizar);
    horaHasta.addEventListener('change', actualizar);
    horaDesde.addEventListener('input', actualizar);
    horaHasta.addEventListener('input', actualizar);
}

// Inicializar cálculo de horas para el primer grupo
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.fecha-hora-group').forEach(setupCalculoHoras);
    updateRemoveButtons();
    
    // Validación del formulario
    document.getElementById('formJornada').addEventListener('submit', function(e) {
        const grupos = document.querySelectorAll('.fecha-hora-group');
        let hayErrores = false;
        
        grupos.forEach(group => {
            const fecha = group.querySelector('.fecha-input').value;
            const horaDesde = group.querySelector('.hora-desde-input').value;
            const horaHasta = group.querySelector('.hora-hasta-input').value;
            
            if (horaDesde && horaHasta && horaHasta <= horaDesde) {
                alert('La hora hasta debe ser mayor que la hora desde');
                hayErrores = true;
            }
        });
        
        if (hayErrores) {
            e.preventDefault();
        }
    });
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
