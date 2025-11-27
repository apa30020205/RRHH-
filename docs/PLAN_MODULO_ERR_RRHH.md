# Plan de Implementación - Módulo Err-RRHH

**Fecha de creación:** 2025-11-26  
**Estado:** En desarrollo  
**Versión del plan:** 1.0

---

## Objetivo

Crear un nuevo módulo "Err-RRHH" que detecte y reporte funcionarios que tienen **nombre o apellido vacíos** en la base de datos (tabla `funcionarios`), comparando con el archivo Excel "personal RRHH".

### Funcionalidad Principal

- Comparar el archivo Excel "personal RRHH" con la tabla `funcionarios`
- Detectar cuando una cédula existe en `funcionarios` pero tiene `nombre` o `apellido` vacíos/NULL
- Guardar estos registros en la tabla `errores_importacion_funcionarios`
- Proporcionar una interfaz para visualizar, buscar, ordenar y marcar errores como resueltos

---

## 1. Base de Datos

### 1.1 Tabla: `errores_importacion_funcionarios`

**Archivo SQL:** `database/migrations/20251126_crear_tabla_errores_importacion_funcionarios.sql`

#### Estructura de la Tabla

**Campos de Control (igual estructura que `errores_importacion_biometrico`):**
- `id_error` int NOT NULL AUTO_INCREMENT - ID único del error (PRIMARY KEY)
- `fila_excel` int DEFAULT NULL - Número de fila en el Excel donde se encontró el error
- `fecha_importacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP - Fecha y hora en que se detectó el error
- `resuelto` tinyint(1) NOT NULL DEFAULT 0 - Indica si el error fue resuelto (1) o pendiente (0)
- `fecha_resolucion` timestamp NULL DEFAULT NULL - Fecha en que se resolvió el error
- `observaciones` text DEFAULT NULL - Notas sobre la resolución del error

**Campos de Datos:**
- `cedula` varchar(20) NOT NULL - Cédula del funcionario (del Excel columna D "CEDULA")
- `nombre_y_apellido` varchar(100) DEFAULT NULL - Nombre y Apellido combinado (del Excel columna E "NOMBRE Y APELLIDO")
- `fecha_nacimiento` date DEFAULT NULL - De tabla funcionarios
- `edad` tinyint DEFAULT NULL - De tabla funcionarios
- `sangre` varchar(5) DEFAULT NULL - De tabla funcionarios
- `no_posicion` int DEFAULT NULL - De tabla funcionarios
- `posicion_funcional` varchar(100) DEFAULT NULL - De tabla funcionarios
- `fecha_inicio` date DEFAULT NULL - De tabla funcionarios
- `sede_provincia` varchar(20) DEFAULT NULL - De tabla funcionarios
- `Direccion` varchar(100) DEFAULT NULL - De tabla funcionarios

**Índices:**
- PRIMARY KEY: `id_error`
- KEY `idx_cedula` (`cedula`)
- KEY `idx_resuelto` (`resuelto`)
- KEY `idx_fecha_importacion` (`fecha_importacion`)

#### Script SQL Final

```sql
CREATE TABLE IF NOT EXISTS `errores_importacion_funcionarios` (
  `id_error` int NOT NULL AUTO_INCREMENT COMMENT 'ID único del error',
  `fila_excel` int DEFAULT NULL COMMENT 'Número de fila en el Excel donde se encontró el error',
  `cedula` varchar(20) NOT NULL COMMENT 'Cédula del funcionario (del Excel columna D)',
  `nombre_y_apellido` varchar(100) DEFAULT NULL COMMENT 'Nombre y Apellido combinado (del Excel columna E)',
  `fecha_nacimiento` date DEFAULT NULL COMMENT 'Fecha de nacimiento (de tabla funcionarios)',
  `edad` tinyint DEFAULT NULL COMMENT 'Edad (de tabla funcionarios)',
  `sangre` varchar(5) DEFAULT NULL COMMENT 'Tipo de sangre (de tabla funcionarios)',
  `no_posicion` int DEFAULT NULL COMMENT 'Número de posición (de tabla funcionarios)',
  `posicion_funcional` varchar(100) DEFAULT NULL COMMENT 'Nombre de la posición funcional (de tabla funcionarios)',
  `fecha_inicio` date DEFAULT NULL COMMENT 'Fecha de inicio laboral (de tabla funcionarios)',
  `sede_provincia` varchar(20) DEFAULT NULL COMMENT 'Sede o provincia (de tabla funcionarios)',
  `Direccion` varchar(100) DEFAULT NULL COMMENT 'Dirección (de tabla funcionarios)',
  `fecha_importacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha y hora en que se detectó el error',
  `resuelto` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Indica si el error fue resuelto manualmente (1) o pendiente (0)',
  `fecha_resolucion` timestamp NULL DEFAULT NULL COMMENT 'Fecha en que se resolvió el error',
  `observaciones` text DEFAULT NULL COMMENT 'Notas sobre la resolución del error',
  PRIMARY KEY (`id_error`),
  KEY `idx_cedula` (`cedula`),
  KEY `idx_resuelto` (`resuelto`),
  KEY `idx_fecha_importacion` (`fecha_importacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Errores de importación RRHH - Funcionarios con nombre o apellido vacíos';
```

#### Notas Importantes

1. **NO incluir campos `nombre` y `apellido` separados** - Solo `nombre_y_apellido` combinado
2. **Datos del Excel:**
   - Columna D: "CEDULA" → campo `cedula`
   - Columna E: "NOMBRE Y APELLIDO" → campo `nombre_y_apellido`
3. **Datos de la tabla funcionarios:**
   - Todos los demás campos se copian de la tabla `funcionarios` cuando existe la cédula
4. **Condición para guardar error:**
   - La cédula del Excel debe existir en `funcionarios`
   - Y (`nombre` está vacío/NULL OR `apellido` está vacío/NULL en `funcionarios`)

#### Estado de Implementación

- [ ] Script SQL creado
- [ ] Tabla creada en base de datos
- [ ] Verificación de estructura completada

---

## 2. Header - Botón Err-RRHH

### 2.1 Archivo: `includes/header.php`

**Ubicación:** Línea ~24 (al lado del botón "Err-Biométrico")

**Cambio requerido:**
```php
<li><a href="<?php echo BASE_URL; ?>/pages/err_rrhh/index.php">Err-RRHH</a></li>
```

**Ubicación exacta:**
- Después de: `<li><a href="<?php echo BASE_URL; ?>/pages/err_biometrico/index.php">Err-Biométrico</a></li>`
- Antes de: `<li><a href="<?php echo BASE_URL; ?>/roles_rrhh/pages/usuarios/listar.php">Usuarios</a></li>`

**Restricción:** Solo visible para administradores (ya está dentro del bloque `if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador')`)

**Página destino:**
- URL: `/pages/err_rrhh/index.php`
- Título visible en la página: "Errores de Importación RRHH"
- Tabla origen: `errores_importacion_funcionarios`
- Funcionamiento: Similar a `pages/err_biometrico/index.php` pero usando la tabla `errores_importacion_funcionarios`

#### Estado de Implementación

- [ ] Botón agregado en header.php
- [ ] Verificado que solo aparece para administradores
- [ ] Enlace funciona correctamente

---

## 3. Página de Listado de Errores

### 3.1 Archivo: `pages/err_rrhh/index.php`

**Base:** Copiar y adaptar desde `pages/err_biometrico/index.php`

#### Funcionalidades Requeridas

1. **Autenticación y Permisos:**
   - Requerir autenticación (middleware)
   - Solo administradores pueden acceder

2. **Búsqueda:**
   - Buscar por: cédula, nombre_y_apellido, fila_excel, fecha_importacion
   - Campo de búsqueda con botón "Buscar"

3. **Filtros:**
   - Checkbox "Mostrar resueltos" (por defecto solo pendientes)
   - Botón "Limpiar" para resetear filtros

4. **Ordenamiento:**
   - Ordenar por: id_error, cedula, nombre_y_apellido, fila_excel, fecha_importacion, resuelto
   - Iconos de ordenamiento (asc/desc)
   - Mantener búsqueda y filtros al ordenar

5. **Estadísticas:**
   - Total de errores
   - Pendientes
   - Resueltos

6. **Tabla de Errores:**
   - Columnas: ID, Cédula, Nombre y Apellido, Fecha Nacimiento, Edad, Sangre, No. Posición, Posición Funcional, Fecha Inicio, Sede/Provincia, Dirección, Fila Excel, Fecha Importación, Estado, Acciones
   - Resaltar filas resueltas (fondo gris)
   - Botones: "Marcar Resuelto" / "Marcar Pendiente"

7. **Título de la página:**
   - `$pageTitle = 'Err-RRHH - Errores de Importación RRHH - Sistema RRHH';`
   - H2 visible en la página: "Errores de Importación RRHH"
   - La página muestra el listado de errores de la tabla `errores_importacion_funcionarios`

#### Cambios Necesarios desde err_biometrico/index.php

1. Cambiar nombre de tabla: `errores_importacion_biometrico` → `errores_importacion_funcionarios`
2. Cambiar campos de búsqueda:
   - `id_excel` → `cedula`
   - `nombre_excel` → `nombre_y_apellido`
   - Eliminar `apellido_excel`
3. Agregar campos adicionales en la tabla: fecha_nacimiento, edad, sangre, no_posicion, posicion_funcional, fecha_inicio, sede_provincia, Direccion
4. Actualizar campos permitidos para ordenamiento
5. Actualizar títulos y textos

#### Estado de Implementación

- [ ] Archivo `pages/err_rrhh/index.php` creado
- [ ] Autenticación y permisos implementados
- [ ] Búsqueda funcionando
- [ ] Filtros funcionando
- [ ] Ordenamiento funcionando
- [ ] Estadísticas mostradas
- [ ] Tabla de errores completa
- [ ] Estilos aplicados

---

## 4. Endpoint para Marcar Errores

### 4.1 Archivo: `pages/err_rrhh/marcar_resuelto.php`

**Base:** Copiar y adaptar desde `pages/err_biometrico/marcar_resuelto.php`

#### Funcionalidad

- Recibir petición POST con JSON: `{id_error: int, resuelto: 0|1}`
- Validar que el usuario sea administrador
- Actualizar campo `resuelto` y `fecha_resolucion` en la tabla `errores_importacion_funcionarios`
- Retornar JSON con resultado

#### Cambios Necesarios

1. Cambiar nombre de tabla: `errores_importacion_biometrico` → `errores_importacion_funcionarios`

#### Estado de Implementación

- [ ] Archivo `pages/err_rrhh/marcar_resuelto.php` creado
- [ ] Validación de permisos implementada
- [ ] Actualización de estado funcionando
- [ ] Respuesta JSON correcta

---

## 5. Script de Procesamiento de Excel

### 5.1 Archivo: `services/excel/procesar_err_rrhh.php`

**Nuevo archivo** - Procesar archivo Excel "personal RRHH" para detectar errores

#### Funcionalidad

1. **Leer archivo Excel "personal RRHH":**
   - Columna D (índice 3): "CEDULA"
   - Columna E (índice 4): "NOMBRE Y APELLIDO"
   - Ignorar fila 1 (encabezados)
   - Procesar desde fila 2 en adelante

2. **Lógica de Detección (Cálculo para encontrar errores):**
   
   **Comparación de cédula entre tabla "funcionarios" y archivo Excel "personal RRHH":**
   
   Para cada fila del Excel (desde fila 2):
   - Obtener `cedula` de columna D (Excel, título "CEDULA")
   - Obtener `nombre_y_apellido` de columna E (Excel, título "NOMBRE Y APELLIDO")
   - Normalizar cédula del Excel (quitar guiones) para comparación
   - Buscar en tabla `funcionarios` donde cédula normalizada coincida
   
   **Condición para guardar error:**
   - Si la cédula del Excel existe en `funcionarios` Y
   - (`nombre` está vacío/NULL OR `apellido` está vacío/NULL OR ambos están vacíos) en la tabla `funcionarios`
   - Entonces: Este registro es un error y se graba en `errores_importacion_funcionarios` para desplegar en la página del botón Err-RRHH
   
   **Campos a guardar:**
   - `cedula` (del Excel, formato original)
   - `nombre_y_apellido` (del Excel, columna E)
   - Todos los demás campos de `funcionarios`: fecha_nacimiento, edad, sangre, no_posicion, posicion_funcional, fecha_inicio, sede_provincia, Direccion
   - `fila_excel` (número de fila en Excel, empezando desde 2)
   - `fecha_importacion` (automático, CURRENT_TIMESTAMP)
   - `resuelto` = 0 (pendiente)

3. **Normalización:**
   - Normalizar cédula (quitar guiones) para comparación
   - Mantener formato original en la base de datos

4. **Respuesta:**
   - Retornar JSON con:
     - `success`: true/false
     - `total_procesados`: número de filas procesadas
     - `errores_encontrados`: número de errores guardados
     - `mensaje`: mensaje descriptivo

#### Integración con Microservicio

- Usar el mismo microservicio Python que `procesar_biometrico.php`
- Endpoint: `http://localhost:5000/api/read-excel`
- Parámetros: `file` (archivo), `header_row` = '0' (encabezados en fila 0)
- Funciona igual que el contenedor biométrico (mismo flujo de drag and drop)

#### Estado de Implementación

- [ ] Archivo `services/excel/procesar_err_rrhh.php` creado
- [ ] Lectura de Excel implementada
- [ ] Lógica de detección implementada
- [ ] Guardado en base de datos funcionando
- [ ] Manejo de errores implementado
- [ ] Respuesta JSON correcta

---

## 6. Interfaz de Importación

### 6.1 Archivo: `services/excel/importar.php`

**Modificar:** Agregar nuevo contenedor drag-and-drop para "Importar Archivo Personal RRHH (Detección de Errores)"

#### Ubicación

- Agregar al final de la página, después de los contenedores existentes

#### Funcionalidad

1. **Contenedor Drag & Drop:**
   - Título: "Importar Archivo Personal RRHH (Detección de Errores)"
   - Descripción: Explicar que detecta funcionarios con nombre/apellido vacíos
   - Zona de arrastrar y soltar archivo
   - Botón para seleccionar archivo

2. **JavaScript:**
   - Función `enviarArchivoErrRRHH(archivo)`
   - Función `procesarArchivoErrRRHH(data)`
   - Event listeners para drag & drop
   - Mostrar resultado: total procesados, errores encontrados

3. **Procesamiento:**
   - Enviar archivo a microservicio
   - Llamar a `procesar_err_rrhh.php` con los datos
   - Mostrar mensaje de éxito/error
   - Opción para ir a la página de listado de errores

#### Estado de Implementación

- [ ] Contenedor drag-and-drop agregado
- [ ] JavaScript implementado
- [ ] Integración con procesar_err_rrhh.php funcionando
- [ ] Mensajes de resultado mostrados
- [ ] Enlace a página de errores funcionando

---

## 7. Verificaciones y Pruebas

### 7.1 Verificación de Base de Datos

- [ ] Tabla `errores_importacion_funcionarios` existe
- [ ] Todos los campos están correctos
- [ ] Índices creados correctamente
- [ ] Comentarios en campos son descriptivos

### 7.2 Verificación de Funcionalidad

- [ ] Botón "Err-RRHH" aparece en header (solo para admin)
- [ ] Página de listado carga correctamente
- [ ] Búsqueda funciona
- [ ] Filtros funcionan
- [ ] Ordenamiento funciona
- [ ] Estadísticas se muestran correctamente
- [ ] Botones "Marcar Resuelto/Pendiente" funcionan
- [ ] Importación de Excel detecta errores correctamente
- [ ] Errores se guardan en la base de datos
- [ ] Listado muestra los errores guardados

### 7.3 Pruebas de Casos Especiales

- [ ] Funcionario con nombre vacío y apellido lleno → se detecta
- [ ] Funcionario con apellido vacío y nombre lleno → se detecta
- [ ] Funcionario con ambos vacíos → se detecta
- [ ] Funcionario con ambos llenos → NO se detecta
- [ ] Cédula que no existe en funcionarios → NO se guarda
- [ ] Excel con formato incorrecto → manejo de error
- [ ] Excel sin datos → mensaje apropiado

---

## 8. Checklist Final

### Archivos a Crear

- [ ] `database/migrations/20251126_crear_tabla_errores_importacion_funcionarios.sql`
- [ ] `pages/err_rrhh/index.php`
- [ ] `pages/err_rrhh/marcar_resuelto.php`
- [ ] `services/excel/procesar_err_rrhh.php`

### Archivos a Modificar

- [ ] `includes/header.php` (agregar botón Err-RRHH)
- [ ] `services/excel/importar.php` (agregar contenedor drag-and-drop)

### Documentación

- [ ] Este documento actualizado con estado final
- [ ] Comentarios en código explicativos
- [ ] README actualizado (si es necesario)

---

## 9. Notas de Desarrollo

### Consideraciones Importantes

1. **Normalización de Cédula:**
   - Al comparar cédulas, normalizar (quitar guiones) para la búsqueda
   - Guardar en la base de datos con el formato original del Excel

2. **Campos Vacíos:**
   - Considerar `NULL` y cadena vacía `''` como vacíos
   - Usar: `(nombre IS NULL OR nombre = '') OR (apellido IS NULL OR apellido = '')`

3. **Consistencia con Err-Biométrico:**
   - Mantener la misma estructura de campos de control
   - Usar los mismos nombres de campos de control (`fecha_importacion`, no `fecha_error`)
   - Mantener la misma interfaz y funcionalidad

4. **Rendimiento:**
   - Los índices en `cedula` y `resuelto` son importantes para búsquedas rápidas
   - Considerar paginación si hay muchos errores

---

## 10. Estado General del Proyecto

**Última actualización:** 2025-11-26

### Progreso

- ✅ Plan documentado
- ✅ Estructura de base de datos definida
- ⏳ Implementación en progreso

### Próximos Pasos

1. Crear tabla en base de datos
2. Agregar botón en header
3. Crear página de listado
4. Crear endpoint para marcar resuelto
5. Crear script de procesamiento
6. Agregar interfaz de importación
7. Probar y verificar

---

**Fin del Documento**

