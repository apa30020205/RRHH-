-- Migración: Aumentar tamaño del campo fun_extra
-- Fecha: 2025-01-XX
-- Descripción: Aumenta el tamaño de fun_extra de VARCHAR(10) a VARCHAR(20) para permitir valores más largos
--              como "Lic. Sueldo" (12 caracteres) y "Lic. Sin Sueldo" (16 caracteres)

ALTER TABLE `funcionarios`
  MODIFY COLUMN `fun_extra` VARCHAR(20) DEFAULT NULL COMMENT 'Clasificación del funcionario: Jefe, Manual, cesante, Préstamo, Lic. Sueldo, Lic. Sin Sueldo, otro. NULL = borrado/desactivado';

DESCRIBE `funcionarios`;

