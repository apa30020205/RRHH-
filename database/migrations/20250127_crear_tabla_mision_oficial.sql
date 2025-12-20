-- Migración: Crear tabla de misión oficial
-- Fecha: 2025-01-27
-- Descripción: Crea la tabla para gestionar misiones oficiales de funcionarios
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Verificar si la tabla ya existe
SET @existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'mision_oficial'
);

-- Si no existe, crear la tabla
CREATE TABLE IF NOT EXISTS `mision_oficial` (
    `id_mision` int NOT NULL AUTO_INCREMENT COMMENT 'ID único de la misión oficial',
    `cedula` varchar(20) NOT NULL COMMENT 'Cédula del funcionario',
    `fecha` date NOT NULL COMMENT 'Fecha en que se realizará la misión oficial',
    `hora_desde` time NOT NULL COMMENT 'Hora de inicio de la misión oficial',
    `hora_hasta` time NOT NULL COMMENT 'Hora de fin de la misión oficial',
    `horas_totales` time GENERATED ALWAYS AS (
        SEC_TO_TIME(
            TIMESTAMPDIFF(SECOND,
                CONCAT(`fecha`, ' ', `hora_desde`),
                CONCAT(`fecha`, ' ', `hora_hasta`)
            )
        )
    ) STORED COMMENT 'Horas totales calculadas automáticamente',
    `motivo` text NOT NULL COMMENT 'Motivo de la misión oficial',
    `usuario_registro` int DEFAULT NULL COMMENT 'ID del usuario que registró la misión',
    `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    `estado` enum('activa','eliminada') NOT NULL DEFAULT 'activa' COMMENT 'Estado de la misión oficial',
    PRIMARY KEY (`id_mision`),
    KEY `idx_cedula` (`cedula`),
    KEY `idx_fecha` (`fecha`),
    KEY `idx_estado` (`estado`),
    CONSTRAINT `fk_mision_oficial_cedula` FOREIGN KEY (`cedula`) REFERENCES `funcionarios` (`cedula`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_mision_oficial_usuario` FOREIGN KEY (`usuario_registro`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Misiones oficiales de funcionarios';

-- Verificar que la tabla se creó correctamente
DESCRIBE `mision_oficial`;
