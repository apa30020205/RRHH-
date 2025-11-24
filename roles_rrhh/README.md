# Módulo de Roles y Autenticación - roles_rrhh

Módulo independiente para gestión de usuarios, autenticación y control de acceso del Sistema RRHH.

## Estructura

```
roles_rrhh/
├── classes/
│   └── Auth.php              # Clase de autenticación
├── middleware/
│   ├── auth_middleware.php   # Middleware para requerir autenticación
│   └── admin_middleware.php  # Middleware para requerir rol admin
├── pages/
│   ├── login.php             # Página de inicio de sesión
│   ├── logout.php            # Cerrar sesión
│   └── usuarios/             # Gestión de usuarios (solo admin)
│       ├── listar.php
│       ├── crear.php
│       ├── editar.php
│       └── eliminar.php
└── README.md
```

## Características

### Roles

1. **Administrador:**
   - Puede crear, editar y eliminar usuarios
   - Puede eliminar funcionarios
   - Puede modificar asistencia
   - Acceso completo al sistema

2. **Usuario:**
   - Solo consultas (no puede modificar)
   - No puede eliminar funcionarios
   - No puede modificar asistencia
   - No puede gestionar usuarios

### Seguridad

- Contraseñas hasheadas con bcrypt
- Sesiones seguras
- Middleware para proteger rutas
- Validación de permisos en cada acción

## Uso

### Proteger una página

```php
// Requerir autenticación
require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';

// Requerir administrador
require_once __DIR__ . '/../../roles_rrhh/middleware/admin_middleware.php';
```

### Verificar rol en código

```php
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

if (Auth::isAdmin()) {
    // Solo administradores
}

if (Auth::isUsuario()) {
    // Solo usuarios normales
}

$currentUser = Auth::getCurrentUser();
```

## Base de Datos

### Tabla: `usuarios`

Importar: `database/migrations/20251119_crear_tabla_usuarios.sql`

**Usuario por defecto:**
- Usuario: `admin`
- Contraseña: `admin123`
- ⚠️ **Cambiar en producción**

## Notas

- Este módulo está separado para facilitar mantenimiento
- Los permisos de vacaciones se manejarán aparte (futuro)
- Cada funcionario debe inscribirse para solicitar permisos

