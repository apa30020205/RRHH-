-- Migración: Agregar columnas de tiempo compensatorio acumulado a funcionarios
-- Fecha: 2025-01-27
-- Descripción: Agrega columnas para almacenar el tiempo compensatorio acumulado
--              que puede ser editado manualmente en el formulario
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Agregar columna de horas acumuladas si no existe
SET @existe_horas = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'funcionarios' 
    AND COLUMN_NAME = 'tiempo_compensatorio_horas_acumuladas'
);

SET @sql_horas = IF(@existe_horas = 0,
    'ALTER TABLE `funcionarios` ADD COLUMN `tiempo_compensatorio_horas_acumuladas` TIME NULL COMMENT ''Horas de tiempo compensatorio acumuladas del funcionario (editable manualmente)'';',
    'SELECT ''Column tiempo_compensatorio_horas_acumuladas already exists, skipping'' AS message;'
);

PREPARE stmt_horas FROM @sql_horas;
EXECUTE stmt_horas;
DEALLOCATE PREPARE stmt_horas;

-- Agregar columna de días acumulados si no existe
SET @existe_dias = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'funcionarios' 
    AND COLUMN_NAME = 'tiempo_compensatorio_dias_acumulados'
);

SET @sql_dias = IF(@existe_dias = 0,
    'ALTER TABLE `funcionarios` ADD COLUMN `tiempo_compensatorio_dias_acumulados` INT NULL DEFAULT 0 COMMENT ''Días de tiempo compensatorio acumulados del funcionario (editable manualmente)'';',
    'SELECT ''Column tiempo_compensatorio_dias_acumulados already exists, skipping'' AS message;'
);

PREPARE stmt_dias FROM @sql_dias;
EXECUTE stmt_dias;
DEALLOCATE PREPARE stmt_dias;

-- Verificar que las columnas se agregaron correctamente
DESCRIBE `funcionarios`;
