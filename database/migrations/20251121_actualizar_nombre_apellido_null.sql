-- Migración: Permitir NULL en nombre y apellido
-- Fecha: 2025-11-21
-- Descripción: Modifica la tabla funcionarios para permitir NULL en nombre y apellido
--              ya que algunos procesos de importación no incluyen estos campos

-- Si la tabla ya existe, aplicar ALTER TABLE
ALTER TABLE `funcionarios` 
  MODIFY `nombre` varchar(40) DEFAULT NULL COMMENT 'nombre y segundo nombre',
  MODIFY `apellido` varchar(50) DEFAULT NULL COMMENT 'apellido y segundo apellido';

