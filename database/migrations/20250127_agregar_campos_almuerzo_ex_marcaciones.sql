-- Migración: Agregar campos de almuerzo y todas_marcaciones a ex_marcaciones
-- Fecha: 2025-01-27
-- Descripción: Agrega los campos almuerzo_entrada, almuerzo_salida y todas_marcaciones
--              a la tabla ex_marcaciones para mantener consistencia con marcaciones
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Verificar si las columnas ya existen antes de agregarlas
SET @col_exists_almuerzo_entrada = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'ex_marcaciones' 
    AND COLUMN_NAME = 'almuerzo_entrada'
);

SET @col_exists_almuerzo_salida = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'ex_marcaciones' 
    AND COLUMN_NAME = 'almuerzo_salida'
);

SET @col_exists_todas_marcaciones = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'ex_marcaciones' 
    AND COLUMN_NAME = 'todas_marcaciones'
);

-- Agregar almuerzo_entrada si no existe
SET @sql_almuerzo_entrada = IF(@col_exists_almuerzo_entrada = 0,
    'ALTER TABLE `ex_marcaciones` ADD COLUMN `almuerzo_entrada` TIME NULL COMMENT ''Hora de entrada del almuerzo detectada automáticamente (entre 11:00 AM y 3:00 PM)'' AFTER `hora_entrada`;',
    'SELECT ''Column almuerzo_entrada already exists'' AS message;'
);
PREPARE stmt FROM @sql_almuerzo_entrada;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar almuerzo_salida si no existe
SET @sql_almuerzo_salida = IF(@col_exists_almuerzo_salida = 0,
    'ALTER TABLE `ex_marcaciones` ADD COLUMN `almuerzo_salida` TIME NULL COMMENT ''Hora de salida del almuerzo detectada automáticamente (entre 11:00 AM y 3:00 PM)'' AFTER `almuerzo_entrada`;',
    'SELECT ''Column almuerzo_salida already exists'' AS message;'
);
PREPARE stmt FROM @sql_almuerzo_salida;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar todas_marcaciones si no existe
SET @sql_todas_marcaciones = IF(@col_exists_todas_marcaciones = 0,
    'ALTER TABLE `ex_marcaciones` ADD COLUMN `todas_marcaciones` TEXT NULL COMMENT ''Todas las horas de marcación del día separadas por comas'' AFTER `hora_salida`;',
    'SELECT ''Column todas_marcaciones already exists'' AS message;'
);
PREPARE stmt FROM @sql_todas_marcaciones;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar que los campos se agregaron correctamente
DESCRIBE `ex_marcaciones`;



