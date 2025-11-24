/**
 * Validación de cédula en tiempo real
 * Sistema RRHH
 */

// Función para validar formato de cédula panameña (JavaScript)
function validarCedulaFormato(cedula) {
    // Eliminar espacios al inicio y final
    cedula = cedula.trim();
    
    // Si está vacía, no mostramos error aún
    if (cedula.length === 0) {
        return { valida: null, mensaje: '' };
    }
    
    // PRIMERO: Validar que solo contenga letras, números y guiones
    if (!/^[A-Z0-9\-]+$/i.test(cedula)) {
        return { 
            valida: false, 
            mensaje: 'La cédula solo puede contener letras, números y guiones' 
        };
    }
    
    // Eliminar guiones para análisis
    const cedulaLimpia = cedula.replace(/[\s\-]/g, '');
    
    // Validar que después de quitar guiones tenga contenido
    if (cedulaLimpia.length === 0) {
        return { 
            valida: false, 
            mensaje: 'La cédula no puede estar vacía' 
        };
    }
    
    // Longitud mínima 5 caracteres (basado en cédulas reales)
    // Máxima 20 caracteres
    if (cedulaLimpia.length < 5) {
        return { 
            valida: false, 
            mensaje: 'La cédula debe tener al menos 5 caracteres (sin contar guiones)' 
        };
    }
    
    if (cedulaLimpia.length > 20) {
        return { 
            valida: false, 
            mensaje: 'La cédula no puede tener más de 20 caracteres (sin contar guiones)' 
        };
    }
    
    // Validar que solo contenga letras y números (sin otros caracteres)
    if (!/^[A-Z0-9]+$/i.test(cedulaLimpia)) {
        return { 
            valida: false, 
            mensaje: 'La cédula solo puede contener letras y números' 
        };
    }
    
    // Validar cédulas numéricas (5-13 dígitos basado en formatos reales)
    if (/^\d+$/.test(cedulaLimpia)) {
        if (cedulaLimpia.length >= 5 && cedulaLimpia.length <= 13) {
            return { valida: true, mensaje: 'Formato válido' };
        }
        return { 
            valida: false, 
            mensaje: 'Cédula numérica debe tener entre 5 y 13 dígitos' 
        };
    }
    
    // Validar cédulas alfanuméricas (basado en formatos reales: PE-12-1588, PE-9-399)
    if (/[A-Z]/i.test(cedulaLimpia) && /[0-9]/.test(cedulaLimpia)) {
        const cantidadNumeros = (cedulaLimpia.match(/\d/g) || []).length;
        const cantidadLetras = (cedulaLimpia.match(/[A-Z]/gi) || []).length;
        
        if (cantidadNumeros < 1 || cantidadLetras < 1) {
            return { 
                valida: false, 
                mensaje: 'Cédula alfanumérica debe tener al menos 1 número y 1 letra' 
            };
        }
        
        if (cedulaLimpia.length < 5) {
            return { 
                valida: false, 
                mensaje: 'Cédula alfanumérica debe tener al menos 5 caracteres' 
            };
        }
        
        // Validar patrones basados en formatos reales
        // Patrón 1: Letras al inicio, números después (PE1234567, PE9399)
        if (/^[A-Z]{2,4}[0-9]{3,}$/i.test(cedulaLimpia)) {
            return { valida: true, mensaje: 'Formato válido' };
        }
        
        // Patrón 2: Números al inicio, letras después
        if (/^[0-9]{3,}[A-Z]{2,4}$/i.test(cedulaLimpia)) {
            return { valida: true, mensaje: 'Formato válido' };
        }
        
        // Patrón 3: Número(s), letra(s), números
        if (/^[0-9]{1,3}[A-Z]{1,3}[0-9]{2,}$/i.test(cedulaLimpia)) {
            return { valida: true, mensaje: 'Formato válido' };
        }
        
        // Patrón 4: Letra(s), números, letra(s)
        if (/^[A-Z]{1,2}[0-9]{3,}[A-Z]{1,2}$/i.test(cedulaLimpia)) {
            return { valida: true, mensaje: 'Formato válido' };
        }
        
        // Si tiene al menos 2 números y 2 letras, aceptar
        if (cantidadNumeros >= 2 && cantidadLetras >= 2 && cedulaLimpia.length >= 5 && cedulaLimpia.length <= 15) {
            return { valida: true, mensaje: 'Formato válido' };
        }
        
        return { 
            valida: false, 
            mensaje: 'Formato alfanumérico inválido' 
        };
    }
    
    return { 
        valida: false, 
        mensaje: 'Formato de cédula inválido. Use formato numérico (8-1234-5678) o alfanumérico (PE-123456-7)' 
    };
}

// Función para verificar cédula en el servidor
function verificarCedulaServidor(cedula, callback) {
    // Cambiar mínimo a 5 caracteres (basado en cédulas reales)
    if (!cedula || cedula.trim().replace(/[\s\-]/g, '').length < 5) {
        callback({ valida: false, existe: false, mensaje: '' });
        return;
    }
    
    // Obtener BASE_URL del atributo data del body
    const body = document.querySelector('body');
    const baseUrl = body ? (body.dataset.baseUrl || '/SISTEMA%20%20RRHH') : '/SISTEMA%20%20RRHH';
    const url = baseUrl + '/api/verificar_cedula.php?cedula=' + encodeURIComponent(cedula);
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            callback(data);
        })
        .catch(error => {
            console.error('Error al verificar cédula:', error);
            callback({ valida: false, existe: false, mensaje: 'Error al verificar. Intente nuevamente.' });
        });
}

// Función para mostrar mensaje de validación
function mostrarMensajeCedula(input, valida, mensaje) {
    // Remover mensaje anterior
    const mensajeAnterior = input.parentNode.querySelector('.mensaje-cedula');
    if (mensajeAnterior) {
        mensajeAnterior.remove();
    }
    
    // Remover clases anteriores
    input.classList.remove('cedula-valida', 'cedula-invalida', 'cedula-duplicada');
    
    // Si no hay mensaje, no mostrar nada
    if (mensaje === '' || valida === null) {
        return;
    }
    
    // Crear elemento de mensaje
    const mensajeDiv = document.createElement('div');
    mensajeDiv.className = 'mensaje-cedula';
    
    if (valida === false) {
        mensajeDiv.className += ' error';
        mensajeDiv.textContent = mensaje;
        input.classList.add('cedula-invalida');
    } else if (valida === true) {
        const existe = mensaje.includes('ya está registrada');
        if (existe) {
            mensajeDiv.className += ' warning';
            mensajeDiv.textContent = mensaje;
            input.classList.add('cedula-duplicada');
        } else {
            mensajeDiv.className += ' success';
            mensajeDiv.textContent = '✓ ' + mensaje;
            input.classList.add('cedula-valida');
        }
    }
    
    input.parentNode.appendChild(mensajeDiv);
}

// Función para inicializar validación en tiempo real
function inicializarValidacionCedula() {
    const inputCedula = document.getElementById('cedula');
    
    if (!inputCedula) {
        // Si no existe, intentar de nuevo después de un momento
        setTimeout(inicializarValidacionCedula, 100);
        return;
    }
    
    let timeoutId = null;
    
    // Validación mientras escribe - INMEDIATA
    inputCedula.addEventListener('input', function() {
        const cedula = this.value.trim();
        
        // Validar formato INMEDIATAMENTE (sin esperar)
        const validacionFormato = validarCedulaFormato(cedula);
        mostrarMensajeCedula(this, validacionFormato.valida, validacionFormato.mensaje);
        
        // Si el formato es válido y tiene al menos 5 caracteres, verificar en servidor
        const cedulaLimpia = cedula.replace(/[\s\-]/g, '');
        if (validacionFormato.valida && cedulaLimpia.length >= 5) {
            // Debounce: esperar 500ms después de que el usuario deje de escribir
            clearTimeout(timeoutId);
            timeoutId = setTimeout(function() {
                verificarCedulaServidor(cedula, function(data) {
                    if (data.valida) {
                        if (data.existe) {
                            mostrarMensajeCedula(inputCedula, true, data.mensaje);
                        } else {
                            mostrarMensajeCedula(inputCedula, true, data.mensaje);
                        }
                    } else {
                        // Si el servidor dice que no es válida, mostrar ese mensaje
                        mostrarMensajeCedula(inputCedula, false, data.mensaje);
                    }
                });
            }, 500);
        }
    });
    
    // Validar al perder el foco
    inputCedula.addEventListener('blur', function() {
        const cedula = this.value.trim();
        if (cedula.length > 0) {
            verificarCedulaServidor(cedula, function(data) {
                mostrarMensajeCedula(inputCedula, data.valida, data.mensaje);
            });
        }
    });
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarValidacionCedula);
} else {
    // DOM ya está listo, ejecutar inmediatamente
    inicializarValidacionCedula();
}

