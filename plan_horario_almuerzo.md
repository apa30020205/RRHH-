# Plan: Detección Automática de Horario de Almuerzo

## Objetivo
Implementar detección automática del horario de almuerzo para filtrar marcaciones falsas del reloj biométrico y mostrar "Alm. Entrada" y "Alm. Salida" en la página de marcaciones.

## Problema Actual
- El reloj biométrico es muy sensible y registra marcaciones cuando las personas pasan cerca
- Puede haber 8-10 marcaciones por día, pero solo la primera y última son relevantes
- Se necesita identificar automáticamente el período de almuerzo (1 hora) entre las 11 AM y 3 PM

## Estructura del Excel

**Columna J - "Hora de Registro":**
- Encabezado en fila 2: "Hora de Registro"
- Datos comienzan en fila 3
- Contiene TODAS las horas de registro individuales del día
- Estas son las que se usarán para detectar el horario de almuerzo

**Otras columnas (ya procesadas):**
- Columna A: ID de Usuario
- Columna F: Grabar fecha
- Columna H: Hora mas temprana (primera entrada)
- Columna I: última Hora (última salida)

## Solución Propuesta

### 1. Modificar Estructura de Base de Datos
**Archivo:** `database/migrations/202501XX_agregar_campos_almuerzo_marcaciones.sql`

Agregar dos nuevos campos a la tabla `marcaciones`:
- `almuerzo_entrada` (TIME, NULL) - Hora de entrada del almuerzo
- `almuerzo_salida` (TIME, NULL) - Hora de salida del almuerzo

### 2. Crear Función de Detección de Almuerzo
**Archivo:** `includes/funciones_deteccion_almuerzo.php` (nuevo)

Función que:
- Recibe todas las marcaciones del día (array de horas)
- Busca un intervalo de aproximadamente 1 hora entre las 11:00 AM y 3:00 PM
- Retorna la hora de entrada y salida del almuerzo detectado
- Lógica:
  - Filtrar marcaciones entre 11:00 y 15:00
  - Buscar el intervalo más cercano a 1 hora (60 minutos)
  - Tolerancia: entre 45 y 75 minutos
  - Si hay múltiples intervalos, elegir el más cercano a 1 hora

### 3. Modificar Procesamiento de Marcaciones
**Archivo:** `services/excel/procesar_marcaciones.php`

Cambios:
- Leer la **Columna J (índice 9)** que contiene "Hora de Registro"
- Guardar TODAS las horas de registro en un array por funcionario/fecha
- Agrupar por cedula + fecha (como ahora)
- Para cada grupo:
  - Identificar primera entrada (Col H) y última salida (Col I) - como ahora
  - **NUEVO:** Procesar todas las horas de la Columna J para detectar almuerzo
  - Detectar horario de almuerzo usando la función nueva con las horas de Columna J
  - Guardar en `almuerzo_entrada` y `almuerzo_salida`
- Mantener compatibilidad con el sistema actual

### 4. Actualizar Visualización de Marcaciones
**Archivo:** `pages/marcaciones/listar.php`

Cambios:
- Agregar dos nuevas columnas en la tabla:
  - "Alm. Entrada" - mostrar `almuerzo_entrada` en formato 12h (a.m./p.m.)
  - "Alm. Salida" - mostrar `almuerzo_salida` en formato 12h (a.m./p.m.)
- **Posición:** ENTRE "Hora Entrada" y "Hora Salida" (no después)
- **Validación visual:**
  - Si `almuerzo_entrada` o `almuerzo_salida` es NULL → fondo rojo
  - Si la diferencia entre `almuerzo_salida` y `almuerzo_entrada` > 60 minutos → fondo rojo
  - Si todo está correcto (almuerzo ≤ 1 hora) → fondo normal
- Si es NULL, mostrar "-" con fondo rojo

### 5. Recalcular Marcaciones Existentes (Opcional)
**Archivo:** `database/migrations/202501XX_recalcular_almuerzo_marcaciones.php` (opcional)

Script para:
- Procesar todas las marcaciones existentes
- Detectar horarios de almuerzo retroactivamente
- Actualizar los campos `almuerzo_entrada` y `almuerzo_salida`

**Nota:** Esto requiere tener acceso a todas las marcaciones individuales del día, que actualmente no se guardan. Se necesitaría re-importar desde el Excel original o modificar el sistema para guardar todas las marcaciones.

## Consideraciones Importantes

### Fuente de Datos
- La **Columna J** del Excel contiene todas las horas de registro individuales
- Durante la importación, se procesarán todas estas horas para detectar el almuerzo
- No es necesario guardar todas las horas individuales en la BD, solo el resultado del almuerzo detectado

### Opciones de Implementación:
1. **Solo nuevas importaciones:** Aplicar la detección solo a marcaciones nuevas (recomendado)
2. **Re-procesar existentes:** Si se tienen los Excel originales, re-importarlos con la nueva lógica

## Archivos a Modificar/Crear

1. `database/migrations/202501XX_agregar_campos_almuerzo_marcaciones.sql` - Migración BD
2. `includes/funciones_deteccion_almuerzo.php` - Nueva función de detección
3. `services/excel/procesar_marcaciones.php` - Modificar procesamiento
4. `pages/marcaciones/listar.php` - Agregar columnas de visualización
5. `database/migrations/202501XX_recalcular_almuerzo_marcaciones.php` - Script opcional para recalcular

## Algoritmo de Detección de Almuerzo

```
1. Obtener todas las horas de registro de la Columna J del día
2. Ordenar todas las horas por orden cronológico
3. Filtrar marcaciones entre 11:00 AM y 3:00 PM
4. Para cada par de marcaciones consecutivas en ese rango:
   - Calcular diferencia de tiempo entre ellas
   - Si la diferencia está entre 45-75 minutos (tolerancia):
     - Guardar como candidato a almuerzo
     - La primera hora del par = almuerzo_entrada
     - La segunda hora del par = almuerzo_salida
5. De todos los candidatos, elegir el más cercano a 60 minutos
6. Si se encuentra, guardar como almuerzo_entrada y almuerzo_salida
7. Si no se encuentra ningún intervalo válido, dejar NULL
```

**Ejemplo:**
- Horas de registro: 7:30, 8:00, 11:45, 12:30, 13:15, 16:00, 17:00
- Filtrar entre 11:00-15:00: 11:45, 12:30, 13:15
- Intervalos:
  - 11:45 → 12:30 = 45 minutos (candidato)
  - 12:30 → 13:15 = 45 minutos (candidato)
- Elegir el más cercano a 60 minutos (ambos son 45, elegir el primero)
- Resultado: almuerzo_entrada = 11:45, almuerzo_salida = 12:30

## Validación Visual en Listado

En `pages/marcaciones/listar.php`:
- **Fondo rojo** si:
  - `almuerzo_entrada` o `almuerzo_salida` es NULL (no detectado)
  - La diferencia entre `almuerzo_salida` y `almuerzo_entrada` > 60 minutos (excedió 1 hora)
- **Fondo normal** si:
  - Ambos campos tienen valores
  - La diferencia es ≤ 60 minutos (almuerzo válido)

## Notas Finales

- La detección se aplicará solo a nuevas importaciones
- Si no se detecta almuerzo o excede 1 hora, se mostrará visualmente en rojo para alertar
- Las columnas se posicionan entre "Hora Entrada" y "Hora Salida" para mantener el flujo lógico

