# Mapeo de Columnas Excel → Base de Datos

## Descripción
Este documento describe cómo se mapean las columnas del archivo Excel a los campos de la base de datos en el módulo de importación.

## Archivo de Procesamiento
- **Archivo:** `services/excel/procesar_rrhh.php`
- **Líneas relevantes:** 87-145 (detección de columnas), 147-220 (validación y procesamiento)

## Mapeo de Columnas

### 1. CEDULA / CÉDULA
- **Columnas Excel detectadas:**
  - `CEDULA` (exacto, case-insensitive)
  - `CÉDULA` (con acento)
  - Cualquier columna que contenga "cedula" (case-insensitive)
- **Campo BD:** `cedula` (varchar(20), NOT NULL, PRIMARY KEY)
- **Validaciones:**
  - Campo obligatorio (no puede estar vacío)
  - Debe pasar `validarCedula()`
  - Se normaliza con `normalizarCedula()` (quita guiones y espacios)
  - Se formatea con `formatearCedula()` (preserva guiones si ya existen)

### 2. FECHA DE NACIMIENTO
- **Columnas Excel detectadas:**
  - Cualquier columna que contenga "fecha" Y "nacimiento" (case-insensitive)
- **Campo BD:** `fecha_nacimiento` (date, NULL)
- **Validaciones:**
  - Campo opcional (puede ser NULL)
  - Se formatea con `formatearFechaBD()` si está presente
  - Acepta formatos: YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY

### 3. EDAD
- **Columnas Excel detectadas:**
  - `EDAD` (exacto, case-insensitive)
- **Campo BD:** `edad` (tinyint, NULL)
- **Validaciones:**
  - Campo opcional (puede ser NULL)
  - Se convierte a entero con `intval()`
  - Si está vacío, se guarda como NULL

### 4. TIPO DE SANGRE
- **Columnas Excel detectadas:**
  - Cualquier columna que contenga "sangre" (case-insensitive)
  - O columna que contenga "tipo" Y "sangre" (case-insensitive)
- **Campo BD:** `sangre` (varchar(5), NULL)
- **Validaciones:**
  - Campo opcional (puede ser NULL)
  - Se sanitiza con `sanitize()`

### 5. POSICIÓN
- **Columnas Excel detectadas:**
  - `POSICIÓN` o `POSICION` (exacto, case-insensitive)
  - **IMPORTANTE:** Solo si NO contiene "FUNCIONAL" (para evitar conflicto con POSICIÓN FUNCIONAL)
- **Campo BD:** `no_posicion` (int, NULL)
- **Validaciones:**
  - Campo opcional (puede ser NULL)
  - Se convierte a entero con `intval()`
  - Si está vacío, se guarda como NULL

### 6. POSICIÓN FUNCIONAL
- **Columnas Excel detectadas:**
  - `POSICIÓN FUNCIONAL` o `POSICION FUNCIONAL` (cadena completa, case-insensitive)
  - **IMPORTANTE:** Se busca como cadena completa PRIMERO para evitar conflicto con "POSICIÓN"
- **Campo BD:** `posicion_funcional` (varchar(100), NULL)
- **Validaciones:**
  - Campo opcional (puede ser NULL)
  - Se trunca a 100 caracteres con `mb_substr()` antes de sanitizar
  - Se sanitiza con `sanitize()`

### 7. FECHA DE INICIO
- **Columnas Excel detectadas:**
  - Cualquier columna que contenga "fecha" Y "inicio" (case-insensitive)
- **Campo BD:** `fecha_inicio` (date, NULL)
- **Validaciones:**
  - Campo opcional (puede ser NULL)
  - Se formatea con `formatearFechaBD()` si está presente
  - Acepta formatos: YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY

### 8. DIRECCIÓN O SEDE
- **Columnas Excel detectadas:**
  - Cualquier columna que contenga "dirección", "direccion" o "sede" (case-insensitive)
- **Campos BD:** 
  - `sede_provincia` (varchar(20), NULL) - Parte antes del guion
  - `Direccion` (varchar(100), NULL) - Parte después del guion
- **Validaciones:**
  - Campo opcional (puede ser NULL)
  - Se divide por guion (`-`): parte antes → `sede_provincia`, parte después → `Direccion`
  - Si no hay guion, todo el valor va a `Direccion` y `sede_provincia` queda NULL
  - Se sanitiza con `sanitize()`

## Columnas NO Mapeadas

### NOMBRE Y APELLIDO
- **Nota:** Esta columna NO se graba en la base de datos
- **Razón:** El proceso de importación no incluye estos campos
- **Mensaje al usuario:** Se muestra en las instrucciones que este campo no se grabará

## Características de Detección

### Case-Insensitive
Todas las búsquedas de columnas son case-insensitive. Ejemplos:
- `CEDULA`, `cedula`, `Cedula` → todas detectadas
- `FECHA DE NACIMIENTO`, `fecha de nacimiento` → ambas detectadas

### Manejo de Acentos
Las búsquedas manejan acentos. Ejemplos:
- `CÉDULA` y `CEDULA` → ambas detectadas
- `POSICIÓN` y `POSICION` → ambas detectadas

### Manejo de Espacios
Los espacios en los nombres de columnas se manejan correctamente:
- `FECHA DE NACIMIENTO` → detectada
- `POSICIÓN FUNCIONAL` → detectada

### Columnas Ignoradas
- Columnas que contienen "Unnamed" en el nombre se ignoran automáticamente

## Orden de Prioridad en Detección

1. **POSICIÓN FUNCIONAL** se busca PRIMERO como cadena completa
2. **POSICIÓN** se busca DESPUÉS, solo si no contiene "FUNCIONAL"
3. Esto evita que "POSICIÓN FUNCIONAL" se detecte incorrectamente como "POSICIÓN"

## Ejemplo de Archivo Excel

| CEDULA | FECHA DE NACIMIENTO | EDAD | TIPO DE SANGRE | POSICIÓN | POSICIÓN FUNCIONAL | FECHA DE INICIO | DIRECCIÓN O SEDE |
|--------|-------------------|------|----------------|----------|-------------------|-----------------|------------------|
| 8-1234-5678 | 15/01/1990 | 34 | O+ | 100 | Analista de Sistemas | 01/01/2020 | Panamá-Ciudad |

**Resultado en BD:**
- `cedula`: `812345678` (normalizada)
- `fecha_nacimiento`: `1990-01-15`
- `edad`: `34`
- `sangre`: `O+`
- `no_posicion`: `100`
- `posicion_funcional`: `Analista de Sistemas`
- `fecha_inicio`: `2020-01-01`
- `sede_provincia`: `Panamá`
- `Direccion`: `Ciudad`

## Notas Importantes

1. **Cédula única:** La cédula es clave primaria, por lo que si se intenta importar un registro con una cédula existente, se actualizará el registro existente en lugar de crear uno nuevo.

2. **Valores NULL:** Todos los campos excepto `cedula` pueden ser NULL. Esto permite importar datos incompletos.

3. **Truncamiento:** `posicion_funcional` se trunca automáticamente a 100 caracteres si excede ese límite.

4. **Sanitización:** Todos los valores de texto se sanitizan con `sanitize()` antes de insertar en la BD para prevenir XSS y otros ataques.

