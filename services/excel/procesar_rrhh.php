<?php
/**
 * Procesar Excel RRHH - Importar desde un solo archivo Excel
 * Sistema RRHH
 * 
 * Procesa el archivo "personal RRHH.xlsx" y lo importa a la base de datos
 * 
 * Mapeo:
 * - CEDULA (con guiones) → cedula
 * - NOMBRE Y APELLIDO → NO se graba
 * - DIRECCIÓN O SEDE → dividir por guion → sede_provincia y Direccion
 * - FECHA DE NACIMIENTO → fecha_nacimiento
 * - EDAD → edad
 * - TIPO DE SANGRE → sangre
 * - POSICIÓN → no_posicion
 * - POSICIÓN FUNCIONAL → posicion_funcional
 * - FECHA DE INICIO → fecha_inicio
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
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
    
    $exitosos = 0;
    $actualizados = 0;
    $errores = [];
    
    // Obtener lista de columnas disponibles (solo en la primera fila para debug)
    $columnasDisponibles = [];
    if (count($excelData['data']) > 0) {
        $columnasDisponibles = array_keys($excelData['data'][0]);
        // Filtrar columnas "Unnamed"
        $columnasDisponibles = array_filter($columnasDisponibles, function($col) {
            return stripos($col, 'unnamed') === false;
        });
    }
    
    foreach ($excelData['data'] as $indice => $fila) {
        try {
            // Extraer datos de la fila (búsqueda flexible de columnas)
            $cedula = null;
            $direccionSede = null;
            $fechaNacimiento = null;
            $edad = null;
            $sangre = null;
            $posicion = null;
            $posicionFuncional = null;
            $fechaInicio = null;
            
            // Buscar columnas (case-insensitive)
            foreach ($fila as $columna => $valor) {
                // Ignorar columnas "Unnamed"
                if (stripos($columna, 'unnamed') !== false) {
                    continue;
                }
                
                $columnaUpper = strtoupper(trim($columna));
                $columnaLimpia = preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ]/', '', $columnaUpper);
                
                // Convertir valor a string si es un objeto (fechas de Excel)
                if (is_object($valor)) {
                    $valor = (string)$valor;
                }
                
                $valor = trim($valor ?? '');
                
                // Mapear según los nombres esperados
                if ($columnaUpper === 'CEDULA' || 
                    $columnaUpper === 'CÉDULA' ||
                    stripos($columna, 'cedula') !== false) {
                    $cedula = $valor;
                }
                elseif (stripos($columna, 'dirección') !== false || 
                        stripos($columna, 'direccion') !== false || 
                        stripos($columna, 'sede') !== false) {
                    // Puede estar como "DIRECCIÓN O SEDE" o similar
                    $direccionSede = $valor;
                }
                elseif (stripos($columna, 'fecha') !== false && 
                        stripos($columna, 'nacimiento') !== false) {
                    $fechaNacimiento = $valor;
                }
                elseif ($columnaUpper === 'EDAD') {
                    $edad = !empty($valor) ? intval($valor) : null;
                }
                elseif (stripos($columna, 'sangre') !== false || 
                       (stripos($columna, 'tipo') !== false && stripos($columna, 'sangre') !== false)) {
                    $sangre = $valor;
                }
                // IMPORTANTE: Buscar "POSICIÓN FUNCIONAL" como cadena completa PRIMERO
                // Esto mapea a posicion_funcional en la BD
                elseif (stripos($columnaUpper, 'POSICIÓN FUNCIONAL') !== false || 
                        stripos($columnaUpper, 'POSICION FUNCIONAL') !== false ||
                        $columnaUpper === 'POSICIÓN FUNCIONAL' ||
                        $columnaUpper === 'POSICION FUNCIONAL') {
                    $posicionFuncional = !empty($valor) ? trim((string)$valor) : '';
                }
                // Buscar solo "POSICIÓN" (sin "FUNCIONAL") para el número
                // Esto mapea a no_posicion en la BD
                elseif (($columnaUpper === 'POSICIÓN' || $columnaUpper === 'POSICION') && 
                        stripos($columnaUpper, 'FUNCIONAL') === false) {
                    $posicion = !empty($valor) ? intval($valor) : null;
                }
                elseif (stripos($columna, 'fecha') !== false && 
                        stripos($columna, 'inicio') !== false) {
                    $fechaInicio = $valor;
                }
            }
            
            // Validar cédula (campo obligatorio)
            if (empty($cedula)) {
                $errores[] = "Fila " . ($indice + 1) . ": No se encontró la cédula";
                continue;
            }
            
            // Validar cédula con función de validación
            if (!validarCedula($cedula)) {
                $errores[] = "Fila " . ($indice + 1) . ": Cédula inválida: $cedula";
                continue;
            }
            
            // La cédula se guarda CON guiones como está en el Excel
            $cedulaFormateada = $cedula; // Mantener formato original con guiones
            
            // Procesar "DIRECCIÓN O SEDE" - dividir por guion
            $sedeProvincia = '';
            $direccion = '';
            if (!empty($direccionSede)) {
                $partes = explode('-', $direccionSede, 2);
                $sedeProvincia = trim($partes[0] ?? '');
                $direccion = trim($partes[1] ?? '');
                
                // Si no hay guion, asignar todo a sede_provincia
                if (empty($direccion) && !empty($sedeProvincia)) {
                    $direccion = '';
                }
            }
            
            // Validar y formatear fechas
            $fechaNac = null;
            if (!empty($fechaNacimiento)) {
                $fechaNac = formatearFechaBD($fechaNacimiento);
                if (!$fechaNac) {
                    $errores[] = "Fila " . ($indice + 1) . ": Fecha de nacimiento inválida: $fechaNacimiento";
                    continue;
                }
            }
            
            $fechaIni = null;
            if (!empty($fechaInicio)) {
                $fechaIni = formatearFechaBD($fechaInicio);
                if (!$fechaIni) {
                    $errores[] = "Fila " . ($indice + 1) . ": Fecha de inicio inválida: $fechaInicio";
                    continue;
                }
            }
            
            // Calcular edad si no está presente pero hay fecha de nacimiento
            if (empty($edad) && $fechaNac) {
                $edad = calcularEdad($fechaNac);
            }
            
            // Validar solo cedula como campo obligatorio
            // Todos los demás campos ahora permiten NULL según la migración
            
            // Asignar valores por defecto si están vacíos (opcional, para evitar warnings)
            if (empty($edad)) {
                $edad = null; // Permitir NULL
            }
            
            // Los demás campos pueden ser NULL, no se validan como obligatorios
            
            // Preparar datos para insertar/actualizar
            // Todos los campos (excepto cedula) pueden ser NULL
            $funcionario = [
                'cedula' => $cedulaFormateada,
                'nombre' => null, // No se graba según requerimiento
                'apellido' => null, // No se graba según requerimiento
                'fecha_nacimiento' => $fechaNac ?: null,
                'edad' => !empty($edad) ? intval($edad) : null,
                'sangre' => !empty($sangre) ? sanitize($sangre) : null,
                'no_posicion' => !empty($posicion) && $posicion > 0 ? intval($posicion) : null,
                'posicion_funcional' => !empty($posicionFuncional) ? mb_substr(sanitize($posicionFuncional), 0, 100) : null,
                'fecha_inicio' => $fechaIni ?: null,
                'sede_provincia' => !empty($sedeProvincia) ? sanitize($sedeProvincia) : null,
                'Direccion' => !empty($direccion) ? sanitize($direccion) : null
            ];
            
            // Verificar si ya existe (usar cédula normalizada para búsqueda)
            $cedulaNormalizada = normalizarCedula($cedulaFormateada);
            $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ? OR cedula = ?");
            $stmtCheck->execute([$cedulaFormateada, $cedulaNormalizada]);
            $existe = $stmtCheck->fetch();
            
            if ($existe) {
                // Actualizar registro existente
                $stmt = $db->prepare("
                    UPDATE funcionarios SET
                        fecha_nacimiento = ?, edad = ?,
                        sangre = ?, no_posicion = ?, posicion_funcional = ?,
                        fecha_inicio = ?, sede_provincia = ?, Direccion = ?
                    WHERE cedula = ?
                ");
                $stmt->execute([
                    $funcionario['fecha_nacimiento'],
                    $funcionario['edad'],
                    $funcionario['sangre'],
                    $funcionario['no_posicion'],
                    $funcionario['posicion_funcional'],
                    $funcionario['fecha_inicio'],
                    $funcionario['sede_provincia'],
                    $funcionario['Direccion'],
                    $existe['cedula'] // Usar la cédula que encontró en la BD
                ]);
                $actualizados++;
            } else {
                // Insertar nuevo registro
                $stmt = $db->prepare("
                    INSERT INTO funcionarios (
                        cedula, nombre, apellido, fecha_nacimiento, edad,
                        sangre, no_posicion, posicion_funcional,
                        fecha_inicio, sede_provincia, Direccion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $funcionario['cedula'],
                    $funcionario['nombre'],
                    $funcionario['apellido'],
                    $funcionario['fecha_nacimiento'],
                    $funcionario['edad'],
                    $funcionario['sangre'],
                    $funcionario['no_posicion'],
                    $funcionario['posicion_funcional'],
                    $funcionario['fecha_inicio'],
                    $funcionario['sede_provincia'],
                    $funcionario['Direccion']
                ]);
                $exitosos++;
            }
            
        } catch (PDOException $e) {
            $errores[] = "Fila " . ($indice + 1) . ": Error de BD - " . $e->getMessage();
        } catch (Exception $e) {
            $errores[] = "Fila " . ($indice + 1) . ": " . $e->getMessage();
        }
    }
    
    // Preparar respuesta
    $totalProcesados = count($excelData['data']);
    $mensaje = "Procesamiento completado. ";
    $mensaje .= "Nuevos registros: $exitosos, ";
    $mensaje .= "Actualizados: $actualizados";
    
    if (count($errores) > 0) {
        $mensaje .= ", Errores: " . count($errores);
    }
    
    echo json_encode([
        'success' => true,
        'mensaje' => $mensaje,
        'estadisticas' => [
            'nuevos' => $exitosos,
            'actualizados' => $actualizados,
            'errores' => count($errores),
            'total_procesados' => $totalProcesados
        ],
        'errores' => array_slice($errores, 0, 50) // Primeros 50 errores
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar: ' . $e->getMessage()
    ]);
}

/**
 * Formatear fecha para base de datos (YYYY-MM-DD)
 * Maneja múltiples formatos: español, inglés, objetos Date, números de Excel
 */
function formatearFechaBD($fecha) {
    if (empty($fecha)) return null;
    
    // Si es un objeto, intentar convertirlo a string
    if (is_object($fecha)) {
        $fecha = (string)$fecha;
    }
    
    // Limpiar espacios extra
    $fecha = trim($fecha);
    
    // Si viene como objeto Date serializado (ej: "Thu, 28 Sep 1978 00:00:00 GMT")
    // strtotime puede manejarlo directamente
    $timestamp = strtotime($fecha);
    if ($timestamp !== false) {
        // Verificar que no sea una fecha inválida (1970-01-01 sería un error común)
        $fechaFormateada = date('Y-m-d', $timestamp);
        if ($fechaFormateada !== '1970-01-01' || strpos($fecha, '1970') !== false) {
            return $fechaFormateada;
        }
    }
    
    // Manejar fechas en español: "4 de octubre de 2024", "23 de septiembre de 2024"
    if (preg_match('/(\d+)\s+de\s+([a-z]+)\s+de\s+(\d{4})/i', $fecha, $matches)) {
        $dia = intval($matches[1]);
        $mesTexto = strtolower(trim($matches[2]));
        $año = intval($matches[3]);
        
        // Mapear meses en español
        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];
        
        if (isset($meses[$mesTexto])) {
            $mes = $meses[$mesTexto];
            // Validar fecha
            if (checkdate($mes, $dia, $año)) {
                return sprintf('%04d-%02d-%02d', $año, $mes, $dia);
            }
        }
    }
    
    // Intentar diferentes formatos comunes de Excel
    $formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y', 'm/d/Y', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'];
    
    foreach ($formatos as $formato) {
        $fechaObj = DateTime::createFromFormat($formato, $fecha);
        if ($fechaObj !== false) {
            $fechaFormateada = $fechaObj->format('Y-m-d');
            // Verificar que sea una fecha válida (no 1970-01-01 por error)
            if ($fechaFormateada !== '1970-01-01') {
                return $fechaFormateada;
            }
        }
    }
    
    // Intentar con strtotime otra vez (puede manejar muchos formatos)
    $timestamp = strtotime($fecha);
    if ($timestamp !== false && $timestamp > 0) {
        $fechaFormateada = date('Y-m-d', $timestamp);
        // Evitar 1970-01-01 a menos que la fecha original lo mencione
        if ($fechaFormateada !== '1970-01-01' || strpos($fecha, '1970') !== false) {
            return $fechaFormateada;
        }
    }
    
    // Si viene como número serial de Excel (ej: 45000)
    if (is_numeric($fecha)) {
        $excelDate = floatval($fecha);
        // Números menores a 1 probablemente no son fechas de Excel
        if ($excelDate >= 1) {
            // Excel usa fecha base 1900-01-01, pero PHP usa 1970-01-01
            // Necesitamos ajustar: Excel serial - 25569 = días desde 1970
            $unixTimestamp = ($excelDate - 25569) * 86400;
            if ($unixTimestamp > 0) {
                return date('Y-m-d', $unixTimestamp);
            }
        }
    }
    
    return null;
}
?>

