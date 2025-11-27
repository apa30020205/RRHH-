<?php
/**
 * Procesar Excel RRHH - Detección de Errores
 * Sistema RRHH
 * 
 * Procesa el archivo "personal RRHH.xlsx" y detecta funcionarios que tienen
 * nombre o apellido vacíos en la tabla funcionarios
 * 
 * Mapeo:
 * - CEDULA (columna D) → comparar con cedula en funcionarios
 * - NOMBRE Y APELLIDO (columna E) → guardar en nombre_y_apellido
 * - Si cedula existe en funcionarios Y (nombre está vacío OR apellido está vacío)
 *   → Guardar en errores_importacion_funcionarios
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
    
    $totalProcesados = 0;
    $erroresEncontrados = 0;
    $errores = [];
    
    // Obtener todas las cédulas de la BD y normalizarlas para matching eficiente
    $stmtAllCedulas = $db->query("SELECT cedula, nombre, apellido, fecha_nacimiento, edad, sangre, no_posicion, posicion_funcional, fecha_inicio, sede_provincia, Direccion FROM funcionarios");
    $todasLasCedulas = $stmtAllCedulas->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear un mapa de cédulas normalizadas -> datos del funcionario
    $mapaFuncionarios = [];
    foreach ($todasLasCedulas as $row) {
        $cedulaOriginal = $row['cedula'];
        $cedulaNormalizada = normalizarCedula($cedulaOriginal);
        $mapaFuncionarios[$cedulaNormalizada] = $row;
    }
    
    // Obtener las columnas detectadas por el microservicio
    $columnasDetectadas = isset($excelData['columns']) ? $excelData['columns'] : [];
    
    // Preparar statement para insertar errores
    $stmtError = $db->prepare("
        INSERT INTO errores_importacion_funcionarios 
        (cedula, nombre_y_apellido, fecha_nacimiento, edad, sangre, no_posicion, 
         posicion_funcional, fecha_inicio, sede_provincia, Direccion, fila_excel, 
         fecha_importacion, resuelto)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)
    ");
    
    // Filtrar datos: ignorar la primera fila si contiene encabezados
    $datosFiltrados = [];
    foreach ($excelData['data'] as $indice => $fila) {
        // Convertir fila a array para verificar valores
        $valores = array_values($fila);
        
        // Verificar si la primera fila contiene encabezados
        if ($indice === 0 && isset($valores[3])) {
            $primerValor = strtoupper(trim($valores[3] ?? ''));
            // Si el valor de la columna D es "CEDULA" o similar, es la fila de encabezados, saltarla
            if ($primerValor === 'CEDULA' || $primerValor === 'CÉDULA') {
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
    
    foreach ($datosFiltrados as $indice => $fila) {
        try {
            // Extraer datos SOLO de las columnas D y E
            // Columna D (índice 3) = CEDULA
            // Columna E (índice 4) = NOMBRE Y APELLIDO
            
            $cedula = null;
            $nombreYApellido = null;
            
            // Si tenemos las columnas detectadas, usarlas para mapear
            if (!empty($columnasDetectadas) && count($columnasDetectadas) >= 5) {
                // Buscar columna D (índice 3) y E (índice 4)
                $columnaD = $columnasDetectadas[3] ?? null; // CEDULA
                $columnaE = $columnasDetectadas[4] ?? null; // NOMBRE Y APELLIDO
                
                // Obtener valores usando los nombres de las columnas
                if ($columnaD && isset($fila[$columnaD])) {
                    $valor = $fila[$columnaD];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $cedula = trim((string)($valor ?? ''));
                }
                
                if ($columnaE && isset($fila[$columnaE])) {
                    $valor = $fila[$columnaE];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $nombreYApellido = trim((string)($valor ?? ''));
                }
            } else {
                // Fallback: convertir fila a array numérico para acceder por índice
                $valores = array_values($fila);
                
                // Columna D (índice 3) = CEDULA
                if (isset($valores[3])) {
                    $valor = $valores[3];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $cedula = trim((string)($valor ?? ''));
                }
                
                // Columna E (índice 4) = NOMBRE Y APELLIDO
                if (isset($valores[4])) {
                    $valor = $valores[4];
                    if (is_object($valor)) {
                        $valor = (string)$valor;
                    }
                    $nombreYApellido = trim((string)($valor ?? ''));
                }
            }
            
            // Validar cédula (campo obligatorio)
            // Nota: $indice + 2 porque la fila 1 son encabezados, los datos empiezan en fila 2
            if (empty($cedula)) {
                $errores[] = "Fila " . ($indice + 2) . ": No se encontró la cédula";
                continue;
            }
            
            // Normalizar cédula del Excel (quitar guiones) para comparación
            $cedulaNormalizada = normalizarCedula($cedula);
            
            // Buscar en el mapa de funcionarios
            if (isset($mapaFuncionarios[$cedulaNormalizada])) {
                // Encontrado: verificar si nombre o apellido están vacíos
                $funcionario = $mapaFuncionarios[$cedulaNormalizada];
                $nombre = $funcionario['nombre'] ?? null;
                $apellido = $funcionario['apellido'] ?? null;
                
                // Verificar si nombre o apellido están vacíos/NULL
                $nombreVacio = (empty($nombre) || $nombre === null || trim($nombre) === '');
                $apellidoVacio = (empty($apellido) || $apellido === null || trim($apellido) === '');
                
                if ($nombreVacio || $apellidoVacio) {
                    // Este es un error: guardar en errores_importacion_funcionarios
                    $stmtError->execute([
                        $cedula, // Cédula original del Excel
                        !empty($nombreYApellido) ? sanitize($nombreYApellido) : null,
                        $funcionario['fecha_nacimiento'] ?: null,
                        $funcionario['edad'] ?: null,
                        $funcionario['sangre'] ?: null,
                        $funcionario['no_posicion'] ?: null,
                        $funcionario['posicion_funcional'] ?: null,
                        $funcionario['fecha_inicio'] ?: null,
                        $funcionario['sede_provincia'] ?: null,
                        $funcionario['Direccion'] ?: null,
                        $indice + 2 // Fila en Excel (fila 1 son encabezados)
                    ]);
                    $erroresEncontrados++;
                }
                // Si ambos están llenos, no es un error, continuar
            } else {
                // Cédula no existe en funcionarios: NO guardar (según especificación)
                // Continuar procesando los demás registros
            }
            
            $totalProcesados++;
            
        } catch (PDOException $e) {
            // Nota: $indice + 2 porque la fila 1 son encabezados, los datos empiezan en fila 2
            $errores[] = "Fila " . ($indice + 2) . ": Error de BD - " . $e->getMessage();
        } catch (Exception $e) {
            // Nota: $indice + 2 porque la fila 1 son encabezados, los datos empiezan en fila 2
            $errores[] = "Fila " . ($indice + 2) . ": " . $e->getMessage();
        }
    }
    
    // Preparar respuesta
    $mensaje = "Procesamiento completado. ";
    $mensaje .= "Total procesados: $totalProcesados";
    
    if ($erroresEncontrados > 0) {
        $mensaje .= ", Errores encontrados: $erroresEncontrados";
    }
    
    if (count($errores) > 0) {
        $mensaje .= ", Errores de procesamiento: " . count($errores);
    }
    
    echo json_encode([
        'success' => true,
        'mensaje' => $mensaje,
        'estadisticas' => [
            'total_procesados' => $totalProcesados,
            'errores_encontrados' => $erroresEncontrados,
            'errores_procesamiento' => count($errores)
        ],
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

