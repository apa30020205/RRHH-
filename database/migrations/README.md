# Migraciones de Base de Datos

Esta carpeta contiene los scripts de migración para actualizar la estructura de la base de datos.

## Estructura

- Cada migración debe tener un nombre descriptivo con fecha
- Formato: `YYYYMMDD_descripcion.sql`
- Ejemplo: `20251119_crear_tabla_permisos.sql`

## Uso

1. Revisar el script de migración
2. Hacer backup de la base de datos
3. Ejecutar el script en phpMyAdmin o desde línea de comandos
4. Verificar que la migración se aplicó correctamente

## Notas

- Siempre hacer backup antes de ejecutar migraciones
- Probar en ambiente de desarrollo primero
- Documentar cambios importantes en los scripts

