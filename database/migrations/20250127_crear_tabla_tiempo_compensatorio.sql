-- Migración: Crear tabla de tiempo compensatorio
-- Fecha: 2025-01-27
-- Descripción: Crea la tabla para gestionar tiempo compensatorio de funcionarios
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Verificar si la tabla ya existe
SET @existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tiempo_compensatorio'
);

-- Si no existe, crear la tabla
CREATE TABLE IF NOT EXISTS `tiempo_compensatorio` (
    `id_tiempo_comp` int NOT NULL AUTO_INCREMENT COMMENT 'ID único del tiempo compensatorio',
    `cedula` varchar(20) NOT NULL COMMENT 'Cédula del funcionario',
    `horas` int NOT NULL DEFAULT 0 COMMENT 'Horas de tiempo compensatorio',
    `dias` int NOT NULL DEFAULT 0 COMMENT 'Días de tiempo compensatorio',
    `fecha_uso` date NOT NULL COMMENT 'Fecha en que se usa el tiempo compensatorio',
    `usuario_registro` int DEFAULT NULL COMMENT 'ID del usuario que registró el tiempo compensatorio',
    `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    `estado` enum('activa','eliminada') NOT NULL DEFAULT 'activa' COMMENT 'Estado del registro',
    PRIMARY KEY (`id_tiempo_comp`),
    KEY `idx_cedula` (`cedula`),
    KEY `idx_fecha_uso` (`fecha_uso`),
    KEY `idx_estado` (`estado`),
    CONSTRAINT `fk_tiempo_compensatorio_cedula` FOREIGN KEY (`cedula`) REFERENCES `funcionarios` (`cedula`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tiempo_compensatorio_usuario` FOREIGN KEY (`usuario_registro`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tiempo compensatorio de funcionarios';

-- Verificar que la tabla se creó correctamente
DESCRIBE `tiempo_compensatorio`;

