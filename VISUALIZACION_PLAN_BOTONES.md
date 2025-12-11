# Visualización del Plan: Botones Toggle en Marcaciones Biométricas

## Vista General de la Interfaz

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  Marcaciones Biométricas - Juan Pérez - 8-1234-5678                        │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐  │
│  │ Horario de: [08:00] hasta [16:00] [Guardar] ✓                      │  │
│  └──────────────────────────────────────────────────────────────────────┘  │
│                                                                              │
│                                                          ┌────────────────┐ │
│                                                          │ [VIP] [Manual]  │ │
│                                                          │ [Cesante]      │ │
│                                                          │ [Préstamo]     │ │
│                                                          │ [Lic. Sueldo]  │ │
│                                                          │ [Lic. Sin S.]  │ │
│                                                          └────────────────┘ │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Diseño Detallado del Panel de Botones

### Posición
- **Ubicación**: Lado derecho de la pantalla
- **Alineación**: Justificado a la derecha (flex-end)
- **Posición vertical**: Debajo del formulario de horario
- **Margen superior**: 1rem

### Estructura del Panel

```
┌─────────────────────────────────────────────┐
│  Panel de Botones (alineado a la derecha)   │
│                                              │
│  ┌──────┐ ┌──────┐ ┌─────────┐             │
│  │  VIP │ │Manual│ │ Cesante │             │
│  └──────┘ └──────┘ └─────────┘             │
│                                              │
│  ┌──────────┐ ┌────────────┐ ┌───────────┐ │
│  │ Préstamo │ │Lic. Sueldo  │ │Lic. Sin  │ │
│  │          │ │             │ │Sueldo    │ │
│  └──────────┘ └────────────┘ └───────────┘ │
└─────────────────────────────────────────────┘
```

## Estados de los Botones

### Estado Normal (Inactivo)
```
┌─────────┐
│  VIP    │  ← Fondo blanco/gris claro
│         │     Borde gris
└─────────┘     Texto negro
```

### Estado Activo (Seleccionado)
```
┌─────────┐
│  VIP    │  ← Fondo verde (#28a745)
│         │     Efecto hundido (box-shadow inset)
└─────────┘     Texto blanco
                Borde verde oscuro
```

## Comportamiento

### Al hacer clic en un botón inactivo:
1. Se desactivan todos los demás botones
2. Se activa el botón seleccionado (verde + hundido)
3. Se envía petición AJAX al servidor
4. Se actualiza el campo `fun_extra` en la BD

### Al hacer clic en un botón activo:
1. Se desactiva el botón actual
2. Se envía `null` al servidor (limpiar fun_extra)
3. Todos los botones quedan en estado normal

## Ejemplo Visual de Estados

### Escenario 1: Ningún botón activo
```
[VIP] [Manual] [Cesante] [Préstamo] [Lic. Sueldo] [Lic. Sin Sueldo]
 ↑      ↑        ↑         ↑           ↑              ↑
blanco blanco  blanco   blanco      blanco         blanco
```

### Escenario 2: Botón "VIP" activo
```
[VIP] [Manual] [Cesante] [Préstamo] [Lic. Sueldo] [Lic. Sin Sueldo]
 ↑      ↑        ↑         ↑           ↑              ↑
VERDE blanco   blanco   blanco      blanco         blanco
hundido
```

### Escenario 3: Botón "Préstamo" activo
```
[VIP] [Manual] [Cesante] [Préstamo] [Lic. Sueldo] [Lic. Sin Sueldo]
 ↑      ↑        ↑         ↑           ↑              ↑
blanco blanco  blanco   VERDE       blanco         blanco
                        hundido
```

## Estilos CSS Propuestos

```css
.panel-fun-extra {
    margin-top: 1rem;
    display: flex;
    justify-content: flex-end;
}

.botones-fun-extra {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-fun-extra {
    padding: 0.5rem 1rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: white;
    color: #333;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9em;
    font-weight: 500;
}

.btn-fun-extra:hover {
    background: #f0f0f0;
    border-color: #999;
}

.btn-fun-extra.activo {
    background: #28a745;
    color: white;
    border-color: #1e7e34;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
    transform: translateY(1px);
}
```

## Valores en Base de Datos

| Botón Visual | Valor en BD | Notas |
|--------------|-------------|-------|
| VIP | `VIP` | Antes era "Jefe" |
| Manual | `Manual` | Sin cambios |
| Cesante | `Cesante` | Antes era "cesante" (minúscula) |
| Préstamo | `Préstamo` | Con tilde |
| Lic. Sueldo | `Lic. Sueldo` | Sin cambios |
| Lic. Sin Sueldo | `Lic. Sin Sueldo` | Sin cambios |

## Responsive Design

En pantallas pequeñas, los botones se apilarán verticalmente:
```
[VIP]
[Manual]
[Cesante]
[Préstamo]
[Lic. Sueldo]
[Lic. Sin Sueldo]
```
