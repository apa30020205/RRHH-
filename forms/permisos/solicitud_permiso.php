<?php
/**
 * Formulario de Solicitud de Permiso
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Solicitud de Permiso - Sistema RRHH';

// Obtener usuario actual para registro
$usuarioId = $_SESSION['id_usuario'] ?? null;

// Variables para mostrar datos
$funcionario = null;
$busqueda = '';
$permisos = [];

// Verificar si la columna permiso_justificado existe (debe estar disponible en todo el archivo)
$columnaPermisoJustificadoExiste = false;
try {
    $dbCheck = Database::getInstance()->getConnection();
    $stmtCheckColPermisoJustificado = $dbCheck->query("
        SELECT COUNT(*) as existe
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'permisos'
        AND COLUMN_NAME = 'permiso_justificado'
    ");
    $columnaPermisoJustificadoExiste = $stmtCheckColPermisoJustificado->fetch()['existe'] > 0;
} catch (Exception $e) {
    // Si hay error, asumir que la columna no existe
    $columnaPermisoJustificadoExiste = false;
}

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
            $cedulaBD = $funcionario['cedula']; // Usar la cédula exacta de la BD
        }
    } catch (Exception $e) {
        mostrarMensaje("Error al buscar funcionario: " . $e->getMessage(), 'error');
    }
}

// Procesar filtro de fechas para listado
$fechaDesdeFiltro = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$fechaHastaFiltro = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

// Variable para almacenar permisos acumulados (se calculará después si hay funcionario)
$permisosAcumuladosMostrar = null;
$permisosInjustificadosAcumuladosMostrar = null;

if ($funcionario) {
    try {
        $db = Database::getInstance()->getConnection();
        $cedulaBD = $funcionario['cedula'];
        
        // Cargar permisos para la tabla (aplicar filtro de fechas si existe)
        $sql = "SELECT id_permiso, motivo, especifique, fecha_desde, hora_desde, 
                       fecha_hasta, hora_hasta, horas_totales, fecha_registro, estado";
        if ($columnaPermisoJustificadoExiste) {
            $sql .= ", permiso_justificado";
        }
        $sql .= " FROM permisos
                WHERE cedula = ? AND estado = 'activa'";
        $params = [$cedulaBD];
        
        if (!empty($fechaDesdeFiltro)) {
            $sql .= " AND fecha_desde >= ?";
            $params[] = $fechaDesdeFiltro;
        }
        
        if (!empty($fechaHastaFiltro)) {
            $sql .= " AND fecha_hasta <= ?";
            $params[] = $fechaHastaFiltro;
        }
        
        $sql .= " ORDER BY fecha_desde DESC, fecha_registro DESC";
        
        $stmtPermisos = $db->prepare($sql);
        $stmtPermisos->execute($params);
        $permisos = $stmtPermisos->fetchAll();
        
        // Calcular sumatoria de TODOS los permisos activos (sin filtro de fechas)
        if ($columnaPermisoJustificadoExiste) {
            // Si la columna existe, separar justificados e injustificados
            $stmtTodosPermisosJustificados = $db->prepare("
                SELECT horas_totales
                FROM permisos
                WHERE cedula = ? AND estado = 'activa' AND permiso_justificado = 1
            ");
            $stmtTodosPermisosJustificados->execute([$cedulaBD]);
            $todosPermisosJustificados = $stmtTodosPermisosJustificados->fetchAll();
            
            // Calcular sumatoria de minutos totales justificados (sin redondear)
            $totalMinutosCalculado = 0;
            foreach ($todosPermisosJustificados as $permiso) {
                if (!empty($permiso['horas_totales'])) {
                    $partes = explode(':', $permiso['horas_totales']);
                    $horas = (int)($partes[0] ?? 0);
                    $minutos = (int)($partes[1] ?? 0);
                    $totalMinutosCalculado += ($horas * 60) + $minutos;
                }
            }
            
            // Calcular sumatoria de TODOS los permisos activos injustificados
            $stmtTodosPermisosInjustificados = $db->prepare("
                SELECT horas_totales
                FROM permisos
                WHERE cedula = ? AND estado = 'activa' AND permiso_justificado = 0
            ");
            $stmtTodosPermisosInjustificados->execute([$cedulaBD]);
            $todosPermisosInjustificados = $stmtTodosPermisosInjustificados->fetchAll();
            
            // Calcular sumatoria de minutos totales injustificados
            $totalMinutosCalculadoInjustificados = 0;
            foreach ($todosPermisosInjustificados as $permiso) {
                if (!empty($permiso['horas_totales'])) {
                    $partes = explode(':', $permiso['horas_totales']);
                    $horas = (int)($partes[0] ?? 0);
                    $minutos = (int)($partes[1] ?? 0);
                    $totalMinutosCalculadoInjustificados += ($horas * 60) + $minutos;
                }
            }
        } else {
            // Si la columna no existe, todos los permisos son justificados
            $stmtTodosPermisos = $db->prepare("
                SELECT horas_totales
                FROM permisos
                WHERE cedula = ? AND estado = 'activa'
            ");
            $stmtTodosPermisos->execute([$cedulaBD]);
            $todosPermisos = $stmtTodosPermisos->fetchAll();
            
            $totalMinutosCalculado = 0;
            foreach ($todosPermisos as $permiso) {
                if (!empty($permiso['horas_totales'])) {
                    $partes = explode(':', $permiso['horas_totales']);
                    $horas = (int)($partes[0] ?? 0);
                    $minutos = (int)($partes[1] ?? 0);
                    $totalMinutosCalculado += ($horas * 60) + $minutos;
                }
            }
            $totalMinutosCalculadoInjustificados = 0;
        }
        
        // Obtener valor guardado en funcionarios si existe
        $stmtPermisosAcum = $db->prepare("
            SELECT permisos_acumulados 
            FROM funcionarios 
            WHERE cedula = ?
        ");
        $stmtPermisosAcum->execute([$cedulaBD]);
        $resultadoPermisos = $stmtPermisosAcum->fetch();
        $permisosAcumuladosGuardados = null;
        
        if (!empty($resultadoPermisos['permisos_acumulados'])) {
            // Si viene como TIME, mantener como string HH:MM para mostrar
            $timeValue = $resultadoPermisos['permisos_acumulados'];
            if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                // Mantener el formato HH:MM para mostrar
                $permisosAcumuladosGuardados = substr($timeValue, 0, 5); // Toma HH:MM
            } else {
                // Si es un número, convertir a HH:MM
                $horasTotales = (int)$timeValue;
                $permisosAcumuladosGuardados = sprintf('%02d:00', $horasTotales);
            }
        }
        
        // Si no hay valor guardado, calcular desde los permisos justificados (convertir minutos a HH:MM)
        if ($permisosAcumuladosGuardados === null && $totalMinutosCalculado > 0) {
            $horasTotales = (int)($totalMinutosCalculado / 60);
            $minutosRestantes = $totalMinutosCalculado % 60;
            $permisosAcumuladosMostrar = sprintf('%02d:%02d', $horasTotales, $minutosRestantes);
        } else {
            $permisosAcumuladosMostrar = $permisosAcumuladosGuardados ?? '00:00';
        }
        
        // Obtener valor guardado de permisos injustificados acumulados si existe (solo si las columnas existen)
        $permisosInjustificadosAcumuladosMostrar = '00:00';
        if ($columnaPermisoJustificadoExiste) {
            // Verificar si la columna permisos_injustificados_acumulados existe
            $stmtCheckColInjustAcum = $db->query("
                SELECT COUNT(*) as existe
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'funcionarios'
                AND COLUMN_NAME = 'permisos_injustificados_acumulados'
            ");
            $columnaInjustAcumExiste = $stmtCheckColInjustAcum->fetch()['existe'] > 0;
            
            if ($columnaInjustAcumExiste) {
                $stmtPermisosInjustAcum = $db->prepare("
                    SELECT permisos_injustificados_acumulados 
                    FROM funcionarios 
                    WHERE cedula = ?
                ");
                $stmtPermisosInjustAcum->execute([$cedulaBD]);
                $resultadoPermisosInjust = $stmtPermisosInjustAcum->fetch();
                $permisosInjustificadosAcumuladosGuardados = null;
                
                if (!empty($resultadoPermisosInjust['permisos_injustificados_acumulados'])) {
                    $timeValue = $resultadoPermisosInjust['permisos_injustificados_acumulados'];
                    if (is_string($timeValue) && strpos($timeValue, ':') !== false) {
                        $permisosInjustificadosAcumuladosGuardados = substr($timeValue, 0, 5);
                    } else {
                        $horasTotales = (int)$timeValue;
                        $permisosInjustificadosAcumuladosGuardados = sprintf('%02d:00', $horasTotales);
                    }
                }
                
                // Si no hay valor guardado, calcular desde los permisos injustificados
                if ($permisosInjustificadosAcumuladosGuardados === null && $totalMinutosCalculadoInjustificados > 0) {
                    $horasTotales = (int)($totalMinutosCalculadoInjustificados / 60);
                    $minutosRestantes = $totalMinutosCalculadoInjustificados % 60;
                    $permisosInjustificadosAcumuladosMostrar = sprintf('%02d:%02d', $horasTotales, $minutosRestantes);
                } else {
                    $permisosInjustificadosAcumuladosMostrar = $permisosInjustificadosAcumuladosGuardados ?? '00:00';
                }
            }
        }
    } catch (Exception $e) {
        mostrarMensaje("Error al procesar permisos: " . $e->getMessage(), 'error');
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Solicitud de Permiso</h2>
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
    .permiso-container {
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
        color: #4caf50;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #4caf50;
    }
    
    .motivo-options {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .motivo-option {
        padding: 0.5rem 0.75rem;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        background: white;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
    }
    
    .motivo-option:hover {
        border-color: #4caf50;
        background: #f1f8f4;
    }
    
    .motivo-option input[type="radio"] {
        margin-right: 0.5rem;
        margin-left: 0;
        cursor: pointer;
        flex-shrink: 0;
    }
    
    .motivo-option input[type="radio"]:checked + label {
        font-weight: bold;
        color: #4caf50;
    }
    
    .motivo-option label {
        cursor: pointer;
        display: inline-block;
        margin: 0;
        flex: 1;
    }
    
    .periodo-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1rem;
    }
    
    .periodo-card {
        padding: 1.5rem;
        background: #c8e6c9;
        border-radius: 8px;
        border: 2px solid #4caf50;
    }
    
    .periodo-card h4 {
        color: #45a049;
        margin-bottom: 1rem;
        font-size: 1.1rem;
    }
    
    .periodo-card .form-group {
        margin-bottom: 1rem;
    }
    
    .periodo-card .form-group label {
        color: #2e7d32;
        font-weight: 500;
        display: block;
        margin-bottom: 0.5rem;
    }
    
    .periodo-card input[type="date"],
    .periodo-card input[type="time"] {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #4caf50;
        border-radius: 4px;
        font-size: 1rem;
    }
    
    .periodo-container {
        margin-bottom: 1rem;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 6px;
        border-left: 4px solid #4caf50;
        position: relative;
    }
    
    .btn-remove-periodo {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: #e74c3c;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        cursor: pointer;
    }
    
    .btn-remove-periodo:hover {
        background: #c0392b;
    }
    
    .btn-add-periodo {
        background: #4caf50;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 4px;
        cursor: pointer;
        margin-top: 1rem;
    }
    
    .btn-add-periodo:hover {
        background: #45a049;
    }
    
    .permisos-list {
        margin-top: 2rem;
    }
    
    .permisos-list h3 {
        color: #4caf50;
        margin-bottom: 1rem;
    }
    
    .permisos-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        margin-top: 1rem;
    }
    
    .permisos-table th {
        background: #4caf50;
        color: white;
        padding: 0.75rem;
        text-align: left;
        border: 1px solid #45a049;
    }
    
    .permisos-table td {
        padding: 0.75rem;
        border: 1px solid #dee2e6;
    }
    
    .permisos-table tr:nth-child(even) {
        background: #f8f9fa;
    }
    
    .permisos-table tr.permiso-injustificado {
        background-color: #ffcccc !important;
    }
    
    .permisos-table tr.permiso-injustificado td {
        color: #721c24;
        font-weight: 500;
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
        background: #c8e6c9;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #4caf50;
    }
    
    .funcionario-info strong {
        color: #2e7d32;
    }
    
    .btn-primary {
        background: #4caf50;
        color: white;
    }
    
    .btn-primary:hover {
        background: #45a049;
    }
</style>

<!-- Sección de Búsqueda de Funcionario -->
<div class="permiso-container">
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
<div class="permiso-container">
    <form method="POST" action="<?php echo BASE_URL; ?>/forms/permisos/procesar_solicitud_permiso.php" id="formPermiso">
        <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
        
        <div class="form-section">
            <h3>Motivo del Permiso</h3>
            <p style="margin-bottom: 1rem; color: #666;">Solicito permiso para ausentarme de mi trabajo por motivos de:</p>
            <div class="motivo-options">
                <div class="motivo-option">
                    <input type="radio" id="motivo_enfermedad" name="motivo" value="Enfermedad" required>
                    <label for="motivo_enfermedad">Enfermedad</label>
                </div>
                <div class="motivo-option">
                    <input type="radio" id="motivo_duelo" name="motivo" value="Duelo" required>
                    <label for="motivo_duelo">Duelo</label>
                </div>
                <div class="motivo-option">
                    <input type="radio" id="motivo_matrimonio" name="motivo" value="Matrimonio" required>
                    <label for="motivo_matrimonio">Matrimonio</label>
                </div>
                <div class="motivo-option">
                    <input type="radio" id="motivo_nacimiento" name="motivo" value="Nacimiento de hijos" required>
                    <label for="motivo_nacimiento">Nacimiento de hijos</label>
                </div>
                <div class="motivo-option">
                    <input type="radio" id="motivo_enfermedad_parientes" name="motivo" value="Enfermedad de parientes cercanos" required>
                    <label for="motivo_enfermedad_parientes">Enfermedad de parientes cercanos</label>
                </div>
                <div class="motivo-option">
                    <input type="radio" id="motivo_eventos_academicos" name="motivo" value="Eventos académicos puntuales" required>
                    <label for="motivo_eventos_academicos">Eventos académicos puntuales</label>
                </div>
                <div class="motivo-option">
                    <input type="radio" id="motivo_otros" name="motivo" value="Otros asuntos personales" required>
                    <label for="motivo_otros">Otros asuntos personales</label>
                </div>
                <div class="motivo-option">
                    <input type="radio" id="motivo_permiso_injustificado" name="motivo" value="Permiso InJustificado" required>
                    <label for="motivo_permiso_injustificado" style="color: #dc3545; font-weight: bold;">Permiso InJustificado</label>
                </div>
            </div>
            
            <div class="form-group" id="especifique-group" style="display: none;">
                <label for="especifique">Especifique: *</label>
                <textarea id="especifique" name="especifique" rows="3" 
                          placeholder="Describa el motivo del permiso..."></textarea>
            </div>
        </div>
        
        <div class="form-section">
            <h3>Período de Permiso</h3>
            <div id="periodos-container">
                <div class="periodo-container" data-index="0">
                    <div class="periodo-group">
                        <div class="periodo-card">
                            <h4>Desde</h4>
                            <div class="form-group">
                                <label for="fecha_desde_0">
                                    <i class="fas fa-calendar"></i> Fecha
                                </label>
                                <input type="date" id="fecha_desde_0" name="periodos[0][fecha_desde]" required class="fecha-desde-input">
                            </div>
                            <div class="form-group">
                                <label for="hora_desde_0">
                                    <i class="fas fa-clock"></i> Horas
                                </label>
                                <input type="time" id="hora_desde_0" name="periodos[0][hora_desde]" required class="hora-desde-input">
                            </div>
                        </div>
                        <div class="periodo-card">
                            <h4>Hasta</h4>
                            <div class="form-group">
                                <label for="fecha_hasta_0">
                                    <i class="fas fa-calendar"></i> Fecha
                                </label>
                                <input type="date" id="fecha_hasta_0" name="periodos[0][fecha_hasta]" required class="fecha-hasta-input">
                            </div>
                            <div class="form-group">
                                <label for="hora_hasta_0">
                                    <i class="fas fa-clock"></i> Horas
                                </label>
                                <input type="time" id="hora_hasta_0" name="periodos[0][hora_hasta]" required class="hora-hasta-input">
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-remove-periodo" onclick="removePeriodo(this)" style="display: none;">
                        <i class="fas fa-times"></i> Eliminar
                    </button>
                </div>
            </div>
            
            <button type="button" class="btn-add-periodo" onclick="addPeriodo()">
                <i class="fas fa-plus"></i> Agregar Otro Período
            </button>
        </div>
        
        <div class="form-actions" style="margin-top: 2rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Solicitud de Permiso
            </button>
            <button type="reset" class="btn">Limpiar</button>
        </div>
    </form>
</div>

<!-- Listado de Permisos Registrados -->
<div class="permiso-container permisos-list">
    <h3>Permisos Registrados</h3>
    
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
    
    <?php if (count($permisos) > 0): ?>
        <table class="permisos-table">
            <thead>
                <tr>
                    <th>Fecha Desde</th>
                    <th>Hora Desde</th>
                    <th>Fecha Hasta</th>
                    <th>Hora Hasta</th>
                    <th>Horas Totales</th>
                    <th>Motivo</th>
                    <th>Especifique</th>
                    <th>Fecha Registro</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permisos as $permiso): 
                    $claseInjustificado = '';
                    if ($columnaPermisoJustificadoExiste && isset($permiso['permiso_justificado']) && $permiso['permiso_justificado'] == 0) {
                        $claseInjustificado = 'permiso-injustificado';
                    }
                ?>
                    <tr class="<?php echo $claseInjustificado; ?>">
                        <td><?php echo date('d/m/Y', strtotime($permiso['fecha_desde'])); ?></td>
                        <td>
                            <?php 
                            if ($permiso['hora_desde']) {
                                $hora = new DateTime($permiso['hora_desde']);
                                $horaFormato = $hora->format('g:i');
                                $ampm = strtolower($hora->format('A'));
                                $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                echo $horaFormato . ' ' . $ampm;
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($permiso['fecha_hasta'])); ?></td>
                        <td>
                            <?php 
                            if ($permiso['hora_hasta']) {
                                $hora = new DateTime($permiso['hora_hasta']);
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
                            if (!empty($permiso['horas_totales'])) {
                                $horasTotales = $permiso['horas_totales'];
                                if (strlen($horasTotales) >= 5) {
                                    echo substr($horasTotales, 0, 5);
                                } else {
                                    echo $horasTotales;
                                }
                            } else {
                                echo '-';
                            }
                            ?>
                        </strong></td>
                        <td><?php 
                            $motivoTexto = htmlspecialchars($permiso['motivo']);
                            if (isset($permiso['motivo']) && $permiso['motivo'] === 'Permiso InJustificado') {
                                echo '<span style="color: #dc3545; font-weight: bold;">' . $motivoTexto . '</span>';
                            } else {
                                echo $motivoTexto;
                            }
                        ?></td>
                        <td><?php echo htmlspecialchars(substr($permiso['especifique'] ?? '', 0, 50)); ?><?php echo strlen($permiso['especifique'] ?? '') > 50 ? '...' : ''; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($permiso['fecha_registro'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay permisos registrados para este funcionario.</p>
    <?php endif; ?>
    
    <!-- Sumatoria de Permisos Acumulados - Debajo de la tabla -->
    <?php if ($funcionario): ?>
    <div style="margin-top: 1rem; color: #666; display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
        <div>
            <strong>Permisos Acumulados:</strong>
            <span id="permisos-acumulados-display" 
                  style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; color: #2e7d32; cursor: pointer; padding: 0.25rem 0.5rem; border-bottom: 1px dashed #2e7d32;" 
                  onmouseover="this.style.background='#c8e6c9';" 
                  onmouseout="this.style.background='transparent';"
                  onclick="editarPermisosAcumulados()"
                  title="Click para editar (formato HH:MM)">
                <?php echo htmlspecialchars($permisosAcumuladosMostrar ?? '00:00'); ?>
            </span>
            <input type="text" 
                   id="permisos-acumulados-input" 
                   value="<?php echo htmlspecialchars($permisosAcumuladosMostrar ?? '00:00'); ?>"
                   pattern="[0-9]{1,2}:[0-5][0-9]"
                   placeholder="HH:MM"
                   style="display: none; font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; padding: 0.25rem 0.5rem; border: 1px solid #4caf50; border-radius: 3px; width: 100px; text-align: center;"
                   onblur="guardarPermisosAcumulados()"
                   onkeydown="if(event.key === 'Enter') { event.preventDefault(); guardarPermisosAcumulados(); } if(event.key === 'Escape') cancelarEdicionPermisos();">
            <button id="btn-guardar-permisos" 
                    onclick="guardarPermisosAcumulados()" 
                    style="display: none; margin-left: 0.5rem; padding: 0.25rem 0.75rem; background: #4caf50; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.9em;">
                Guardar
            </button>
            <span id="mensaje-permisos" style="color: #28a745; font-weight: bold; margin-left: 0.5rem;"></span>
        </div>
        
        <?php if ($columnaPermisoJustificadoExiste): ?>
        <div>
            <strong style="color: #dc3545;">Permisos Acumulados InJustificados:</strong>
            <span id="permisos-injustificados-acumulados-display" 
                  style="font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; color: #dc3545; cursor: pointer; padding: 0.25rem 0.5rem; border-bottom: 1px dashed #dc3545;" 
                  onmouseover="this.style.background='#ffe6e6';" 
                  onmouseout="this.style.background='transparent';"
                  onclick="editarPermisosInjustificadosAcumulados()"
                  title="Click para editar (formato HH:MM)">
                <?php echo htmlspecialchars($permisosInjustificadosAcumuladosMostrar ?? '00:00'); ?>
            </span>
            <input type="text" 
                   id="permisos-injustificados-acumulados-input" 
                   value="<?php echo htmlspecialchars($permisosInjustificadosAcumuladosMostrar ?? '00:00'); ?>"
                   pattern="[0-9]{1,2}:[0-5][0-9]"
                   placeholder="HH:MM"
                   style="display: none; font-size: 1.1em; font-weight: bold; margin-left: 0.5rem; padding: 0.25rem 0.5rem; border: 1px solid #dc3545; border-radius: 3px; width: 100px; text-align: center;"
                   onblur="guardarPermisosInjustificadosAcumulados()"
                   onkeydown="if(event.key === 'Enter') { event.preventDefault(); guardarPermisosInjustificadosAcumulados(); } if(event.key === 'Escape') cancelarEdicionPermisosInjustificados();">
            <button id="btn-guardar-permisos-injustificados" 
                    onclick="guardarPermisosInjustificadosAcumulados()" 
                    style="display: none; margin-left: 0.5rem; padding: 0.25rem 0.75rem; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 0.9em;">
                Guardar
            </button>
            <span id="mensaje-permisos-injustificados" style="color: #28a745; font-weight: bold; margin-left: 0.5rem;"></span>
        </div>
        <?php endif; ?>
    </div>
    <input type="hidden" id="cedula-funcionario" value="<?php echo htmlspecialchars($funcionario['cedula'], ENT_QUOTES); ?>">
    <input type="hidden" id="permisos-acumulados-original" value="<?php echo htmlspecialchars($permisosAcumuladosMostrar ?? '00:00'); ?>">
    <input type="hidden" id="permisos-injustificados-acumulados-original" value="<?php echo htmlspecialchars($permisosInjustificadosAcumuladosMostrar ?? '00:00'); ?>">
    <?php endif; ?>
</div>

<script>
let periodoIndex = 1;

// Mostrar/ocultar campo especifique según motivo seleccionado
document.querySelectorAll('input[name="motivo"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const especifiqueGroup = document.getElementById('especifique-group');
        const especifiqueInput = document.getElementById('especifique');
        
        if (this.value === 'Otros asuntos personales') {
            especifiqueGroup.style.display = 'block';
            especifiqueInput.required = true;
        } else {
            especifiqueGroup.style.display = 'none';
            especifiqueInput.required = false;
            especifiqueInput.value = '';
        }
    });
});

function addPeriodo() {
    const container = document.getElementById('periodos-container');
    const newContainer = document.createElement('div');
    newContainer.className = 'periodo-container';
    newContainer.setAttribute('data-index', periodoIndex);
    
    newContainer.innerHTML = `
        <div class="periodo-group">
            <div class="periodo-card">
                <h4>Desde</h4>
                <div class="form-group">
                    <label for="fecha_desde_${periodoIndex}">
                        <i class="fas fa-calendar"></i> Fecha
                    </label>
                    <input type="date" id="fecha_desde_${periodoIndex}" name="periodos[${periodoIndex}][fecha_desde]" required class="fecha-desde-input">
                </div>
                <div class="form-group">
                    <label for="hora_desde_${periodoIndex}">
                        <i class="fas fa-clock"></i> Horas
                    </label>
                    <input type="time" id="hora_desde_${periodoIndex}" name="periodos[${periodoIndex}][hora_desde]" required class="hora-desde-input">
                </div>
            </div>
            <div class="periodo-card">
                <h4>Hasta</h4>
                <div class="form-group">
                    <label for="fecha_hasta_${periodoIndex}">
                        <i class="fas fa-calendar"></i> Fecha
                    </label>
                    <input type="date" id="fecha_hasta_${periodoIndex}" name="periodos[${periodoIndex}][fecha_hasta]" required class="fecha-hasta-input">
                </div>
                <div class="form-group">
                    <label for="hora_hasta_${periodoIndex}">
                        <i class="fas fa-clock"></i> Horas
                    </label>
                    <input type="time" id="hora_hasta_${periodoIndex}" name="periodos[${periodoIndex}][hora_hasta]" required class="hora-hasta-input">
                </div>
            </div>
        </div>
        <button type="button" class="btn-remove-periodo" onclick="removePeriodo(this)">
            <i class="fas fa-times"></i> Eliminar
        </button>
    `;
    
    container.appendChild(newContainer);
    periodoIndex++;
    updateRemoveButtons();
}

function removePeriodo(button) {
    const container = button.closest('.periodo-container');
    container.remove();
    updateRemoveButtons();
}

function updateRemoveButtons() {
    const containers = document.querySelectorAll('.periodo-container');
    containers.forEach(container => {
        const btnRemove = container.querySelector('.btn-remove-periodo');
        if (btnRemove) {
            btnRemove.style.display = containers.length > 1 ? 'block' : 'none';
        }
    });
}

// Validación del formulario
document.addEventListener('DOMContentLoaded', function() {
    updateRemoveButtons();
    
    document.getElementById('formPermiso').addEventListener('submit', function(e) {
        const periodos = document.querySelectorAll('.periodo-container');
        let hayErrores = false;
        
        periodos.forEach(container => {
            const fechaDesde = container.querySelector('.fecha-desde-input').value;
            const horaDesde = container.querySelector('.hora-desde-input').value;
            const fechaHasta = container.querySelector('.fecha-hasta-input').value;
            const horaHasta = container.querySelector('.hora-hasta-input').value;
            
            if (fechaDesde && fechaHasta && fechaDesde > fechaHasta) {
                alert('La fecha hasta debe ser mayor o igual que la fecha desde');
                hayErrores = true;
            } else if (fechaDesde === fechaHasta && horaDesde && horaHasta && horaHasta <= horaDesde) {
                alert('Cuando las fechas son iguales, la hora hasta debe ser mayor que la hora desde');
                hayErrores = true;
            }
        });
        
        // Validar que si motivo es "Otros asuntos personales", especifique debe estar lleno
        const motivoOtros = document.querySelector('input[name="motivo"]:checked');
        if (motivoOtros && motivoOtros.value === 'Otros asuntos personales') {
            const especifique = document.getElementById('especifique').value.trim();
            if (!especifique) {
                alert('Debe especificar el motivo cuando selecciona "Otros asuntos personales"');
                hayErrores = true;
            }
        }
        
        if (hayErrores) {
            e.preventDefault();
        }
    });
});

// Funciones para editar permisos acumulados
function editarPermisosAcumulados() {
    const display = document.getElementById('permisos-acumulados-display');
    const input = document.getElementById('permisos-acumulados-input');
    const btnGuardar = document.getElementById('btn-guardar-permisos');
    const mensaje = document.getElementById('mensaje-permisos');
    
    display.style.display = 'none';
    input.style.display = 'inline-block';
    btnGuardar.style.display = 'inline-block';
    mensaje.textContent = '';
    input.focus();
    input.select();
}

function cancelarEdicionPermisos() {
    const display = document.getElementById('permisos-acumulados-display');
    const input = document.getElementById('permisos-acumulados-input');
    const btnGuardar = document.getElementById('btn-guardar-permisos');
    const original = document.getElementById('permisos-acumulados-original');
    const mensaje = document.getElementById('mensaje-permisos');
    
    input.value = original.value;
    
    display.style.display = 'inline';
    input.style.display = 'none';
    btnGuardar.style.display = 'none';
    mensaje.textContent = '';
}

function guardarPermisosAcumulados() {
    const input = document.getElementById('permisos-acumulados-input');
    const display = document.getElementById('permisos-acumulados-display');
    const btnGuardar = document.getElementById('btn-guardar-permisos');
    const cedula = document.getElementById('cedula-funcionario').value;
    const mensaje = document.getElementById('mensaje-permisos');
    
    const permisosValue = input.value.trim();
    const regexPermisos = /^([0-9]{1,2}):([0-5][0-9])$/;
    
    if (!regexPermisos.test(permisosValue)) {
        alert('Por favor ingrese un formato válido (HH:MM). Ejemplo: 33:30');
        input.focus();
        return;
    }
    
    const partes = permisosValue.split(':');
    const horas = parseInt(partes[0]);
    const minutos = parseInt(partes[1]);
    
    if (horas > 838) {
        alert('El valor de horas no puede exceder 838 horas (límite de MySQL TIME)');
        input.focus();
        return;
    }
    
    btnGuardar.disabled = true;
    btnGuardar.textContent = 'Guardando...';
    
    const permisosFormatoTime = String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0') + ':00';
    
    fetch('<?php echo BASE_URL; ?>/forms/permisos/actualizar_permisos_acumulados.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            cedula: cedula,
            permisos_acumulados: permisosFormatoTime
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            display.textContent = permisosValue;
            document.getElementById('permisos-acumulados-original').value = permisosValue;
            display.style.display = 'inline';
            input.style.display = 'none';
            btnGuardar.style.display = 'none';
            mensaje.textContent = '✓ Guardado';
            mensaje.style.color = '#28a745';
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
        alert('Error al guardar los permisos acumulados');
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
    });
}

// Funciones para editar permisos injustificados acumulados
function editarPermisosInjustificadosAcumulados() {
    const display = document.getElementById('permisos-injustificados-acumulados-display');
    const input = document.getElementById('permisos-injustificados-acumulados-input');
    const btnGuardar = document.getElementById('btn-guardar-permisos-injustificados');
    const mensaje = document.getElementById('mensaje-permisos-injustificados');
    
    display.style.display = 'none';
    input.style.display = 'inline-block';
    btnGuardar.style.display = 'inline-block';
    mensaje.textContent = '';
    input.focus();
    input.select();
}

function cancelarEdicionPermisosInjustificados() {
    const display = document.getElementById('permisos-injustificados-acumulados-display');
    const input = document.getElementById('permisos-injustificados-acumulados-input');
    const btnGuardar = document.getElementById('btn-guardar-permisos-injustificados');
    const original = document.getElementById('permisos-injustificados-acumulados-original');
    const mensaje = document.getElementById('mensaje-permisos-injustificados');
    
    input.value = original.value;
    
    display.style.display = 'inline';
    input.style.display = 'none';
    btnGuardar.style.display = 'none';
    mensaje.textContent = '';
}

function guardarPermisosInjustificadosAcumulados() {
    const input = document.getElementById('permisos-injustificados-acumulados-input');
    const display = document.getElementById('permisos-injustificados-acumulados-display');
    const btnGuardar = document.getElementById('btn-guardar-permisos-injustificados');
    const cedula = document.getElementById('cedula-funcionario').value;
    const mensaje = document.getElementById('mensaje-permisos-injustificados');
    
    const permisosValue = input.value.trim();
    const regexPermisos = /^([0-9]{1,2}):([0-5][0-9])$/;
    
    if (!regexPermisos.test(permisosValue)) {
        alert('Por favor ingrese un formato válido (HH:MM). Ejemplo: 33:30');
        input.focus();
        return;
    }
    
    const partes = permisosValue.split(':');
    const horas = parseInt(partes[0]);
    const minutos = parseInt(partes[1]);
    
    if (horas > 838) {
        alert('El valor de horas no puede exceder 838 horas (límite de MySQL TIME)');
        input.focus();
        return;
    }
    
    btnGuardar.disabled = true;
    btnGuardar.textContent = 'Guardando...';
    
    const permisosFormatoTime = String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0') + ':00';
    
    fetch('<?php echo BASE_URL; ?>/forms/permisos/actualizar_permisos_injustificados_acumulados.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            cedula: cedula,
            permisos_injustificados_acumulados: permisosFormatoTime
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            display.textContent = permisosValue;
            document.getElementById('permisos-injustificados-acumulados-original').value = permisosValue;
            display.style.display = 'inline';
            input.style.display = 'none';
            btnGuardar.style.display = 'none';
            mensaje.textContent = '✓ Guardado';
            mensaje.style.color = '#28a745';
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
        alert('Error al guardar los permisos injustificados acumulados');
        btnGuardar.disabled = false;
        btnGuardar.textContent = 'Guardar';
    });
}
</script>

<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>




