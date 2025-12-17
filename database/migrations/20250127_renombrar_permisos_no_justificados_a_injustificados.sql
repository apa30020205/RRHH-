-- Migración: Renombrar campos de "Permisos No Justificados" a "Permisos InJustificados"
-- Fecha: 2025-01-27
-- Descripción: Renombra las columnas relacionadas con permisos no justificados
--              de "no_justificados" a "injustificados" para cambiar la nomenclatura
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Verificar qué columnas existen antes de renombrarlas
SET @existe_time = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'funcionarios' 
    AND COLUMN_NAME = 'permisos_no_justificados_acumulados'
);

SET @existe_dias = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'funcionarios' 
    AND COLUMN_NAME = 'permisos_no_justificados_dias_acumulados'
);

SET @existe_horas = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'funcionarios' 
    AND COLUMN_NAME = 'permisos_no_justificados_horas_acumuladas'
);

-- Renombrar campo TIME si existe
SET @sql_time = IF(@existe_time > 0,
    'ALTER TABLE `funcionarios` CHANGE COLUMN `permisos_no_justificados_acumulados` `permisos_injustificados_acumulados` TIME NULL COMMENT ''Permisos injustificados acumulados en formato DDD:HH:00:00 (días:horas)'';',
    'SELECT ''Column permisos_no_justificados_acumulados does not exist, skipping'' AS message;'
);
PREPARE stmt FROM @sql_time;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Renombrar campo días si existe
SET @sql_dias = IF(@existe_dias > 0,
    'ALTER TABLE `funcionarios` CHANGE COLUMN `permisos_no_justificados_dias_acumulados` `permisos_injustificados_dias_acumulados` DECIMAL(5,2) DEFAULT 0 COMMENT ''Días de permisos injustificados acumulados (3 días/año)'';',
    'SELECT ''Column permisos_no_justificados_dias_acumulados does not exist, skipping'' AS message;'
);
PREPARE stmt FROM @sql_dias;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Renombrar campo horas si existe
SET @sql_horas = IF(@existe_horas > 0,
    'ALTER TABLE `funcionarios` CHANGE COLUMN `permisos_no_justificados_horas_acumuladas` `permisos_injustificados_horas_acumuladas` DECIMAL(5,2) DEFAULT 0 COMMENT ''Horas de permisos injustificados acumuladas (parte de los 3 días)'';',
    'SELECT ''Column permisos_no_justificados_horas_acumuladas does not exist, skipping'' AS message;'
);
PREPARE stmt FROM @sql_horas;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar que los campos se renombraron correctamente
DESCRIBE `funcionarios`;
