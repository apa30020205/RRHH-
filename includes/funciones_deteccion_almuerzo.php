<?php
/**
 * Funciones de Detección de Horario de Almuerzo
 * Sistema RRHH
 * 
 * Detecta automáticamente el horario de almuerzo basado en las marcaciones del día
 * Rango: 11:30 AM a 2:30 PM (14:30)
 * Duración mínima: 30 minutos
 * Duración máxima: 75 minutos
 */

/**
 * Detecta el horario de almuerzo a partir de un array de horas de registro
 * 
 * @param array $horasRegistro Array de horas en formato 'HH:MM:SS' o 'HH:MM'
 * @return array|null Retorna ['entrada' => 'HH:MM:SS', 'salida' => 'HH:MM:SS'] o null si no se detecta
 */
function detectarHorarioAlmuerzo($horasRegistro) {
    if (empty($horasRegistro) || !is_array($horasRegistro)) {
        return null;
    }
    
    // Convertir todas las horas a objetos DateTime para comparación
    $horasDateTime = [];
    foreach ($horasRegistro as $hora) {
        if (empty($hora)) continue;
        
        // Normalizar formato (agregar segundos si faltan)
        $horaNormalizada = trim($hora);
        if (strlen($horaNormalizada) == 5) {
            $horaNormalizada .= ':00';
        }
        
        // Intentar parsear la hora
        try {
            $dt = DateTime::createFromFormat('H:i:s', $horaNormalizada);
            if ($dt === false) {
                $dt = DateTime::createFromFormat('H:i', $horaNormalizada);
            }
            if ($dt !== false) {
                $horasDateTime[] = $dt;
            }
        } catch (Exception $e) {
            continue;
        }
    }
    
    if (empty($horasDateTime)) {
        return null;
    }
    
    // Ordenar por orden cronológico
    usort($horasDateTime, function($a, $b) {
        return $a->getTimestamp() - $b->getTimestamp();
    });
    
    // Filtrar marcaciones entre 11:30 AM y 2:30 PM (14:30)
    $horasAlmuerzo = [];
    foreach ($horasDateTime as $dt) {
        $hora = (int)$dt->format('H');
        $minuto = (int)$dt->format('i');
        $horaDecimal = $hora + ($minuto / 60.0);
        
        // Rango: 11:30 (11.5) a 14:30 (14.5)
        if ($horaDecimal >= 11.5 && $horaDecimal <= 14.5) {
            $horasAlmuerzo[] = $dt;
        }
    }
    
    if (count($horasAlmuerzo) < 2) {
        return null; // Necesitamos al menos 2 marcaciones para detectar un intervalo
    }
    
    // Buscar intervalos entre 30 y 75 minutos (almuerzo mínimo 30 minutos, máximo 75 minutos)
    $candidatos = [];
    for ($i = 0; $i < count($horasAlmuerzo) - 1; $i++) {
        $entrada = $horasAlmuerzo[$i];
        $salida = $horasAlmuerzo[$i + 1];
        
        // Calcular diferencia en minutos
        $diff = $salida->getTimestamp() - $entrada->getTimestamp();
        $minutos = (int)($diff / 60);
        
        // Si está entre 30 y 75 minutos, es un candidato
        if ($minutos >= 30 && $minutos <= 75) {
            $candidatos[] = [
                'entrada' => $entrada,
                'salida' => $salida,
                'minutos' => $minutos,
                'diferencia_60' => abs($minutos - 60) // Diferencia con 60 minutos ideales
            ];
        }
    }
    
    if (empty($candidatos)) {
        return null;
    }
    
    // Elegir el candidato más cercano a 60 minutos
    usort($candidatos, function($a, $b) {
        return $a['diferencia_60'] - $b['diferencia_60'];
    });
    
    $mejorCandidato = $candidatos[0];
    
    return [
        'entrada' => $mejorCandidato['entrada']->format('H:i:s'),
        'salida' => $mejorCandidato['salida']->format('H:i:s')
    ];
}
