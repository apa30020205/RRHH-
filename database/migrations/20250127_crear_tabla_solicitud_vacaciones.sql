-- Migración: Crear tabla de solicitud de vacaciones
-- Fecha: 2025-01-27
-- Descripción: Crea la tabla para gestionar solicitudes de vacaciones de funcionarios
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Verificar si la tabla ya existe
SET @existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'solicitud_vacaciones'
);

-- Si no existe, crear la tabla
CREATE TABLE IF NOT EXISTS `solicitud_vacaciones` (
    `id_vacacion` int NOT NULL AUTO_INCREMENT COMMENT 'ID único de la solicitud de vacación',
    `cedula` varchar(20) NOT NULL COMMENT 'Cédula del funcionario',
    `dias_solicitados` int DEFAULT NULL COMMENT 'Total de días solicitados en la declaración',
    `fecha_inicio` date DEFAULT NULL COMMENT 'Fecha en que inician las vacaciones',
    `fecha_retorno` date DEFAULT NULL COMMENT 'Fecha en que retorna a labores',
    `resolucion` varchar(100) DEFAULT NULL COMMENT 'Número de resolución',
    `fecha_resolucion` date DEFAULT NULL COMMENT 'Fecha de la resolución',
    `dias_vacacion` int NOT NULL COMMENT 'Días de vacación de esta línea/registro',
    `observaciones` text COMMENT 'Observaciones generales',
    `usuario_registro` int DEFAULT NULL COMMENT 'ID del usuario que registró la solicitud',
    `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    `estado` enum('activa','eliminada') NOT NULL DEFAULT 'activa' COMMENT 'Estado de la solicitud',
    PRIMARY KEY (`id_vacacion`),
    KEY `idx_cedula` (`cedula`),
    KEY `idx_fecha_inicio` (`fecha_inicio`),
    KEY `idx_fecha_retorno` (`fecha_retorno`),
    KEY `idx_estado` (`estado`),
    CONSTRAINT `fk_solicitud_vacaciones_cedula` FOREIGN KEY (`cedula`) REFERENCES `funcionarios` (`cedula`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_solicitud_vacaciones_usuario` FOREIGN KEY (`usuario_registro`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Solicitudes de vacaciones de funcionarios';

-- Verificar que la tabla se creó correctamente
DESCRIBE `solicitud_vacaciones`;

