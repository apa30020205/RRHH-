<?php
/**
 * Procesar Excel - Unir dos archivos y alimentar base de datos
 * Sistema RRHH
 * 
 * Paso 1 y 2: Unir Excel biométrico (ID, Nombre, Apellido) con Excel filtro (todos los demás datos)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Solo administradores pueden procesar/modificar asistencia
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

// Obtener datos JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['biometrico']) || !isset($data['filtro'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos. Se requieren ambos archivos Excel.']);
    exit();
}

$datosBiometrico = $data['biometrico'];
$datosFiltro = $data['filtro'];

try {
    // Validar que los datos tengan la estructura correcta
    if (!isset($datosBiometrico['data']) || !isset($datosFiltro['data'])) {
        throw new Exception('Estructura de datos inválida. Los archivos no contienen datos.');
    }

    $db = Database::getInstance()->getConnection();
    
    // ============================================
    // PASO 1: Procesar Excel Biométrico
    // ============================================
    // Excel 1: Tiene "ID" (cédula sin guiones), "Nombre", "Apellido"
    
    $mapaBiometrico = [];
    $columnasBiometrico = isset($datosBiometrico['columns']) ? $datosBiometrico['columns'] : [];
    $debugBiometrico = [];
    
    foreach ($datosBiometrico['data'] as $indice => $fila) {
        // Buscar la columna "ID" (puede estar en diferentes formatos)
        $cedula = null;
        $nombre = null;
        $apellido = null;
        
        // Buscar ID (cédula sin guiones) - puede estar como "ID", "id", "Id", etc.
        foreach ($fila as $columna => $valor) {
            $columnaUpper = strtoupper(trim($columna));
            $valor = trim($valor);
            
            // Buscar ID - más flexible
            if ($columnaUpper === 'ID' || 
                $columnaUpper === 'CEDULA' || 
                $columnaUpper === 'CÉDULA' ||
                stripos($columna, 'id') !== false && stripos($columna, 'nombre') === false) {
                $cedula = $valor;
            } 
            // Buscar Nombre
            elseif (stripos($columna, 'nombre') !== false && 
                    stripos($columna, 'apellido') === false &&
                    stripos($columna, 'completo') === false) {
                $nombre = $valor;
            } 
            // Buscar Apellido
            elseif (stripos($columna, 'apellido') !== false) {
                $apellido = $valor;
            }
        }
        
        if ($cedula) {
            // Normalizar cédula (sin guiones, mayúsculas)
            $cedulaNormalizada = normalizarCedula($cedula);
            
            if ($nombre && $apellido) {
                $mapaBiometrico[$cedulaNormalizada] = [
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'cedula_original' => $cedula
                ];
            } else {
                // Guardar aunque no tenga nombre/apellido para debugging
                $debugBiometrico[] = "Fila " . ($indice + 1) . ": ID=$cedula (normalizada=$cedulaNormalizada) - Sin nombre/apellido";
            }
        } else {
            $debugBiometrico[] = "Fila " . ($indice + 1) . ": No se encontró ID";
        }
    }
    
    // ============================================
    // PASO 2: Procesar Excel Filtro y Unir
    // ============================================
    // Excel 2: Tiene "CEDULA" (con guiones), y todos los demás datos
    
    $funcionariosCompletos = [];
    $errores = [];
    $exitosos = 0;
    $duplicados = 0;
    $sinMatch = 0;
    
    foreach ($datosFiltro['data'] as $indice => $fila) {
        try {
            // Buscar campos en la fila (case-insensitive)
            $cedula = null;
            $fechaNacimiento = null;
            $edad = null;
            $sangre = null;
            $posicion = null;
            $posicionFuncional = null;
            $fechaInicio = null;
            $direccionSede = null;
            
            foreach ($fila as $columna => $valor) {
                $columnaUpper = strtoupper(trim($columna));
                $valor = trim($valor);
                
                // Mapear columnas - búsqueda más flexible
                if ($columnaUpper === 'CEDULA' || 
                    $columnaUpper === 'CÉDULA' ||
                    $columnaUpper === 'CEDULA ID' ||
                    stripos($columna, 'cedula') !== false && stripos($columna, 'nacimiento') === false) {
                    $cedula = $valor;
                } elseif (stripos($columna, 'fecha') !== false && stripos($columna, 'nacimiento') !== false) {
                    $fechaNacimiento = $valor;
                } elseif ($columnaUpper === 'EDAD') {
                    $edad = intval($valor);
                } elseif (stripos($columna, 'sangre') !== false || stripos($columna, 'tipo') !== false && stripos($columna, 'sangre') !== false) {
                    $sangre = $valor;
                } elseif ($columnaUpper === 'POSICIÓN' || $columnaUpper === 'POSICION' || stripos($columna, 'posición') !== false) {
                    // Verificar que no sea "POSICIÓN FUNCIONAL"
                    if (stripos($columna, 'funcional') === false) {
                        $posicion = intval($valor);
                    }
                } elseif (stripos($columna, 'posición') !== false && stripos($columna, 'funcional') !== false) {
                    $posicionFuncional = $valor;
                } elseif (stripos($columna, 'fecha') !== false && stripos($columna, 'inicio') !== false) {
                    $fechaInicio = $valor;
                } elseif (stripos($columna, 'dirección') !== false || stripos($columna, 'direccion') !== false || stripos($columna, 'sede') !== false) {
                    $direccionSede = $valor;
                }
            }
            
            if (!$cedula) {
                $errores[] = "Fila " . ($indice + 1) . ": No se encontró la cédula";
                continue;
            }
            
            // Normalizar cédula (sin guiones, mayúsculas)
            $cedulaNormalizada = normalizarCedula($cedula);
            
            // Buscar nombre y apellido del Excel biométrico
            $nombre = null;
            $apellido = null;
            if (isset($mapaBiometrico[$cedulaNormalizada])) {
                $nombre = $mapaBiometrico[$cedulaNormalizada]['nombre'];
                $apellido = $mapaBiometrico[$cedulaNormalizada]['apellido'];
            } else {
                $sinMatch++;
                // Mostrar información de debugging
                $cedulasDisponibles = array_slice(array_keys($mapaBiometrico), 0, 5);
                $errores[] = "Fila " . ($indice + 1) . ": Cédula '$cedula' (normalizada: '$cedulaNormalizada') no encontrada en biométrico. " .
                            "Total en biométrico: " . count($mapaBiometrico) . ". " .
                            "Ejemplos disponibles: " . implode(', ', $cedulasDisponibles);
                continue;
            }
            
            // Procesar "DIRECCIÓN O SEDE" - separar por guión
            $sedeProvincia = '';
            $direccion = '';
            if ($direccionSede) {
                $partes = explode('-', $direccionSede, 2);
                $sedeProvincia = trim($partes[0] ?? '');
                $direccion = trim($partes[1] ?? '');
            }
            
            // Validar campos requeridos
            if (empty($nombre) || empty($apellido)) {
                $errores[] = "Fila " . ($indice + 1) . ": Nombre o apellido vacío";
                continue;
            }
            
            // Validar y formatear fechas
            if ($fechaNacimiento) {
                $fechaNac = formatearFechaBD($fechaNacimiento);
                if (!$fechaNac) {
                    $errores[] = "Fila " . ($indice + 1) . ": Fecha de nacimiento inválida: $fechaNacimiento";
                    continue;
                }
            } else {
                $fechaNac = null;
            }
            
            if ($fechaInicio) {
                $fechaIni = formatearFechaBD($fechaInicio);
                if (!$fechaIni) {
                    $errores[] = "Fila " . ($indice + 1) . ": Fecha de inicio inválida: $fechaInicio";
                    continue;
                }
            } else {
                $fechaIni = null;
            }
            
            // Calcular edad si no está presente
            if (!$edad && $fechaNac) {
                $edad = calcularEdad($fechaNac);
            }
            
            // Preparar datos para insertar
            $funcionario = [
                'cedula' => $cedulaNormalizada,
                'nombre' => sanitize($nombre),
                'apellido' => sanitize($apellido),
                'fecha_nacimiento' => $fechaNac,
                'edad' => $edad ? intval($edad) : 0,
                'sangre' => sanitize($sangre ?? ''),
                'no_posicion' => $posicion ? intval($posicion) : 0,
                'posicion_funcional' => sanitize($posicionFuncional ?? ''),
                'fecha_inicio' => $fechaIni,
                'sede_provincia' => sanitize($sedeProvincia),
                'Direccion' => sanitize($direccion)
            ];
            
            // Verificar si ya existe
            $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
            $stmtCheck->execute([$cedulaNormalizada]);
            if ($stmtCheck->fetch()) {
                $duplicados++;
                // Actualizar en lugar de insertar
                $stmt = $db->prepare("
                    UPDATE funcionarios SET
                        nombre = ?, apellido = ?, fecha_nacimiento = ?, edad = ?,
                        sangre = ?, no_posicion = ?, posicion_funcional = ?,
                        fecha_inicio = ?, sede_provincia = ?, Direccion = ?
                    WHERE cedula = ?
                ");
                $stmt->execute([
                    $funcionario['nombre'],
                    $funcionario['apellido'],
                    $funcionario['fecha_nacimiento'],
                    $funcionario['edad'],
                    $funcionario['sangre'],
                    $funcionario['no_posicion'],
                    $funcionario['posicion_funcional'],
                    $funcionario['fecha_inicio'],
                    $funcionario['sede_provincia'],
                    $funcionario['Direccion'],
                    $funcionario['cedula']
                ]);
                $exitosos++;
            } else {
                // Insertar nuevo
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
    $mensaje = "Procesamiento completado. ";
    $mensaje .= "Exitosos: $exitosos, ";
    $mensaje .= "Duplicados (actualizados): $duplicados, ";
    $mensaje .= "Sin match en biométrico: $sinMatch";
    
    if (count($errores) > 0) {
        $mensaje .= ", Errores: " . count($errores);
    }
    
    echo json_encode([
        'success' => true,
        'mensaje' => $mensaje,
        'estadisticas' => [
            'exitosos' => $exitosos,
            'duplicados' => $duplicados,
            'sin_match' => $sinMatch,
            'errores' => count($errores),
            'total_procesados' => count($datosFiltro['data']),
            'total_biometrico' => count($mapaBiometrico),
            'total_filtro' => count($datosFiltro['data'])
        ],
        'errores' => array_slice($errores, 0, 20), // Primeros 20 errores
        'debug' => [
            'columnas_biometrico' => $columnasBiometrico,
            'debug_biometrico' => array_slice($debugBiometrico, 0, 10) // Primeros 10 debug
        ]
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
 */
function formatearFechaBD($fecha) {
    if (empty($fecha)) return null;
    
    // Intentar diferentes formatos
    $formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'];
    
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
?>
