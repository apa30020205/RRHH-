-- Migración: Permitir NULL en todos los campos excepto cedula
-- Fecha: 2025-11-21
-- Descripción: Modifica la tabla funcionarios para permitir NULL en todos los campos
--              excepto cedula, facilitando la importación de datos incompletos
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Modificar todos los campos para permitir NULL (excepto cedula que se mantiene como NOT NULL)
ALTER TABLE `funcionarios` 
  MODIFY `nombre` varchar(40) DEFAULT NULL COMMENT 'nombre y segundo nombre',
  MODIFY `apellido` varchar(50) DEFAULT NULL COMMENT 'apellido y segundo apellido',
  MODIFY `fecha_nacimiento` date DEFAULT NULL,
  MODIFY `edad` tinyint DEFAULT NULL COMMENT 'Fecha de nacimiento calculable pero se puede pobne al llenar',
  MODIFY `sangre` varchar(5) DEFAULT NULL COMMENT 'tipo de sangre o+ etc',
  MODIFY `no_posicion` int DEFAULT NULL COMMENT 'es unica ',
  MODIFY `posicion_funcional` varchar(45) DEFAULT NULL COMMENT 'Nombre de la posicion',
  MODIFY `fecha_inicio` date DEFAULT NULL COMMENT 'cuando comenzo a laborar es unico con cada empleado y podiras cambiar de posicion funcional pero mantienes antiguedad',
  MODIFY `sede_provincia` varchar(20) DEFAULT NULL COMMENT 'en que lugar trabaja sede provincia comarca',
  MODIFY `Direccion` varchar(100) DEFAULT NULL COMMENT 'Para que direccion, regional o Espacio del empendedor trabaja';

