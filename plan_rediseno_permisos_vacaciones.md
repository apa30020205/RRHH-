# Plan: Rediseño de Página "Listado Permisos/Vacaciones"

## Objetivo
Rediseñar la página `forms/permisos/index.php` para que se vea exactamente como la imagen proporcionada, con 6 cards en grid 2x3.

## Archivo a Modificar
- `forms/permisos/index.php`

## Cambios Requeridos

### 1. Estructura de las 6 Cards
Las 6 opciones deben ser exactamente:
1. **Jornada Extraordinaria** (Azul) - Icono: reloj
2. **Misión Oficial** (Rojo) - Icono: avión
3. **Reincorporación** (Morado) - Icono: flecha circular/refresh
4. **Tiempo Compensatorio** (Naranja) - Icono: reloj de arena
5. **Solicitud de Permiso** (Verde) - Icono: calendario con check
6. **Solicitud de Vacaciones** (Rosa) - Icono: sombrilla de playa

### 2. Layout Grid
- Grid de 2 columnas x 3 filas (fijo o responsive)
- Espaciado consistente entre cards
- Fondo gris claro para la página

### 3. Estilo de Cada Card
- Fondo blanco
- Sombra sutil (box-shadow)
- Borde izquierdo delgado con color del tema (3-4px)
- Icono grande centrado en la parte superior
- Título en negrita, texto oscuro
- Subtítulo más pequeño, texto gris
- Botón "Llenar Formulario" con:
  - Color del tema de la card
  - Icono de lápiz (fa-edit o fa-pencil)
  - Texto blanco
  - Bordes redondeados

### 4. Colores por Card
1. Azul: #2196F3 o similar
2. Rojo: #f44336 o similar
3. Morado: #9c27b0 o similar
4. Naranja: #ff9800 o similar
5. Verde: #4caf50 o similar
6. Rosa: #e91e63 o similar

### 5. Textos de Cada Card
1. **Jornada Extraordinaria**
   - Subtítulo: "Autorización para laborar en jornada extraordinaria"

2. **Misión Oficial**
   - Subtítulo: "Solicitud de misión oficial"

3. **Reincorporación**
   - Subtítulo: "Notificación de reincorporación"

4. **Tiempo Compensatorio**
   - Subtítulo: "Solicitud de uso de tiempo compensatorio"

5. **Solicitud de Permiso**
   - Subtítulo: "Solicitud de permiso personal"

6. **Solicitud de Vacaciones**
   - Subtítulo: "Solicitud de vacaciones"

### 6. Funcionalidad
- Al hacer clic en cualquier card o botón, mostrar modal "En construcción"
- Mantener el modal existente o mejorarlo

## Implementación

### Pasos:
1. Reemplazar el contenido actual de las 6 cards
2. Actualizar los iconos según la imagen
3. Ajustar el grid para que sea 2 columnas x 3 filas
4. Aplicar los colores exactos de la imagen
5. Ajustar textos (títulos y subtítulos)
6. Estilizar botones con icono de lápiz
7. Ajustar espaciado y sombras para que coincida con la imagen

## Notas
- Mantener la funcionalidad del modal "En construcción"
- El diseño debe ser responsive pero priorizar el layout 2x3 en pantallas grandes
- Usar Font Awesome para los iconos

