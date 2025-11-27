-- =====================================================
-- Crear tabla errores_importacion_funcionarios
-- =====================================================
-- Fecha: 2025-11-26
-- Descripción: Tabla para almacenar errores de importación RRHH
--              Funcionarios con nombre o apellido vacíos
-- =====================================================

CREATE TABLE IF NOT EXISTS `errores_importacion_funcionarios` (
  `id_error` int NOT NULL AUTO_INCREMENT COMMENT 'ID único del error',
  `cedula` varchar(20) NOT NULL COMMENT 'Cédula del funcionario (del Excel columna D)',
  `nombre_y_apellido` varchar(100) DEFAULT NULL COMMENT 'Nombre y Apellido combinado (del Excel columna E)',
  `fecha_nacimiento` date DEFAULT NULL COMMENT 'Fecha de nacimiento (de tabla funcionarios)',
  `edad` tinyint DEFAULT NULL COMMENT 'Edad (de tabla funcionarios)',
  `sangre` varchar(5) DEFAULT NULL COMMENT 'Tipo de sangre (de tabla funcionarios)',
  `no_posicion` int DEFAULT NULL COMMENT 'Número de posición (de tabla funcionarios)',
  `posicion_funcional` varchar(100) DEFAULT NULL COMMENT 'Nombre de la posición funcional (de tabla funcionarios)',
  `fecha_inicio` date DEFAULT NULL COMMENT 'Fecha de inicio laboral (de tabla funcionarios)',
  `sede_provincia` varchar(20) DEFAULT NULL COMMENT 'Sede o provincia (de tabla funcionarios)',
  `Direccion` varchar(100) DEFAULT NULL COMMENT 'Dirección (de tabla funcionarios)',
  `fila_excel` int DEFAULT NULL COMMENT 'Número de fila en el Excel donde se encontró el error',
  `fecha_importacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora en que se detectó el error',
  `resuelto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el error fue resuelto manualmente (1) o pendiente (0)',
  `fecha_resolucion` timestamp NULL DEFAULT NULL COMMENT 'Fecha en que se resolvió el error',
  `observaciones` text DEFAULT NULL COMMENT 'Notas sobre la resolución del error',
  PRIMARY KEY (`id_error`),
  KEY `idx_cedula` (`cedula`),
  KEY `idx_resuelto` (`resuelto`),
  KEY `idx_fecha_importacion` (`fecha_importacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Errores de importación RRHH - Funcionarios con nombre o apellido vacíos';

