-- Migración: Agregar campo fun_extra a tabla funcionarios
-- Fecha: 2025-01-XX
-- Descripción: Agrega el campo fun_extra VARCHAR(10) para clasificar funcionarios (Jefe, Manual, otro)
--              NULL = valor borrado/desactivado

ALTER TABLE `funcionarios`
  ADD COLUMN `fun_extra` VARCHAR(10) DEFAULT NULL COMMENT 'Clasificación del funcionario: Jefe, Manual, otro. NULL = borrado/desactivado' 
  AFTER `fun_horario_especial`;

DESCRIBE `funcionarios`;

