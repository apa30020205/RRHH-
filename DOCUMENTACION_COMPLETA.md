# DOCUMENTACIÓN COMPLETA - SISTEMA DE RECURSOS HUMANOS (RRHH)

## ÍNDICE
1. [Introducción](#introducción)
2. [Estructura del Proyecto](#estructura-del-proyecto)
3. [Configuración](#configuración)
4. [Base de Datos](#base-de-datos)
5. [Funciones Principales](#funciones-principales)
6. [Seguridad](#seguridad)
7. [Flujos de Trabajo](#flujos-de-trabajo)
8. [Guía de Desarrollo](#guía-de-desarrollo)
9. [Validación de Cédulas Panameñas](#validación-de-cédulas-panameñas)

---

## INTRODUCCIÓN

Este documento explica en detalle la arquitectura, componentes y funcionamiento del Sistema de Recursos Humanos desarrollado en PHP.

### Objetivo del Sistema
Gestionar funcionarios, permisos y asistencia de manera centralizada y eficiente para instituciones gubernamentales panameñas.

### Tecnologías Utilizadas
- **Backend:** PHP 7.4+
- **Base de Datos:** MySQL 8.0
- **Servidor:** Apache (Laragon)
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)

---

## ESTRUCTURA DEL PROYECTO

### Árbol de Directorios
```
SISTEMA RRHH/
├── database/              # Scripts SQL y migraciones
│   ├── rrhh_funcionarios.sql
│   └── migrations/
├── config/                # Configuración del sistema
│   ├── database.php
│   └── constants.php
├── includes/              # Archivos reutilizables
│   ├── header.php
│   ├── footer.php
│   └── functions.php
├── pages/                 # Páginas principales
│   ├── index.php
│   └── funcionarios/
├── services/              # Microservicios
│   └── excel/
├── forms/                 # Formularios
│   └── permisos/
├── classes/               # Clases PHP
│   └── Database.php
├── api/                   # Endpoints API
├── assets/                # Recursos estáticos
│   ├── css/
│   ├── js/
│   └── images/
├── uploads/               # Archivos subidos
│   ├── documentos/
│   └── excel/
└── logs/                  # Logs del sistema
```

---

## CONFIGURACIÓN

### 1. `config/database.php`

**Propósito:** Define las constantes de conexión a la base de datos y proporciona una función para obtener la conexión.

**Constantes definidas:**
```php
DB_HOST      // localhost
DB_USER      // root
DB_PASS      // '' (vacía por defecto en Laragon)
DB_NAME      // rrhh
DB_CHARSET   // utf8mb4
```

**Función `getDBConnection()`:**
- Implementa patrón Singleton
- Crea conexión PDO con configuración segura
- Reutiliza la misma conexión en toda la aplicación

**Configuración PDO:**
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` - Lanza excepciones en errores
- `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC` - Retorna arrays asociativos
- `PDO::ATTR_EMULATE_PREPARES => false` - Usa prepared statements nativos

### 2. `config/constants.php`

**Propósito:** Define constantes globales del sistema.

**Categorías:**
1. **Rutas del Sistema:**
   - `BASE_PATH` - Ruta física del proyecto
   - `BASE_URL` - URL base del proyecto
   - `UPLOADS_PATH`, `UPLOADS_DOCUMENTOS`, `UPLOADS_EXCEL`
   - `LOGS_PATH`

2. **Configuración de Archivos:**
   - `MAX_FILE_SIZE` - 5MB
   - `ALLOWED_EXCEL_TYPES` - Tipos MIME permitidos

3. **Configuración de Permisos:**
   - `TIPOS_PERMISOS` - Array con los 6 tipos
   - `ESTADOS_PERMISO` - Estados posibles

---

## BASE DE DATOS

### Tabla: `funcionarios`

**Campos principales:**
- `cedula` (VARCHAR(20), PK) - Cédula del funcionario
- `nombre` (VARCHAR(40)) - Nombre y segundo nombre
- `apellido` (VARCHAR(50)) - Apellido y segundo apellido
- `fecha_nacimiento` (DATE)
- `edad` (TINYINT)
- `sangre` (VARCHAR(5)) - Tipo de sangre
- `no_posicion` (INT, UNIQUE) - Número de posición
- `posicion_funcional` (VARCHAR(45))
- `fecha_inicio` (DATE)
- `sede_provincia` (VARCHAR(20))
- `Direccion` (VARCHAR(100))

**Índices:**
- PRIMARY KEY: `cedula`
- UNIQUE: `cedula`, `no_posicion`

---

## FUNCIONES PRINCIPALES

### `includes/functions.php`

#### 1. `sanitize($data)`
Limpia entrada del usuario para prevenir XSS.
- Elimina HTML (`strip_tags`)
- Escapa caracteres especiales (`htmlspecialchars`)
- Elimina espacios al inicio/final (`trim`)

#### 2. `validarCedula($cedula)` ⭐ MEJORADA
Valida cédulas panameñas (numéricas y alfanuméricas).

**Características:**
- Acepta guiones en cualquier posición
- Soporta cédulas numéricas (8-13 dígitos)
- Soporta cédulas alfanuméricas (panameños nacidos en extranjero)
- Normaliza formato automáticamente

**Ejemplos válidos:**
- `8-1234-5678`
- `812345678`
- `8-12-34-5678`
- `PE-123456-7` (alfanumérica)
- `8A-1234-5678` (alfanumérica)

#### 3. `normalizarCedula($cedula)`
Normaliza cédula para almacenamiento consistente.
- Elimina guiones y espacios
- Convierte a mayúsculas
- Útil para guardar en BD

#### 4. `formatearCedula($cedula, $formato)`
Formatea cédula para mostrar.
- Formato con guiones: `8-1234-5678`
- Formato sin guiones: `812345678`

#### 5. `formatearFecha($fecha, $formato)`
Convierte fecha a formato legible.
- Por defecto: `d/m/Y` (18/11/2025)

#### 6. `calcularEdad($fechaNacimiento)`
Calcula edad desde fecha de nacimiento.
- Usa `DateTime::diff()` para precisión

#### 7. `mostrarMensaje($mensaje, $tipo)` y `obtenerMensaje()`
Sistema de mensajes en sesión.
- Tipos: success, error, warning, info
- Útil después de redirecciones

---

## VALIDACIÓN DE CÉDULAS PANAMEÑAS

### Función `validarCedula()`

**Lógica de validación:**

1. **Limpieza inicial:**
   - Elimina espacios
   - Elimina guiones (para análisis)

2. **Validación de longitud:**
   - Mínimo: 8 caracteres
   - Máximo: 20 caracteres

3. **Validación de caracteres:**
   - Solo letras y números (A-Z, 0-9)

4. **Validación específica:**

   **a) Cédulas numéricas:**
   - Solo dígitos (0-9)
   - Longitud: 8-13 dígitos
   - Ejemplos: `812345678`, `8-1234-5678`

   **b) Cédulas alfanuméricas:**
   - Contiene letras Y números
   - Mínimo 3 números
   - Mínimo 8 caracteres totales
   - Ejemplos: `PE-123456-7`, `8A-1234-5678`

### Función `normalizarCedula()`

**Uso:** Almacenar cédulas de forma consistente en BD.

```php
$cedula = "8-1234-5678";
$normalizada = normalizarCedula($cedula);
// Resultado: "812345678"
```

### Función `formatearCedula()`

**Uso:** Mostrar cédulas con formato estándar.

```php
$cedula = "812345678";
$formateada = formatearCedula($cedula);
// Resultado: "8-1234-5678"
```

---

## SEGURIDAD

### Medidas Implementadas

1. **Prepared Statements:**
   - Previene SQL injection
   - Usado en todas las consultas con parámetros

2. **Sanitización:**
   - Función `sanitize()` en todos los inputs
   - `htmlspecialchars()` en todas las salidas

3. **Validación:**
   - Validación en cliente (JavaScript)
   - Validación en servidor (PHP)
   - Validación de tipos de datos

4. **Protección de Archivos:**
   - `.htaccess` protege archivos sensibles
   - Carpetas `config/` y `logs/` bloqueadas

5. **Manejo de Errores:**
   - Errores ocultos al usuario
   - Logs guardados en `logs/`
   - Mensajes genéricos

---

## FLUJOS DE TRABAJO

### Flujo: Crear Funcionario

1. Usuario accede a `pages/funcionarios/crear.php`
2. Página carga constantes, funciones, Database, header
3. Usuario llena formulario
4. JavaScript valida en cliente
5. Usuario envía formulario (POST)
6. PHP valida en servidor:
   - Valida cédula con `validarCedula()`
   - Normaliza cédula con `normalizarCedula()`
   - Sanitiza datos
   - Calcula edad
7. PHP ejecuta INSERT con prepared statement
8. Guarda mensaje de éxito en sesión
9. Redirige a `listar.php`
10. `listar.php` muestra mensaje
11. JavaScript auto-oculta mensaje después de 5 segundos

### Flujo: Listar Funcionarios

1. Usuario accede a `pages/funcionarios/listar.php`
2. PHP obtiene conexión (Singleton)
3. Ejecuta query: `SELECT * FROM funcionarios`
4. Muestra resultados en tabla
5. Formatea cédulas con `formatearCedula()`
6. Cada fila tiene enlaces a ver/editar

---

## CLASES PHP

### `classes/Database.php` - Patrón Singleton

**¿Qué es Singleton?**
Patrón de diseño que garantiza una sola instancia de una clase.

**Por qué Singleton:**
- Una sola conexión a BD por petición
- Mejor rendimiento
- Control centralizado

**Uso:**
```php
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT * FROM funcionarios WHERE cedula = ?");
```

**Protecciones:**
- Constructor privado → No se puede hacer `new Database()`
- `__clone()` privado → No se puede clonar
- `__wakeup()` → No se puede deserializar

---

## ARCHIVOS DE CONFIGURACIÓN

### `.htaccess`

**Funcionalidades:**
1. Protección de archivos sensibles (.sql, .log, .ini, .bak)
2. Configuración PHP (ocultar errores, guardar en logs)
3. UTF-8 forzado
4. Protección de carpetas (config/, logs/)
5. Página de inicio por defecto

### `.gitignore`

**Archivos ignorados:**
- Logs (*.log)
- Uploads (contenido)
- Configuración local
- Archivos temporales
- Carpetas de IDE

### `SISTEMA RRHH.code-workspace`

**Configuración:**
- Define carpeta raíz
- Formato automático al guardar
- Encoding UTF-8
- Excluye uploads/ y logs/ del watcher

---

## GUÍA DE DESARROLLO

### Agregar Nueva Funcionalidad

1. **Crear página:**
   - En `pages/` o subcarpeta apropiada
   - Incluir header y footer
   - Usar constantes de `config/constants.php`

2. **Agregar función si es necesario:**
   - En `includes/functions.php`
   - Documentar con PHPDoc

3. **Agregar estilos:**
   - En `assets/css/style.css`
   - Usar clases existentes cuando sea posible

### Mejores Prácticas

1. ✅ Siempre usar prepared statements
2. ✅ Sanitizar todos los inputs
3. ✅ Validar en cliente y servidor
4. ✅ Manejar errores con try-catch
5. ✅ Usar mensajes de sesión
6. ✅ Documentar código
7. ✅ Normalizar cédulas antes de guardar
8. ✅ Formatear cédulas al mostrar

---

## PRÓXIMOS PASOS

1. ✅ Estructura base creada
2. ✅ Validación de cédulas mejorada
3. ⏳ Migrar microservicio de Excel
4. ⏳ Migrar y revisar 6 formularios de permisos
5. ⏳ Crear tablas de permisos y asistencia
6. ⏳ Implementar estadísticas en dashboard

---

## CONCLUSIÓN

Este sistema está diseñado para ser:
- **Escalable:** Fácil agregar nuevos módulos
- **Mantenible:** Código organizado y documentado
- **Seguro:** Múltiples capas de seguridad
- **Eficiente:** Uso de patrones de diseño
- **Profesional:** Estructura estándar de la industria

---

**Versión del Documento:** 1.0  
**Fecha:** Noviembre 2025  
**Autor:** Sistema RRHH Development Team

