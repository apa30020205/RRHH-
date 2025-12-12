-- Migración: Agregar campo para guardar todas las marcaciones del día
-- Este campo guardará todas las horas de registro (Columna J) para calcular el almuerzo al momento de mostrar

ALTER TABLE `marcaciones`
  ADD COLUMN `todas_marcaciones` TEXT NULL COMMENT 'Todas las horas de registro del día separadas por comas (formato HH:MM:SS)'
  AFTER `hora_entrada`;

DESCRIBE `marcaciones`;
