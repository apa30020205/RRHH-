<?php
/**
 * Formulario de Tiempo Compensatorio
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Tiempo Compensatorio - Sistema RRHH';

// Variables para mostrar datos
$funcionario = null;
$busqueda = '';
$tiempoCompensatorio = [];

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

// Variables para acumulados
$tiempoCompHorasAcumuladasMostrar = '00:00';
$tiempoCompDiasAcumuladosMostrar = 0;

if ($funcionario) {
    try {
        $db = Database::getInstance()->getConnection();
        $cedulaBD = $funcionario['cedula'];
        
        // Cargar tiempo compensatorio para la tabla (aplicar filtro de fechas si existe)
        $sql = "SELECT id_tiempo_comp, horas, dias, fecha_uso, fecha_registro, estado
                FROM tiempo_compensatorio
                WHERE cedula = ? AND estado = 'activa'";
        $params = [$cedulaBD];
        
        if (!empty($fechaDesdeFiltro)) {
            $sql .= " AND fecha_uso >= ?";
            $params[] = $fechaDesdeFiltro;
        }
        
        if (!empty($fechaHastaFiltro)) {
            $sql .= " AND fecha_uso <= ?";
            $params[] = $fechaHastaFiltro;
        }
        
        $sql .= " ORDER BY fecha_uso DESC, fecha_registro DESC";
        
        $stmtTiempoComp = $db->prepare($sql);
        $stmtTiempoComp->execute($params);
        $tiempoCompensatorio = $stmtTiempoComp->fetchAll();
        
        // Obtener acumulados del funcionario
        $stmtAcum = $db->prepare("
            SELECT tiempo_compensatorio_horas_acumuladas, tiempo_compensatorio_dias_acumulados
            FROM funcionarios 
            WHERE cedula = ?
        ");
        $stmtAcum->execute([$cedulaBD]);
        $resultadoAcum = $stmtAcum->fetch();
        
        if (!empty($resultadoAcum['tiempo_compensatorio_horas_acumuladas'])) {
            $timeValue = $resultadoAcum['tiempo_compensatorio_horas_acumuladas'];
            // MySQL TIME puede venir como string en formato 'HH:MM:SS' o '-HH:MM:SS' para negativos
            if (is_string($timeValue)) {
                // Detectar si es negativo (comienza con '-')
                $esNegativo = (strpos($timeValue, '-') === 0);
                $timeValueSinSigno = $esNegativo ? substr($timeValue, 1) : $timeValue;
                
                if (strpos($timeValueSinSigno, ':') !== false) {
                    $prefijo = $esNegativo ? '-' : '';
                    $tiempoCompHorasAcumuladasMostrar = $prefijo . substr($timeValueSinSigno, 0, 5);
                } else {
                    $horasTotales = (int)$timeValueSinSigno;
                    if ($esNegativo) {
                        $horasTotales = -$horasTotales;
                    }
                    $signo = $horasTotales < 0 ? '-' : '';
                    $tiempoCompHorasAcumuladasMostrar = $signo . sprintf('%02d:00', abs($horasTotales));
                }
            } else {
                $horasTotales = (int)$timeValue;
                $signo = $horasTotales < 0 ? '-' : '';
                $tiempoCompHorasAcumuladasMostrar = $signo . sprintf('%02d:00', abs($horasTotales));
            }
        }
        
        $tiempoCompDiasAcumuladosMostrar = (int)($resultadoAcum['tiempo_compensatorio_dias_acumulados'] ?? 0);
    } catch (Exception $e) {
        mostrarMensaje("Error al procesar tiempo compensatorio: " . $e->getMessage(), 'error');
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Tiempo Compensatorio</h2>
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
    .tiempo-comp-container {
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
        color: #ff9800;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #ff9800;
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
    
    .form-group input[type="number"],
    .form-group input[type="date"] {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 1rem;
    }
    
    .contenedores-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-top: 1rem;
    }
    
    .contenedor-tiempo,
    .contenedor-fecha {
        background: #fff3e0;
        padding: 1.5rem;
        border-radius: 8px;
        border-left: 4px solid #ff9800;
    }
    
    .contenedor-tiempo h4,
    .contenedor-fecha h4 {
        color: #e65100;
        margin-bottom: 1rem;
        font-weight: bold;
    }
    
    .horas-dias-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .tiempo-comp-list {
        margin-top: 2rem;
    }
    
    .tiempo-comp-list h3 {
        color: #ff9800;
        margin-bottom: 1rem;
    }
    
    .tiempo-comp-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        margin-top: 1rem;
    }
    
    .tiempo-comp-table th {
        background: #ff9800;
        color: white;
        padding: 0.75rem;
        text-align: left;
        border: 1px solid #f57c00;
    }
    
    .tiempo-comp-table td {
        padding: 0.75rem;
        border: 1px solid #dee2e6;
    }
    
    .tiempo-comp-table tr:nth-child(even) {
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
        background: #fff3e0;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #ff9800;
    }
    
    .funcionario-info strong {
        color: #e65100;
    }
    
    .btn-primary {
        background: #ff9800;
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
        background: #f57c00;
    }
    
    .btn {
        background: #ff9800;
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
        background: #f57c00;
    }
    
    .acumulado-section {
        margin-top: 1.5rem;
        padding: 1rem;
        background: #fff3e0;
        border-radius: 6px;
        border-left: 4px solid #ff9800;
    }
    
    .acumulado-section h4 {
        color: #e65100;
        margin-bottom: 1rem;
        font-weight: bold;
    }
    
    .acumulado-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
</style>

<!-- Sección de Búsqueda de Funcionario -->
<div class="tiempo-comp-container">
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
<div class="tiempo-comp-container">
    <form method="POST" action="<?php echo BASE_URL; ?>/forms/permisos/procesar_tiempo_compensatorio.php" id="formTiempoComp">
        <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
        
        <div class="form-section">
            <h3>Tiempo Solicitado</h3>
            
            <div class="contenedores-row">
                <div class="contenedor-tiempo">
                    <h4>Tiempo Compensatorio</h4>
                    <div class="horas-dias-row">
                        <div class="form-group">
                            <label for="horas">Horas</label>
                            <input type="number" id="horas" name="horas" 
                                   min="0" max="23" value="0" required>
                        </div>
                        <div class="form-group">
                            <label for="dias">Días</label>
                            <input type="number" id="dias" name="dias" 
                                   min="0" max="99" value="0" required>
                        </div>
                    </div>
                </div>
                
                <div class="contenedor-fecha">
                    <h4>Fecha de Uso</h4>
                    <p style="margin-bottom: 0.5rem; color: #555; font-size: 0.9em;">Fecha en que hace uso del tiempo *</p>
                    <div class="form-group">
                        <input type="date" id="fecha_uso" name="fecha_uso" required>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-actions" style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Tiempo Compensatorio
            </button>
            <button type="reset" class="btn">Limpiar</button>
        </div>
    </form>
    
    <!-- Sección de Acumulados -->
    <div class="acumulado-section">
        <h4>Tiempo Compensatorio Acumulado</h4>
        <div class="acumulado-row">
            <div>
                <label><strong>Horas Acumuladas:</strong></label>
                <span id="horas-acumuladas-display" 
                      style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; color: #ff9800; cursor: pointer; padding: 0.25rem 0.5rem; border-bottom: 1px dashed #ff9800;" 
                      onmouseover="this.style.background='#fff3e0';" 
                      onmouseout="this.style.background='transparent';"
                      onclick="editarHorasAcumuladas()"
                      title="Click para editar (formato HH:MM o solo número de horas)">
                    <?php echo htmlspecialchars($tiempoCompHorasAcumuladasMostrar); ?>
                </span>
                <input type="text" 
                       id="horas-acumuladas-input" 
                       value="<?php echo htmlspecialchars($tiempoCompHorasAcumuladasMostrar); ?>"
                       pattern="[0-9]{1,3}:[0-5][0-9]|[0-9]+"
                       placeholder="HH:MM o número de horas"
                       style="display: none; font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; padding: 0.25rem 0.5rem; border: 1px solid #ff9800; border-radius: 3px; width: 120px; text-align: center;"
                       onblur="guardarHorasAcumuladas()"
                       onkeydown="if(event.key === 'Enter') { event.preventDefault(); guardarHorasAcumuladas(); } if(event.key === 'Escape') { cancelarEdicionHoras(); }">
                <button id="btn-guardar-horas" 
                        onclick="guardarHorasAcumuladas()" 
                        style="display: none; margin-left: 0.5rem; padding: 0.25rem 0.75rem; background: #ff9800; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.9em;">
                    Guardar
                </button>
                <span id="mensaje-horas" style="color: #28a745; font-weight: bold; margin-left: 0.5rem;"></span>
            </div>
            
            <div>
                <label><strong>Días Acumulados:</strong></label>
                <span id="dias-acumulados-display" 
                      style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; color: #ff9800; cursor: pointer; padding: 0.25rem 0.5rem; border-bottom: 1px dashed #ff9800;" 
                      onmouseover="this.style.background='#fff3e0';" 
                      onmouseout="this.style.background='transparent';"
                      onclick="editarDiasAcumulados()"
                      title="Click para editar">
                    <?php echo $tiempoCompDiasAcumuladosMostrar; ?>
                </span>
                <input type="number" 
                       id="dias-acumulados-input" 
                       value="<?php echo $tiempoCompDiasAcumuladosMostrar; ?>"
                       min="0"
                       style="display: none; font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; padding: 0.25rem 0.5rem; border: 1px solid #ff9800; border-radius: 3px; width: 100px; text-align: center;"
                       onblur="guardarDiasAcumulados()"
                       onkeydown="if(event.key === 'Enter') { event.preventDefault(); guardarDiasAcumulados(); } if(event.key === 'Escape') { cancelarEdicionDias(); }">
                <button id="btn-guardar-dias" 
                        onclick="guardarDiasAcumulados()" 
                        style="display: none; margin-left: 0.5rem; padding: 0.25rem 0.75rem; background: #ff9800; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.9em;">
                    Guardar
                </button>
                <span id="mensaje-dias" style="color: #28a745; font-weight: bold; margin-left: 0.5rem;"></span>
            </div>
        </div>
        <input type="hidden" id="cedula-funcionario" value="<?php echo htmlspecialchars($funcionario['cedula'], ENT_QUOTES); ?>">
        <input type="hidden" id="horas-acumuladas-original" value="<?php echo htmlspecialchars($tiempoCompHorasAcumuladasMostrar); ?>">
        <input type="hidden" id="dias-acumulados-original" value="<?php echo $tiempoCompDiasAcumuladosMostrar; ?>">
    </div>
</div>

<!-- Listado de Tiempo Compensatorio Registrado -->
<div class="tiempo-comp-container tiempo-comp-list">
    <h3>Tiempo Compensatorio Registrado</h3>
    
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
    
    <?php if (count($tiempoCompensatorio) > 0): ?>
        <table class="tiempo-comp-table">
            <thead>
                <tr>
                    <th>Fecha Uso</th>
                    <th>Horas</th>
                    <th>Días</th>
                    <th>Fecha Registro</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tiempoCompensatorio as $tiempoComp): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($tiempoComp['fecha_uso'])); ?></td>
                        <td><strong><?php echo $tiempoComp['horas']; ?></strong></td>
                        <td><strong><?php echo $tiempoComp['dias']; ?></strong></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($tiempoComp['fecha_registro'])); ?></td>
                        <td>
                            <?php if (Auth::isAdmin()): ?>
                            <a href="<?php echo BASE_URL; ?>/forms/permisos/eliminar_tiempo_compensatorio.php?id_tiempo_comp=<?php echo $tiempoComp['id_tiempo_comp']; ?>&cedula=<?php echo urlencode($funcionario['cedula'] ?? ''); ?>&fecha_desde=<?php echo urlencode($fechaDesdeFiltro ?? ''); ?>&fecha_hasta=<?php echo urlencode($fechaHastaFiltro ?? ''); ?>" 
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
        <p style="color: #666; padding: 1rem;">No hay tiempo compensatorio registrado.</p>
    <?php endif; ?>
</div>

<script>
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

function cancelarEdicionHoras() {
    const display = document.getElementById('horas-acumuladas-display');
    const input = document.getElementById('horas-acumuladas-input');
    const btnGuardar = document.getElementById('btn-guardar-horas');
    const original = document.getElementById('horas-acumuladas-original');
    const mensaje = document.getElementById('mensaje-horas');
    
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
    
    let horasValue = input.value.trim();
    
    // Detectar si es negativo
    const esNegativo = horasValue.startsWith('-');
    const valorSinSigno = esNegativo ? horasValue.substring(1) : horasValue;
    
    // Si es solo un número, convertirlo a formato H:MM, HH:MM o HHH:MM (según el número de dígitos)
    const regexSoloNumero = /^(-?[0-9]+)$/;
    if (regexSoloNumero.test(horasValue)) {
        const horas = parseInt(horasValue);
        const horasAbs = Math.abs(horas);
        if (horasAbs > 838) {
            alert('El valor de horas no puede exceder 838 horas (límite de MySQL TIME)');
            input.focus();
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
            return;
        }
        const signo = horas < 0 ? '-' : '';
        // No usar padStart para mantener el número de dígitos original (permite 1, 2 o 3 dígitos)
        horasValue = signo + String(horasAbs) + ':00';
    }
    
    // Validar formato H:MM, HH:MM o HHH:MM (permitir negativos y hasta 838 horas)
    const regexHoras = /^(-?[0-9]{1,3}):([0-5][0-9])$/;
    
    if (!regexHoras.test(horasValue)) {
        alert('Por favor ingrese un formato válido (H:MM, HH:MM, HHH:MM o solo número de horas). Ejemplo: 100:00, 33:30, -5:00 o 100');
        input.focus();
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
        return;
    }
    
    // Verificar límite absoluto
    const partes = horasValue.split(':');
    const horasParte = parseInt(partes[0]);
    const horasAbs = Math.abs(horasParte);
    if (horasAbs > 838) {
        alert('El valor de horas no puede exceder 838 horas (límite de MySQL TIME)');
        input.focus();
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
        return;
    }
    
    // Agregar segundos si no los tiene (verificar si tiene formato HH:MM o HHH:MM sin segundos)
    // Si solo tiene 2 partes (horas y minutos), agregar segundos
    if (partes.length === 2) {
        horasValue += ':00';
    }
    
    btnGuardar.disabled = true;
    btnGuardar.textContent = 'Guardando...';
    
    fetch('<?php echo BASE_URL; ?>/forms/permisos/actualizar_tiempo_compensatorio_acumulado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            cedula: cedula,
            horas_acumuladas: horasValue,
            tipo: 'horas'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Extraer HH:MM o HHH:MM (o -HH:MM/-HHH:MM si es negativo) para mostrar (eliminar los segundos)
            // Si tiene formato HH:MM:SS o HHH:MM:SS, tomar solo hasta el último ":"
            const partesCompletas = horasValue.split(':');
            const horasParaMostrar = partesCompletas.length === 3 
                ? partesCompletas[0] + ':' + partesCompletas[1] 
                : horasValue; // Si ya está en formato HH:MM, usarlo tal cual
            display.textContent = horasParaMostrar;
            document.getElementById('horas-acumuladas-original').value = horasParaMostrar;
            input.value = horasParaMostrar;
            display.style.display = 'inline';
            input.style.display = 'none';
            btnGuardar.style.display = 'none';
            mensaje.textContent = '✓ Guardado';
            setTimeout(() => { mensaje.textContent = ''; }, 2000);
        } else {
            alert('Error: ' + (data.error || 'No se pudo actualizar'));
            input.focus();
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al comunicarse con el servidor');
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
    });
}

// Funciones para editar días acumulados
function editarDiasAcumulados() {
    const display = document.getElementById('dias-acumulados-display');
    const input = document.getElementById('dias-acumulados-input');
    const btnGuardar = document.getElementById('btn-guardar-dias');
    const mensaje = document.getElementById('mensaje-dias');
    
    display.style.display = 'none';
    input.style.display = 'inline-block';
    btnGuardar.style.display = 'inline-block';
    mensaje.textContent = '';
    input.focus();
    input.select();
}

function cancelarEdicionDias() {
    const display = document.getElementById('dias-acumulados-display');
    const input = document.getElementById('dias-acumulados-input');
    const btnGuardar = document.getElementById('btn-guardar-dias');
    const original = document.getElementById('dias-acumulados-original');
    const mensaje = document.getElementById('mensaje-dias');
    
    input.value = original.value;
    
    display.style.display = 'inline';
    input.style.display = 'none';
    btnGuardar.style.display = 'none';
    mensaje.textContent = '';
}

function guardarDiasAcumulados() {
    const input = document.getElementById('dias-acumulados-input');
    const display = document.getElementById('dias-acumulados-display');
    const btnGuardar = document.getElementById('btn-guardar-dias');
    const cedula = document.getElementById('cedula-funcionario').value;
    const mensaje = document.getElementById('mensaje-dias');
    
    // Permitir valores negativos
    const diasValue = parseInt(input.value) || 0;
    
    btnGuardar.disabled = true;
    btnGuardar.textContent = 'Guardando...';
    
    fetch('<?php echo BASE_URL; ?>/forms/permisos/actualizar_tiempo_compensatorio_acumulado.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            cedula: cedula,
            dias_acumulados: diasValue,
            tipo: 'dias'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            display.textContent = diasValue;
            document.getElementById('dias-acumulados-original').value = diasValue;
            input.value = diasValue;
            display.style.display = 'inline';
            input.style.display = 'none';
            btnGuardar.style.display = 'none';
            mensaje.textContent = '✓ Guardado';
            setTimeout(() => { mensaje.textContent = ''; }, 2000);
        } else {
            alert('Error: ' + (data.error || 'No se pudo actualizar'));
            input.focus();
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al comunicarse con el servidor');
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
    });
}
</script>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

