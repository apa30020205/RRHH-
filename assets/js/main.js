/**
 * JavaScript principal del Sistema RRHH
 */

// Inicialización cuando el DOM está listo
document.addEventListener('DOMContentLoaded', function() {
    // Auto-ocultar mensajes después de 5 segundos
    // EXCEPTO los mensajes con clase 'no-auto-hide' o mensajes importantes
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        // NO ocultar si tiene la clase no-auto-hide O el atributo data-persist
        if (alert.classList.contains('no-auto-hide') || alert.getAttribute('data-persist') === 'true') {
            return; // Saltar este alert completamente
        }
        
        // No ocultar mensajes de error críticos del microservicio
        const texto = (alert.textContent || alert.innerText || '').toLowerCase();
        const esMensajeImportante = texto.includes('microservicio') ||
                                     texto.includes('⚠️') ||
                                     texto.includes('python');
        
        // Si es un mensaje importante, NO hacer nada (no ocultar)
        if (esMensajeImportante) {
            return; // Saltar este alert completamente
        }
        
        // Solo ocultar mensajes normales (éxito, info)
        setTimeout(function() {
            // Verificar nuevamente antes de ocultar (por si cambió)
            if (!alert.classList.contains('no-auto-hide') && 
                !alert.textContent.toLowerCase().includes('microservicio')) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }
        }, 5000);
    });
    
    // Validación de formularios
    const forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });
    });
});

/**
 * Valida un formulario
 */
function validateForm(form) {
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(function(field) {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('error');
            showFieldError(field, 'Este campo es obligatorio');
        } else {
            field.classList.remove('error');
            clearFieldError(field);
        }
    });
    
    return isValid;
}

/**
 * Muestra error en un campo
 */
function showFieldError(field, message) {
    clearFieldError(field);
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.color = '#e74c3c';
    errorDiv.style.fontSize = '0.875rem';
    errorDiv.style.marginTop = '0.25rem';
    field.parentNode.appendChild(errorDiv);
}

/**
 * Limpia el error de un campo
 */
function clearFieldError(field) {
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
}

/**
 * Confirmación antes de eliminar
 */
function confirmDelete(message) {
    return confirm(message || '¿Está seguro de que desea eliminar este registro?');
}

