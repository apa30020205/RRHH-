-- Migración: Agregar campo permiso_justificado a tabla permisos
-- Fecha: 2025-01-27
-- Descripción: Agrega un campo para distinguir entre permisos justificados e injustificados
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Verificar si la columna ya existe
SET @existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'permisos' 
    AND COLUMN_NAME = 'permiso_justificado'
);

SET @sql = IF(@existe = 0,
    'ALTER TABLE `permisos` 
     ADD COLUMN `permiso_justificado` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1=justificado, 0=injustificado'',
     ADD INDEX `idx_permiso_justificado` (`permiso_justificado`);',
    'SELECT ''Column permiso_justificado already exists, skipping'' AS message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar que la columna se agregó correctamente
DESCRIBE `permisos`;


