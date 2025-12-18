-- Migración: Agregar columna de horas extraordinarias acumuladas a funcionarios
-- Fecha: 2025-01-27
-- Descripción: Agrega una columna para almacenar las horas extraordinarias acumuladas
--              que puede ser editada manualmente en el formulario de Jornada Extraordinaria
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Agregar columna si no existe
SET @existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'funcionarios' 
    AND COLUMN_NAME = 'horas_extraordinarias_acumuladas'
);

SET @sql = IF(@existe = 0,
    'ALTER TABLE `funcionarios` ADD COLUMN `horas_extraordinarias_acumuladas` TIME NULL COMMENT ''Horas extraordinarias acumuladas del funcionario (editable manualmente)'';',
    'SELECT ''Column horas_extraordinarias_acumuladas already exists, skipping'' AS message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar que la columna se agregó correctamente
DESCRIBE `funcionarios`;



