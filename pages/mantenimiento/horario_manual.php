<?php
/**
 * Horario Manual
 * Módulo de Mantenimiento
 * Permite capturar manualmente las horas de entrada y salida para funcionarios marcados como "Manual"
 */

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/funciones_calculo_horas.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener funcionarios marcados como "Manual"
    $stmt = $db->prepare("
        SELECT cedula, nombre, apellido, fun_extra
        FROM funcionarios
        WHERE fun_extra = 'Manual'
        ORDER BY nombre, apellido
    ");
    $stmt->execute();
    $funcionariosManual = $stmt->fetchAll();
    
    // Procesar actualización de marcación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_marcacion') {
        $cedula = sanitize($_POST['cedula']);
        $fecha = sanitize($_POST['fecha']);
        $hora_entrada = !empty($_POST['hora_entrada']) ? sanitize($_POST['hora_entrada']) : null;
        $hora_salida = !empty($_POST['hora_salida']) ? sanitize($_POST['hora_salida']) : null;
        
        if (empty($cedula) || empty($fecha)) {
            mostrarMensaje("Cédula y fecha son obligatorios", 'error');
        } else {
            // Verificar si existe marcación para esa fecha
            $stmtCheck = $db->prepare("SELECT id_marcacion FROM marcaciones WHERE cedula = ? AND fecha = ?");
            $stmtCheck->execute([$cedula, $fecha]);
            $existe = $stmtCheck->fetch();
            
            if ($existe) {
                // Actualizar
                $stmtUpdate = $db->prepare("
                    UPDATE marcaciones 
                    SET hora_entrada = ?, hora_salida = ?, horas_trabajadas = NULL, tiempo_faltante = NULL
                    WHERE cedula = ? AND fecha = ?
                ");
                $stmtUpdate->execute([$hora_entrada, $hora_salida, $cedula, $fecha]);
                
                // Recalcular horas trabajadas
                if ($hora_entrada && $hora_salida) {
                    // Obtener si es funcionario especial
                    $stmtEspecial = $db->prepare("SELECT fun_horario_especial FROM funcionarios WHERE cedula = ?");
                    $stmtEspecial->execute([$cedula]);
                    $func = $stmtEspecial->fetch();
                    $esEspecial = intval($func['fun_horario_especial'] ?? 0) === 1;
                    
                    // Calcular horas trabajadas
                    $resultado = calcularHorasTrabajadas($hora_entrada, $hora_salida, $esEspecial);
                    
                    if ($resultado) {
                        $stmtRecalc = $db->prepare("
                            UPDATE marcaciones 
                            SET horas_trabajadas = ?, tiempo_faltante = ?
                            WHERE cedula = ? AND fecha = ?
                        ");
                        $stmtRecalc->execute([
                            $resultado['horas_trabajadas'],
                            $resultado['tiempo_faltante'],
                            $cedula,
                            $fecha
                        ]);
                    }
                }
                
                mostrarMensaje("Marcación actualizada exitosamente", 'success');
            } else {
                // Crear nueva
                $stmtInsert = $db->prepare("
                    INSERT INTO marcaciones (cedula, fecha, hora_entrada, hora_salida, horas_trabajadas, tiempo_faltante)
                    VALUES (?, ?, ?, ?, NULL, NULL)
                ");
                $stmtInsert->execute([$cedula, $fecha, $hora_entrada, $hora_salida]);
                
                // Recalcular horas trabajadas
                if ($hora_entrada && $hora_salida) {
                    $stmtEspecial = $db->prepare("SELECT fun_horario_especial FROM funcionarios WHERE cedula = ?");
                    $stmtEspecial->execute([$cedula]);
                    $func = $stmtEspecial->fetch();
                    $esEspecial = intval($func['fun_horario_especial'] ?? 0) === 1;
                    
                    $resultado = calcularHorasTrabajadas($hora_entrada, $hora_salida, $esEspecial);
                    
                    if ($resultado) {
                        $stmtRecalc = $db->prepare("
                            UPDATE marcaciones 
                            SET horas_trabajadas = ?, tiempo_faltante = ?
                            WHERE cedula = ? AND fecha = ?
                        ");
                        $stmtRecalc->execute([
                            $resultado['horas_trabajadas'],
                            $resultado['tiempo_faltante'],
                            $cedula,
                            $fecha
                        ]);
                    }
                }
                
                mostrarMensaje("Marcación creada exitosamente", 'success');
            }
            
            redirect(BASE_URL . '/pages/mantenimiento/index.php#horario-manual');
        }
    }
    
    // Obtener marcaciones de funcionarios manuales
    $cedulaFiltro = isset($_GET['cedula']) ? sanitize($_GET['cedula']) : '';
    $fechaFiltro = isset($_GET['fecha']) ? sanitize($_GET['fecha']) : date('Y-m-d');
    
    $sqlMarcaciones = "
        SELECT m.*, f.nombre, f.apellido
        FROM marcaciones m
        INNER JOIN funcionarios f ON m.cedula = f.cedula
        WHERE f.fun_extra = 'Manual'
    ";
    $paramsMarcaciones = [];
    
    if (!empty($cedulaFiltro)) {
        $sqlMarcaciones .= " AND m.cedula = ?";
        $paramsMarcaciones[] = $cedulaFiltro;
    }
    
    if (!empty($fechaFiltro)) {
        $sqlMarcaciones .= " AND m.fecha = ?";
        $paramsMarcaciones[] = $fechaFiltro;
    }
    
    $sqlMarcaciones .= " ORDER BY m.fecha DESC, f.nombre, f.apellido LIMIT 100";
    
    $stmtMarcaciones = $db->prepare($sqlMarcaciones);
    $stmtMarcaciones->execute($paramsMarcaciones);
    $marcaciones = $stmtMarcaciones->fetchAll();
    
} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
    $funcionariosManual = [];
    $marcaciones = [];
}
?>

<div class="page-content">
    <div class="info-section" style="margin-bottom: 2rem; padding: 1rem; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;">
        <p><strong>Nota:</strong> Esta sección permite capturar manualmente las horas de entrada y salida para funcionarios marcados como "Manual" en el campo <code>fun_extra</code>.</p>
    </div>
    
    <!-- Filtros -->
    <div class="filters-section" style="margin-bottom: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
        <h3>Filtros</h3>
        <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="seccion" value="horario-manual">
            <div>
                <label for="cedula_filtro">Funcionario:</label>
                <select id="cedula_filtro" name="cedula" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Todos</option>
                    <?php foreach ($funcionariosManual as $func): ?>
                    <option value="<?php echo htmlspecialchars($func['cedula']); ?>" 
                            <?php echo (isset($_GET['cedula']) && $_GET['cedula'] === $func['cedula']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($func['nombre'] ?? ''); ?> <?php echo htmlspecialchars($func['apellido'] ?? ''); ?>
                        (<?php echo htmlspecialchars($func['cedula']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="fecha_filtro">Fecha:</label>
                <input type="date" 
                       id="fecha_filtro" 
                       name="fecha" 
                       value="<?php echo htmlspecialchars($fechaFiltro); ?>"
                       style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="<?php echo BASE_URL; ?>/pages/mantenimiento/index.php#horario-manual" class="btn">
                Limpiar
            </a>
        </form>
    </div>
    
    <!-- Lista de Funcionarios Manuales -->
    <div class="funcionarios-section" style="margin-bottom: 2rem;">
        <h3>Funcionarios con Horario Manual (<?php echo count($funcionariosManual); ?>)</h3>
        <?php if (count($funcionariosManual) === 0): ?>
        <div class="alert alert-info">
            No hay funcionarios marcados como "Manual". 
            Puedes marcar funcionarios como "Manual" desde la <a href="<?php echo BASE_URL; ?>/pages/funcionarios/listar.php">Lista de Funcionarios</a>.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table-excel" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Cédula</th>
                        <th>Nombre</th>
                        <th>Apellido</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionariosManual as $func): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($func['cedula']); ?></td>
                        <td><?php echo htmlspecialchars($func['nombre'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($func['apellido'] ?? ''); ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/pages/mantenimiento/index.php#horario-manual&cedula=<?php echo urlencode($func['cedula']); ?>&fecha=<?php echo date('Y-m-d'); ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-clock"></i> Registrar Horario
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Formulario de Registro/Edición -->
    <div class="form-section" style="margin-bottom: 2rem; padding: 1.5rem; background: #fff; border: 1px solid #ddd; border-radius: 8px;">
        <h3>Registrar/Editar Horario</h3>
        <form method="POST" action="" style="max-width: 600px;">
            <input type="hidden" name="accion" value="actualizar_marcacion">
            <div class="form-group">
                <label for="cedula_select">Funcionario *</label>
                <select id="cedula_select" name="cedula" required style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">Seleccione un funcionario</option>
                    <?php foreach ($funcionariosManual as $func): ?>
                    <option value="<?php echo htmlspecialchars($func['cedula']); ?>"
                            <?php echo (isset($_GET['cedula']) && $_GET['cedula'] === $func['cedula']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($func['nombre'] ?? ''); ?> <?php echo htmlspecialchars($func['apellido'] ?? ''); ?>
                        (<?php echo htmlspecialchars($func['cedula']); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="fecha_input">Fecha *</label>
                <input type="date" 
                       id="fecha_input" 
                       name="fecha" 
                       required
                       value="<?php echo htmlspecialchars($fechaFiltro); ?>"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="hora_entrada">Hora de Entrada</label>
                <input type="time" 
                       id="hora_entrada" 
                       name="hora_entrada"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div class="form-group">
                <label for="hora_salida">Hora de Salida</label>
                <input type="time" 
                       id="hora_salida" 
                       name="hora_salida"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <div class="form-actions" style="margin-top: 1rem;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Guardar Horario
                </button>
            </div>
        </form>
    </div>
    
    <!-- Lista de Marcaciones Recientes -->
    <div class="marcaciones-section">
        <h3>Marcaciones Recientes</h3>
        <?php if (count($marcaciones) === 0): ?>
        <div class="alert alert-info">
            No hay marcaciones registradas para los filtros seleccionados.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table-excel" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Funcionario</th>
                        <th>Cédula</th>
                        <th>Hora Entrada</th>
                        <th>Hora Salida</th>
                        <th>Horas Trabajadas</th>
                        <th>Tardanza/Irregular</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marcaciones as $marc): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($marc['fecha']); ?></td>
                        <td><?php echo htmlspecialchars($marc['nombre'] ?? ''); ?> <?php echo htmlspecialchars($marc['apellido'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($marc['cedula']); ?></td>
                        <td>
                            <?php 
                            if ($marc['hora_entrada']) {
                                $hora = new DateTime($marc['hora_entrada']);
                                echo $hora->format('g:i a.m.');
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($marc['hora_salida']) {
                                $hora = new DateTime($marc['hora_salida']);
                                echo $hora->format('g:i a.m.');
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($marc['horas_trabajadas']) {
                                $horas = new DateTime($marc['horas_trabajadas']);
                                echo $horas->format('H:i');
                            } else {
                                echo '<span style="color: #dc3545;">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($marc['tiempo_faltante'] && $marc['tiempo_faltante'] !== '00:00:00') {
                                $faltante = new DateTime($marc['tiempo_faltante']);
                                echo '<span style="color: #721c24; font-weight: bold;">' . $faltante->format('H:i') . '</span>';
                            } else {
                                echo '00:00';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

