-- Script de Verificación de Estructura de Tabla funcionarios
-- Fecha: 2025-11-21
-- Descripción: Verifica que todas las migraciones estén aplicadas correctamente
--              y que la estructura de la tabla sea la esperada

-- Verificar estructura completa de la tabla
SELECT 
    COLUMN_NAME as 'Campo',
    COLUMN_TYPE as 'Tipo',
    IS_NULLABLE as 'Permite NULL',
    COLUMN_DEFAULT as 'Valor por Defecto',
    COLUMN_COMMENT as 'Comentario'
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'funcionarios'
ORDER BY 
    ORDINAL_POSITION;

-- Verificación específica de campos críticos
SELECT 
    'Verificación de Migraciones' as 'Verificación',
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'cedula' 
            AND IS_NULLABLE = 'NO'
        ) THEN '✓ Cedula es NOT NULL (correcto)'
        ELSE '✗ ERROR: Cedula debe ser NOT NULL'
    END as 'Cedula',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'nombre' 
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ Nombre permite NULL (correcto)'
        ELSE '✗ ERROR: Nombre debe permitir NULL'
    END as 'Nombre',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'apellido' 
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ Apellido permite NULL (correcto)'
        ELSE '✗ ERROR: Apellido debe permitir NULL'
    END as 'Apellido',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'posicion_funcional' 
            AND COLUMN_TYPE = 'varchar(100)'
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ Posicion_funcional es varchar(100) y permite NULL (correcto)'
        ELSE '✗ ERROR: Posicion_funcional debe ser varchar(100) y permitir NULL'
    END as 'Posicion_Funcional',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'fecha_nacimiento' 
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ Fecha_nacimiento permite NULL (correcto)'
        ELSE '✗ ERROR: Fecha_nacimiento debe permitir NULL'
    END as 'Fecha_Nacimiento',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'edad' 
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ Edad permite NULL (correcto)'
        ELSE '✗ ERROR: Edad debe permitir NULL'
    END as 'Edad',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'sangre' 
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ Sangre permite NULL (correcto)'
        ELSE '✗ ERROR: Sangre debe permitir NULL'
    END as 'Sangre',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'no_posicion' 
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ No_posicion permite NULL (correcto)'
        ELSE '✗ ERROR: No_posicion debe permitir NULL'
    END as 'No_Posicion',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'fecha_inicio' 
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ Fecha_inicio permite NULL (correcto)'
        ELSE '✗ ERROR: Fecha_inicio debe permitir NULL'
    END as 'Fecha_Inicio',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'sede_provincia' 
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ Sede_provincia permite NULL (correcto)'
        ELSE '✗ ERROR: Sede_provincia debe permitir NULL'
    END as 'Sede_Provincia',
    
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'funcionarios' 
            AND COLUMN_NAME = 'Direccion' 
            AND IS_NULLABLE = 'YES'
        ) THEN '✓ Direccion permite NULL (correcto)'
        ELSE '✗ ERROR: Direccion debe permitir NULL'
    END as 'Direccion';

-- Verificar índices y constraints
SELECT 
    CONSTRAINT_NAME as 'Constraint',
    CONSTRAINT_TYPE as 'Tipo',
    TABLE_NAME as 'Tabla'
FROM 
    INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'funcionarios';

-- Resumen de verificación
SELECT 
    COUNT(*) as 'Total_Campos',
    SUM(CASE WHEN IS_NULLABLE = 'YES' THEN 1 ELSE 0 END) as 'Campos_Nullables',
    SUM(CASE WHEN IS_NULLABLE = 'NO' THEN 1 ELSE 0 END) as 'Campos_No_Nullables'
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'funcionarios';

