<?php
/**
 * Procesar Excel Marcaciones Biométricas
 * Sistema RRHH
 * 
 * Procesa el archivo Excel de marcaciones biométricas y guarda en tabla marcaciones
 * 
 * Mapeo:
 * - ID de Usuario (columna A) → comparar con cedula en funcionarios
 * - Grabar fecha (columna F) → fecha
 * - Hora mas temprana (columna H) → hora_entrada
 * - última Hora (columna I) → hora_salida
 * 
 * Encabezados en fila 2 (índice 1)
 * Datos desde fila 3 en adelante
 */

header('Content-Type: application/json');
// Habilitar logging de errores para debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/funciones_calculo_horas.php';
require_once __DIR__ . '/../../includes/funciones_deteccion_almuerzo.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Solo administradores pueden procesar/modificar
if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permisos para realizar esta acción']);
    exit();
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Obtener datos JSON del microservicio
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['excel_data'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos. Se requiere el archivo Excel.']);
    exit();
}

$excelData = $data['excel_data'];

try {
    // Validar que los datos tengan la estructura correcta
    if (!isset($excelData['data']) || !is_array($excelData['data'])) {
        throw new Exception('Estructura de datos inválida. El archivo no contiene datos.');
    }

    $db = Database::getInstance()->getConnection();
    
    // Verificar que los campos de almuerzo existan en la tabla (si no existen, usar campos antiguos)
    $camposAlmuerzoExisten = false;
    try {
        $stmtCheck = $db->query("SHOW COLUMNS FROM marcaciones LIKE 'almuerzo_entrada'");
        $camposAlmuerzoExisten = $stmtCheck->rowCount() > 0;
    } catch (PDOException $e) {
        // Si hay error, asumir que no existen
        $camposAlmuerzoExisten = false;
    }
    
    // Verificar si existe el campo todas_marcaciones
    $campoTodasMarcacionesExiste = false;
    try {
        $stmtCheck = $db->query("SHOW COLUMNS FROM marcaciones LIKE 'todas_marcaciones'");
        $campoTodasMarcacionesExiste = $stmtCheck->rowCount() > 0;
    } catch (PDOException $e) {
        $campoTodasMarcacionesExiste = false;
    }
    
    $totalProcesados = 0;
    $marcacionesGuardadas = 0;
    $marcacionesActualizadas = 0;
    $noEncontrados = [];
    $errores = [];
    
    // Obtener todas las cédulas de la BD y normalizarlas para matching eficiente
    $stmtAllCedulas = $db->query("SELECT cedula FROM funcionarios");
    $todasLasCedulas = $stmtAllCedulas->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear un mapa de cédulas normalizadas -> cédula original
    $mapaCedulas = [];
    foreach ($todasLasCedulas as $row) {
        $cedulaOriginal = $row['cedula'];
        $cedulaNormalizada = normalizarCedula($cedulaOriginal);
        $mapaCedulas[$cedulaNormalizada] = $cedulaOriginal;
    }
    
    // Obtener las columnas detectadas por el microservicio
    $columnasDetectadas = isset($excelData['columns']) ? $excelData['columns'] : [];
    
    // Preparar statement para insertar/actualizar marcaciones
    // Si los campos de almuerzo no existen, usar la estructura antigua
    if ($camposAlmuerzoExisten) {
        if ($campoTodasMarcacionesExiste) {
            // Incluir todas_marcaciones si existe
            $stmtMarcacion = $db->prepare("
                INSERT INTO marcaciones (cedula, fecha, hora_entrada, todas_marcaciones, almuerzo_salida, almuerzo_entrada, hora_salida, horas_trabajadas, tiempo_faltante, fecha_importacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    hora_entrada = VALUES(hora_entrada),
                    todas_marcaciones = VALUES(todas_marcaciones),
                    almuerzo_salida = VALUES(almuerzo_salida),
                    almuerzo_entrada = VALUES(almuerzo_entrada),
                    hora_salida = VALUES(hora_salida),
                    horas_trabajadas = VALUES(horas_trabajadas),
                    tiempo_faltante = VALUES(tiempo_faltante),
                    fecha_importacion = NOW()
            ");
        } else {
            // Sin todas_marcaciones
            $stmtMarcacion = $db->prepare("
                INSERT INTO marcaciones (cedula, fecha, hora_entrada, almuerzo_salida, almuerzo_entrada, hora_salida, horas_trabajadas, tiempo_faltante, fecha_importacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    hora_entrada = VALUES(hora_entrada),
                    almuerzo_salida = VALUES(almuerzo_salida),
                    almuerzo_entrada = VALUES(almuerzo_entrada),
                    hora_salida = VALUES(hora_salida),
                    horas_trabajadas = VALUES(horas_trabajadas),
                    tiempo_faltante = VALUES(tiempo_faltante),
                    fecha_importacion = NOW()
            ");
        }
    } else {
        // Estructura antigua sin campos de almuerzo
        if ($campoTodasMarcacionesExiste) {
            $stmtMarcacion = $db->prepare("
                INSERT INTO marcaciones (cedula, fecha, hora_entrada, todas_marcaciones, hora_salida, horas_trabajadas, tiempo_faltante, fecha_importacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    hora_entrada = VALUES(hora_entrada),
                    todas_marcaciones = VALUES(todas_marcaciones),
                    hora_salida = VALUES(hora_salida),
                    horas_trabajadas = VALUES(horas_trabajadas),
                    tiempo_faltante = VALUES(tiempo_faltante),
                    fecha_importacion = NOW()
            ");
        } else {
            $stmtMarcacion = $db->prepare("
                INSERT INTO marcaciones (cedula, fecha, hora_entrada, hora_salida, horas_trabajadas, tiempo_faltante, fecha_importacion)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    hora_entrada = VALUES(hora_entrada),
                    hora_salida = VALUES(hora_salida),
                    horas_trabajadas = VALUES(horas_trabajadas),
                    tiempo_faltante = VALUES(tiempo_faltante),
                    fecha_importacion = NOW()
            ");
        }
    }
    
    // Array para agrupar marcaciones por cedula + fecha
    // Estructura: $marcacionesAgrupadas[cedula][fecha] = ['entradas' => [], 'salidas' => []]
    $marcacionesAgrupadas = [];
    
    // Filtrar datos: ignorar la primera fila si contiene encabezados (fila 2 del Excel = índice 1)
    $datosFiltrados = [];
    foreach ($excelData['data'] as $indice => $fila) {
        // Convertir fila a array para verificar valores
        $valores = array_values($fila);
        
        // Verificar si la primera fila contiene encabezados (fila 2 del Excel)
        // Si el valor de la columna A es "ID" o similar, es la fila de encabezados, saltarla
        if ($indice === 0 && isset($valores[0])) {
            $primerValor = strtoupper(trim($valores[0] ?? ''));
            if ($primerValor === 'ID' || $primerValor === 'ID DE USUARIO' || $primerValor === 'ID DE USUARIO' || stripos($primerValor, 'id') === 0) {
                continue; // Saltar esta fila (encabezados)
            }
        }
        
        // Agregar fila a datos filtrados
        $datosFiltrados[] = $fila;
    }
    
    // Si no se filtró nada pero hay datos, usar todos los datos
    if (empty($datosFiltrados) && !empty($excelData['data'])) {
        $datosFiltrados = $excelData['data'];
    }
    
    // Procesar cada fila y agrupar por cedula + fecha
    foreach ($datosFiltrados as $indice => $fila) {
        try {
            // Extraer datos de las columnas A, F, H, I, J
            // Columna A (índice 0) = ID de Usuario
            // Columna F (índice 5) = Grabar fecha
            // Columna H (índice 7) = Hora mas temprana
            // Columna I (índice 8) = última Hora
            // Columna J (índice 9) = Hora de Registro (todas las horas del día)
            
            $idUsuario = null;
            $fecha = null;
            $horaEntrada = null;
            $horaSalida = null;
            $horaRegistro = null; // Columna J
            
            // Si tenemos las columnas detectadas, usarlas para mapear
            // Nota: La columna J puede no existir en algunos archivos, por lo que solo requerimos 9 columnas mínimo
            if (!empty($columnasDetectadas) && count($columnasDetectadas) >= 9) {
                // Buscar columnas por nombre o índice
                $columnaA = $columnasDetectadas[0] ?? null; // ID de Usuario
                $columnaF = $columnasDetectadas[5] ?? null; // Grabar fecha
                $columnaH = $columnasDetectadas[7] ?? null; // Hora mas temprana
                $columnaI = $columnasDetectadas[8] ?? null; // última Hora
                $columnaJ = $columnasDetectadas[9] ?? null; // Hora de Registro
                
                // Obtener valores usando los nombres de las columnas
                if ($columnaA && isset($fila[$columnaA])) {
                    $valor = $fila[$columnaA];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $idUsuario = trim((string)($valor ?? ''));
                }
                
                if ($columnaF && isset($fila[$columnaF])) {
                    $valor = $fila[$columnaF];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $fecha = trim((string)($valor ?? ''));
                }
                
                if ($columnaH && isset($fila[$columnaH])) {
                    $valor = $fila[$columnaH];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $horaEntrada = trim((string)($valor ?? ''));
                }
                
                if ($columnaI && isset($fila[$columnaI])) {
                    $valor = $fila[$columnaI];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $horaSalida = trim((string)($valor ?? ''));
                }
                
                if ($columnaJ && isset($fila[$columnaJ])) {
                    $valor = $fila[$columnaJ];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $horaRegistro = trim((string)($valor ?? ''));
                }
            } else {
                // Fallback: convertir fila a array numérico para acceder por índice
                $valores = array_values($fila);
                
                // Columna A (índice 0) = ID de Usuario
                if (isset($valores[0])) {
                    $valor = $valores[0];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $idUsuario = trim((string)($valor ?? ''));
                }
                
                // Columna F (índice 5) = Grabar fecha
                if (isset($valores[5])) {
                    $valor = $valores[5];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $fecha = trim((string)($valor ?? ''));
                }
                
                // Columna H (índice 7) = Hora mas temprana
                if (isset($valores[7])) {
                    $valor = $valores[7];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $horaEntrada = trim((string)($valor ?? ''));
                }
                
                // Columna I (índice 8) = última Hora
                if (isset($valores[8])) {
                    $valor = $valores[8];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $horaSalida = trim((string)($valor ?? ''));
                }
                
                // Columna J (índice 9) = Hora de Registro
                if (isset($valores[9])) {
                    $valor = $valores[9];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $horaRegistro = trim((string)($valor ?? ''));
                }
            }
            
            // Validar ID Usuario (campo obligatorio)
            // Nota: $indice + 3 porque la fila 2 son encabezados, los datos empiezan en fila 3
            if (empty($idUsuario)) {
                $errores[] = "Fila " . ($indice + 3) . ": No se encontró el ID de Usuario";
                continue;
            }
            
            // Normalizar ID del Excel (quitar guiones) para comparación
            $idNormalizado = normalizarCedula($idUsuario);
            
            // Buscar en el mapa de cédulas
            if (!isset($mapaCedulas[$idNormalizado])) {
                // No encontrado: agregar a lista de no encontrados
                $noEncontrados[] = [
                    'id' => $idUsuario,
                    'fila' => $indice + 3
                ];
                continue; // Continuar procesando los demás registros
            }
            
            // Encontrado: obtener cédula original
            $cedulaBD = $mapaCedulas[$idNormalizado];
            
            // Validar y formatear fecha
            if (empty($fecha)) {
                $errores[] = "Fila " . ($indice + 3) . ": No se encontró la fecha";
                continue;
            }
            
            // Formatear fecha a formato MySQL (YYYY-MM-DD)
            $fechaFormateada = formatearFechaBD($fecha);
            if (!$fechaFormateada) {
                $errores[] = "Fila " . ($indice + 3) . ": Fecha inválida: " . $fecha;
                continue;
            }
            
            // Formatear horas a formato MySQL (HH:MM:SS)
            $horaEntradaFormateada = !empty($horaEntrada) ? formatearHoraBD($horaEntrada) : null;
            $horaSalidaFormateada = !empty($horaSalida) ? formatearHoraBD($horaSalida) : null;
            
            // Agrupar por cedula + fecha
            if (!isset($marcacionesAgrupadas[$cedulaBD])) {
                $marcacionesAgrupadas[$cedulaBD] = [];
            }
            if (!isset($marcacionesAgrupadas[$cedulaBD][$fechaFormateada])) {
                $marcacionesAgrupadas[$cedulaBD][$fechaFormateada] = [
                    'entradas' => [],
                    'salidas' => [],
                    'horas_registro' => [] // Todas las horas de la Columna J
                ];
            }
            
            // Agregar entrada y salida a los arrays
            if ($horaEntradaFormateada) {
                $marcacionesAgrupadas[$cedulaBD][$fechaFormateada]['entradas'][] = $horaEntradaFormateada;
            }
            if ($horaSalidaFormateada) {
                $marcacionesAgrupadas[$cedulaBD][$fechaFormateada]['salidas'][] = $horaSalidaFormateada;
            }
            
            // Agregar hora de registro (Columna J) si existe
            // La columna J puede contener múltiples horas separadas por coma, punto y coma, o salto de línea
            if (!empty($horaRegistro)) {
                // Separar múltiples horas si están en una celda
                $horasSeparadas = preg_split('/[,;\n\r]+/', $horaRegistro);
                
                foreach ($horasSeparadas as $hora) {
                    $hora = trim($hora);
                    if (empty($hora)) continue;
                    
                    // Normalizar formato de hora (agregar segundos si faltan)
                    $horaNormalizada = $hora;
                    if (strlen($horaNormalizada) == 5) {
                        $horaNormalizada .= ':00';
                    }
                    
                    // Validar que sea una hora válida
                    $dt = DateTime::createFromFormat('H:i:s', $horaNormalizada);
                    if ($dt === false) {
                        $dt = DateTime::createFromFormat('H:i', $horaNormalizada);
                    }
                    if ($dt !== false) {
                        $marcacionesAgrupadas[$cedulaBD][$fechaFormateada]['horas_registro'][] = $dt->format('H:i:s');
                    }
                }
            }
            
            $totalProcesados++;
            
        } catch (Exception $e) {
            // Nota: $indice + 3 porque la fila 2 son encabezados, los datos empiezan en fila 3
            $errores[] = "Fila " . ($indice + 3) . ": " . $e->getMessage();
        }
    }
    
    // Procesar marcaciones agrupadas: tomar primera entrada y última salida
    foreach ($marcacionesAgrupadas as $cedula => $fechas) {
        foreach ($fechas as $fecha => $marcacion) {
            try {
                // Obtener información del funcionario para obtener horario personalizado
                $stmtFuncionario = $db->prepare("SELECT h_entrada, h_salida FROM funcionarios WHERE cedula = ?");
                $stmtFuncionario->execute([$cedula]);
                $funcionario = $stmtFuncionario->fetch();
                $hEntradaFunc = $funcionario['h_entrada'] ?? null;
                $hSalidaFunc = $funcionario['h_salida'] ?? null;
                
                // Primera entrada (más temprana)
                $horaEntradaFinal = null;
                if (!empty($marcacion['entradas'])) {
                    sort($marcacion['entradas']); // Ordenar de menor a mayor
                    $horaEntradaFinal = $marcacion['entradas'][0]; // Primera (más temprana)
                }
                
                // Última salida (más tardía)
                $horaSalidaFinal = null;
                if (!empty($marcacion['salidas'])) {
                    sort($marcacion['salidas']); // Ordenar de menor a mayor
                    $horaSalidaFinal = $marcacion['salidas'][count($marcacion['salidas']) - 1]; // Última (más tardía)
                }
                
                // Preparar todas las horas de registro como string separado por comas
                $todasMarcacionesStr = null;
                if (!empty($marcacion['horas_registro']) && is_array($marcacion['horas_registro'])) {
                    // Eliminar duplicados y ordenar
                    $horasUnicas = array_unique($marcacion['horas_registro']);
                    sort($horasUnicas);
                    $todasMarcacionesStr = implode(',', $horasUnicas);
                }
                
                // Ya NO calculamos el almuerzo aquí, se calculará al momento de mostrar
                // Dejamos almuerzo_salida y almuerzo_entrada como NULL para que se calcule en listar.php
                $almuerzoEntrada = null;
                $almuerzoSalida = null;
                
                // Calcular horas trabajadas y tiempo faltante usando la función
                $horasTrabajadas = null;
                $tiempoFaltante = null;
                
                if ($horaEntradaFinal && $horaSalidaFinal) {
                    // Usar la función calcularHorasTrabajadas con el horario del funcionario
                    $resultado = calcularHorasTrabajadas($horaEntradaFinal, $horaSalidaFinal, $hEntradaFunc, $hSalidaFunc);
                    
                    if ($resultado) {
                        // Guardar las horas reales en BD (horas_trabajadas)
                        $horasTrabajadas = $resultado['horas_trabajadas'];
                        $tiempoFaltante = $resultado['tiempo_faltante'];
                    }
                }
                
                // Guardar o actualizar marcación
                if ($camposAlmuerzoExisten) {
                    if ($campoTodasMarcacionesExiste) {
                        // Con todas_marcaciones
                        $stmtMarcacion->execute([
                            $cedula,
                            $fecha,
                            $horaEntradaFinal,
                            $todasMarcacionesStr,  // todas_marcaciones
                            $almuerzoSalida,  // almuerzo_salida (NULL, se calculará al mostrar)
                            $almuerzoEntrada, // almuerzo_entrada (NULL, se calculará al mostrar)
                            $horaSalidaFinal,
                            $horasTrabajadas,
                            $tiempoFaltante
                        ]);
                    } else {
                        // Sin todas_marcaciones
                        $stmtMarcacion->execute([
                            $cedula,
                            $fecha,
                            $horaEntradaFinal,
                            $almuerzoSalida,  // almuerzo_salida
                            $almuerzoEntrada, // almuerzo_entrada
                            $horaSalidaFinal,
                            $horasTrabajadas,
                            $tiempoFaltante
                        ]);
                    }
                } else {
                    // Estructura antigua sin campos de almuerzo
                    if ($campoTodasMarcacionesExiste) {
                        $stmtMarcacion->execute([
                            $cedula,
                            $fecha,
                            $horaEntradaFinal,
                            $todasMarcacionesStr,  // todas_marcaciones
                            $horaSalidaFinal,
                            $horasTrabajadas,
                            $tiempoFaltante
                        ]);
                    } else {
                        $stmtMarcacion->execute([
                            $cedula,
                            $fecha,
                            $horaEntradaFinal,
                            $horaSalidaFinal,
                            $horasTrabajadas,
                            $tiempoFaltante
                        ]);
                    }
                }
                
                // Verificar si fue insert o update
                if ($stmtMarcacion->rowCount() > 0) {
                    // Si rowCount es 1, fue INSERT; si es 2, fue UPDATE
                    if ($stmtMarcacion->rowCount() === 1) {
                        $marcacionesGuardadas++;
                    } else {
                        $marcacionesActualizadas++;
                    }
                }
                
            } catch (PDOException $e) {
                $errores[] = "Error al guardar marcación (Cédula: $cedula, Fecha: $fecha): " . $e->getMessage();
            }
        }
    }
    
    // Preparar respuesta
    $mensaje = "Procesamiento completado. ";
    $mensaje .= "Total procesados: $totalProcesados";
    
    if ($marcacionesGuardadas > 0) {
        $mensaje .= ", Marcaciones guardadas: $marcacionesGuardadas";
    }
    if ($marcacionesActualizadas > 0) {
        $mensaje .= ", Marcaciones actualizadas: $marcacionesActualizadas";
    }
    if (count($noEncontrados) > 0) {
        $mensaje .= ", No encontrados: " . count($noEncontrados);
    }
    if (count($errores) > 0) {
        $mensaje .= ", Errores: " . count($errores);
    }
    
    echo json_encode([
        'success' => true,
        'mensaje' => $mensaje,
        'estadisticas' => [
            'total_procesados' => $totalProcesados,
            'marcaciones_guardadas' => $marcacionesGuardadas,
            'marcaciones_actualizadas' => $marcacionesActualizadas,
            'no_encontrados' => count($noEncontrados),
            'errores' => count($errores)
        ],
        'no_encontrados' => array_slice($noEncontrados, 0, 50), // Limitar a 50 para no sobrecargar
        'errores' => array_slice($errores, 0, 50) // Limitar a 50 errores
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar el archivo: ' . $e->getMessage()
    ]);
}

/**
 * Formatear fecha para base de datos (YYYY-MM-DD)
 */
function formatearFechaBD($fecha) {
    if (empty($fecha)) return null;
    
    // Intentar diferentes formatos
    $formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y', 'm/d/Y'];
    
    foreach ($formatos as $formato) {
        $fechaObj = DateTime::createFromFormat($formato, $fecha);
        if ($fechaObj !== false) {
            return $fechaObj->format('Y-m-d');
        }
    }
    
    // Intentar con strtotime como último recurso
    $timestamp = strtotime($fecha);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

/**
 * Formatear hora para base de datos (HH:MM:SS)
 */
function formatearHoraBD($hora) {
    if (empty($hora)) return null;
    
    // Intentar diferentes formatos de hora
    $formatos = ['H:i:s', 'H:i', 'h:i:s A', 'h:i A', 'g:i A'];
    
    foreach ($formatos as $formato) {
        $horaObj = DateTime::createFromFormat($formato, $hora);
        if ($horaObj !== false) {
            return $horaObj->format('H:i:s');
        }
    }
    
    // Intentar con strtotime como último recurso
    $timestamp = strtotime($hora);
    if ($timestamp !== false) {
        return date('H:i:s', $timestamp);
    }
    
    // Si es solo un número, asumir que es hora:minuto
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $hora, $matches)) {
        $hora = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $minuto = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        return "$hora:$minuto:00";
    }
    
    return null;
}
?>

