-- Migración: Agregar columna de permisos injustificados acumulados a funcionarios
-- Fecha: 2025-01-27
-- Descripción: Agrega una columna para almacenar los permisos injustificados acumulados
--              que puede ser editada manualmente en el formulario de Solicitud de Permiso
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Agregar columna si no existe
SET @existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'funcionarios' 
    AND COLUMN_NAME = 'permisos_injustificados_acumulados'
);

SET @sql = IF(@existe = 0,
    'ALTER TABLE `funcionarios` ADD COLUMN `permisos_injustificados_acumulados` TIME NULL COMMENT ''Permisos injustificados acumulados del funcionario (editable manualmente)'';',
    'SELECT ''Column permisos_injustificados_acumulados already exists, skipping'' AS message;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar que la columna se agregó correctamente
DESCRIBE `funcionarios`;

