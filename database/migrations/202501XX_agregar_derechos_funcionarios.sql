-- Migración: Agregar campos de derechos de funcionarios por año
-- Fecha: 2025-01-XX
-- Descripción: Agrega campos para gestionar derechos de funcionarios por año:
--              - Vacaciones: 30 días/año (tomados por día)
--              - Permisos Justificados: 15 días/horas al año
--              - Permisos No Justificados: 3 días/horas al año

ALTER TABLE `funcionarios`
  ADD COLUMN `vacaciones_dias_acumulados` DECIMAL(5,2) DEFAULT 0 COMMENT 'Días de vacaciones acumulados (30 días/año, tomados por día)',
  ADD COLUMN `permisos_justificados_dias_acumulados` DECIMAL(5,2) DEFAULT 0 COMMENT 'Días de permisos justificados acumulados (15 días/año)',
  ADD COLUMN `permisos_justificados_horas_acumuladas` DECIMAL(5,2) DEFAULT 0 COMMENT 'Horas de permisos justificados acumuladas (parte de los 15 días)',
  ADD COLUMN `permisos_no_justificados_dias_acumulados` DECIMAL(5,2) DEFAULT 0 COMMENT 'Días de permisos no justificados acumulados (3 días/año)',
  ADD COLUMN `permisos_no_justificados_horas_acumuladas` DECIMAL(5,2) DEFAULT 0 COMMENT 'Horas de permisos no justificados acumuladas (parte de los 3 días)',
  ADD COLUMN `ano_derechos` YEAR DEFAULT NULL COMMENT 'Año al que corresponden estos derechos';

DESCRIBE `funcionarios`;
