-- =====================================================
-- TABLA: USUARIOS - Sistema de Roles y Autenticación
-- =====================================================
-- Fecha: 2025-11-19
-- Descripción: Tabla para gestionar usuarios y roles del sistema RRHH

DROP TABLE IF EXISTS `usuarios`;

CREATE TABLE `usuarios` (
  `id_usuario` int NOT NULL AUTO_INCREMENT COMMENT 'ID único del usuario',
  `username` varchar(50) NOT NULL COMMENT 'Nombre de usuario (único)',
  `password_hash` varchar(255) NOT NULL COMMENT 'Hash de la contraseña (bcrypt)',
  `nombre_completo` varchar(100) NOT NULL COMMENT 'Nombre completo del usuario',
  `email` varchar(100) DEFAULT NULL COMMENT 'Email del usuario',
  `rol` enum('administrador','usuario') NOT NULL DEFAULT 'usuario' COMMENT 'Rol del usuario',
  `activo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Usuario activo (1) o inactivo (0)',
  `creado_por` int DEFAULT NULL COMMENT 'ID del administrador que creó el usuario',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
  `ultimo_acceso` timestamp NULL DEFAULT NULL COMMENT 'Última vez que accedió al sistema',
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `username_UNIQUE` (`username`),
  UNIQUE KEY `email_UNIQUE` (`email`),
  KEY `idx_rol` (`rol`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `fk_usuarios_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Usuarios del sistema RRHH';

-- =====================================================
-- USUARIO ADMINISTRADOR POR DEFECTO
-- =====================================================
-- Contraseña: admin123 (debe cambiarse en producción)
-- Hash generado con password_hash('admin123', PASSWORD_BCRYPT)

INSERT INTO `usuarios` (`username`, `password_hash`, `nombre_completo`, `rol`, `activo`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador del Sistema', 'administrador', 1);

-- =====================================================
-- NOTAS
-- =====================================================
-- 1. Solo los administradores pueden crear/modificar usuarios
-- 2. Las contraseñas se almacenan con hash bcrypt
-- 3. El campo 'creado_por' registra quién creó el usuario
-- 4. Los usuarios inactivos no pueden iniciar sesión

