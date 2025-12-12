-- Migración: Reorganizar campos de almuerzo en la tabla marcaciones
-- Fecha: 2025-01-XX
-- Descripción: Reorganiza los campos almuerzo_entrada y almuerzo_salida
--              para que queden entre hora_entrada y hora_salida
--
-- Orden deseado:
-- 1. hora_entrada
-- 2. almuerzo_salida
-- 3. almuerzo_entrada
-- 4. hora_salida
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Reorganizar almuerzo_salida: mover a posición 5 (después de hora_entrada)
ALTER TABLE `marcaciones`
  MODIFY COLUMN `almuerzo_salida` TIME NULL COMMENT 'Hora de salida del almuerzo detectada automáticamente (entre 11:00 AM y 3:00 PM)'
  AFTER `hora_entrada`;

-- Reorganizar almuerzo_entrada: mover de posición 10 a posición 6 (después de almuerzo_salida)
ALTER TABLE `marcaciones`
  MODIFY COLUMN `almuerzo_entrada` TIME NULL COMMENT 'Hora de entrada del almuerzo detectada automáticamente (entre 11:00 AM y 3:00 PM)'
  AFTER `almuerzo_salida`;

-- Verificar que los campos se reorganizaron correctamente
DESCRIBE `marcaciones`;
