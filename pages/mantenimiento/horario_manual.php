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
    // tipo: 'entrada' o 'salida' para inferir mejor a.m./p.m.
    function formatearHoraConAMPM(input, tipo = 'entrada') {
        const valor = input.value.trim();
        
        if (valor === '') {
            return;
        }
        
        // Detectar si el usuario especificó a.m./p.m. manualmente
        let valorLimpio = valor;
        let tieneAMPM = false;
        let esAM = false;
        
        // Buscar a.m. o p.m. (con o sin puntos, con o sin espacios)
        const regexAM = /a\.?m\.?/i;
        const regexPM = /p\.?m\.?/i;
        
        if (regexAM.test(valorLimpio)) {
            // El usuario especificó a.m. (con o sin puntos)
            valorLimpio = valorLimpio.replace(/a\.?m\.?/gi, '').trim();
            tieneAMPM = true;
            esAM = true;
        } else if (regexPM.test(valorLimpio)) {
            // El usuario especificó p.m. (con o sin puntos)
            valorLimpio = valorLimpio.replace(/p\.?m\.?/gi, '').trim();
            tieneAMPM = true;
            esAM = false;
        }
        
        // Validar formato HH:MM o H:MM
        const regexHora = /^(\d{1,2}):(\d{2})$/;
        const match = valorLimpio.match(regexHora);
        
        if (!match) {
            // Si no coincide con el formato esperado pero tiene a.m./p.m., normalizar el formato
            if (tieneAMPM) {
                // Intentar extraer la hora aunque el formato no sea perfecto
                const matchHora = valor.match(/^(\d{1,2}):(\d{2})/);
                if (matchHora) {
                    let horas = parseInt(matchHora[1], 10);
                    const minutos = matchHora[2];
                    const ampm = esAM ? 'a.m.' : 'p.m.';
                    // Ajustar horas si es necesario
                    if (esAM && horas === 0) {
                        horas = 12;
                    }
                    input.value = horas + ':' + minutos + ' ' + ampm;
                }
            }
            return;
        }
        
        let horas = parseInt(match[1], 10);
        const minutos = match[2];
        
        // Determinar a.m./p.m.
        let ampm = '';
        
        if (tieneAMPM) {
            // El usuario especificó a.m. o p.m., normalizar a formato con puntos (a.m./p.m.)
            ampm = esAM ? 'a.m.' : 'p.m.';
            // Ajustar horas si es necesario
            if (esAM && horas === 0) {
                horas = 12; // 0:00 a.m. = 12:00 a.m.
            }
            // Si es p.m. y la hora es 1-11, mantenerla (ya está en formato 12h)
            // Si es p.m. y la hora es 12, mantenerla
        } else {
            // No especificó a.m./p.m., inferir según el tipo
            if (horas === 0) {
                horas = 12;
                ampm = 'a.m.';
            } else if (horas < 12) {
                // 1-11: inferir según el tipo
                if (tipo === 'entrada') {
                    ampm = 'a.m.'; // Sugerencia para entrada
                } else {
                    ampm = 'p.m.'; // Sugerencia para salida
                }
            } else if (horas === 12) {
                ampm = 'p.m.';
            } else {
                // 13-23: convertir a formato 12h y marcar como p.m.
                horas -= 12;
                ampm = 'p.m.';
            }
        }
        
        // Actualizar el valor del input con formato normalizado (a.m./p.m. con puntos)
        input.value = horas + ':' + minutos + ' ' + ampm;
    }
    
    // Guardar valores iniciales de todos los inputs para detectar cambios
    const valoresIniciales = new Map();
    const inputsHora = document.querySelectorAll('.input-hora-entrada, .input-hora-salida');
    inputsHora.forEach(function(input) {
        // Guardar valor inicial
        const fecha = input.getAttribute('data-fecha');
        const tipo = input.classList.contains('input-hora-entrada') ? 'entrada' : 'salida';
        const clave = fecha + '_' + tipo;
        valoresIniciales.set(clave, input.value.trim());
        
        // Formatear al perder el foco (blur), pasando el tipo (entrada/salida)
        // Siempre normalizar el formato a.m./p.m. con puntos
        input.addEventListener('blur', function() {
            const valor = this.value.trim();
            // Si el campo está vacío, no hacer nada
            if (valor === '') {
                return;
            }
            // Siempre formatear para normalizar a.m./p.m. con puntos
            // Esto asegura que "4:00 pm" se convierta a "4:00 p.m."
            const tipoInput = this.classList.contains('input-hora-entrada') ? 'entrada' : 'salida';
            formatearHoraConAMPM(this, tipoInput);
        });
        
        // Permitir números, dos puntos, espacios y letras (para a.m./p.m.)
        input.addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.which);
            // Permitir números, dos puntos, espacios y letras a, m, p (para escribir a.m./p.m.)
            if (!/[0-9:]/.test(char) && e.which !== 32 && 
                !/[aApPmM]/.test(char) && e.which !== 46) { // 46 es el punto (.)
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
            // Recopilar solo las marcaciones que fueron modificadas
            const marcaciones = [];
            const filas = document.querySelectorAll('#tabla-horarios tbody tr');
            let hayErrores = false;
            
            filas.forEach(function(fila) {
                const fecha = fila.getAttribute('data-fecha');
                const inputEntrada = fila.querySelector('.input-hora-entrada');
                const inputSalida = fila.querySelector('.input-hora-salida');
                
                if (!fecha) return;
                
                // Obtener valores actuales
                const valorEntradaActual = inputEntrada ? inputEntrada.value.trim() : '';
                const valorSalidaActual = inputSalida ? inputSalida.value.trim() : '';
                
                // Obtener valores iniciales
                const claveEntrada = fecha + '_entrada';
                const claveSalida = fecha + '_salida';
                const valorEntradaInicial = valoresIniciales.get(claveEntrada) || '';
                const valorSalidaInicial = valoresIniciales.get(claveSalida) || '';
                
                // Verificar si hubo cambios
                const entradaCambio = valorEntradaActual !== valorEntradaInicial;
                const salidaCambio = valorSalidaActual !== valorSalidaInicial;
                
                // Solo procesar si hubo cambios
                if (entradaCambio || salidaCambio) {
                    // Convertir horas de formato 12h a 24h antes de enviar
                    let horaEntrada24 = null;
                    let horaSalida24 = null;
                    
                    if (valorEntradaActual !== '') {
                        horaEntrada24 = convertirHora12a24(valorEntradaActual);
                        if (!horaEntrada24) {
                            mostrarMensaje('Error: Formato de hora de entrada inválido en fecha ' + fecha, 'error');
                            hayErrores = true;
                            return;
                        }
                    }
                    
                    if (valorSalidaActual !== '') {
                        horaSalida24 = convertirHora12a24(valorSalidaActual);
                        if (!horaSalida24) {
                            mostrarMensaje('Error: Formato de hora de salida inválido en fecha ' + fecha, 'error');
                            hayErrores = true;
                            return;
                        }
                    }
                    
                    // Solo agregar si hay al menos un cambio válido
                    marcaciones.push({
                        fecha: fecha,
                        hora_entrada: horaEntrada24,
                        hora_salida: horaSalida24
                    });
                }
            });
            
            if (hayErrores) {
                return;
            }
            
            if (marcaciones.length === 0) {
                mostrarMensaje('No hay cambios para guardar', 'info');
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
            let clase = 'alert alert-';
            let icono = '';
            
            if (tipo === 'success') {
                clase += 'success';
                icono = '✓';
            } else if (tipo === 'error') {
                clase += 'error';
                icono = '✗';
            } else if (tipo === 'info') {
                clase += 'info';
                icono = 'ℹ';
            } else {
                clase += 'info';
                icono = 'ℹ';
            }
            
            mensajeDiv.className = clase;
            mensajeDiv.innerHTML = '<strong>' + icono + '</strong> ' + mensaje;
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
