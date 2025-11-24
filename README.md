# Sistema de Recursos Humanos (RRHH)

Sistema completo para la gestión de funcionarios, permisos y asistencia.

## Estructura del Proyecto

```
SISTEMA RRHH/
├── database/              # Scripts SQL y migraciones
│   ├── rrhh_funcionarios.sql
│   └── migrations/
│
├── config/                # Configuración del sistema
│   ├── database.php       # Configuración de BD
│   └── constants.php      # Constantes del sistema
│
├── includes/              # Archivos incluidos
│   ├── header.php
│   ├── footer.php
│   └── functions.php      # Funciones auxiliares
│
├── pages/                 # Páginas principales
│   ├── index.php         # Dashboard
│   └── funcionarios/     # Módulo de funcionarios
│
├── services/              # Microservicios
│   └── excel/            # Importación de Excel
│
├── forms/                 # Formularios
│   └── permisos/         # 6 formularios de permisos
│
├── classes/               # Clases PHP
│   └── Database.php      # Clase de conexión
│
├── api/                   # Endpoints API
│
├── assets/                # Recursos estáticos
│   ├── css/
│   ├── js/
│   └── images/
│
└── uploads/               # Archivos subidos
    ├── documentos/
    └── excel/
```

## Requisitos

- PHP 7.4 o superior
- MySQL 8.0 o superior
- Apache (Laragon)
- phpMyAdmin

## Instalación

1. **Importar base de datos:**
   - Abrir phpMyAdmin: `http://localhost/phpmyadmin`
   - Crear base de datos: `rrhh`
   - Importar: `database/rrhh_funcionarios.sql`

2. **Configurar conexión:**
   - Editar `config/database.php` si es necesario
   - Por defecto usa: localhost, root, sin contraseña

3. **Permisos de carpetas:**
   - Asegurar que `uploads/` tenga permisos de escritura
   - Asegurar que `logs/` tenga permisos de escritura

## Base de Datos

### Tabla: `funcionarios`
- `cedula` (PK) - Cédula del funcionario
- `nombre` - Nombre y segundo nombre
- `apellido` - Apellido y segundo apellido
- `fecha_nacimiento` - Fecha de nacimiento
- `edad` - Edad
- `sangre` - Tipo de sangre
- `no_posicion` - Número de posición (único)
- `posicion_funcional` - Nombre de la posición
- `fecha_inicio` - Fecha de inicio laboral
- `sede_provincia` - Sede/Provincia/Comarca
- `Direccion` - Dirección de trabajo

## Sistema de Roles y Autenticación

✅ **Módulo `roles_rrhh` implementado**

- Autenticación de usuarios
- Roles: Administrador y Usuario
- Protección de rutas con middleware
- Gestión de usuarios (solo administradores)

**Usuario por defecto:**
- Usuario: `admin`
- Contraseña: `admin123`
- ⚠️ Cambiar en producción

Ver: `roles_rrhh/README.md` y `roles_rrhh/INSTALACION.md`

## Próximos Pasos

1. ✅ Estructura de carpetas creada
2. ✅ Sistema de roles y autenticación
3. ⏳ Migrar microservicio de importación Excel
4. ⏳ Migrar y revisar los 6 formularios de permisos
5. ⏳ Crear tablas de permisos y asistencia

## Desarrollo

- **Workspace:** Abrir `SISTEMA RRHH.code-workspace` en Cursor/VS Code
- **URL Local:** `http://localhost/SISTEMA%20RRHH/`
- **Logs:** `logs/php_errors.log`

## Notas

- El sistema está preparado para migrar el microservicio de Excel
- Los formularios de permisos pueden migrarse a `forms/permisos/`
- Las nuevas tablas se crearán en `database/migrations/`

