-- =====================================================
-- TABLA: ERRORES DE IMPORTACIÓN BIOMÉTRICA
-- =====================================================
-- Fecha: 2025-11-21
-- Descripción: Tabla para almacenar errores de importación del archivo "personal biométrico"
--              cuando el ID del Excel no existe como cédula en la tabla funcionarios

CREATE TABLE IF NOT EXISTS `errores_importacion_biometrico` (
  `id_error` int NOT NULL AUTO_INCREMENT COMMENT 'ID único del error',
  `id_excel` varchar(20) NOT NULL COMMENT 'ID del Excel (sin guiones)',
  `nombre_excel` varchar(40) DEFAULT NULL COMMENT 'Nombre del Excel',
  `apellido_excel` varchar(50) DEFAULT NULL COMMENT 'Apellido del Excel',
  `fila_excel` int DEFAULT NULL COMMENT 'Número de fila en el Excel donde se encontró el error',
  `fecha_importacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora en que se detectó el error',
  `resuelto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el error fue resuelto manualmente (1) o pendiente (0)',
  `fecha_resolucion` timestamp NULL DEFAULT NULL COMMENT 'Fecha en que se resolvió el error',
  `observaciones` text DEFAULT NULL COMMENT 'Notas sobre la resolución del error',
  PRIMARY KEY (`id_error`),
  KEY `idx_id_excel` (`id_excel`),
  KEY `idx_resuelto` (`resuelto`),
  KEY `idx_fecha_importacion` (`fecha_importacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Errores de importación biométrica - IDs no encontrados en funcionarios';

-- =====================================================
-- NOTAS
-- =====================================================
-- 1. Esta tabla almacena los registros del Excel "personal biométrico" 
--    cuyo ID no existe como cédula en la tabla funcionarios
-- 2. El campo 'resuelto' permite marcar errores que ya fueron corregidos manualmente
-- 3. Se puede buscar por ID del Excel para encontrar errores específicos
-- 4. Los errores se pueden ordenar por fecha de importación, ID, nombre, etc.

