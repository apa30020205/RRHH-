<?php
/**
 * Procesar Excel Biométrico - Actualizar Nombre y Apellido
 * Sistema RRHH
 * 
 * Procesa el archivo "personal biométrico.xlsx" y actualiza solo
 * los campos "nombre" y "apellido" en la tabla funcionarios
 * 
 * Mapeo:
 * - ID (sin guiones) → relacionar con cedula (puede tener guiones)
 * - Nombre (columna B) → nombre
 * - Apellido (columna C) → apellido
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
    
    $actualizados = 0;
    $noEncontrados = [];
    $errores = [];
    
    // Obtener todas las cédulas de la BD y normalizarlas para matching eficiente
    // Esto evita hacer una consulta por cada fila del Excel
    $stmtAllCedulas = $db->query("SELECT cedula FROM funcionarios");
    $todasLasCedulas = $stmtAllCedulas->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear un mapa de cédulas normalizadas -> cédula original
    $mapaCedulas = [];
    foreach ($todasLasCedulas as $row) {
        $cedulaOriginal = $row['cedula'];
        $cedulaNormalizada = normalizarCedula($cedulaOriginal);
        $mapaCedulas[$cedulaNormalizada] = $cedulaOriginal;
    }
    
    // Filtrar datos: ignorar la primera fila si contiene encabezados
    // Verificar si la primera fila contiene los encabezados (ID, Nombre, Apellido)
    $datosFiltrados = [];
    foreach ($excelData['data'] as $indice => $fila) {
        // Convertir fila a array para verificar valores
        $valores = array_values($fila);
        
        // Verificar si la primera fila contiene encabezados
        // Si el primer valor es "ID" o contiene texto que parece encabezado, saltarlo
        if ($indice === 0 && isset($valores[0])) {
            $primerValor = strtoupper(trim($valores[0] ?? ''));
            // Si el primer valor es "ID" o similar, es la fila de encabezados, saltarla
            if ($primerValor === 'ID' || $primerValor === 'CÉDULA' || $primerValor === 'CEDULA') {
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
    
    // Obtener las columnas detectadas por el microservicio
    // Si hay columnas definidas, usarlas para mapear correctamente
    $columnasDetectadas = isset($excelData['columns']) ? $excelData['columns'] : [];
    
    foreach ($datosFiltrados as $indice => $fila) {
        try {
            // Extraer datos SOLO de las primeras 3 columnas (A, B, C)
            // Columna A (índice 0) = ID
            // Columna B (índice 1) = Nombre
            // Columna C (índice 2) = Apellido
            // IGNORAR todas las demás columnas
            
            $id = null;
            $nombre = null;
            $apellido = null;
            
            // Si tenemos las columnas detectadas, usarlas para mapear
            if (!empty($columnasDetectadas) && count($columnasDetectadas) >= 3) {
                // Usar las primeras 3 columnas detectadas
                $columnaA = $columnasDetectadas[0]; // ID
                $columnaB = $columnasDetectadas[1]; // Nombre
                $columnaC = $columnasDetectadas[2]; // Apellido
                
                // Obtener valores usando los nombres de las columnas
                if (isset($fila[$columnaA])) {
                    $valor = $fila[$columnaA];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $id = trim((string)($valor ?? ''));
                }
                
                if (isset($fila[$columnaB])) {
                    $valor = $fila[$columnaB];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $nombre = trim((string)($valor ?? ''));
                }
                
                if (isset($fila[$columnaC])) {
                    $valor = $fila[$columnaC];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $apellido = trim((string)($valor ?? ''));
                }
            } else {
                // Fallback: convertir fila a array numérico para acceder por índice
                // Esto garantiza que accedemos por posición, no por nombre de columna
                $valores = array_values($fila);
                
                // Solo procesar las primeras 3 columnas
                // Columna A (índice 0) = ID
                if (isset($valores[0])) {
                    $valor = $valores[0];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $id = trim((string)($valor ?? ''));
                }
                
                // Columna B (índice 1) = Nombre
                if (isset($valores[1])) {
                    $valor = $valores[1];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $nombre = trim((string)($valor ?? ''));
                }
                
                // Columna C (índice 2) = Apellido
                if (isset($valores[2])) {
                    $valor = $valores[2];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $apellido = trim((string)($valor ?? ''));
                }
            }
            
            // Validar ID (campo obligatorio)
            // Nota: $indice + 2 porque la fila 1 son encabezados, los datos empiezan en fila 2
            if (empty($id)) {
                $errores[] = "Fila " . ($indice + 2) . ": No se encontró el ID";
                continue;
            }
            
            // Normalizar ID del Excel (quitar guiones y espacios)
            $idNormalizado = normalizarCedula($id);
            
            // Buscar en el mapa de cédulas
            if (isset($mapaCedulas[$idNormalizado])) {
                // Encontrado: actualizar nombre y apellido
                $cedulaBD = $mapaCedulas[$idNormalizado];
                
                // Sanitizar nombre y apellido
                $nombreSanitizado = !empty($nombre) ? sanitize($nombre) : null;
                $apellidoSanitizado = !empty($apellido) ? sanitize($apellido) : null;
                
                // Actualizar solo nombre y apellido
                $stmt = $db->prepare("
                    UPDATE funcionarios 
                    SET nombre = ?, apellido = ? 
                    WHERE cedula = ?
                ");
                $stmt->execute([
                    $nombreSanitizado,
                    $apellidoSanitizado,
                    $cedulaBD
                ]);
                
                $actualizados++;
            } else {
                // No encontrado: agregar a lista de no encontrados
                // Nota: $indice + 2 porque la fila 1 son encabezados, los datos empiezan en fila 2
                $noEncontrados[] = [
                    'id' => $id,
                    'nombre' => $nombre ?: '',
                    'apellido' => $apellido ?: '',
                    'fila' => $indice + 2
                ];
                // Continuar procesando los demás registros (no detener)
            }
            
        } catch (PDOException $e) {
            // Nota: $indice + 2 porque la fila 1 son encabezados, los datos empiezan en fila 2
            $errores[] = "Fila " . ($indice + 2) . ": Error de BD - " . $e->getMessage();
        } catch (Exception $e) {
            // Nota: $indice + 2 porque la fila 1 son encabezados, los datos empiezan en fila 2
            $errores[] = "Fila " . ($indice + 2) . ": " . $e->getMessage();
        }
    }
    
    // Preparar respuesta
    $totalProcesados = count($datosFiltrados);
    $mensaje = "Procesamiento completado. ";
    $mensaje .= "Actualizados: $actualizados";
    
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
            'actualizados' => $actualizados,
            'no_encontrados' => count($noEncontrados),
            'errores' => count($errores)
        ],
        'no_encontrados' => $noEncontrados, // Lista completa para mostrar en frontend
        'errores' => array_slice($errores, 0, 50) // Limitar a 50 errores para no sobrecargar
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar el archivo: ' . $e->getMessage()
    ]);
}
?>

