<?php
/**
 * Horario Manual
 * Módulo de Mantenimiento
 * Permite capturar manualmente las horas de entrada y salida para funcionarios
 */

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/funciones_calculo_horas.php';

$funcionario = null;
// Leer parámetros GET (pueden venir directamente o desde el hash parseado)
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$cedulaSeleccionada = isset($_GET['cedula']) ? sanitize($_GET['cedula']) : '';
$fechaSeleccionadaRaw = isset($_GET['fecha']) ? trim($_GET['fecha']) : date('Y-m-d');
$marcacionExistente = null;

// Normalizar la fecha a formato YYYY-MM-DD
if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $fechaSeleccionadaRaw, $matches)) {
    // Formato MM/DD/YYYY -> YYYY-MM-DD
    $fechaSeleccionada = sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
} else {
    // Intentar parsear con strtotime
    $timestamp = strtotime($fechaSeleccionadaRaw);
    if ($timestamp !== false) {
        $fechaSeleccionada = date('Y-m-d', $timestamp);
    } else {
        $fechaSeleccionada = $fechaSeleccionadaRaw; // Usar tal cual si no se puede parsear
    }
}

// Debug: verificar si los parámetros están llegando
// error_log("GET params: " . print_r($_GET, true));

try {
    $db = Database::getInstance()->getConnection();
    
    // Búsqueda de funcionario (igual a crear_editar.php)
    if (!empty($busqueda)) {
        // Buscar por cédula, nombre o apellido
        $stmt = $db->prepare("
            SELECT * FROM funcionarios 
            WHERE cedula LIKE ? OR nombre LIKE ? OR apellido LIKE ?
            LIMIT 1
        ");
        $busquedaLimpia = '%' . $busqueda . '%';
        $stmt->execute([$busquedaLimpia, $busquedaLimpia, $busquedaLimpia]);
        $funcionario = $stmt->fetch();
        
        if ($funcionario) {
            $cedulaSeleccionada = $funcionario['cedula'];
        }
    }
    
    // Si viene cédula por GET (desde cambio de fecha o redirección), buscar el funcionario
    if (!$funcionario && !empty($cedulaSeleccionada)) {
        $stmt = $db->prepare("SELECT * FROM funcionarios WHERE cedula = ?");
        $stmt->execute([$cedulaSeleccionada]);
        $funcionario = $stmt->fetch();
    }
    
    // Si hay funcionario seleccionado y fecha, buscar marcación existente
    // IMPORTANTE: Siempre resetear $marcacionExistente para evitar valores antiguos
    $marcacionExistente = null;
    
    if ($funcionario && !empty($fechaSeleccionada)) {
        // La fecha ya está normalizada arriba, usar directamente
        $fechaFormateada = $fechaSeleccionada;
        
        // Buscar con la cédula original (con guiones)
        $stmtMarcacion = $db->prepare("SELECT * FROM marcaciones WHERE cedula = ? AND fecha = ?");
        $stmtMarcacion->execute([$funcionario['cedula'], $fechaFormateada]);
        $marcacionExistente = $stmtMarcacion->fetch(PDO::FETCH_ASSOC);
        
        // Si no se encuentra, intentar con la cédula normalizada (sin guiones)
        if (!$marcacionExistente) {
            $cedulaNormalizada = normalizarCedula($funcionario['cedula']);
            $stmtMarcacion2 = $db->prepare("SELECT * FROM marcaciones WHERE cedula = ? AND fecha = ?");
            $stmtMarcacion2->execute([$cedulaNormalizada, $fechaFormateada]);
            $marcacionExistente = $stmtMarcacion2->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    // Procesar actualización de marcación (ahora se hace por AJAX, este código se mantiene por compatibilidad pero no se usa)
    // El guardado real se hace en guardar_marcacion.php
    if (false && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_marcacion') {
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
            
            // Redirigir manteniendo la sección de horario-manual activa
            // Redirigir usando solo el hash para mantener la sección activa
            // Los parámetros cedula y fecha se pasan en el hash para que el JavaScript los lea
            redirect(BASE_URL . '/pages/mantenimiento/index.php#horario-manual');
        }
    }
    
} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
    $funcionario = null;
    $marcacionExistente = null;
}
?>

<div class="page-content">
    <div class="info-section" style="margin-bottom: 2rem; padding: 1rem; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;">
        <p><strong>Nota:</strong> Esta sección permite capturar manualmente las horas de entrada y salida para funcionarios.</p>
    </div>
    
    <!-- Búsqueda de Funcionario -->
    <div class="search-section" style="margin-bottom: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
        <h3>Buscar Funcionario para Editar</h3>
        <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="seccion" value="horario-manual">
            <?php if ($funcionario): ?>
                <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
            <?php endif; ?>
            <?php if (!empty($fechaSeleccionada)): ?>
                <input type="hidden" name="fecha" value="<?php echo htmlspecialchars($fechaSeleccionada); ?>">
            <?php endif; ?>
            <div style="flex: 1; min-width: 300px;">
                <label for="buscar_funcionario">Buscar por Cédula, Nombre o Apellido:</label>
                <input type="text" 
                       id="buscar_funcionario" 
                       name="buscar" 
                       value="<?php echo htmlspecialchars($busqueda); ?>"
                       placeholder="Ingrese cédula, nombre o apellido"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
            <?php if (!empty($busqueda) || !empty($cedulaSeleccionada)): ?>
                <a href="<?php echo BASE_URL; ?>/pages/mantenimiento/index.php?seccion=horario-manual#horario-manual" class="btn btn-secondary">
                    Limpiar
                </a>
            <?php endif; ?>
        </form>
        
        <?php if (!empty($busqueda) && !$funcionario): ?>
            <div class="alert alert-warning" style="margin-top: 1rem;">
                No se encontró ningún funcionario con "<?php echo htmlspecialchars($busqueda); ?>"
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Formulario de Registro/Edición -->
    <?php if ($funcionario): ?>
    <div class="form-section" style="margin-bottom: 2rem; padding: 1.5rem; background: #fff; border: 1px solid #ddd; border-radius: 8px;">
        <h3>Registrar/Editar Horario</h3>
        
        <!-- Mostrar información del funcionario encontrado -->
        <div style="margin-bottom: 1.5rem; padding: 1rem; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 4px;">
            <p style="margin: 0; font-weight: bold; color: #2e7d32;">
                <i class="fas fa-user"></i> 
                <?php echo htmlspecialchars($funcionario['nombre'] ?? ''); ?> 
                <?php echo htmlspecialchars($funcionario['apellido'] ?? ''); ?> 
                - 
                <?php echo htmlspecialchars($funcionario['cedula']); ?>
            </p>
        </div>
        
        <form id="form-horario-manual" style="max-width: 600px;">
            <input type="hidden" id="cedula_hidden" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
            
            <div class="form-group">
                <label for="fecha_input">Fecha *</label>
                <input type="date" 
                       id="fecha_input" 
                       name="fecha" 
                       required
                       value="<?php echo htmlspecialchars($fechaSeleccionada); ?>"
                       onchange="cambiarFecha()"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div class="form-group">
                <label for="hora_entrada">Hora de Entrada</label>
                <input type="time" 
                       id="hora_entrada" 
                       name="hora_entrada"
                       value="<?php 
                       // Asegurar que solo se muestre la hora si hay marcación para esta fecha específica
                       if ($marcacionExistente && isset($marcacionExistente['hora_entrada']) && !empty($marcacionExistente['hora_entrada'])) {
                           echo htmlspecialchars(substr($marcacionExistente['hora_entrada'], 0, 5));
                       } else {
                           echo ''; // Limpiar campo si no hay marcación
                       }
                       ?>"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div class="form-group">
                <label for="hora_salida">Hora de Salida</label>
                <input type="time" 
                       id="hora_salida" 
                       name="hora_salida"
                       value="<?php 
                       // Asegurar que solo se muestre la hora si hay marcación para esta fecha específica
                       if ($marcacionExistente && isset($marcacionExistente['hora_salida']) && !empty($marcacionExistente['hora_salida'])) {
                           echo htmlspecialchars(substr($marcacionExistente['hora_salida'], 0, 5));
                       } else {
                           echo ''; // Limpiar campo si no hay marcación
                       }
                       ?>"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div id="mensaje-horario" style="margin-top: 1rem; display: none;"></div>
            
            <div class="form-actions" style="margin-top: 1rem;">
                <button type="submit" class="btn btn-success" id="btn-guardar-horario">
                    <i class="fas fa-save"></i> Guardar Horario
                </button>
                <a href="<?php echo BASE_URL; ?>/pages/mantenimiento/index.php#horario-manual" class="btn btn-secondary">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function cambiarFecha() {
    const fecha = document.getElementById('fecha_input').value;
    const cedulaInput = document.getElementById('cedula_hidden');
    const cedula = cedulaInput ? cedulaInput.value : '';
    const horaEntradaInput = document.getElementById('hora_entrada');
    const horaSalidaInput = document.getElementById('hora_salida');
    
    if (fecha && cedula) {
        // Limpiar campos mientras se busca
        if (horaEntradaInput) horaEntradaInput.value = '';
        if (horaSalidaInput) horaSalidaInput.value = '';
        
        // Hacer petición AJAX para obtener la marcación
        fetch('<?php echo BASE_URL; ?>/pages/mantenimiento/obtener_marcacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cedula: cedula,
                fecha: fecha
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.marcacion) {
                // Llenar campos con los valores encontrados
                if (horaEntradaInput && data.marcacion.hora_entrada) {
                    horaEntradaInput.value = data.marcacion.hora_entrada.substring(0, 5);
                }
                if (horaSalidaInput && data.marcacion.hora_salida) {
                    horaSalidaInput.value = data.marcacion.hora_salida.substring(0, 5);
                }
            } else {
                // No hay marcación, campos ya están limpios
                console.log('No se encontró marcación para esta fecha');
            }
        })
        .catch(error => {
            console.error('Error al obtener marcación:', error);
        });
        
    }
}

// Manejar envío del formulario con AJAX
document.addEventListener('DOMContentLoaded', function() {
    const formHorario = document.getElementById('form-horario-manual');
    const mensajeDiv = document.getElementById('mensaje-horario');
    const btnGuardar = document.getElementById('btn-guardar-horario');
    
    if (formHorario) {
        formHorario.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const cedula = document.getElementById('cedula_hidden').value;
            const fecha = document.getElementById('fecha_input').value;
            const horaEntrada = document.getElementById('hora_entrada').value;
            const horaSalida = document.getElementById('hora_salida').value;
            
            if (!cedula || !fecha) {
                mostrarMensaje('Cédula y fecha son obligatorios', 'error');
                return;
            }
            
            // Deshabilitar botón mientras se guarda
            btnGuardar.disabled = true;
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            
            // Hacer petición AJAX para guardar
            fetch('<?php echo BASE_URL; ?>/pages/mantenimiento/guardar_marcacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cedula: cedula,
                    fecha: fecha,
                    hora_entrada: horaEntrada || null,
                    hora_salida: horaSalida || null
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarMensaje(data.message, 'success');
                    // Ocultar mensaje después de 3 segundos
                    setTimeout(function() {
                        mensajeDiv.style.display = 'none';
                    }, 3000);
                } else {
                    mostrarMensaje('Error: ' + (data.error || 'Error desconocido'), 'error');
                }
            })
            .catch(error => {
                console.error('Error al guardar marcación:', error);
                mostrarMensaje('Error de comunicación con el servidor', 'error');
            })
            .finally(function() {
                // Rehabilitar botón
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Horario';
            });
        });
    }
    
    function mostrarMensaje(mensaje, tipo) {
        if (mensajeDiv) {
            mensajeDiv.style.display = 'block';
            mensajeDiv.className = 'alert alert-' + (tipo === 'success' ? 'success' : 'error');
            mensajeDiv.innerHTML = '<strong>' + (tipo === 'success' ? '✓' : '✗') + '</strong> ' + mensaje;
        }
    }
});
</script>

