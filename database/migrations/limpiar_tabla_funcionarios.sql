-- Script para limpiar la tabla funcionarios
-- Fecha: 2025-11-21
-- Descripción: Elimina todos los registros de la tabla funcionarios
--              Útil para hacer pruebas de importación desde cero
--
-- ⚠️ ADVERTENCIAS:
-- 1. Este script elimina TODOS los registros de la tabla funcionarios
-- 2. Hacer backup antes de ejecutar si hay datos importantes
-- 3. Solo usar en ambiente de desarrollo/pruebas

-- Verificar estado ANTES de limpiar
SELECT 
    'ANTES DE LIMPIAR' as 'Estado',
    COUNT(*) as total_registros 
FROM `funcionarios`;

-- Eliminar todos los registros
DELETE FROM `funcionarios`;

-- Verificar que la tabla esté vacía
SELECT 
    'DESPUÉS DE LIMPIAR' as 'Estado',
    COUNT(*) as total_registros 
FROM `funcionarios`;

-- Verificar que la estructura de la tabla se mantenga intacta
-- (Verificar que todos los campos existan)
SELECT 
    'VERIFICACIÓN DE ESTRUCTURA' as 'Verificación',
    COUNT(*) as total_campos,
    CASE 
        WHEN COUNT(*) = 11 THEN '✓ Estructura correcta (11 campos)'
        ELSE CONCAT('✗ ERROR: Se esperaban 11 campos, se encontraron ', COUNT(*))
    END as resultado
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'funcionarios';

-- Verificar que los índices se mantengan
SELECT 
    'VERIFICACIÓN DE ÍNDICES' as 'Verificación',
    COUNT(*) as total_indices,
    CASE 
        WHEN COUNT(*) >= 1 THEN '✓ Índices presentes'
        ELSE '✗ ERROR: Faltan índices'
    END as resultado
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'funcionarios';

-- Verificar que la constraint PRIMARY KEY exista
SELECT 
    'VERIFICACIÓN DE PRIMARY KEY' as 'Verificación',
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'funcionarios'
            AND CONSTRAINT_TYPE = 'PRIMARY KEY'
        ) THEN '✓ PRIMARY KEY presente'
        ELSE '✗ ERROR: PRIMARY KEY no encontrada'
    END as resultado;

