-- Migración: Agregar columna de misiones oficiales acumuladas a funcionarios
-- Fecha: 2025-01-27
-- Descripción: Agrega una columna para almacenar las misiones oficiales acumuladas
--              que puede ser editada manualmente en el formulario de Misión Oficial
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Agregar columna si no existe
SET @existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'funcionarios' 
    AND COLUMN_NAME = 'mision_oficial_acumuladas'
);

SET @sql = IF(@existe = 0,
    'ALTER TABLE `funcionarios` ADD COLUMN `mision_oficial_acumuladas` TIME NULL COMMENT ''Misiones oficiales acumuladas del funcionario (editable manualmente)'';',
    'SELECT ''Column mision_oficial_acumuladas already exists, skipping'' AS message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar que la columna se agregó correctamente
DESCRIBE `funcionarios`;
