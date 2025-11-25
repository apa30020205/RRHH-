# Estado del Módulo de Importación Excel

## Fecha de Documentación
2025-11-21

## Resumen Ejecutivo
Este documento describe el estado actual del módulo de importación de Excel, incluyendo configuración requerida, casos de prueba, errores conocidos y recomendaciones.

## Configuración Requerida

### 1. Base de Datos
- **Base de datos:** `rrhh`
- **Tabla:** `funcionarios`
- **Migraciones aplicadas:**
  - `20251121_actualizar_nombre_apellido_null.sql` - Permite NULL en nombre y apellido
  - `20251121_permitir_null_todos_campos.sql` - Permite NULL en todos los campos excepto cedula
  - `20251121_aumentar_posicion_funcional.sql` - Aumenta posicion_funcional a varchar(100)

### 2. Microservicio Python
- **URL:** `http://localhost:5000`
- **Endpoints:**
  - `/api/health` - Verificación de estado
  - `/api/read-excel` - Lectura de archivos Excel
- **Ubicación:** `C:\AMPYME\MICROSERVICIO LECTURA DE EXCEL`
- **Inicio:** Ejecutar `python app.py` o `start.bat`

### 3. Archivos del Sistema
- **Interfaz:** `services/excel/importar.php`
- **Procesamiento:** `services/excel/procesar_rrhh.php`
- **Funciones auxiliares:** `includes/functions.php`
- **Conexión BD:** `classes/Database.php`

## Flujo de Importación

### Paso 1: Carga de Archivo
1. Usuario accede a `services/excel/importar.php`
2. Sistema verifica estado del microservicio
3. Usuario arrastra o selecciona archivo Excel
4. JavaScript envía archivo al microservicio Python

### Paso 2: Lectura del Archivo
1. Microservicio lee el archivo Excel
2. Devuelve JSON con estructura:
   ```json
   {
     "success": true,
     "data": [...],
     "columns": [...],
     "total_rows": 100
   }
   ```

### Paso 3: Vista Previa
1. Frontend muestra vista previa de datos (primeras 5 filas)
2. Muestra estadísticas (total de filas, columnas detectadas)
3. Muestra botón "Procesar e Importar"

### Paso 4: Procesamiento
1. Usuario hace clic en "Procesar e Importar"
2. JavaScript envía datos a `procesar_rrhh.php`
3. Backend procesa cada fila:
   - Detecta columnas (case-insensitive)
   - Valida cédula (obligatoria)
   - Normaliza y formatea datos
   - Inserta o actualiza en BD

### Paso 5: Resultado
1. Backend retorna estadísticas:
   - Nuevos registros
   - Registros actualizados
   - Errores encontrados
2. Frontend muestra resultado al usuario

## Mapeo de Columnas

Ver documento detallado: `docs/MAPEO_COLUMNAS_EXCEL.md`

### Resumen
- **CEDULA** → `cedula` (obligatorio, clave primaria)
- **FECHA DE NACIMIENTO** → `fecha_nacimiento` (opcional)
- **EDAD** → `edad` (opcional)
- **TIPO DE SANGRE** → `sangre` (opcional)
- **POSICIÓN** → `no_posicion` (opcional)
- **POSICIÓN FUNCIONAL** → `posicion_funcional` (opcional, max 100 chars)
- **FECHA DE INICIO** → `fecha_inicio` (opcional)
- **DIRECCIÓN O SEDE** → `sede_provincia` y `Direccion` (opcional, dividido por guion)

## Validaciones Implementadas

### Cédula
- ✅ Campo obligatorio
- ✅ Validación de formato (`validarCedula()`)
- ✅ Normalización (`normalizarCedula()`)
- ✅ Formateo (`formatearCedula()`)

### Fechas
- ✅ Formato YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY
- ✅ Conversión a formato BD (`formatearFechaBD()`)
- ✅ Campos opcionales (pueden ser NULL)

### Texto
- ✅ Sanitización (`sanitize()`)
- ✅ Truncamiento de `posicion_funcional` a 100 caracteres
- ✅ Manejo de valores NULL

## Seguridad

### Implementada
- ✅ Verificación de permisos (solo administradores)
- ✅ Validación de método HTTP (solo POST)
- ✅ Validación de estructura JSON
- ✅ Sanitización de todos los datos de entrada
- ✅ Uso de prepared statements (protección SQL injection)
- ✅ Validación de formato de cédula

### Verificación
Ejecutar: `tests/test_seguridad.php`

## Casos de Prueba

### Casos Válidos
1. ✅ Archivo Excel con todas las columnas requeridas
2. ✅ Archivo Excel con columnas opcionales faltantes
3. ✅ Cédulas con y sin guiones
4. ✅ Fechas en diferentes formatos
5. ✅ Valores NULL en campos opcionales
6. ✅ `posicion_funcional` con más de 100 caracteres (se trunca)

### Casos de Error
1. ✅ Archivo sin columna CEDULA
2. ✅ Cédulas inválidas o vacías
3. ✅ Fechas inválidas
4. ✅ Microservicio no disponible
5. ✅ Archivo muy grande (>50MB)
6. ✅ Usuario sin permisos de administrador

## Errores Conocidos

### Ninguno Actualmente
No se han identificado errores conocidos en el módulo actual.

### Soluciones Preventivas
- Script de limpieza de BD antes de pruebas: `database/migrations/limpiar_tabla_funcionarios.sql`
- Script de verificación de estructura: `database/migrations/verificar_estructura_tabla.sql`
- Script de verificación de microservicio: `services/excel/verificar_microservicio.php`

## Scripts de Verificación

### 1. Verificación de Migraciones
**Archivo:** `database/migrations/verificar_estructura_tabla.sql`
**Uso:** Ejecutar en phpMyAdmin o cliente MySQL
**Verifica:**
- Estructura completa de la tabla
- Campos NULL permitidos
- Tamaño de `posicion_funcional` (varchar 100)
- Índices y constraints

### 2. Limpieza de Base de Datos
**Archivo:** `database/migrations/limpiar_tabla_funcionarios.sql`
**Uso:** Ejecutar antes de pruebas de importación
**Acción:** Elimina todos los registros manteniendo estructura

### 3. Verificación de Microservicio
**Archivo:** `services/excel/verificar_microservicio.php`
**URL:** `http://localhost/SISTEMA%20%20RRHH/services/excel/verificar_microservicio.php`
**Verifica:**
- Estado del microservicio
- Endpoints disponibles
- Función `verificarMicroservicio()`

### 4. Pruebas de Funciones Auxiliares
**Archivo:** `tests/test_funciones_auxiliares.php`
**URL:** `http://localhost/SISTEMA%20%20RRHH/tests/test_funciones_auxiliares.php`
**Prueba:**
- `validarCedula()`
- `normalizarCedula()`
- `formatearCedula()`
- `formatearFechaBD()`
- `sanitize()`

### 5. Pruebas de Seguridad
**Archivo:** `tests/test_seguridad.php`
**URL:** `http://localhost/SISTEMA%20%20RRHH/tests/test_seguridad.php`
**Verifica:**
- Permisos de administrador
- Validación de método HTTP
- Validación de JSON
- Sanitización de datos
- Protección SQL injection

## Checklist de Verificación Pre-Importación

Antes de realizar una importación, verificar:

- [ ] Microservicio Python está corriendo
- [ ] Base de datos tiene estructura correcta (ejecutar script de verificación)
- [ ] Tabla `funcionarios` está limpia (si es necesario)
- [ ] Usuario tiene permisos de administrador
- [ ] Archivo Excel tiene formato correcto
- [ ] Archivo Excel tiene columna CEDULA
- [ ] Tamaño del archivo < 50MB

## Recomendaciones

### Antes de Agregar Nuevas Funcionalidades
1. Ejecutar todos los scripts de verificación
2. Probar con archivo Excel de prueba
3. Verificar que todas las validaciones funcionen
4. Revisar documentación de mapeo de columnas

### Antes de Modificar el Módulo
1. Hacer backup de la base de datos
2. Documentar cambios propuestos
3. Probar en ambiente de desarrollo primero
4. Actualizar esta documentación

### Mantenimiento
1. Revisar logs de errores regularmente
2. Verificar que el microservicio esté actualizado
3. Mantener scripts de verificación actualizados
4. Documentar nuevos casos de prueba

## Archivos Relacionados

- `services/excel/importar.php` - Interfaz de usuario
- `services/excel/procesar_rrhh.php` - Lógica de procesamiento
- `includes/functions.php` - Funciones auxiliares
- `classes/Database.php` - Conexión a BD
- `docs/MAPEO_COLUMNAS_EXCEL.md` - Documentación de mapeo
- `database/migrations/` - Scripts de migración

## Contacto y Soporte

Para problemas o preguntas sobre el módulo de importación:
1. Revisar esta documentación
2. Ejecutar scripts de verificación
3. Revisar logs del sistema
4. Consultar código fuente comentado

