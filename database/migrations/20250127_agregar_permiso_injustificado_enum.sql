-- Migración: Agregar 'Permiso InJustificado' al ENUM de motivo en tabla permisos
-- Fecha: 2025-01-27
-- Descripción: Agrega el nuevo valor 'Permiso InJustificado' al ENUM del campo motivo
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Modificar el ENUM para incluir 'Permiso InJustificado'
ALTER TABLE `permisos` 
MODIFY COLUMN `motivo` enum(
    'Enfermedad',
    'Duelo',
    'Matrimonio',
    'Nacimiento de hijos',
    'Enfermedad de parientes cercanos',
    'Eventos académicos puntuales',
    'Otros asuntos personales',
    'Permiso InJustificado'
) NOT NULL COMMENT 'Motivo del permiso';

-- Verificar el cambio
DESCRIBE `permisos`;
