<?php
/**
 * Funciones de Cálculo de Horas Trabajadas
 * Sistema RRHH
 * 
 * Funciones compartidas para calcular horas trabajadas y tiempo faltante
 */

/**
 * Calcula horas trabajadas y tiempo faltante
 * @param string $horaEntrada Hora de entrada (formato H:i:s o H:i)
 * @param string $horaSalida Hora de salida (formato H:i:s o H:i)
 * @param int $esEspecial 1 si es funcionario especial, 0 si no
 * @return array|null Array con 'horas_trabajadas' y 'tiempo_faltante' o null si hay error
 */
function calcularHorasTrabajadas($horaEntrada, $horaSalida, $esEspecial) {
    // Asegurar que $esEspecial sea 0 o 1 (tratar NULL como 0)
    $esEspecial = intval($esEspecial) === 1 ? 1 : 0;
    if (empty($horaEntrada) || empty($horaSalida)) {
        return null;
    }
    
    // Convertir horas a objetos DateTime
    $entrada = DateTime::createFromFormat('H:i:s', $horaEntrada);
    if (!$entrada) {
        $entrada = DateTime::createFromFormat('H:i', $horaEntrada);
    }
    
    $salida = DateTime::createFromFormat('H:i:s', $horaSalida);
    if (!$salida) {
        $salida = DateTime::createFromFormat('H:i', $horaSalida);
    }
    
    if (!$entrada || !$salida) {
        return null;
    }
    
    // Guardar valores originales
    $entradaOriginal = clone $entrada;
    $salidaOriginal = clone $salida;
    
    // Para funcionarios NORMALES (no especiales)
    if (!$esEspecial) {
        // Si la entrada es antes de las 8:00 AM, usar 8:00 AM como hora de entrada
        $horaLimiteEntrada = DateTime::createFromFormat('H:i:s', '08:00:00');
        if ($entrada < $horaLimiteEntrada) {
            $entrada = clone $horaLimiteEntrada;
        }
        
        // Si la salida es después de las 4:00 PM, usar 4:00 PM como límite
        $horaLimiteSalida = DateTime::createFromFormat('H:i:s', '16:00:00');
        if ($salida > $horaLimiteSalida) {
            $salida = clone $horaLimiteSalida;
        }
    }
    // Para funcionarios ESPECIALES, usar TODO el horario (sin límites)
    // No se modifica $entrada ni $salida, se usan los valores originales
    
    // Calcular diferencia en minutos
    $minutosEntrada = $entrada->format('H') * 60 + $entrada->format('i');
    $minutosSalida = $salida->format('H') * 60 + $salida->format('i');
    $minutosTrabajados = $minutosSalida - $minutosEntrada;
    
    // Si los minutos trabajados son negativos o cero, retornar null
    if ($minutosTrabajados <= 0) {
        return [
            'horas_trabajadas' => '00:00:00',
            'tiempo_faltante' => '08:00:00'
        ];
    }
    
    // Convertir minutos a horas:minutos
    $horas = floor($minutosTrabajados / 60);
    $minutos = $minutosTrabajados % 60;
    $horasTrabajadas = sprintf('%02d:%02d:00', $horas, $minutos);
    
    // Calcular tiempo faltante
    $minutosRequeridos = 480; // 8 horas * 60 minutos
    $tiempoFaltante = '00:00:00';
    
    if ($minutosTrabajados < $minutosRequeridos) {
        // Para funcionarios ESPECIALES, la tardanza se calcula desde la hora de salida real hasta completar 8 horas
        // Para funcionarios NORMALES, la tardanza es desde la hora de salida hasta las 4:00 PM
        if ($esEspecial) {
            // Funcionario especial: tardanza = 8 horas - horas trabajadas (usando horario completo)
            $minutosFaltantes = $minutosRequeridos - $minutosTrabajados;
            $horasFaltantes = floor($minutosFaltantes / 60);
            $minutosFaltantesRestantes = $minutosFaltantes % 60;
            $tiempoFaltante = sprintf('%02d:%02d:00', $horasFaltantes, $minutosFaltantesRestantes);
        } else {
            // Funcionario normal: tardanza desde hora de salida hasta 4:00 PM
            if ($salidaOriginal < DateTime::createFromFormat('H:i:s', '16:00:00')) {
                $minutosSalidaOriginal = $salidaOriginal->format('H') * 60 + $salidaOriginal->format('i');
                $minutosHasta4PM = (16 * 60) - $minutosSalidaOriginal;
                $horasFaltantes = floor($minutosHasta4PM / 60);
                $minutosFaltantesRestantes = $minutosHasta4PM % 60;
                $tiempoFaltante = sprintf('%02d:%02d:00', $horasFaltantes, $minutosFaltantesRestantes);
            } else {
                // Si sale después de las 4:00 PM, tardanza = 8 horas - horas trabajadas
                $minutosFaltantes = $minutosRequeridos - $minutosTrabajados;
                $horasFaltantes = floor($minutosFaltantes / 60);
                $minutosFaltantesRestantes = $minutosFaltantes % 60;
                $tiempoFaltante = sprintf('%02d:%02d:00', $horasFaltantes, $minutosFaltantesRestantes);
            }
        }
    }
    
    return [
        'horas_trabajadas' => $horasTrabajadas,
        'tiempo_faltante' => $tiempoFaltante
    ];
}
