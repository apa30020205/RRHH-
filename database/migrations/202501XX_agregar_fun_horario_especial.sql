-- Migración: Agregar campo fun_horario_especial
-- Fecha: 2025-01-XX
-- Descripción: Agrega campo para identificar funcionarios especiales (Agentes de Seguridad, choferes, etc.)
--              que pueden acumular horas trabajadas desde antes de las 8:00 AM

ALTER TABLE `funcionarios`
  ADD COLUMN `fun_horario_especial` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el funcionario tiene horario especial (1) o normal (0). Funcionarios especiales pueden acumular horas desde antes de las 8:00 AM' 
  AFTER `Direccion`;

-- Verificar que el campo se agregó correctamente
DESCRIBE `funcionarios`;

