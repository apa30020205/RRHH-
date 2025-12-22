<?php
/**
 * Formulario de Misión Oficial
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Misión Oficial - Sistema RRHH';

// Obtener usuario actual para registro
$usuarioId = $_SESSION['id_usuario'] ?? null;

// Variables para mostrar datos
$funcionario = null;
$busqueda = '';
$misiones = [];

// Procesar búsqueda de funcionario
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Si viene cédula por GET (del filtro o enlace antiguo), usarla como búsqueda
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
        
        // Si se encontró, guardar la cédula para usar después
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

// Variable para almacenar misiones acumuladas (se calculará después si hay funcionario)
$misionesAcumuladasMostrar = null;

if ($funcionario) {
    try {
        $db = Database::getInstance()->getConnection();
        $cedulaBD = $funcionario['cedula'];
        
        // Cargar misiones para la tabla (aplicar filtro de fechas si existe)
        $sql = "SELECT id_mision, fecha, hora_desde, hora_hasta, horas_totales, 
                       motivo, fecha_registro, estado
                FROM mision_oficial
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
        
        $stmtMisiones = $db->prepare($sql);
        $stmtMisiones->execute($params);
        $misiones = $stmtMisiones->fetchAll();
        
        // Calcular sumatoria de TODAS las misiones activas (sin filtro de fechas)
        $stmtTodasMisiones = $db->prepare("
            SELECT horas_totales
            FROM mision_oficial
            WHERE cedula = ? AND estado = 'activa'
        ");
        $stmtTodasMisiones->execute([$cedulaBD]);
        $todasMisiones = $stmtTodasMisiones->fetchAll();
        
        // Calcular sumatoria de minutos totales (sin redondear)
        $totalMinutosCalculado = 0;
        foreach ($todasMisiones as $mision) {
            if (!empty($mision['horas_totales'])) {
                $partes = explode(':', $mision['horas_totales']);
                $horas = (int)($partes[0] ?? 0);
                $minutos = (int)($partes[1] ?? 0);
                // Sumar minutos totales (sin redondear)
                $totalMinutosCalculado += ($horas * 60) + $minutos;
            }
        }
        
        // Obtener valor guardado en funcionarios si existe
        $stmtMisionesAcum = $db->prepare("
            SELECT mision_oficial_acumuladas 
            FROM funcionarios 
            WHERE cedula = ?
        ");
        $stmtMisionesAcum->execute([$cedulaBD]);
        $resultadoMisiones = $stmtMisionesAcum->fetch();
        $misionesAcumuladasGuardadas = null;
        
        if (!empty($resultadoMisiones['mision_oficial_acumuladas'])) {
            // Si viene como TIME, mantener como string HH:MM para mostrar
            $timeValue = $resultadoMisiones['mision_oficial_acumuladas'];
            if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                // Mantener el formato HH:MM para mostrar
                $misionesAcumuladasGuardadas = substr($timeValue, 0, 5); // Toma HH:MM
            } else {
                // Si es un número, convertir a HH:MM
                $horasTotales = (int)$timeValue;
                $misionesAcumuladasGuardadas = sprintf('%02d:00', $horasTotales);
            }
        }
        
        // Si no hay valor guardado, calcular desde las misiones (convertir minutos a HH:MM)
        if ($misionesAcumuladasGuardadas === null && $totalMinutosCalculado > 0) {
            $horasTotales = (int)($totalMinutosCalculado / 60);
            $minutosRestantes = $totalMinutosCalculado % 60;
            $misionesAcumuladasMostrar = sprintf('%02d:%02d', $horasTotales, $minutosRestantes);
        } else {
            $misionesAcumuladasMostrar = $misionesAcumuladasGuardadas ?? '00:00';
        }
    } catch (Exception $e) {
        mostrarMensaje("Error al procesar misiones: " . $e->getMessage(), 'error');
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Misión Oficial</h2>
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
    .mision-container {
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
        color: #dc3545;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #dc3545;
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
    
    .form-group input[type="date"],
    .form-group input[type="time"],
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
    
    .fecha-hora-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    .misiones-list {
        margin-top: 2rem;
    }
    
    .misiones-list h3 {
        color: #dc3545;
        margin-bottom: 1rem;
    }
    
    .misiones-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        margin-top: 1rem;
    }
    
    .misiones-table th {
        background: #dc3545;
        color: white;
        padding: 0.75rem;
        text-align: left;
        border: 1px solid #c82333;
    }
    
    .misiones-table td {
        padding: 0.75rem;
        border: 1px solid #dee2e6;
    }
    
    .misiones-table tr:nth-child(even) {
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
        background: #ffe6e6;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #dc3545;
    }
    
    .funcionario-info strong {
        color: #c82333;
    }
    
    .btn-primary {
        background: #dc3545;
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
        background: #c82333;
    }
    
    .btn {
        background: #dc3545;
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
        background: #c82333;
    }
</style>

<!-- Sección de Búsqueda de Funcionario -->
<div class="mision-container">
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
<div class="mision-container">
    <form method="POST" action="<?php echo BASE_URL; ?>/forms/permisos/procesar_mision_oficial.php" id="formMision">
        <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
        
        <div class="form-section">
            <h3>Detalles de la Misión Oficial</h3>
            <div class="fecha-hora-row">
                <div class="form-group">
                    <label for="fecha">Fecha en que realizará la misión oficial *</label>
                    <input type="date" id="fecha" name="fecha" required>
                </div>
                <div class="form-group">
                    <label for="hora_desde">Desde (Hora) *</label>
                    <input type="time" id="hora_desde" name="hora_desde" required>
                </div>
                <div class="form-group">
                    <label for="hora_hasta">Hasta (Hora) *</label>
                    <input type="time" id="hora_hasta" name="hora_hasta" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="motivo">Motivo *</label>
                <textarea id="motivo" name="motivo" rows="5" required 
                          placeholder="Describa el motivo de la misión oficial..."></textarea>
            </div>
        </div>
        
        <div class="form-actions" style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Misión Oficial
            </button>
            <button type="reset" class="btn">Limpiar</button>
        </div>
    </form>
</div>

<!-- Listado de Misiones Registradas -->
<div class="mision-container misiones-list">
    <h3>Misiones Oficiales Registradas</h3>
    
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
    
    <?php if (count($misiones) > 0): ?>
        <table class="misiones-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora Desde</th>
                    <th>Hora Hasta</th>
                    <th>Horas Totales</th>
                    <th>Motivo</th>
                    <th>Fecha Registro</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($misiones as $mision): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($mision['fecha'])); ?></td>
                        <td>
                            <?php 
                            if ($mision['hora_desde']) {
                                $hora = new DateTime($mision['hora_desde']);
                                $horaFormato = $hora->format('g:i');
                                $ampm = strtolower($hora->format('A'));
                                $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                echo $horaFormato . ' ' . $ampm;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($mision['hora_hasta']) {
                                $hora = new DateTime($mision['hora_hasta']);
                                $horaFormato = $hora->format('g:i');
                                $ampm = strtolower($hora->format('A'));
                                $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                echo $horaFormato . ' ' . $ampm;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><strong>
                            <?php 
                            if (!empty($mision['horas_totales'])) {
                                // Formatear para mostrar solo HH:MM (sin segundos)
                                $horasTotales = $mision['horas_totales'];
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
                        <td><?php echo htmlspecialchars(substr($mision['motivo'], 0, 50)); ?><?php echo strlen($mision['motivo']) > 50 ? '...' : ''; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($mision['fecha_registro'])); ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/forms/permisos/eliminar_mision_oficial.php?id_mision=<?php echo $mision['id_mision']; ?>&cedula=<?php echo urlencode($funcionario['cedula'] ?? ''); ?>&fecha_desde=<?php echo urlencode($fechaDesdeFiltro ?? ''); ?>&fecha_hasta=<?php echo urlencode($fechaHastaFiltro ?? ''); ?>" 
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
        <p>No hay misiones oficiales registradas para este funcionario.</p>
    <?php endif; ?>
    
    <!-- Sumatoria de Misiones Oficiales Acumuladas - Debajo de la tabla -->
    <?php if ($funcionario): ?>
    <div style="margin-top: 1rem; color: #666; display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
        <div>
            <strong>Misiones Oficiales Acumuladas:</strong>
            <span id="misiones-acumuladas-display" 
                  style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; color: #dc3545; cursor: pointer; padding: 0.25rem 0.5rem; border-bottom: 1px dashed #dc3545;" 
                  onmouseover="this.style.background='#ffe6e6';" 
                  onmouseout="this.style.background='transparent';"
                  onclick="editarMisionesAcumuladas()"
                  title="Click para editar (formato HH:MM o solo número de horas)">
                <?php echo htmlspecialchars($misionesAcumuladasMostrar ?? '00:00'); ?>
            </span>
            <input type="text" 
                   id="misiones-acumuladas-input" 
                   value="<?php echo htmlspecialchars($misionesAcumuladasMostrar ?? '00:00'); ?>"
                   pattern="[0-9]{1,3}:[0-5][0-9]|[0-9]+"
                   placeholder="HH:MM o número de horas"
                   style="display: none; font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; padding: 0.25rem 0.5rem; border: 1px solid #dc3545; border-radius: 3px; width: 120px; text-align: center;"
                   onblur="guardarMisionesAcumuladas()"
                   onkeydown="if(event.key === 'Enter') { event.preventDefault(); guardarMisionesAcumuladas(); } if(event.key === 'Escape') { cancelarEdicion(); }">
            <button id="btn-guardar-misiones" 
                    onclick="guardarMisionesAcumuladas()" 
                    style="display: none; margin-left: 0.5rem; padding: 0.25rem 0.75rem; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.9em;">
                Guardar
            </button>
            <span id="mensaje-misiones" style="color: #28a745; font-weight: bold; margin-left: 0.5rem;"></span>
        </div>
    </div>
    <input type="hidden" id="cedula-funcionario" value="<?php echo htmlspecialchars($funcionario['cedula'], ENT_QUOTES); ?>">
    <input type="hidden" id="misiones-acumuladas-original" value="<?php echo htmlspecialchars($misionesAcumuladasMostrar ?? '00:00'); ?>">
    <?php endif; ?>
</div>

<script>
// Funciones para editar misiones acumuladas
function editarMisionesAcumuladas() {
    const display = document.getElementById('misiones-acumuladas-display');
    const input = document.getElementById('misiones-acumuladas-input');
    const btnGuardar = document.getElementById('btn-guardar-misiones');
    const mensaje = document.getElementById('mensaje-misiones');
    
    display.style.display = 'none';
    input.style.display = 'inline-block';
    btnGuardar.style.display = 'inline-block';
    mensaje.textContent = '';
    input.focus();
    input.select();
}

function cancelarEdicion() {
    const display = document.getElementById('misiones-acumuladas-display');
    const input = document.getElementById('misiones-acumuladas-input');
    const btnGuardar = document.getElementById('btn-guardar-misiones');
    const original = document.getElementById('misiones-acumuladas-original');
    const mensaje = document.getElementById('mensaje-misiones');
    
    // Restaurar valor original
    input.value = original.value;
    
    display.style.display = 'inline';
    input.style.display = 'none';
    btnGuardar.style.display = 'none';
    mensaje.textContent = '';
}

function guardarMisionesAcumuladas() {
    const input = document.getElementById('misiones-acumuladas-input');
    const display = document.getElementById('misiones-acumuladas-display');
    const btnGuardar = document.getElementById('btn-guardar-misiones');
    const cedula = document.getElementById('cedula-funcionario').value;
    const mensaje = document.getElementById('mensaje-misiones');
    
    // Obtener valor y limpiar
    let horasValue = input.value.trim();
    
    // Si es solo un número, convertirlo a formato HH:MM (ej: "5" -> "05:00")
    const regexSoloNumero = /^([0-9]+)$/;
    if (regexSoloNumero.test(horasValue)) {
        const horas = parseInt(horasValue);
        if (horas > 838) {
            alert('El valor de horas no puede exceder 838 horas (límite de MySQL TIME)');
            input.focus();
            return;
        }
        horasValue = String(horas).padStart(2, '0') + ':00';
    }
    
    // Validar formato HH:MM
    const regexHoras = /^([0-9]{1,3}):([0-5][0-9])$/;
    
    if (!regexHoras.test(horasValue)) {
        alert('Por favor ingrese un formato válido (HH:MM o solo número de horas). Ejemplo: 33:30 o 5');
        input.focus();
        // Restaurar botón en caso de error
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
        // Restaurar botón en caso de error
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
    fetch('<?php echo BASE_URL; ?>/forms/permisos/actualizar_mision_oficial_acumuladas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            cedula: cedula,
            mision_oficial_acumuladas: horasFormatoTime
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar display con nuevo valor (formato HH:MM)
            display.textContent = horasValue;
            
            // Actualizar valor original
            document.getElementById('misiones-acumuladas-original').value = horasValue;
            
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
        alert('Error al guardar las misiones acumuladas');
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
    });
}

// Validación del formulario
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('formMision').addEventListener('submit', function(e) {
        const horaDesde = document.getElementById('hora_desde').value;
        const horaHasta = document.getElementById('hora_hasta').value;
        
        if (horaDesde && horaHasta && horaHasta <= horaDesde) {
            alert('La hora hasta debe ser mayor que la hora desde');
            e.preventDefault();
        }
    });
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

