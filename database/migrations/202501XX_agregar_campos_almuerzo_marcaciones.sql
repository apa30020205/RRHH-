-- Migración: Agregar campos de horario de almuerzo a la tabla marcaciones
-- Fecha: 2025-01-XX
-- Descripción: Agrega campos para almacenar la hora de entrada y salida del almuerzo
--              detectada automáticamente durante la importación de marcaciones
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Agregar campos para horario de almuerzo
ALTER TABLE `marcaciones`
  ADD COLUMN `almuerzo_entrada` TIME NULL COMMENT 'Hora de entrada del almuerzo detectada automáticamente (entre 11:00 AM y 3:00 PM)',
  ADD COLUMN `almuerzo_salida` TIME NULL COMMENT 'Hora de salida del almuerzo detectada automáticamente (entre 11:00 AM y 3:00 PM)'
  AFTER `hora_entrada`;

-- Verificar que los campos se agregaron correctamente
DESCRIBE `marcaciones`;
