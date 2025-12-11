<?php
/**
 * Funciones de Cálculo de Horas Trabajadas
 * Sistema RRHH
 * 
 * Funciones compartidas para calcular horas trabajadas y tiempo faltante
 */

/**
 * Calcula horas trabajadas y tiempo faltante
 * IMPORTANTE: La BD siempre guarda las horas reales. Esta función calcula las horas contabilizadas para visualización.
 * 
 * @param string $horaEntrada Hora de entrada del reloj (formato H:i:s o H:i)
 * @param string $horaSalida Hora de salida del reloj (formato H:i:s o H:i)
 * @param string $hEntradaFuncionario Hora de entrada del horario del funcionario (formato H:i:s o H:i, NULL = 08:00:00)
 * @param string $hSalidaFuncionario Hora de salida del horario del funcionario (formato H:i:s o H:i, NULL = 16:00:00)
 * @return array|null Array con 'horas_trabajadas' (reales), 'horas_contabilizadas' (dentro del horario) y 'tiempo_faltante' o null si hay error
 */
function calcularHorasTrabajadas($horaEntrada, $horaSalida, $hEntradaFuncionario = null, $hSalidaFuncionario = null) {
    if (empty($horaEntrada) || empty($horaSalida)) {
        return null;
    }
    
    // Valores por defecto si no se especifica horario del funcionario
    if ($hEntradaFuncionario === null) {
        $hEntradaFuncionario = '08:00:00';
    }
    if ($hSalidaFuncionario === null) {
        $hSalidaFuncionario = '16:00:00';
    }
    
    // Convertir horas del reloj a objetos DateTime
    $entradaReloj = DateTime::createFromFormat('H:i:s', $horaEntrada);
    if (!$entradaReloj) {
        $entradaReloj = DateTime::createFromFormat('H:i', $horaEntrada);
    }
    
    $salidaReloj = DateTime::createFromFormat('H:i:s', $horaSalida);
    if (!$salidaReloj) {
        $salidaReloj = DateTime::createFromFormat('H:i', $horaSalida);
    }
    
    if (!$entradaReloj || !$salidaReloj) {
        return null;
    }
    
    // Convertir horario del funcionario a objetos DateTime
    $horaEntradaFunc = DateTime::createFromFormat('H:i:s', $hEntradaFuncionario);
    if (!$horaEntradaFunc) {
        $horaEntradaFunc = DateTime::createFromFormat('H:i', $hEntradaFuncionario);
    }
    
    $horaSalidaFunc = DateTime::createFromFormat('H:i:s', $hSalidaFuncionario);
    if (!$horaSalidaFunc) {
        $horaSalidaFunc = DateTime::createFromFormat('H:i', $hSalidaFuncionario);
    }
    
    if (!$horaEntradaFunc || !$horaSalidaFunc) {
        return null;
    }
    
    // 1. CALCULAR HORAS TRABAJADAS REALES (del reloj) - Se guarda en BD
    $minutosEntradaReal = $entradaReloj->format('H') * 60 + $entradaReloj->format('i');
    $minutosSalidaReal = $salidaReloj->format('H') * 60 + $salidaReloj->format('i');
    $minutosTrabajadosReales = $minutosSalidaReal - $minutosEntradaReal;
    
    if ($minutosTrabajadosReales <= 0) {
        return [
            'horas_trabajadas' => '00:00:00', // Reales (se guarda en BD)
            'horas_contabilizadas' => '00:00:00', // Para visualización
            'tiempo_faltante' => '08:00:00'
        ];
    }
    
    $horasReales = floor($minutosTrabajadosReales / 60);
    $minutosReales = $minutosTrabajadosReales % 60;
    $horasTrabajadasReales = sprintf('%02d:%02d:00', $horasReales, $minutosReales);
    
    // 2. CALCULAR HORAS CONTABILIZADAS (dentro del horario del funcionario) - Solo para visualización
    // Determinar el rango de horas que cuenta dentro del horario del funcionario
    $entradaContabilizada = clone $entradaReloj;
    $salidaContabilizada = clone $salidaReloj;
    
    // Si la entrada es antes del horario del funcionario, usar la hora del horario
    if ($entradaReloj < $horaEntradaFunc) {
        $entradaContabilizada = clone $horaEntradaFunc;
    }
    
    // Si la salida es después del horario del funcionario, usar la hora del horario
    if ($salidaReloj > $horaSalidaFunc) {
        $salidaContabilizada = clone $horaSalidaFunc;
    }
    
    // Si la entrada es después del horario de salida, no cuenta nada
    if ($entradaContabilizada >= $horaSalidaFunc) {
        $horasContabilizadas = '00:00:00';
    } else {
        $minutosEntradaCont = $entradaContabilizada->format('H') * 60 + $entradaContabilizada->format('i');
        $minutosSalidaCont = $salidaContabilizada->format('H') * 60 + $salidaContabilizada->format('i');
        $minutosContabilizados = $minutosSalidaCont - $minutosEntradaCont;
        
        if ($minutosContabilizados <= 0) {
            $horasContabilizadas = '00:00:00';
        } else {
            $horasCont = floor($minutosContabilizados / 60);
            $minutosCont = $minutosContabilizados % 60;
            $horasContabilizadas = sprintf('%02d:%02d:00', $horasCont, $minutosCont);
        }
    }
    
    // 3. CALCULAR TIEMPO FALTANTE (basado en el horario del funcionario)
    $minutosHorarioFunc = ($horaSalidaFunc->format('H') * 60 + $horaSalidaFunc->format('i')) - 
                          ($horaEntradaFunc->format('H') * 60 + $horaEntradaFunc->format('i'));
    $minutosRequeridos = $minutosHorarioFunc; // Horas requeridas según el horario del funcionario
    
    $minutosEntradaCont = $entradaContabilizada->format('H') * 60 + $entradaContabilizada->format('i');
    $minutosSalidaCont = $salidaContabilizada->format('H') * 60 + $salidaContabilizada->format('i');
    $minutosTrabajadosCont = $minutosSalidaCont - $minutosEntradaCont;
    
    $tiempoFaltante = '00:00:00';
    if ($minutosTrabajadosCont < $minutosRequeridos) {
        $minutosFaltantes = $minutosRequeridos - $minutosTrabajadosCont;
        $horasFaltantes = floor($minutosFaltantes / 60);
        $minutosFaltantesRestantes = $minutosFaltantes % 60;
        $tiempoFaltante = sprintf('%02d:%02d:00', $horasFaltantes, $minutosFaltantesRestantes);
    }
    
    return [
        'horas_trabajadas' => $horasTrabajadasReales, // Reales (se guarda en BD)
        'horas_contabilizadas' => $horasContabilizadas, // Para visualización
        'tiempo_faltante' => $tiempoFaltante
    ];
}
