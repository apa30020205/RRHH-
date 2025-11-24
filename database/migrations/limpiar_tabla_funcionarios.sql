-- Script para limpiar la tabla funcionarios
-- Fecha: 2025-11-21
-- Descripción: Elimina todos los registros de la tabla funcionarios
--              Útil para hacer pruebas de importación desde cero
--
-- ⚠️ ADVERTENCIAS:
-- 1. Este script elimina TODOS los registros de la tabla funcionarios
-- 2. Hacer backup antes de ejecutar si hay datos importantes
-- 3. Solo usar en ambiente de desarrollo/pruebas

-- Eliminar todos los registros
DELETE FROM `funcionarios`;

-- Opcional: Reiniciar el contador de auto-increment si hubiera
-- (No aplica a esta tabla ya que no tiene auto-increment)

-- Verificar que la tabla esté vacía
SELECT COUNT(*) as total_registros FROM `funcionarios`;

