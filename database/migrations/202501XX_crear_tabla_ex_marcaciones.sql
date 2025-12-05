-- Migración: Crear tabla ex_marcaciones
-- Fecha: 2025-01-XX
-- Descripción: Crea la tabla ex_marcaciones con la misma estructura que marcaciones
--              para almacenar las marcaciones de funcionarios que han sido cesados

CREATE TABLE IF NOT EXISTS `ex_marcaciones` (
  `id_marcacion` int NOT NULL AUTO_INCREMENT COMMENT 'ID único de la marcación',
  `cedula` varchar(20) NOT NULL COMMENT 'Cédula del ex-funcionario',
  `fecha` date NOT NULL COMMENT 'Fecha de la marcación',
  `hora_entrada` time DEFAULT NULL COMMENT 'Hora de entrada (primera del día)',
  `hora_salida` time DEFAULT NULL COMMENT 'Hora de salida (última del día)',
  `horas_trabajadas` time DEFAULT NULL COMMENT 'Horas y minutos trabajados en el día (formato HH:MM:SS)',
  `tiempo_faltante` time DEFAULT NULL COMMENT 'Tiempo faltante si no se cumplen 8 horas (formato HH:MM:SS)',
  `fecha_importacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora de importación',
  PRIMARY KEY (`id_marcacion`),
  KEY `idx_cedula` (`cedula`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_fecha_importacion` (`fecha_importacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Marcaciones biométricas de ex-funcionarios';

DESCRIBE `ex_marcaciones`;

