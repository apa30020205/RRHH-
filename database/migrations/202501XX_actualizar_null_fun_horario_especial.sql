-- Migración: Actualizar valores NULL a 0 en fun_horario_especial
-- Fecha: 2025-01-XX
-- Descripción: Actualiza todos los registros que tienen NULL en fun_horario_especial a 0
--              para asegurar que por defecto todos los funcionarios sean normales (no especiales)

UPDATE funcionarios 
SET fun_horario_especial = 0 
WHERE fun_horario_especial IS NULL;

-- Verificar que no queden valores NULL
SELECT COUNT(*) as registros_con_null 
FROM funcionarios 
WHERE fun_horario_especial IS NULL;

-- Mostrar resumen
SELECT 
    fun_horario_especial,
    COUNT(*) as total
FROM funcionarios
GROUP BY fun_horario_especial;

