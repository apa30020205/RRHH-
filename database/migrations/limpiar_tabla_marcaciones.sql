-- Script para limpiar la tabla marcaciones
-- Fecha: 2025-11-27
-- Descripción: Trunca la tabla marcaciones para permitir reimportación de datos

-- Desactivar verificación de claves foráneas temporalmente
SET FOREIGN_KEY_CHECKS = 0;

-- Truncar tabla (elimina todos los registros pero mantiene la estructura)
TRUNCATE TABLE `marcaciones`;

-- Reactivar verificación de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- Verificar que la tabla está vacía
SELECT COUNT(*) as total_registros FROM `marcaciones`;

