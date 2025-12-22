-- Migración: Crear tabla de reincorporación
-- Fecha: 2025-01-27
-- Descripción: Crea la tabla para gestionar reincorporaciones de funcionarios
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Verificar si la tabla ya existe
SET @existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'reincorporacion'
);

-- Si no existe, crear la tabla
CREATE TABLE IF NOT EXISTS `reincorporacion` (
    `id_reincorporacion` int NOT NULL AUTO_INCREMENT COMMENT 'ID único de la reincorporación',
    `cedula` varchar(20) NOT NULL COMMENT 'Cédula del funcionario',
    `motivo_ausencia` enum('Licencia con sueldo','Licencia sin sueldo','Licencia especial','Vacaciones','Prestando funciones en otra Institución') NOT NULL COMMENT 'Motivo de la ausencia',
    `puesto` varchar(100) NOT NULL COMMENT 'Puesto al que se reincorpora',
    `no_posicion` int DEFAULT NULL COMMENT 'Número de posición',
    `unidad_administrativa` text COMMENT 'Unidad administrativa',
    `fecha_reincorporacion` date NOT NULL COMMENT 'Fecha de reincorporación',
    `usuario_registro` int DEFAULT NULL COMMENT 'ID del usuario que registró la reincorporación',
    `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    `estado` enum('activa','eliminada') NOT NULL DEFAULT 'activa' COMMENT 'Estado de la reincorporación',
    PRIMARY KEY (`id_reincorporacion`),
    KEY `idx_cedula` (`cedula`),
    KEY `idx_fecha_reincorporacion` (`fecha_reincorporacion`),
    KEY `idx_estado` (`estado`),
    CONSTRAINT `fk_reincorporacion_cedula` FOREIGN KEY (`cedula`) REFERENCES `funcionarios` (`cedula`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_reincorporacion_usuario` FOREIGN KEY (`usuario_registro`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Reincorporaciones de funcionarios';

-- Verificar que la tabla se creó correctamente
DESCRIBE `reincorporacion`;

