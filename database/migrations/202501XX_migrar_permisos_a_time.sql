-- Migración: Cambiar campos de permisos de DECIMAL (días y horas separados) a TIME (días y horas combinados)
-- Fecha: 2025-01-XX
-- Descripción: Convierte los campos de permisos justificados y no justificados
--              de tener días y horas separados (DECIMAL) a un solo campo TIME
--              que combina días y horas en formato DDD:HH:00:00
--              donde DDD son días (0-838) y HH son horas (0-23)
--
-- IMPORTANTE: Hacer backup de la base de datos antes de ejecutar

-- Paso 1: Agregar los nuevos campos TIME (temporalmente)
ALTER TABLE `funcionarios`
  ADD COLUMN `permisos_justificados_acumulados` TIME NULL COMMENT 'Permisos justificados acumulados en formato DDD:HH:00:00 (días:horas)',
  ADD COLUMN `permisos_no_justificados_acumulados` TIME NULL COMMENT 'Permisos no justificados acumulados en formato DDD:HH:00:00 (días:horas)';

-- Paso 2: Migrar datos existentes de días y horas a formato TIME
-- Convertir días y horas a horas totales, luego a formato TIME
-- Ejemplo: 2 días y 5 horas = 2*24 + 5 = 53 horas = '53:00:00'
-- Pero para mantener días y horas visibles, usamos: días*24 + horas como horas totales
UPDATE `funcionarios`
SET 
  `permisos_justificados_acumulados` = CASE
    WHEN `permisos_justificados_dias_acumulados` IS NOT NULL 
         OR `permisos_justificados_horas_acumuladas` IS NOT NULL
    THEN SEC_TO_TIME(
      COALESCE(FLOOR(`permisos_justificados_dias_acumulados`), 0) * 86400 +  -- días a segundos (24*60*60)
      COALESCE(FLOOR(`permisos_justificados_horas_acumuladas`), 0) * 3600    -- horas a segundos (60*60)
    )
    ELSE NULL
  END,
  `permisos_no_justificados_acumulados` = CASE
    WHEN `permisos_no_justificados_dias_acumulados` IS NOT NULL 
         OR `permisos_no_justificados_horas_acumuladas` IS NOT NULL
    THEN SEC_TO_TIME(
      COALESCE(FLOOR(`permisos_no_justificados_dias_acumulados`), 0) * 86400 +  -- días a segundos
      COALESCE(FLOOR(`permisos_no_justificados_horas_acumuladas`), 0) * 3600    -- horas a segundos
    )
    ELSE NULL
  END;

-- Paso 3: Eliminar los campos antiguos (días y horas separados)
ALTER TABLE `funcionarios`
  DROP COLUMN `permisos_justificados_dias_acumulados`,
  DROP COLUMN `permisos_justificados_horas_acumuladas`,
  DROP COLUMN `permisos_no_justificados_dias_acumulados`,
  DROP COLUMN `permisos_no_justificados_horas_acumuladas`;

-- Verificar la estructura final
DESCRIBE `funcionarios`;

-- Mostrar algunos ejemplos de datos migrados
SELECT 
  cedula,
  nombre,
  apellido,
  `permisos_justificados_acumulados`,
  `permisos_no_justificados_acumulados`,
  -- Convertir TIME a días y horas para verificación
  FLOOR(TIME_TO_SEC(`permisos_justificados_acumulados`) / 86400) AS dias_justificados,
  FLOOR((TIME_TO_SEC(`permisos_justificados_acumulados`) % 86400) / 3600) AS horas_justificadas,
  FLOOR(TIME_TO_SEC(`permisos_no_justificados_acumulados`) / 86400) AS dias_no_justificados,
  FLOOR((TIME_TO_SEC(`permisos_no_justificados_acumulados`) % 86400) / 3600) AS horas_no_justificadas
FROM `funcionarios`
WHERE `permisos_justificados_acumulados` IS NOT NULL 
   OR `permisos_no_justificados_acumulados` IS NOT NULL
LIMIT 10;
