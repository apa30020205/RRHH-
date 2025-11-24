# Instalación del Módulo de Roles y Autenticación

## Paso 1: Importar la Base de Datos

1. Abre phpMyAdmin o tu cliente MySQL
2. Selecciona la base de datos `rrhh`
3. Importa el archivo: `database/migrations/20251119_crear_tabla_usuarios.sql`

## Paso 2: Verificar Usuario por Defecto

Después de importar, deberías tener un usuario administrador:

- **Usuario:** `admin`
- **Contraseña:** `admin123`
- ⚠️ **IMPORTANTE:** Cambiar esta contraseña en producción

## Paso 3: Probar el Sistema

1. Accede a: `http://localhost/SISTEMA%20%20RRHH/`
2. Serás redirigido al login
3. Inicia sesión con las credenciales por defecto
4. Verifica que puedas acceder al sistema

## Paso 4: Crear Usuarios Adicionales

1. Inicia sesión como administrador
2. Ve a: **Usuarios** (en el menú)
3. Crea nuevos usuarios según necesites

## Estructura de Roles

### Administrador
- ✅ Crear, editar y eliminar usuarios
- ✅ Eliminar funcionarios
- ✅ Modificar asistencia (cuando se implemente)
- ✅ Acceso completo al sistema

### Usuario
- ✅ Consultar funcionarios
- ✅ Ver información
- ❌ No puede eliminar funcionarios
- ❌ No puede modificar asistencia
- ❌ No puede gestionar usuarios

## Notas de Seguridad

- Las contraseñas se almacenan con hash bcrypt
- Las sesiones se validan en cada página
- Los middlewares protegen las rutas automáticamente
- Solo los administradores pueden asignar contraseñas

## Solución de Problemas

### Error: "Usuario no encontrado"
- Verifica que la tabla `usuarios` existe
- Verifica que el usuario por defecto fue creado

### Error: "No tienes permisos"
- Verifica que estás iniciado sesión
- Verifica que tu rol es `administrador` en la base de datos

### No puedo acceder al sistema
- Verifica que la sesión está iniciada
- Limpia las cookies del navegador
- Verifica que `session_start()` se ejecuta correctamente

