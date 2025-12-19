-- Migración: Crear tabla de permisos
-- Fecha: 2025-01-27
-- Descripción: Crea la tabla para gestionar solicitudes de permisos de funcionarios
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Verificar si la tabla ya existe
SET @existe = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'permisos'
);

-- Si no existe, crear la tabla
CREATE TABLE IF NOT EXISTS `permisos` (
    `id_permiso` int NOT NULL AUTO_INCREMENT COMMENT 'ID único del permiso',
    `cedula` varchar(20) NOT NULL COMMENT 'Cédula del funcionario',
    `motivo` enum('Enfermedad','Duelo','Matrimonio','Nacimiento de hijos','Enfermedad de parientes cercanos','Eventos académicos puntuales','Otros asuntos personales') NOT NULL COMMENT 'Motivo del permiso',
    `especifique` text COMMENT 'Especificación adicional cuando motivo es Otros asuntos personales',
    `fecha_desde` date NOT NULL COMMENT 'Fecha de inicio del permiso',
    `hora_desde` time NOT NULL COMMENT 'Hora de inicio del permiso',
    `fecha_hasta` date NOT NULL COMMENT 'Fecha de fin del permiso',
    `hora_hasta` time NOT NULL COMMENT 'Hora de fin del permiso',
    `horas_totales` time GENERATED ALWAYS AS (
        SEC_TO_TIME(
            TIMESTAMPDIFF(SECOND, 
                CONCAT(`fecha_desde`, ' ', `hora_desde`),
                CONCAT(`fecha_hasta`, ' ', `hora_hasta`)
            )
        )
    ) STORED COMMENT 'Horas totales calculadas automáticamente',
    `usuario_registro` int DEFAULT NULL COMMENT 'ID del usuario que registró el permiso',
    `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro',
    `estado` enum('activa','cancelada') NOT NULL DEFAULT 'activa' COMMENT 'Estado del permiso',
    PRIMARY KEY (`id_permiso`),
    KEY `idx_cedula` (`cedula`),
    KEY `idx_fecha_desde` (`fecha_desde`),
    KEY `idx_fecha_hasta` (`fecha_hasta`),
    KEY `idx_estado` (`estado`),
    CONSTRAINT `fk_permisos_cedula` FOREIGN KEY (`cedula`) REFERENCES `funcionarios` (`cedula`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_permisos_usuario` FOREIGN KEY (`usuario_registro`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Solicitudes de permisos de funcionarios';

-- Verificar que la tabla se creó correctamente
DESCRIBE `permisos`;




