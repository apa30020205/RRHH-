-- Migración: Aumentar tamaño del campo posicion_funcional
-- Fecha: 2025-11-21
-- Descripción: Aumenta el tamaño de posicion_funcional de varchar(45) a varchar(100)
--              para permitir nombres de posiciones más largos

ALTER TABLE `funcionarios` 
  MODIFY `posicion_funcional` varchar(100) DEFAULT NULL COMMENT 'Nombre de la posicion';

