# GUÍA COMPLETA DEL SISTEMA DE RECURSOS HUMANOS (RRHH)

**Versión:** 1.5  
**Fecha:** Enero 2025

---

## 📋 ÍNDICE

1. [Introducción](#1-introducción)
2. [Instalación Paso a Paso](#2-instalación-paso-a-paso)
3. [Primer Acceso al Sistema](#3-primer-acceso-al-sistema)
4. [Módulos del Sistema](#4-módulos-del-sistema)
5. [Procesos Comunes](#5-procesos-comunes)
6. [Base de Datos](#6-base-de-datos)
7. [Preguntas Frecuentes](#7-preguntas-frecuentes)

---

## 1. INTRODUCCIÓN

### ¿Qué es este sistema?

El Sistema de Recursos Humanos (RRHH) es una plataforma web para gestionar:
- **Funcionarios** y su información personal/laboral
- **Permisos y vacaciones** (6 tipos diferentes)
- **Marcaciones biométricas** (asistencia)
- **Jornadas extraordinarias** y tiempo compensatorio
- **Misiones oficiales** y reincorporaciones

### Tecnologías

- **Backend:** PHP 7.4+
- **Base de Datos:** MySQL 8.0
- **Servidor:** Apache (Laragon)
- **Frontend:** HTML5, CSS3, JavaScript

---

## 2. INSTALACIÓN PASO A PASO

### Paso 1: Requisitos Previos

Asegúrate de tener instalado:
- ✅ **Laragon** (o XAMPP/WAMP) con Apache y MySQL
- ✅ **PHP 7.4 o superior**
- ✅ **phpMyAdmin** (incluido en Laragon)

### Paso 2: Instalar el Sistema

1. **Ubicar el proyecto:**
   - El sistema debe estar en: `C:\laragon\www\SISTEMA RRHH\`
   - Si está en otra ubicación, ajusta las rutas en `config/constants.php`

2. **Crear la Base de Datos:**
   - Abre phpMyAdmin: `http://localhost/phpmyadmin`
   - Clic en "Nueva" (crear base de datos)
   - Nombre: `rrhh`
   - Intercalación: `utf8mb4_0900_ai_ci`
   - Clic en "Crear"

3. **Importar Tablas Base:**
   - Selecciona la base de datos `rrhh`
   - Ve a la pestaña "Importar"
   - Selecciona el archivo: `database/rrhh_funcionarios.sql`
   - Clic en "Continuar"

4. **Importar Migraciones (Opcional pero Recomendado):**
   
   Ejecuta estos scripts SQL en orden desde `database/migrations/`:
   
   **Módulos de Permisos/Vacaciones:**
   - `20250127_crear_tabla_permisos.sql`
   - `20250127_crear_tabla_reincorporacion.sql`
   - `20250127_crear_tabla_tiempo_compensatorio.sql`
   - `20250127_crear_tabla_solicitud_vacaciones.sql`
   - `20250127_crear_tabla_mision_oficial.sql`
   
   **Columnas Adicionales:**
   - `20250127_agregar_permisos_acumulados.sql`
   - `20250127_agregar_horas_extraordinarias_acumuladas.sql`
   - `20250127_agregar_mision_oficial_acumuladas.sql`
   - `20250127_agregar_tiempo_compensatorio_acumulado.sql`
   - `20250127_agregar_permisos_injustificados_acumulados.sql`

5. **Importar Tabla de Usuarios:**
   - `database/migrations/20251119_crear_tabla_usuarios.sql`

6. **Configurar Conexión (si es necesario):**
   - Abre: `config/database.php`
   - Por defecto funciona con Laragon:
     - Host: `localhost`
     - Usuario: `root`
     - Contraseña: (vacía)
     - Base de datos: `rrhh`

7. **Verificar Permisos de Carpetas:**
   - La carpeta `uploads/` debe tener permisos de escritura
   - La carpeta `logs/` debe tener permisos de escritura

### Paso 3: Verificar Instalación

1. Abre el navegador
2. Ve a: `http://localhost/SISTEMA%20RRHH/`
3. Deberías ver la página de login

---

## 3. PRIMER ACCESO AL SISTEMA

### Credenciales por Defecto

- **Usuario:** `admin`
- **Contraseña:** `admin123`

⚠️ **IMPORTANTE:** Cambia esta contraseña después del primer acceso.

### Iniciar Sesión

1. Abre: `http://localhost/SISTEMA%20RRHH/`
2. Introduce usuario y contraseña
3. Clic en "Iniciar Sesión"
4. Serás redirigido al Dashboard

### Cambiar Contraseña

1. Ve a: **Usuarios** (menú superior)
2. Busca tu usuario
3. Clic en "Editar"
4. Cambia la contraseña
5. Guarda los cambios

---

## 4. MÓDULOS DEL SISTEMA

### 4.1. Dashboard (Página Principal)

**Ubicación:** `pages/index.php`

**Qué muestra:**
- Resumen de funcionarios
- Enlaces rápidos a módulos principales
- Accesos directos a funciones comunes

**Cómo usar:**
- Desde aquí puedes navegar a cualquier módulo del sistema

---

### 4.2. Gestión de Funcionarios

**Ubicación:** `pages/funcionarios/`

#### 4.2.1. Listar Funcionarios

**Paso a paso:**

1. Ve a: **Funcionarios** → **Listar Funcionarios**
2. Verás una lista de todos los funcionarios registrados
3. Puedes buscar por:
   - Cédula
   - Nombre
   - Apellido
4. **Acciones disponibles:**
   - **Ver:** Ver detalles completos del funcionario
   - **Editar:** Modificar información del funcionario
   - **Eliminar:** (Solo administradores) Eliminar funcionario

#### 4.2.2. Crear Nuevo Funcionario

**Paso a paso:**

1. Ve a: **Funcionarios** → **Crear Funcionario**
2. Llena el formulario:
   - **Cédula:** (Obligatorio) Cédula panameña válida
   - **Nombre:** Nombre y segundo nombre
   - **Apellido:** Apellido y segundo apellido
   - **Fecha de Nacimiento:** Se calcula automáticamente la edad
   - **Tipo de Sangre:** (Opcional)
   - **Número de Posición:** (Obligatorio) Número único
   - **Posición Funcional:** Nombre del puesto
   - **Fecha de Inicio:** Fecha en que inició labores
   - **Sede/Provincia:** Sede de trabajo
   - **Dirección:** Dirección completa
   - **Horario de Entrada:** Ejemplo: 08:00
   - **Horario de Salida:** Ejemplo: 16:00
3. Clic en **"Guardar Funcionario"**
4. Verás mensaje de confirmación

#### 4.2.3. Editar Funcionario

**Paso a paso:**

1. Desde el listado, busca el funcionario
2. Clic en **"Editar"**
3. Modifica los campos necesarios
4. Clic en **"Guardar Cambios"**

#### 4.2.4. Ver Detalles de Funcionario

**Paso a paso:**

1. Desde el listado, busca el funcionario
2. Clic en **"Ver"**
3. Verás toda la información del funcionario:
   - Datos personales
   - Datos laborales
   - Horarios
   - Derechos acumulados (vacaciones, permisos, etc.)

#### 4.2.5. Configurar Derechos del Funcionario

**Paso a paso:**

1. Ve a: **Marcaciones Biométricas**
2. Busca un funcionario por cédula o nombre
3. En la sección **"Permisos/Vacaciones"** verás:
   - **Vacaciones (días):** Editable directamente
4. Modifica el valor
5. Clic en **"Actualizar Derechos"**
6. El valor se guarda automáticamente

---

### 4.3. Permisos/Vacaciones

**Ubicación:** `forms/permisos/`

El sistema tiene **6 módulos diferentes** de permisos/vacaciones, cada uno con su color distintivo:

#### 4.3.1. Jornada Extraordinaria (Azul #2196F3)

**Qué hace:**
- Registra horas extraordinarias trabajadas
- Acumula horas extraordinarias
- Permite editar el acumulado manualmente

**Paso a paso:**

1. Ve a: **Permisos/Vacaciones** → **Jornada Extraordinaria**
2. **Buscar Funcionario:**
   - Escribe cédula, nombre o apellido
   - Clic en **"Buscar"**
3. **Registrar Jornada:**
   - **Fecha:** Fecha en que trabajó horas extra
   - **Hora Desde:** Hora de inicio (formato 24h)
   - **Hora Hasta:** Hora de finalización
   - **Justificación:** Motivo de las horas extra
   - Clic en **"Guardar Jornada Extraordinaria"**
4. **Ver Horas Acumuladas:**
   - En la parte inferior verás: **"Horas Extraordinarias Acumuladas"**
   - Puedes hacer clic para editar manualmente
   - Formato: HH:MM (ejemplo: 33:30) o solo número (ejemplo: 5 se convierte a 05:00)
5. **Eliminar Jornada:**
   - En el listado, clic en **"Eliminar"** (icono de basura rojo)
   - Confirma la eliminación
   - Las horas se restan automáticamente del acumulado

**En Marcaciones Biométricas:**
- Las jornadas extraordinarias aparecen en el listado si no hay marcación del reloj
- Se muestran en la columna **"Horas Día"** en color azul oscuro
- Aparece la sección **"Jornadas Extraordinarias del Período"** con el total

---

#### 4.3.2. Misión Oficial (Rojo #dc3545)

**Qué hace:**
- Registra misiones oficiales del funcionario
- Acumula horas de misiones oficiales
- Permite editar el acumulado manualmente

**Paso a paso:**

1. Ve a: **Permisos/Vacaciones** → **Misión Oficial**
2. **Buscar Funcionario**
3. **Registrar Misión:**
   - **Fecha:** Fecha de la misión
   - **Hora Desde:** Hora de inicio
   - **Hora Hasta:** Hora de finalización
   - **Motivo:** Descripción detallada del motivo
   - Clic en **"Guardar Misión Oficial"**
4. **Ver Horas Acumuladas:**
   - Haz clic en **"Misiones Oficiales Acumuladas"** para editar
5. **Eliminar Misión:**
   - Clic en **"Eliminar"** en el listado

**En Marcaciones Biométricas:**
- Aparece la sección **"Misión Oficial del Período"** con el total

---

#### 4.3.3. Reincorporación (Morado #9c27b0)

**Qué hace:**
- Registra reincorporaciones de funcionarios después de ausencias
- No acumula horas, solo guarda el registro
- Muestra mensaje recordatorio para incorporar desde EX/Funcionario

**Paso a paso:**

1. Ve a: **Permisos/Vacaciones** → **Reincorporación**
2. **Buscar Funcionario**
3. **Llenar Formulario:**
   - **Motivo de Ausencia:** Selecciona una opción:
     - Licencia con sueldo
     - Licencia sin sueldo
     - Licencia especial
     - Vacaciones
     - Prestando funciones en otra Institución
   - **Puesto:** Puesto al que se reincorpora (se prellena automáticamente)
   - **Posición N°:** Número de posición
   - **Unidad Administrativa:** Unidad donde trabaja
   - **Fecha de Reincorporación:** Fecha en que se reincorpora
   - Clic en **"Guardar Reincorporación"**
4. **Mensaje Importante:**
   - Después de guardar, aparece un mensaje en negrita:
     - **"Recuerde Incorporar al Funcionario desde EX/Funcionario"**

**En Marcaciones Biométricas:**
- Aparece la sección **"Reincorporación del Período"** con todos los registros

---

#### 4.3.4. Tiempo Compensatorio (Naranja #ff9800)

**Qué hace:**
- Registra tiempo compensatorio (horas y días)
- Acumula tanto horas como días por separado
- Permite editar ambos acumulados manualmente

**Paso a paso:**

1. Ve a: **Permisos/Vacaciones** → **Tiempo Compensatorio**
2. **Buscar Funcionario**
3. **Registrar Tiempo:**
   - **Horas:** Número de horas (ejemplo: 5)
   - **Días:** Número de días (ejemplo: 2)
   - **Fecha de Uso:** Fecha en que se usa el tiempo compensatorio
   - Clic en **"Guardar Tiempo Compensatorio"**
4. **Ver Acumulados:**
   - **Horas Acumuladas:** Haz clic para editar (formato HH:MM o número)
   - **Días Acumulados:** Haz clic para editar (número entero)
5. **Eliminar Registro:**
   - Clic en **"Eliminar"** en el listado
   - Las horas y días se restan automáticamente

**En Marcaciones Biométricas:**
- Aparece la sección **"Tiempo Compensatorio del Período"** con totales de horas y días

---

#### 4.3.5. Solicitud de Permiso (Verde #4caf50)

**Qué hace:**
- Registra solicitudes de permisos justificados e injustificados
- Acumula horas de permisos (total y injustificados por separado)
- Permite editar ambos acumulados manualmente

**Paso a paso:**

1. Ve a: **Permisos/Vacaciones** → **Solicitud de Permiso**
2. **Buscar Funcionario**
3. **Registrar Permiso:**
   - **Fecha:** Fecha del permiso
   - **Hora Desde:** Hora de inicio
   - **Hora Hasta:** Hora de finalización
   - **Motivo:** Selecciona el motivo:
     - Enfermedad matrimonial
     - Enfermedad de parientes cercanos
     - Otros asuntos personales
     - Duelo
     - Nacimiento de hijos
     - Eventos académicos
     - **Permiso InJustificado** (aparece en rojo)
   - Clic en **"Guardar Permiso"**
4. **Ver Acumulados:**
   - **Permisos Acumulados:** Total de permisos (haz clic para editar)
   - **Permisos InJustificados Acumulados:** Solo permisos injustificados (haz clic para editar)
5. **Eliminar Permiso:**
   - Clic en **"Eliminar"** en el listado
   - Si era injustificado, se resta de ambos acumulados automáticamente

**En Marcaciones Biométricas:**
- Aparece la sección **"Permisos del Período"** con el total
- Los permisos injustificados aparecen en rojo en la columna "Motivo"
- Se muestra el total de permisos injustificados del período en rojo

---

#### 4.3.6. Solicitud de Vacaciones (Rosa #e91e63)

**Qué hace:**
- Registra solicitudes de vacaciones con múltiples períodos
- Cada período tiene resolución, fecha y días
- No acumula, solo guarda registros históricos

**Paso a paso:**

1. Ve a: **Permisos/Vacaciones** → **Solicitud de Vacaciones**
2. **Buscar Funcionario**
3. **Llenar Declaración:**
   - **Días Solicitados:** Total de días en la declaración
   - **Fecha Inicio:** Fecha en que inician las vacaciones
   - **Fecha Retorno:** Fecha en que retorna a labores
   - **Observaciones:** Notas adicionales
4. **Agregar Períodos de Vacaciones:**
   - Clic en **"+ Agregar vacaciones"**
   - Se agrega una fila a la tabla con:
     - **Resolución:** Número de resolución
     - **Fecha:** Fecha de la resolución
     - **Días:** Días de vacación de este período
     - **Acciones:** Botón para eliminar la fila
   - Puedes agregar múltiples filas
5. **Guardar:**
   - Clic en **"Guardar Solicitud de Vacaciones"**
   - Se guarda un registro por cada fila de la tabla
6. **Ver Vacaciones Registradas:**
   - En la parte inferior verás todas las vacaciones guardadas
   - Puedes filtrar por fechas

**En Marcaciones Biométricas:**
- Aparece la sección **"Solicitud de Vacaciones del Período"** con todos los registros

---

### 4.4. Marcaciones Biométricas

**Ubicación:** `pages/marcaciones/listar.php`

**Qué hace:**
- Muestra todas las marcaciones del reloj biométrico
- Calcula horas trabajadas, tardanzas, faltas
- Integra jornadas extraordinarias, permisos, misiones, etc.
- Permite configurar derechos del funcionario

**Paso a paso para ver marcaciones:**

1. Ve a: **Marcaciones Biométricas** (menú superior)
2. **Filtrar:**
   - **Por Funcionario:** Busca por cédula, nombre o apellido
   - **Por Fechas:** Selecciona fecha desde y hasta
   - Clic en **"Buscar"**
3. **Ver Resultados:**
   - Tabla con todas las marcaciones del período
   - Columnas:
     - **Fecha**
     - **Hora Entrada**
     - **Almuerzo Salida**
     - **Almuerzo Entrada**
     - **Hora Salida**
     - **Horas Trabajadas**
     - **Horas Día:** Si hay jornada extraordinaria sin marcación, aparece en azul
     - **Estado:** Tardanza, irregular, etc.
4. **Resumen del Período:**
   - **Total de registros**
   - **Total Horas Trabajadas**
   - **Total Jornadas Extraordinarias del Período**
   - **Total Tardanza/Irregular**

**Secciones que aparecen después del filtro:**

1. **Jornadas Extraordinarias del Período:**
   - Lista todas las jornadas extraordinarias del período
   - Total de horas
   - Horas Extraordinarias Acumuladas (del funcionario)

2. **Misión Oficial del Período:**
   - Lista todas las misiones oficiales
   - Total de horas
   - Misiones Oficiales Acumuladas

3. **Reincorporación del Período:**
   - Lista todas las reincorporaciones

4. **Tiempo Compensatorio del Período:**
   - Lista tiempo compensatorio usado
   - Total de horas y días
   - Tiempo Compensatorio Acumulado

5. **Permisos del Período:**
   - Lista todos los permisos
   - Total de horas
   - Permisos Acumulados
   - Permisos InJustificados Acumulados
   - Total Permisos Injustificados del Período (en rojo)

6. **Solicitud de Vacaciones del Período:**
   - Lista todas las solicitudes de vacaciones

**Configurar Derechos del Funcionario:**

1. Filtra por un funcionario específico
2. En la sección **"Permisos/Vacaciones"**:
   - **Vacaciones (días):** Edita directamente el valor
   - Clic en **"Actualizar Derechos"**

---

### 4.5. Importación de Excel

**Ubicación:** `services/excel/importar.php`

**Qué hace:**
- Importa datos desde archivos Excel
- Dos tipos: **Personal RRHH** y **Marcaciones Biométricas**

#### 4.5.1. Importar Personal RRHH

**Paso a paso:**

1. Ve a: **Importar Excel** (menú) → Pestaña **"Personal RRHH"**
2. **Preparar Archivo Excel:**
   - El archivo debe tener columnas específicas
   - Consulta: `docs/MAPEO_COLUMNAS_EXCEL.md`
3. **Subir Archivo:**
   - Clic en **"Seleccionar archivo"**
   - Selecciona tu archivo `.xlsx` o `.xls`
   - Clic en **"Importar Personal RRHH"**
4. **Revisar Resultados:**
   - Se mostrarán errores si los hay
   - Los funcionarios válidos se importarán
   - Los errores se guardan en la tabla `errores_importacion_funcionarios`

#### 4.5.2. Importar Marcaciones Biométricas

**Paso a paso:**

1. Ve a: **Importar Excel** → Pestaña **"Marcaciones Biométricas"**
2. **Preparar Archivo:**
   - El archivo debe tener formato específico del reloj biométrico
   - Columnas: Cédula, Fecha, Hora, Tipo de Marcación
3. **Subir Archivo:**
   - Selecciona el archivo
   - Clic en **"Importar Marcaciones"**
4. **Procesamiento:**
   - El sistema procesa cada marcación
   - Calcula horas trabajadas automáticamente
   - Detecta almuerzo automáticamente
   - Crea registros en la tabla `marcaciones`

---

### 4.6. Mantenimiento

**Ubicación:** `pages/mantenimiento/`  
**Acceso:** Solo Administradores

**Funciones disponibles:**

#### 4.6.1. Gestión de Funcionarios Especiales
- Marcar funcionarios como "especiales" (horarios diferentes)
- Configurar horarios manuales

#### 4.6.2. Cesantes
- Gestionar funcionarios cesantes
- Mover a tabla `ex_funcionarios`

#### 4.6.3. Reintegrar Ex-Funcionario
- Reincorporar funcionarios que estaban cesantes
- Mover de `ex_funcionarios` a `funcionarios`

#### 4.6.4. Crear/Editar Marcación Manual
- Crear o modificar marcaciones manualmente
- Útil para corregir errores

---

### 4.7. Gestión de Errores

#### 4.7.1. Err-Biométrico

**Ubicación:** `pages/err_biometrico/`  
**Acceso:** Solo Administradores

**Qué hace:**
- Lista errores encontrados en marcaciones biométricas
- Permite marcarlos como resueltos

**Paso a paso:**

1. Ve a: **Err-Biométrico** (menú)
2. Verás lista de errores
3. Para cada error puedes:
   - Ver detalles
   - **Marcar como Resuelto**

#### 4.7.2. Err-RRHH

**Ubicación:** `pages/err_rrhh/`  
**Acceso:** Solo Administradores

**Qué hace:**
- Lista errores encontrados en datos de funcionarios
- Permite marcarlos como resueltos

**Uso similar a Err-Biométrico**

---

### 4.8. Gestión de Usuarios

**Ubicación:** `roles_rrhh/pages/usuarios/`  
**Acceso:** Solo Administradores

**Qué hace:**
- Crear, editar y eliminar usuarios del sistema
- Asignar roles (Administrador o Usuario)

**Paso a paso para crear usuario:**

1. Ve a: **Usuarios** (menú)
2. Clic en **"Crear Usuario"**
3. Llena el formulario:
   - **Nombre de Usuario:** (único)
   - **Contraseña:** (se hashea automáticamente)
   - **Rol:** Administrador o Usuario
4. Clic en **"Guardar Usuario"**

**Roles:**

- **Administrador:**
  - Acceso completo al sistema
  - Puede eliminar funcionarios
  - Puede modificar marcaciones
  - Puede gestionar usuarios

- **Usuario:**
  - Solo consultas
  - No puede eliminar
  - No puede modificar asistencia
  - No puede gestionar usuarios

---

## 5. PROCESOS COMUNES

### 5.1. Proceso Completo: Registrar un Nuevo Funcionario y su Primera Marcación

**Paso a paso:**

1. **Crear Funcionario:**
   - Ve a: **Funcionarios** → **Crear Funcionario**
   - Llena todos los datos
   - Guarda

2. **Configurar Derechos:**
   - Ve a: **Marcaciones Biométricas**
   - Busca el funcionario
   - Configura **"Vacaciones (días)"** si es necesario
   - Actualiza derechos

3. **Importar Marcaciones:**
   - Ve a: **Importar Excel** → **Marcaciones Biométricas**
   - Sube archivo con las marcaciones del funcionario
   - El sistema procesará automáticamente

4. **Verificar:**
   - Ve a: **Marcaciones Biométricas**
   - Busca el funcionario
   - Verifica que las marcaciones aparecen correctamente

---

### 5.2. Proceso: Funcionario Pide Permiso

**Paso a paso:**

1. **Registrar Permiso:**
   - Ve a: **Permisos/Vacaciones** → **Solicitud de Permiso**
   - Busca el funcionario
   - Ingresa fecha, horas y motivo
   - Guarda

2. **Verificar en Marcaciones:**
   - Ve a: **Marcaciones Biométricas**
   - Busca el funcionario y filtra por fechas
   - En la sección **"Permisos del Período"** verás el permiso
   - Si es injustificado, aparece en rojo

3. **Verificar Acumulados:**
   - En el formulario de permisos, verifica los acumulados
   - Si necesitas ajustar, haz clic y edita manualmente

---

### 5.3. Proceso: Funcionario Trabaja Horas Extraordinarias

**Paso a paso:**

1. **Registrar Jornada:**
   - Ve a: **Permisos/Vacaciones** → **Jornada Extraordinaria**
   - Busca el funcionario
   - Ingresa fecha, horas desde/hasta y justificación
   - Guarda

2. **Verificar en Marcaciones:**
   - Ve a: **Marcaciones Biométricas**
   - Busca el funcionario y la fecha
   - Si no hay marcación del reloj, la jornada aparece en la columna **"Horas Día"** en azul
   - En **"Jornadas Extraordinarias del Período"** verás el total

3. **Verificar Acumulados:**
   - Las horas se suman automáticamente al acumulado
   - Puedes editar el acumulado manualmente si es necesario

---

### 5.4. Proceso: Funcionario se Va de Vacaciones

**Paso a paso:**

1. **Registrar Solicitud:**
   - Ve a: **Permisos/Vacaciones** → **Solicitud de Vacaciones**
   - Busca el funcionario
   - Llena la declaración (días solicitados, fechas)
   - Agrega períodos con resolución, fecha y días
   - Guarda

2. **Verificar:**
   - En **Marcaciones Biométricas**, busca el funcionario
   - En **"Solicitud de Vacaciones del Período"** verás todos los períodos

---

### 5.5. Proceso: Corregir una Marcación Incorrecta

**Paso a paso:**

1. **Identificar el Error:**
   - Ve a: **Marcaciones Biométricas**
   - Busca el funcionario y la fecha
   - Identifica qué está mal (hora de entrada, salida, etc.)

2. **Corregir Manualmente:**
   - Ve a: **Mantenimiento** (solo administradores)
   - Busca la marcación
   - Edita los campos necesarios
   - Guarda

**Alternativa (si es administrador):**
- En algunas páginas puedes editar directamente (dependiendo de la implementación)

---

## 6. BASE DE DATOS

### 6.1. Tablas Principales

#### `funcionarios`
Almacena información de funcionarios activos.

**Campos importantes:**
- `cedula` (PK): Cédula del funcionario
- `nombre`, `apellido`: Nombre completo
- `h_entrada`, `h_salida`: Horarios de trabajo
- `vacaciones_dias_acumulados`: Días de vacaciones
- `horas_extraordinarias_acumuladas`: Horas extra (TIME)
- `permisos_acumulados`: Permisos totales (TIME)
- `permisos_injustificados_acumulados`: Permisos injustificados (TIME)
- `mision_oficial_acumuladas`: Misiones oficiales (TIME)
- `tiempo_compensatorio_horas_acumuladas`: Tiempo comp. horas (TIME)
- `tiempo_compensatorio_dias_acumulados`: Tiempo comp. días (INT)

#### `marcaciones`
Almacena registros del reloj biométrico.

**Campos importantes:**
- `id_marcacion` (PK)
- `cedula`: Cédula del funcionario
- `fecha`: Fecha de la marcación
- `hora_entrada`, `hora_salida`: Horas de entrada/salida
- `almuerzo_salida`, `almuerzo_entrada`: Horas de almuerzo
- `horas_trabajadas`: Calculado automáticamente
- `tiempo_faltante`: Tiempo que falta

#### `jornada_extraordinaria`
Almacena jornadas extraordinarias.

**Campos:**
- `id_jornada` (PK)
- `cedula`
- `fecha`, `hora_desde`, `hora_hasta`
- `horas_totales`: Calculado automáticamente (GENERATED)
- `justificacion`
- `estado`: 'activa' o 'eliminada'

#### `permisos`
Almacena solicitudes de permisos.

**Campos:**
- `id_permiso` (PK)
- `cedula`
- `fecha`, `hora_desde`, `hora_hasta`
- `horas_totales`: Calculado automáticamente
- `motivo`: ENUM con los motivos disponibles
- `estado`: 'activa' o 'eliminada'

#### `mision_oficial`
Almacena misiones oficiales.

**Similar estructura a `jornada_extraordinaria`**

#### `reincorporacion`
Almacena reincorporaciones.

**Campos:**
- `id_reincorporacion` (PK)
- `cedula`
- `motivo_ausencia`: ENUM
- `puesto`, `no_posicion`, `unidad_administrativa`
- `fecha_reincorporacion`

#### `tiempo_compensatorio`
Almacena tiempo compensatorio usado.

**Campos:**
- `id_tiempo_comp` (PK)
- `cedula`
- `horas`, `dias`: Cantidad usada
- `fecha_uso`

#### `solicitud_vacaciones`
Almacena solicitudes de vacaciones.

**Campos:**
- `id_vacacion` (PK)
- `cedula`
- `dias_solicitados`, `fecha_inicio`, `fecha_retorno`
- `resolucion`, `fecha_resolucion`, `dias_vacacion`
- `observaciones`

#### `usuarios`
Almacena usuarios del sistema.

**Campos:**
- `id_usuario` (PK)
- `nombre_usuario`: Único
- `contraseña`: Hasheada con bcrypt
- `rol`: 'administrador' o 'usuario'

#### `ex_funcionarios`
Almacena funcionarios cesantes (estructura similar a `funcionarios`)

#### `ex_marcaciones`
Almacena marcaciones de funcionarios cesantes

---

### 6.2. Relaciones entre Tablas

- `funcionarios.cedula` → Referenciada por:
  - `marcaciones.cedula`
  - `jornada_extraordinaria.cedula`
  - `permisos.cedula`
  - `mision_oficial.cedula`
  - `reincorporacion.cedula`
  - `tiempo_compensatorio.cedula`
  - `solicitud_vacaciones.cedula`

- Todas las relaciones tienen `ON DELETE CASCADE` y `ON UPDATE CASCADE`

---

## 7. PREGUNTAS FRECUENTES

### ¿Cómo cambio la contraseña del administrador?

1. Ve a: **Usuarios**
2. Busca el usuario `admin`
3. Clic en **"Editar"**
4. Cambia la contraseña
5. Guarda

---

### ¿Por qué no veo las marcaciones de un funcionario?

**Posibles causas:**
1. El funcionario no existe en la base de datos
2. No se han importado las marcaciones desde Excel
3. El filtro de fechas está ocultando los resultados
4. El funcionario está en `ex_funcionarios` (cesante)

**Solución:**
- Verifica que el funcionario existe
- Importa las marcaciones desde Excel
- Ajusta los filtros de fecha

---

### ¿Cómo funciona el cálculo de horas trabajadas?

El sistema calcula automáticamente:
- **Horas trabajadas = (Hora Salida - Hora Entrada) - Tiempo de Almuerzo**

Si hay almuerzo:
- Se resta el tiempo entre `almuerzo_salida` y `almuerzo_entrada`

---

### ¿Qué pasa si elimino una jornada extraordinaria?

- Se resta automáticamente del campo `horas_extraordinarias_acumuladas` en la tabla `funcionarios`

---

### ¿Qué pasa si elimino un permiso injustificado?

- Se resta del campo `permisos_acumulados`
- También se resta del campo `permisos_injustificados_acumulados`

---

### ¿Puedo editar manualmente los acumulados?

**Sí**, en todos los formularios de permisos puedes:
1. Hacer clic en el valor acumulado
2. Editarlo directamente
3. Formato: HH:MM (ejemplo: 33:30) o solo número (ejemplo: 5 se convierte a 05:00)
4. Guardar

---

### ¿Qué significa "Permiso InJustificado"?

Es un tipo especial de permiso que:
- Se acumula por separado en `permisos_injustificados_acumulados`
- Aparece en rojo en los listados
- Se resta de ambos acumulados al eliminarse

---

### ¿Cómo importo datos desde Excel?

1. Prepara el archivo Excel con el formato correcto
2. Ve a: **Importar Excel**
3. Selecciona el tipo (Personal RRHH o Marcaciones)
4. Sube el archivo
5. Revisa los errores si los hay
6. Los datos válidos se importan automáticamente

---

### ¿Qué hacer si hay un error en una marcación?

**Si eres administrador:**
1. Ve a: **Mantenimiento**
2. Busca la marcación
3. Edítala manualmente
4. Guarda

---

### ¿Cómo veo el resumen de un funcionario?

1. Ve a: **Marcaciones Biométricas**
2. Busca el funcionario (sin filtrar fechas o con rango amplio)
3. Verás todas las secciones:
   - Jornadas Extraordinarias del Período
   - Misión Oficial del Período
   - Reincorporación del Período
   - Tiempo Compensatorio del Período
   - Permisos del Período
   - Solicitud de Vacaciones del Período
4. También verás los acumulados totales de cada tipo

---

### ¿Qué significan los colores en los formularios?

Cada módulo tiene su color distintivo:
- 🔵 **Azul:** Jornada Extraordinaria
- 🔴 **Rojo:** Misión Oficial
- 🟣 **Morado:** Reincorporación
- 🟠 **Naranja:** Tiempo Compensatorio
- 🟢 **Verde:** Solicitud de Permiso
- 🌸 **Rosa/Fucsia:** Solicitud de Vacaciones

---

## 8. ESTRUCTURA DE ARCHIVOS IMPORTANTES

### Archivos de Configuración

- `config/database.php`: Configuración de conexión a BD
- `config/constants.php`: Constantes del sistema (rutas, URLs)

### Archivos de Funciones

- `includes/functions.php`: Funciones auxiliares (sanitize, redirect, etc.)
- `includes/funciones_calculo_horas.php`: Cálculos de horas trabajadas
- `includes/funciones_deteccion_almuerzo.php`: Detección automática de almuerzo

### Clases

- `classes/Database.php`: Clase singleton para conexión a BD
- `roles_rrhh/classes/Auth.php`: Clase de autenticación

### Middlewares

- `roles_rrhh/middleware/auth_middleware.php`: Requiere autenticación
- `roles_rrhh/middleware/admin_middleware.php`: Requiere rol administrador

---

## 9. CONTACTO Y SOPORTE

Para dudas o problemas:
1. Revisa esta documentación
2. Verifica los logs en `logs/php_errors.log`
3. Revisa la consola del navegador (F12) para errores JavaScript
4. Consulta `DOCUMENTACION_COMPLETA.md` para detalles técnicos

---

## 10. NOTAS IMPORTANTES

⚠️ **Seguridad:**
- Cambia la contraseña por defecto del administrador
- No compartas credenciales
- Usa usuarios con rol "Usuario" para consultas normales

💡 **Rendimiento:**
- Los filtros de fecha ayudan a mejorar el rendimiento
- Para funcionarios con muchas marcaciones, filtra por períodos cortos

📊 **Datos:**
- Los acumulados se calculan automáticamente al agregar/eliminar registros
- Puedes editarlos manualmente si necesitas ajustes

🔄 **Backups:**
- Realiza backups regulares de la base de datos
- Especialmente antes de ejecutar migraciones

---

**Fin de la Guía**

Para más detalles técnicos, consulta `DOCUMENTACION_COMPLETA.md`

