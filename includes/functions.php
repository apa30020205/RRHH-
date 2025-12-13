<?php
/**
 * Funciones auxiliares del sistema
 * Sistema RRHH
 */

/**
 * Sanitiza entrada de datos
 * @param string $data
 * @return string
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida formato de cédula panameña
 * Acepta cédulas numéricas y alfanuméricas (para panameños nacidos en el extranjero)
 * Permite guiones en cualquier posición
 * 
 * Formatos aceptados:
 * - Numéricas: 8-1234-5678, 8-12-34-5678, 812345678, etc. (8-13 dígitos)
 * - Alfanuméricas: PE-123456-7, 8A-1234-5678, etc. (mínimo 8 caracteres, al menos 3 números)
 * 
 * @param string $cedula
 * @return bool
 */
function validarCedula($cedula) {
    // Eliminar espacios al inicio y final
    $cedula = trim($cedula);
    
    // Si está vacía, no es válida
    if (empty($cedula)) {
        return false;
    }
    
    // PRIMERO: Validar que solo contenga letras, números y guiones
    // No permitir ningún otro carácter especial
    if (!preg_match('/^[A-Z0-9\-]+$/i', $cedula)) {
        return false;
    }
    
    // Eliminar guiones y espacios para análisis
    $cedulaSinGuiones = preg_replace('/[\s\-]/', '', $cedula);
    
    // Validar que después de quitar guiones, tenga contenido
    if (empty($cedulaSinGuiones)) {
        return false;
    }
    
    // Longitud mínima 5 caracteres (basado en cédulas reales como 1-38-520 = 6 dígitos)
    // Máxima 20 caracteres (para alfanuméricas)
    if (strlen($cedulaSinGuiones) < 5) {
        return false;
    }
    
    if (strlen($cedulaSinGuiones) > 20) {
        return false;
    }
    
    // Validar que solo contenga letras y números (sin caracteres especiales)
    if (!preg_match('/^[A-Z0-9]+$/i', $cedulaSinGuiones)) {
        return false;
    }
    
    // Validaciones específicas para cédulas panameñas
    
    // 1. Cédulas numéricas puras
    // Formatos reales observados: 5-11 dígitos
    // Ejemplos: 1-38-520 (6 dígitos), 8-771-1418 (9 dígitos), 10-707-2043 (10 dígitos)
    if (preg_match('/^\d+$/', $cedulaSinGuiones)) {
        // Debe tener entre 5 y 13 dígitos (basado en cédulas reales)
        if (strlen($cedulaSinGuiones) >= 5 && strlen($cedulaSinGuiones) <= 13) {
            return true;
        }
        return false;
    }
    
    // 2. Cédulas alfanuméricas (para panameños nacidos en el extranjero)
    // Formatos reales observados: PE-12-1588, PE-9-399, PE-9-537
    if (preg_match('/[A-Z]/i', $cedulaSinGuiones) && preg_match('/[0-9]/', $cedulaSinGuiones)) {
        $cantidadNumeros = preg_match_all('/[0-9]/', $cedulaSinGuiones);
        $cantidadLetras = preg_match_all('/[A-Z]/i', $cedulaSinGuiones);
        
        // Validar que tenga al menos 1 número y al menos 1 letra (mínimo básico)
        if ($cantidadNumeros < 1 || $cantidadLetras < 1) {
            return false;
        }
        
        // Longitud mínima 5 caracteres (basado en PE-9-399 = PE9399 = 6 caracteres)
        if (strlen($cedulaSinGuiones) < 5) {
            return false;
        }
        
        // VALIDACIÓN BASADA EN FORMATOS REALES
        
        // Patrón 1: Letras al inicio, números después (PE1234567, PE9399, PE9537)
        // Formato: PE-XX-XXXX o PE-X-XXX
        if (preg_match('/^[A-Z]{2,4}[0-9]{3,}$/i', $cedulaSinGuiones)) {
            return true;
        }
        
        // Patrón 2: Números al inicio, letras después (1234567PE)
        if (preg_match('/^[0-9]{3,}[A-Z]{2,4}$/i', $cedulaSinGuiones)) {
            return true;
        }
        
        // Patrón 3: Número(s), letra(s), números (8A1234567)
        if (preg_match('/^[0-9]{1,3}[A-Z]{1,3}[0-9]{2,}$/i', $cedulaSinGuiones)) {
            return true;
        }
        
        // Patrón 4: Letra(s), números, letra(s) (P1234567E)
        if (preg_match('/^[A-Z]{1,2}[0-9]{3,}[A-Z]{1,2}$/i', $cedulaSinGuiones)) {
            return true;
        }
        
        // Si tiene al menos 2 números y 2 letras, y longitud razonable, aceptar
        // Esto permite formatos menos comunes pero válidos
        if ($cantidadNumeros >= 2 && $cantidadLetras >= 2 && strlen($cedulaSinGuiones) >= 5 && strlen($cedulaSinGuiones) <= 15) {
            return true;
        }
        
        return false;
    }
    
    // Si no cumple ningún patrón, no es válida
    return false;
}

/**
 * Normaliza una cédula (elimina guiones y espacios, convierte a mayúsculas)
 * Útil para almacenar en base de datos de forma consistente
 * 
 * @param string $cedula
 * @return string
 */
function normalizarCedula($cedula) {
    // Eliminar espacios y guiones, convertir a mayúsculas
    $cedula = preg_replace('/[\s\-]/', '', $cedula);
    return strtoupper($cedula);
}

/**
 * Formatea una cédula para mostrar
 * Si la cédula ya tiene guiones, los mantiene tal como están
 * Si no tiene guiones, los agrega según formato estándar
 * 
 * @param string $cedula
 * @param string $formato Formato deseado: 'con-guiones' o 'sin-guiones'
 * @return string
 */
function formatearCedula($cedula, $formato = 'con-guiones') {
    // Si se solicita sin guiones, normalizar
    if ($formato === 'sin-guiones') {
        return normalizarCedula($cedula);
    }
    
    // Si la cédula ya tiene guiones, mantenerla tal como está
    if (strpos($cedula, '-') !== false) {
        return trim($cedula);
    }
    
    // Si no tiene guiones, normalizar y luego formatear
    $cedulaLimpia = normalizarCedula($cedula);
    
    // Si es numérica, formatear según longitud
    if (preg_match('/^\d+$/', $cedulaLimpia)) {
        $longitud = strlen($cedulaLimpia);
        
        // Formato para 10 dígitos: 10-707-2043 (2-3-4)
        if ($longitud == 10) {
            return substr($cedulaLimpia, 0, 2) . '-' . 
                   substr($cedulaLimpia, 2, 3) . '-' . 
                   substr($cedulaLimpia, 5);
        }
        // Formato estándar panameño: 8-1234-5678 (1-4-4) para 9 dígitos
        elseif ($longitud == 9) {
            return substr($cedulaLimpia, 0, 1) . '-' . 
                   substr($cedulaLimpia, 1, 4) . '-' . 
                   substr($cedulaLimpia, 5);
        }
        // Formato para 8 dígitos: 8-1234-567 (1-4-3)
        elseif ($longitud == 8) {
            return substr($cedulaLimpia, 0, 1) . '-' . 
                   substr($cedulaLimpia, 1, 4) . '-' . 
                   substr($cedulaLimpia, 5);
        }
        // Formato para 6 dígitos: 1-38-520 (1-2-3)
        elseif ($longitud == 6) {
            return substr($cedulaLimpia, 0, 1) . '-' . 
                   substr($cedulaLimpia, 1, 2) . '-' . 
                   substr($cedulaLimpia, 3);
        }
        // Para otras longitudes, intentar formato flexible
        elseif ($longitud >= 5 && $longitud <= 13) {
            // Intentar formato 1-2-3 para 6 dígitos
            if ($longitud == 6) {
                return substr($cedulaLimpia, 0, 1) . '-' . 
                       substr($cedulaLimpia, 1, 2) . '-' . 
                       substr($cedulaLimpia, 3);
            }
            // Formato 1-4-4 para 9+ dígitos
            elseif ($longitud >= 9) {
                return substr($cedulaLimpia, 0, 1) . '-' . 
                       substr($cedulaLimpia, 1, 4) . '-' . 
                       substr($cedulaLimpia, 5);
            }
        }
    }
    
    // Para alfanuméricas o si no se pudo formatear, devolver tal como está
    return trim($cedula);
}

/**
 * Formatea fecha para mostrar
 * @param string $fecha
 * @param string $formato
 * @return string
 */
function formatearFecha($fecha, $formato = 'd/m/Y') {
    if (empty($fecha)) return '';
    $date = new DateTime($fecha);
    return $date->format($formato);
}

/**
 * Calcula edad desde fecha de nacimiento
 * @param string $fechaNacimiento
 * @return int
 */
function calcularEdad($fechaNacimiento) {
    $nacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($nacimiento);
    return $edad->y;
}

/**
 * Redirige a una página
 * @param string $url
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Muestra mensaje de error/success
 * @param string $mensaje
 * @param string $tipo (success, error, warning, info)
 */
function mostrarMensaje($mensaje, $tipo = 'info') {
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['tipo_mensaje'] = $tipo;
}

/**
 * Obtiene y limpia mensaje de sesión
 * @return array|null
 */
function obtenerMensaje() {
    if (isset($_SESSION['mensaje'])) {
        $mensaje = [
            'texto' => $_SESSION['mensaje'],
            'tipo' => $_SESSION['tipo_mensaje'] ?? 'info'
        ];
        unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);
        return $mensaje;
    }
    return null;
}

/**
 * Convierte un valor TIME de MySQL a días y horas
 * @param string|null $timeValue Valor TIME en formato HH:MM:SS o NULL
 * @return array ['dias' => int, 'horas' => int]
 */
function timeToDiasHoras($timeValue) {
    if (empty($timeValue) || $timeValue === null) {
        return ['dias' => 0, 'horas' => 0];
    }
    
    // Parsear el valor TIME (puede ser HH:MM:SS o HH:MM)
    $partes = explode(':', $timeValue);
    $horas = (int)($partes[0] ?? 0);
    $minutos = (int)($partes[1] ?? 0);
    $segundos = (int)($partes[2] ?? 0);
    
    // Convertir todo a segundos
    $totalSegundos = $horas * 3600 + $minutos * 60 + $segundos;
    
    // Convertir a días y horas
    $dias = floor($totalSegundos / 86400); // 86400 segundos = 1 día
    $horasRestantes = floor(($totalSegundos % 86400) / 3600); // Horas restantes
    
    return [
        'dias' => $dias,
        'horas' => $horasRestantes
    ];
}

/**
 * Convierte días y horas a formato TIME de MySQL
 * @param int $dias Número de días
 * @param int $horas Número de horas (0-23)
 * @return string|null Valor TIME en formato HH:MM:SS o NULL si ambos son 0
 */
function diasHorasToTime($dias, $horas) {
    $dias = (int)$dias;
    $horas = (int)$horas;
    
    if ($dias === 0 && $horas === 0) {
        return null;
    }
    
    // Convertir días a horas y sumar
    $horasTotales = ($dias * 24) + $horas;
    
    // Formatear como TIME (HH:MM:SS)
    return sprintf('%02d:00:00', $horasTotales);
}
?>

