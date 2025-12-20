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
$busqueda = '';
$jornadas = [];

// Procesar búsqueda de funcionario
// Permitir búsqueda por cédula, nombre o apellido
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Si viene cédula por GET (del filtro o enlace antiguo), usarla como búsqueda
if (empty($busqueda) && isset($_GET['cedula']) && !empty($_GET['cedula'])) {
    $busqueda = trim($_GET['cedula']);
}

if (!empty($busqueda)) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar funcionario - por cédula, nombre o apellido (como en horario_manual)
        $stmt = $db->prepare("
            SELECT cedula, nombre, apellido FROM funcionarios 
            WHERE cedula LIKE ? OR nombre LIKE ? OR apellido LIKE ?
            LIMIT 1
        ");
        $busquedaLike = '%' . $busqueda . '%';
        $stmt->execute([$busquedaLike, $busquedaLike, $busquedaLike]);
        $funcionario = $stmt->fetch();
        
        // Si se encontró, guardar la cédula para usar después
        if ($funcionario) {
            $cedulaBD = $funcionario['cedula']; // Usar la cédula exacta de la BD
        }
    } catch (Exception $e) {
        mostrarMensaje("Error al buscar funcionario: " . $e->getMessage(), 'error');
    }
}

// Procesar filtro de fechas para listado
$fechaDesdeFiltro = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fechaHastaFiltro = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Procesar filtro de fechas y cargar jornadas para mostrar en tabla
// Variable para almacenar horas acumuladas (se calculará después si hay funcionario)
$horasAcumuladasMostrar = null;

if ($funcionario) {
    try {
        $db = Database::getInstance()->getConnection();
        $cedulaBD = $funcionario['cedula'];
        
        // Cargar jornadas para la tabla (aplicar filtro de fechas si existe)
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
        
        // Calcular sumatoria de TODAS las horas extraordinarias activas (sin filtro de fechas)
        $stmtTodasJornadas = $db->prepare("
            SELECT horas_totales
            FROM jornada_extraordinaria
            WHERE cedula = ? AND estado = 'activa'
        ");
        $stmtTodasJornadas->execute([$cedulaBD]);
        $todasJornadas = $stmtTodasJornadas->fetchAll();
        
        // Calcular sumatoria de minutos totales (sin redondear)
        $totalMinutosCalculado = 0;
        foreach ($todasJornadas as $jornada) {
            if (!empty($jornada['horas_totales'])) {
                $partes = explode(':', $jornada['horas_totales']);
                $horas = (int)($partes[0] ?? 0);
                $minutos = (int)($partes[1] ?? 0);
                // Sumar minutos totales (sin redondear)
                $totalMinutosCalculado += ($horas * 60) + $minutos;
            }
        }
        
        // Obtener valor guardado en funcionarios si existe (convertir de TIME a minutos)
        $stmtHorasAcum = $db->prepare("
            SELECT horas_extraordinarias_acumuladas 
            FROM funcionarios 
            WHERE cedula = ?
        ");
        $stmtHorasAcum->execute([$cedulaBD]);
        $resultadoHoras = $stmtHorasAcum->fetch();
        $horasAcumuladasGuardadas = null;
        
        if (!empty($resultadoHoras['horas_extraordinarias_acumuladas'])) {
            // Si viene como TIME, mantener como string HH:MM para mostrar
            $timeValue = $resultadoHoras['horas_extraordinarias_acumuladas'];
            if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                // Mantener el formato HH:MM para mostrar
                $horasAcumuladasGuardadas = substr($timeValue, 0, 5); // Toma HH:MM
            } else {
                // Si es un número, convertir a HH:MM
                $horasTotales = (int)$timeValue;
                $horasAcumuladasGuardadas = sprintf('%02d:00', $horasTotales);
            }
        }
        
        // Si no hay valor guardado, calcular desde las jornadas (convertir minutos a HH:MM)
        if ($horasAcumuladasGuardadas === null && $totalMinutosCalculado > 0) {
            $horasTotales = (int)($totalMinutosCalculado / 60);
            $minutosRestantes = $totalMinutosCalculado % 60;
            $horasAcumuladasMostrar = sprintf('%02d:%02d', $horasTotales, $minutosRestantes);
        } else {
            $horasAcumuladasMostrar = $horasAcumuladasGuardadas ?? '00:00';
        }
    } catch (Exception $e) {
        mostrarMensaje("Error al procesar jornadas: " . $e->getMessage(), 'error');
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
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jornadas as $jornada): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($jornada['fecha'])); ?></td>
                        <td>
                            <?php 
                            if ($jornada['hora_desde']) {
                                $hora = new DateTime($jornada['hora_desde']);
                                // Formato 12 horas con a.m./p.m.
                                $horaFormato = $hora->format('g:i');
                                $ampm = strtolower($hora->format('A')); // am o pm
                                $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                echo $horaFormato . ' ' . $ampm;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($jornada['hora_hasta']) {
                                $hora = new DateTime($jornada['hora_hasta']);
                                // Formato 12 horas con a.m./p.m.
                                $horaFormato = $hora->format('g:i');
                                $ampm = strtolower($hora->format('A')); // am o pm
                                $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                echo $horaFormato . ' ' . $ampm;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><strong>
                            <?php 
                            if (!empty($jornada['horas_totales'])) {
                                // Formatear para mostrar solo HH:MM (sin segundos)
                                $horasTotales = $jornada['horas_totales'];
                                if (strlen($horasTotales) >= 5) {
                                    echo substr($horasTotales, 0, 5); // Toma solo HH:MM
                                } else {
                                    echo $horasTotales;
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </strong></td>
                        <td><?php echo htmlspecialchars(substr($jornada['justificacion'], 0, 50)); ?><?php echo strlen($jornada['justificacion']) > 50 ? '...' : ''; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($jornada['fecha_registro'])); ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/forms/permisos/eliminar_jornada_extraordinaria.php?id_jornada=<?php echo $jornada['id_jornada']; ?>&cedula=<?php echo urlencode($funcionario['cedula'] ?? ''); ?>&fecha_desde=<?php echo urlencode($fechaDesdeFiltro ?? ''); ?>&fecha_hasta=<?php echo urlencode($fechaHastaFiltro ?? ''); ?>" 
                               style="color: #dc3545; text-decoration: none;"
                               onclick="return confirm('¿Eliminarás registro sí o no?')">
                                <i class="fas fa-trash" style="color: #dc3545;"></i> <span style="color: #dc3545;">Eliminar</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay jornadas extraordinarias registradas para este funcionario.</p>
    <?php endif; ?>
    
    <!-- Sumatoria de Horas Extraordinarias Acumuladas - Debajo de la tabla -->
    <?php if ($funcionario): ?>
    <div style="margin-top: 1rem; color: #666; display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
        <div>
            <strong>Horas Extraordinarias Acumuladas:</strong>
            <span id="horas-acumuladas-display" 
                  style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; color: #1976D2; cursor: pointer; padding: 0.25rem 0.5rem; border-bottom: 1px dashed #1976D2;" 
                  onmouseover="this.style.background='#e3f2fd';" 
                  onmouseout="this.style.background='transparent';"
                  onclick="editarHorasAcumuladas()"
                  title="Click para editar (formato HH:MM o solo número de horas)">
                <?php echo htmlspecialchars($horasAcumuladasMostrar ?? '00:00'); ?>
            </span>
            <input type="text" 
                   id="horas-acumuladas-input" 
                   value="<?php echo htmlspecialchars($horasAcumuladasMostrar ?? '00:00'); ?>"
                   pattern="[0-9]{1,3}:[0-5][0-9]|[0-9]+"
                   placeholder="HH:MM o número"
                   style="display: none; font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; padding: 0.25rem 0.5rem; border: 1px solid #2196F3; border-radius: 3px; width: 100px; text-align: center;"
                   onblur="guardarHorasAcumuladas()"
                   onkeydown="if(event.key === 'Enter') { event.preventDefault(); guardarHorasAcumuladas(); } if(event.key === 'Escape') cancelarEdicion();">
            <button id="btn-guardar-horas" 
                    onclick="guardarHorasAcumuladas()" 
                    style="display: none; margin-left: 0.5rem; padding: 0.25rem 0.75rem; background: #2196F3; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.9em;">
                Guardar
            </button>
            <span id="mensaje-horas" style="color: #28a745; font-weight: bold; margin-left: 0.5rem;"></span>
        </div>
    </div>
    <input type="hidden" id="cedula-funcionario" value="<?php echo htmlspecialchars($funcionario['cedula'], ENT_QUOTES); ?>">
    <input type="hidden" id="horas-acumuladas-original" value="<?php echo htmlspecialchars($horasAcumuladasMostrar ?? '00:00'); ?>">
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

// Funciones para editar horas acumuladas
function editarHorasAcumuladas() {
    const display = document.getElementById('horas-acumuladas-display');
    const input = document.getElementById('horas-acumuladas-input');
    const btnGuardar = document.getElementById('btn-guardar-horas');
    const mensaje = document.getElementById('mensaje-horas');
    
    display.style.display = 'none';
    input.style.display = 'inline-block';
    btnGuardar.style.display = 'inline-block';
    mensaje.textContent = '';
    input.focus();
    input.select();
}

function cancelarEdicion() {
    const display = document.getElementById('horas-acumuladas-display');
    const input = document.getElementById('horas-acumuladas-input');
    const btnGuardar = document.getElementById('btn-guardar-horas');
    const original = document.getElementById('horas-acumuladas-original');
    const mensaje = document.getElementById('mensaje-horas');
    
    // Restaurar valor original
    input.value = original.value;
    
    display.style.display = 'inline';
    input.style.display = 'none';
    btnGuardar.style.display = 'none';
    mensaje.textContent = '';
}

function guardarHorasAcumuladas() {
    const input = document.getElementById('horas-acumuladas-input');
    const display = document.getElementById('horas-acumuladas-display');
    const btnGuardar = document.getElementById('btn-guardar-horas');
    const cedula = document.getElementById('cedula-funcionario').value;
    const mensaje = document.getElementById('mensaje-horas');
    
    // Obtener valor y limpiar
    let horasValue = input.value.trim();
    
    // Si es solo un número, convertirlo a formato HH:MM (ej: "5" -> "05:00")
    const regexSoloNumero = /^([0-9]+)$/;
    if (regexSoloNumero.test(horasValue)) {
        const horas = parseInt(horasValue);
        if (horas > 838) {
            alert('El valor de horas no puede exceder 838 horas (límite de MySQL TIME)');
            input.focus();
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
            return;
        }
        horasValue = String(horas).padStart(2, '0') + ':00';
    }
    
    // Validar formato HH:MM
    const regexHoras = /^([0-9]{1,3}):([0-5][0-9])$/;
    
    if (!regexHoras.test(horasValue)) {
        alert('Por favor ingrese un formato válido (HH:MM o solo número de horas). Ejemplo: 33:30 o 5');
        input.focus();
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
        return;
    }
    
    // Validar que horas no exceda 838 (límite de TIME en MySQL)
    const partes = horasValue.split(':');
    const horas = parseInt(partes[0]);
    const minutos = parseInt(partes[1]);
    
    if (horas > 838) {
        alert('El valor de horas no puede exceder 838 horas (límite de MySQL TIME)');
        input.focus();
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
        return;
    }
    
    // Deshabilitar botón durante la petición
    btnGuardar.disabled = true;
    btnGuardar.textContent = 'Guardando...';
    
    // Convertir a formato TIME (HH:MM:SS) para guardar en BD
    const horasFormatoTime = String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0') + ':00';
    
    // Enviar petición AJAX
    fetch('<?php echo BASE_URL; ?>/forms/permisos/actualizar_horas_extraordinarias_acumuladas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            cedula: cedula,
            horas_acumuladas: horasFormatoTime
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar display con nuevo valor (formato HH:MM)
            display.textContent = horasValue;
            
            // Actualizar valor original
            document.getElementById('horas-acumuladas-original').value = horasValue;
            
            // Actualizar input también
            input.value = horasValue;
            
            // Volver a modo lectura
            display.style.display = 'inline';
            input.style.display = 'none';
            btnGuardar.style.display = 'none';
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
            
            // Mostrar mensaje de éxito
            mensaje.textContent = '✓ Guardado';
            mensaje.style.color = '#28a745';
            
            // Ocultar mensaje después de 2 segundos
            setTimeout(() => {
                mensaje.textContent = '';
            }, 2000);
        } else {
            alert('Error al guardar: ' + (data.error || 'Error desconocido'));
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al guardar las horas acumuladas');
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
    });
}
</script>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>






