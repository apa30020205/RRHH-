# GUÍA PASO A PASO - DESARROLLO DEL SISTEMA RRHH

## ✅ PASO 1: Validación de Cédulas Panameñas - COMPLETADO

### Lo que se ha hecho:

1. **Función `validarCedula()` mejorada:**
   - ✅ Acepta cédulas numéricas (8-13 dígitos)
   - ✅ Acepta cédulas alfanuméricas (panameños nacidos en extranjero)
   - ✅ Permite guiones en cualquier posición
   - ✅ Validación robusta con múltiples patrones

2. **Nuevas funciones agregadas:**
   - ✅ `normalizarCedula()` - Normaliza para almacenamiento (sin guiones, mayúsculas)
   - ✅ `formatearCedula()` - Formatea para mostrar (con guiones)

3. **Páginas actualizadas:**
   - ✅ `crear.php` - Normaliza cédula antes de guardar, verifica duplicados
   - ✅ `listar.php` - Formatea cédulas al mostrar
   - ✅ `ver.php` - Formatea cédula al mostrar, normaliza en búsqueda
   - ✅ `editar.php` - Formatea cédula al mostrar, normaliza en búsqueda

4. **Documentación creada:**
   - ✅ `DOCUMENTACION_COMPLETA.md` - Documentación técnica completa
   - ✅ `GUIA_PASO_A_PASO.md` - Esta guía

---

## 📋 PASO 2: Probar el Sistema

### 2.1 Importar Base de Datos

1. Abre phpMyAdmin: `http://localhost/phpmyadmin`
2. Crea base de datos: `rrhh`
3. Selecciona la base de datos
4. Ve a "Importar"
5. Selecciona: `database/rrhh_funcionarios.sql`
6. Clic en "Continuar"

### 2.2 Probar Validación de Cédulas

**Cédulas válidas para probar:**

**Numéricas:**
- `8-1234-5678`
- `812345678`
- `8-12-34-5678`
- `1234567890123` (13 dígitos)

**Alfanuméricas:**
- `PE-123456-7`
- `8A-1234-5678`
- `ABC1234567`

**Cédulas inválidas (deben rechazarse):**
- `1234567` (menos de 8 caracteres)
- `12345678901234567890` (más de 20 caracteres)
- `ABC` (solo letras, sin números)
- `12` (muy corta)

### 2.3 Probar Funcionalidad Completa

1. **Crear funcionario:**
   - Ve a: `http://localhost/SISTEMA%20RRHH/pages/funcionarios/crear.php`
   - Llena el formulario con una cédula válida
   - Verifica que se guarde correctamente

2. **Listar funcionarios:**
   - Ve a: `http://localhost/SISTEMA%20RRHH/pages/funcionarios/listar.php`
   - Verifica que las cédulas se muestren formateadas (con guiones)

3. **Ver funcionario:**
   - Clic en "Ver" de cualquier funcionario
   - Verifica que la cédula se muestre formateada

4. **Editar funcionario:**
   - Clic en "Editar"
   - Modifica algún campo
   - Guarda y verifica que funcione

---

## 🎯 PASO 3: Próximos Pasos de Desarrollo

### 3.1 Migrar Microservicio de Excel

**Ubicación:** `services/excel/`

**Archivos a crear:**
- `importar.php` - Endpoint principal
- `procesar.php` - Lógica de procesamiento
- `validar.php` - Validaciones de archivo Excel

**Funcionalidades:**
- Subir archivo Excel
- Validar formato
- Procesar datos
- Importar a base de datos
- Manejar errores

### 3.2 Migrar Formularios de Permisos

**Ubicación:** `forms/permisos/`

**Archivos a crear:**
- `vacaciones.php`
- `medico.php`
- `personal.php`
- `maternidad.php`
- `paternidad.php`
- `compensatorio.php`
- `procesar.php` - Procesador común

**Funcionalidades:**
- Formularios de solicitud
- Validación de datos
- Guardar en base de datos (cuando se cree la tabla)

### 3.3 Crear Tablas de Base de Datos

**Tablas a crear:**
1. `permisos` - Solicitudes de permisos
2. `asistencia_diaria` - Registro de asistencia

**Relaciones:**
- Ambas usan `cedula` como Foreign Key
- Relación con tabla `funcionarios`

---

## 📝 NOTAS IMPORTANTES

### Sobre las Cédulas

- **Almacenamiento:** Todas las cédulas se guardan normalizadas (sin guiones, mayúsculas)
- **Visualización:** Se muestran formateadas (con guiones cuando es numérica)
- **Búsqueda:** Siempre normalizar antes de buscar en BD

### Sobre la Base de Datos

- La cédula se almacena sin guiones para consistencia
- Se puede buscar con o sin guiones (se normaliza automáticamente)
- La validación permite ambos formatos al ingresar

### Sobre el Desarrollo

- Siempre usar `normalizarCedula()` antes de guardar
- Siempre usar `formatearCedula()` al mostrar
- Validar con `validarCedula()` antes de procesar

---

## 🔍 VERIFICACIÓN

### Checklist de Funcionalidad

- [ ] Base de datos importada correctamente
- [ ] Validación de cédulas numéricas funciona
- [ ] Validación de cédulas alfanuméricas funciona
- [ ] Crear funcionario funciona
- [ ] Listar funcionarios muestra cédulas formateadas
- [ ] Ver funcionario muestra cédula formateada
- [ ] Editar funcionario funciona
- [ ] Búsqueda funciona con cédulas con/sin guiones

---

## 📚 RECURSOS

- **Documentación completa:** `DOCUMENTACION_COMPLETA.md`
- **Código fuente:** Revisar `includes/functions.php` para funciones de cédulas
- **Ejemplos:** Ver `pages/funcionarios/` para implementación

---

**Última actualización:** Noviembre 2025  
**Estado:** Paso 1 completado ✅

