<?php
/**
 * Horario Manual
 * Módulo de Mantenimiento
 * Permite capturar manualmente las horas de entrada y salida para funcionarios
 * en un rango de fechas
 */

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/funciones_calculo_horas.php';

$funcionario = null;
$fechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$fechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$cedulaSeleccionada = isset($_GET['cedula']) ? sanitize($_GET['cedula']) : '';
$marcacionesRango = [];

// Función para generar array de fechas entre desde y hasta
function generarRangoFechas($fechaDesde, $fechaHasta) {
    $fechas = [];
    if (empty($fechaDesde) || empty($fechaHasta)) {
        return $fechas;
    }
    
    $inicio = new DateTime($fechaDesde);
    $fin = new DateTime($fechaHasta);
    
    // Asegurar que fin sea >= inicio
    if ($inicio > $fin) {
        return $fechas;
    }
    
    // Generar todas las fechas del rango (inclusive)
    $fechaActual = clone $inicio;
    while ($fechaActual <= $fin) {
        $fechas[] = $fechaActual->format('Y-m-d');
        $fechaActual->modify('+1 day');
    }
    
    return $fechas;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Búsqueda de funcionario
    if (!empty($busqueda)) {
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
    
    // Si viene cédula por GET, buscar el funcionario
    if (!$funcionario && !empty($cedulaSeleccionada)) {
        $stmt = $db->prepare("SELECT * FROM funcionarios WHERE cedula = ?");
        $stmt->execute([$cedulaSeleccionada]);
        $funcionario = $stmt->fetch();
    }
    
    // Si hay funcionario y rango de fechas, obtener marcaciones del rango
    if ($funcionario && !empty($fechaDesde) && !empty($fechaHasta)) {
        // Validar que fecha_desde <= fecha_hasta
        $desde = new DateTime($fechaDesde);
        $hasta = new DateTime($fechaHasta);
        
        if ($desde <= $hasta) {
            // Generar array de fechas del rango
            $fechasRango = generarRangoFechas($fechaDesde, $fechaHasta);
            
            // Obtener todas las marcaciones del rango en una sola query
            if (!empty($fechasRango)) {
                $placeholders = str_repeat('?,', count($fechasRango) - 1) . '?';
                $stmtMarcaciones = $db->prepare("
                    SELECT fecha, hora_entrada, hora_salida, horas_trabajadas, tiempo_faltante
                    FROM marcaciones 
                    WHERE cedula = ? AND fecha IN ($placeholders)
                ");
                $params = array_merge([$funcionario['cedula']], $fechasRango);
                $stmtMarcaciones->execute($params);
                $marcacionesExistentes = $stmtMarcaciones->fetchAll(PDO::FETCH_ASSOC);
                
                // Crear mapa de marcaciones por fecha para acceso rápido
                $mapaMarcaciones = [];
                foreach ($marcacionesExistentes as $marc) {
                    $mapaMarcaciones[$marc['fecha']] = $marc;
                }
                
                // Crear array completo con todas las fechas del rango
                foreach ($fechasRango as $fecha) {
                    if (isset($mapaMarcaciones[$fecha])) {
                        $marcacionesRango[] = $mapaMarcaciones[$fecha];
                    } else {
                        // Fecha sin marcación
                        $marcacionesRango[] = [
                            'fecha' => $fecha,
                            'hora_entrada' => null,
                            'hora_salida' => null,
                            'horas_trabajadas' => null,
                            'tiempo_faltante' => null
                        ];
                    }
                }
            }
        }
    }
    
} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
    $funcionario = null;
    $marcacionesRango = [];
}
?>

<div class="page-content">
    <div class="info-section" style="margin-bottom: 2rem; padding: 1rem; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;">
        <p><strong>Nota:</strong> Esta sección permite capturar manualmente las horas de entrada y salida para funcionarios en un rango de fechas.</p>
    </div>
    
    <!-- Búsqueda de Funcionario y Rango de Fechas -->
    <div class="search-section" style="margin-bottom: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
        <h3>Buscar Funcionario y Rango de Fechas</h3>
        <form method="GET" action="" id="form-busqueda">
            <input type="hidden" name="seccion" value="horario-manual">
            <?php if ($funcionario): ?>
                <input type="hidden" name="cedula" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; align-items: end; margin-bottom: 1rem;">
                <div>
                    <label for="buscar_funcionario">Buscar por Cédula, Nombre o Apellido:</label>
                    <input type="text" 
                           id="buscar_funcionario" 
                           name="buscar" 
                           value="<?php echo htmlspecialchars($busqueda); ?>"
                           placeholder="Ingrese cédula, nombre o apellido"
                           style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label for="fecha_desde">Fecha Desde:</label>
                    <input type="date" 
                           id="fecha_desde" 
                           name="fecha_desde" 
                           value="<?php echo htmlspecialchars($fechaDesde); ?>"
                           required
                           style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <label for="fecha_hasta">Fecha Hasta:</label>
                    <input type="date" 
                           id="fecha_hasta" 
                           name="fecha_hasta" 
                           value="<?php echo htmlspecialchars($fechaHasta); ?>"
                           required
                           style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </div>
            
            <?php if (!empty($busqueda) || !empty($cedulaSeleccionada) || !empty($fechaDesde) || !empty($fechaHasta)): ?>
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
        
        <?php if ($funcionario && (!empty($fechaDesde) || !empty($fechaHasta)) && (empty($fechaDesde) || empty($fechaHasta))): ?>
            <div class="alert alert-warning" style="margin-top: 1rem;">
                Por favor, seleccione ambas fechas (Desde y Hasta) para ver el rango.
            </div>
        <?php endif; ?>
        
        <?php if ($funcionario && !empty($fechaDesde) && !empty($fechaHasta)): ?>
            <?php
            $desde = new DateTime($fechaDesde);
            $hasta = new DateTime($fechaHasta);
            if ($desde > $hasta):
            ?>
                <div class="alert alert-error" style="margin-top: 1rem;">
                    La fecha "Desde" debe ser menor o igual a la fecha "Hasta".
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Tabla de Marcaciones del Rango -->
    <?php if ($funcionario && !empty($marcacionesRango)): ?>
    <div class="table-section" style="margin-bottom: 2rem;">
        <!-- Información del funcionario -->
        <div style="margin-bottom: 1rem; padding: 1rem; background: #e8f5e9; border-left: 4px solid #4caf50; border-radius: 4px;">
            <p style="margin: 0; font-weight: bold; color: #2e7d32;">
                <i class="fas fa-user"></i> 
                <?php echo htmlspecialchars($funcionario['nombre'] ?? ''); ?> 
                <?php echo htmlspecialchars($funcionario['apellido'] ?? ''); ?> 
                - 
                <?php echo htmlspecialchars($funcionario['cedula']); ?>
            </p>
            <p style="margin: 0.5rem 0 0 0; color: #2e7d32;">
                Rango: <?php echo date('d/m/Y', strtotime($fechaDesde)); ?> - <?php echo date('d/m/Y', strtotime($fechaHasta)); ?>
                (<?php echo count($marcacionesRango); ?> días)
            </p>
        </div>
        
        <!-- Tabla tipo Excel -->
        <div style="overflow-x: auto; background: white; border: 1px solid #ddd; border-radius: 4px;">
            <table class="table-excel" id="tabla-horarios" style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                <thead>
                    <tr style="background: #343a40; color: white;">
                        <th style="padding: 0.75rem; text-align: left; border: 1px solid #dee2e6; min-width: 120px;">Fecha</th>
                        <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6; min-width: 150px;">Hora Entrada</th>
                        <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6; min-width: 150px;">Hora Salida</th>
                        <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6; min-width: 120px;">Horas Trabajadas</th>
                        <th style="padding: 0.75rem; text-align: center; border: 1px solid #dee2e6; min-width: 120px;">Tardanza/Irregular</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marcacionesRango as $index => $marcacion): ?>
                        <tr data-fecha="<?php echo htmlspecialchars($marcacion['fecha']); ?>" style="border-bottom: 1px solid #dee2e6;">
                            <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; font-weight: bold;">
                                <?php echo date('d/m/Y', strtotime($marcacion['fecha'])); ?>
                            </td>
                            <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center;">
                                <input type="text" 
                                       class="input-hora-entrada" 
                                       data-fecha="<?php echo htmlspecialchars($marcacion['fecha']); ?>"
                                       value="<?php 
                                       if ($marcacion['hora_entrada']) {
                                           $hora = new DateTime($marcacion['hora_entrada']);
                                           $horaFormato = $hora->format('g:i');
                                           $ampm = strtolower($hora->format('A'));
                                           $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                           echo htmlspecialchars($horaFormato . ' ' . $ampm);
                                       }
                                       ?>"
                                       style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 3px; text-align: center; font-size: 0.95em; background: #fff;">
                            </td>
                            <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center;">
                                <input type="text" 
                                       class="input-hora-salida" 
                                       data-fecha="<?php echo htmlspecialchars($marcacion['fecha']); ?>"
                                       value="<?php 
                                       if ($marcacion['hora_salida']) {
                                           $hora = new DateTime($marcacion['hora_salida']);
                                           $horaFormato = $hora->format('g:i');
                                           $ampm = strtolower($hora->format('A'));
                                           $ampm = str_replace(['am', 'pm'], ['a.m.', 'p.m.'], $ampm);
                                           echo htmlspecialchars($horaFormato . ' ' . $ampm);
                                       }
                                       ?>"
                                       style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 3px; text-align: center; font-size: 0.95em; background: #fff;">
                            </td>
                            <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center;">
                                <?php 
                                if ($marcacion['horas_trabajadas']) {
                                    $horas = new DateTime($marcacion['horas_trabajadas']);
                                    echo $horas->format('H:i');
                                } else {
                                    echo '<span style="color: #999;">-</span>';
                                }
                                ?>
                            </td>
                            <td style="padding: 0.5rem 0.75rem; border: 1px solid #dee2e6; text-align: center; <?php 
                                if ($marcacion['tiempo_faltante'] && $marcacion['tiempo_faltante'] !== '00:00:00') {
                                    echo 'background-color: #ffcccc; color: #721c24; font-weight: bold;';
                                }
                            ?>">
                                <?php 
                                if ($marcacion['tiempo_faltante']) {
                                    $faltante = new DateTime($marcacion['tiempo_faltante']);
                                    echo $faltante->format('H:i');
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
        
        <!-- Botón Guardar Todo -->
        <div style="margin-top: 1.5rem; text-align: center;">
            <button type="button" id="btn-guardar-todo" class="btn btn-success" style="padding: 0.75rem 2rem; font-size: 1.1em;">
                <i class="fas fa-save"></i> Guardar Todo
            </button>
        </div>
        
        <!-- Mensaje de resultado -->
        <div id="mensaje-resultado" style="margin-top: 1rem; display: none;"></div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnGuardarTodo = document.getElementById('btn-guardar-todo');
    const mensajeDiv = document.getElementById('mensaje-resultado');
    const cedula = '<?php echo $funcionario ? htmlspecialchars($funcionario['cedula'], ENT_QUOTES) : ''; ?>';
    
    // Función para convertir hora de formato 12h (con a.m./p.m.) a formato 24h (HH:MM:SS)
    function convertirHora12a24(horaTexto) {
        if (!horaTexto || horaTexto.trim() === '') {
            return null;
        }
        
        // Limpiar espacios
        horaTexto = horaTexto.trim();
        
        // Si ya tiene a.m. o p.m., procesarlo
        let esAM = false;
        let esPM = false;
        
        if (horaTexto.toLowerCase().includes('a.m.')) {
            esAM = true;
            horaTexto = horaTexto.replace(/a\.m\./gi, '').trim();
        } else if (horaTexto.toLowerCase().includes('p.m.')) {
            esPM = true;
            horaTexto = horaTexto.replace(/p\.m\./gi, '').trim();
        } else if (horaTexto.toLowerCase().includes('am')) {
            esAM = true;
            horaTexto = horaTexto.replace(/am/gi, '').trim();
        } else if (horaTexto.toLowerCase().includes('pm')) {
            esPM = true;
            horaTexto = horaTexto.replace(/pm/gi, '').trim();
        }
        
        // Validar formato HH:MM o H:MM
        const regexHora = /^(\d{1,2}):(\d{2})$/;
        const match = horaTexto.match(regexHora);
        
        if (!match) {
            return null; // Formato inválido
        }
        
        let horas = parseInt(match[1], 10);
        const minutos = parseInt(match[2], 10);
        
        // Validar rangos
        if (horas < 0 || horas > 23 || minutos < 0 || minutos > 59) {
            return null;
        }
        
        // Si no se especificó a.m./p.m., intentar inferir
        if (!esAM && !esPM) {
            // Si la hora es 0-11, asumir a.m. (a menos que sea 0, entonces asumir 12 a.m.)
            // Si la hora es 12-23, asumir p.m. (pero 12-23 en formato 24h ya es p.m.)
            if (horas >= 12) {
                esPM = true;
            } else {
                esAM = true;
            }
        }
        
        // Convertir a formato 24h
        if (esAM) {
            if (horas === 12) {
                horas = 0; // 12 a.m. = 00:00
            }
            // Ya está en formato correcto (0-11)
        } else if (esPM) {
            if (horas !== 12) {
                horas += 12; // 1-11 p.m. = 13-23
            }
            // 12 p.m. = 12:00 (ya está correcto)
        }
        
        // Formatear a HH:MM:SS
        return String(horas).padStart(2, '0') + ':' + String(minutos).padStart(2, '0') + ':00';
    }
    
    // Función para formatear hora automáticamente con a.m./p.m.
    function formatearHoraConAMPM(input) {
        const valor = input.value.trim();
        
        if (valor === '') {
            return;
        }
        
        // Si ya tiene a.m. o p.m., no hacer nada
        if (valor.toLowerCase().includes('a.m.') || valor.toLowerCase().includes('p.m.')) {
            return;
        }
        
        // Validar formato HH:MM o H:MM
        const regexHora = /^(\d{1,2}):(\d{2})$/;
        const match = valor.match(regexHora);
        
        if (!match) {
            return; // Formato inválido, no formatear
        }
        
        let horas = parseInt(match[1], 10);
        const minutos = match[2];
        
        // Determinar a.m./p.m.
        let ampm = '';
        if (horas === 0) {
            horas = 12;
            ampm = 'a.m.';
        } else if (horas < 12) {
            ampm = 'a.m.';
        } else if (horas === 12) {
            ampm = 'p.m.';
        } else {
            horas -= 12;
            ampm = 'p.m.';
        }
        
        // Actualizar el valor del input
        input.value = horas + ':' + minutos + ' ' + ampm;
    }
    
    // Aplicar formateo automático a todos los inputs de hora
    const inputsHora = document.querySelectorAll('.input-hora-entrada, .input-hora-salida');
    inputsHora.forEach(function(input) {
        // Formatear al perder el foco (blur)
        input.addEventListener('blur', function() {
            formatearHoraConAMPM(this);
        });
        
        // Permitir solo números, dos puntos y espacios
        input.addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.which);
            if (!/[0-9:]/.test(char) && e.which !== 32) {
                e.preventDefault();
            }
        });
    });
    
    // Validar formulario de búsqueda
    const formBusqueda = document.getElementById('form-busqueda');
    if (formBusqueda) {
        formBusqueda.addEventListener('submit', function(e) {
            const fechaDesde = document.getElementById('fecha_desde').value;
            const fechaHasta = document.getElementById('fecha_hasta').value;
            
            if (!fechaDesde || !fechaHasta) {
                e.preventDefault();
                alert('Por favor, seleccione ambas fechas (Desde y Hasta)');
                return false;
            }
            
            if (fechaDesde > fechaHasta) {
                e.preventDefault();
                alert('La fecha "Desde" debe ser menor o igual a la fecha "Hasta"');
                return false;
            }
        });
    }
    
    // Guardar todas las marcaciones
    if (btnGuardarTodo && cedula) {
        btnGuardarTodo.addEventListener('click', function() {
            // Recopilar todas las marcaciones de la tabla
            const marcaciones = [];
            const filas = document.querySelectorAll('#tabla-horarios tbody tr');
            
            filas.forEach(function(fila) {
                const fecha = fila.getAttribute('data-fecha');
                const inputEntrada = fila.querySelector('.input-hora-entrada');
                const inputSalida = fila.querySelector('.input-hora-salida');
                
                if (fecha) {
                    // Convertir horas de formato 12h a 24h antes de enviar
                    let horaEntrada24 = null;
                    let horaSalida24 = null;
                    
                    if (inputEntrada && inputEntrada.value.trim() !== '') {
                        horaEntrada24 = convertirHora12a24(inputEntrada.value);
                        if (!horaEntrada24) {
                            mostrarMensaje('Error: Formato de hora de entrada inválido en fecha ' + fecha, 'error');
                            return;
                        }
                    }
                    
                    if (inputSalida && inputSalida.value.trim() !== '') {
                        horaSalida24 = convertirHora12a24(inputSalida.value);
                        if (!horaSalida24) {
                            mostrarMensaje('Error: Formato de hora de salida inválido en fecha ' + fecha, 'error');
                            return;
                        }
                    }
                    
                    marcaciones.push({
                        fecha: fecha,
                        hora_entrada: horaEntrada24,
                        hora_salida: horaSalida24
                    });
                }
            });
            
            if (marcaciones.length === 0) {
                mostrarMensaje('No hay marcaciones para guardar', 'error');
                return;
            }
            
            // Deshabilitar botón mientras se guarda
            btnGuardarTodo.disabled = true;
            btnGuardarTodo.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            
            // Hacer petición AJAX para guardar todas las marcaciones
            fetch('<?php echo BASE_URL; ?>/pages/mantenimiento/guardar_marcaciones_masivo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cedula: cedula,
                    marcaciones: marcaciones
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarMensaje(data.message, 'success');
                    // Recargar la página después de 2 segundos para mostrar datos actualizados
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    mostrarMensaje('Error: ' + (data.error || 'Error desconocido'), 'error');
                }
            })
            .catch(error => {
                console.error('Error al guardar marcaciones:', error);
                mostrarMensaje('Error de comunicación con el servidor', 'error');
            })
            .finally(function() {
                // Rehabilitar botón
                btnGuardarTodo.disabled = false;
                btnGuardarTodo.innerHTML = '<i class="fas fa-save"></i> Guardar Todo';
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

<style>
/* Estilos para tabla tipo Excel */
.table-excel {
    font-family: Arial, sans-serif;
}

.table-excel tbody tr:hover {
    background-color: #f5f5f5;
}

.table-excel tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.table-excel tbody tr:nth-child(even):hover {
    background-color: #f0f0f0;
}

.table-excel input[type="time"] {
    font-size: 0.9em;
}

.table-excel input[type="time"]:focus {
    outline: 2px solid #2196f3;
    border-color: #2196f3;
}
</style>
