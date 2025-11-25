<?php
/**
 * Importar Excel - Interfaz con Drag & Drop
 * Sistema RRHH
 * 
 * Integración con microservicio Python para leer archivos Excel
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Importar Excel - Sistema RRHH';

// URL del microservicio Python
define('MICROSERVICIO_URL', 'http://localhost:5000/api/read-excel');
define('MICROSERVICIO_HEALTH', 'http://localhost:5000/api/health');

/**
 * Verificar si el microservicio está disponible
 */
function verificarMicroservicio() {
    // Intentar con el endpoint de health
    $ch = curl_init(MICROSERVICIO_HEALTH);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Si el health check funciona, retornar true
    if ($http_code === 200) {
        return true;
    }
    
    // Si falla, intentar con el endpoint principal (por si no tiene /health)
    if ($http_code === 0 || $http_code >= 400) {
        $ch2 = curl_init('http://localhost:5000/');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch2);
        $http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        
        return ($http_code2 > 0 && $http_code2 < 500);
    }
    
    return false;
}

$microservicio_disponible = verificarMicroservicio();

// Debug: Si quieres ver el estado en desarrollo, descomenta esto:
// error_log("Microservicio disponible: " . ($microservicio_disponible ? 'SÍ' : 'NO'));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Importar Datos desde Excel</h2>
    <a href="<?php echo BASE_URL; ?>/pages/index.php" class="btn">Volver</a>
</div>

<!-- Estado del Microservicio -->
<?php if (!$microservicio_disponible): ?>
<div class="alert alert-error no-auto-hide" data-persist="true">
    <strong>⚠️ Microservicio no disponible:</strong> El microservicio Python no está corriendo.
    <p class="mt-2">Para iniciarlo:</p>
    <ol class="ml-4 mt-2">
        <li>1. Abre una terminal</li>
        <li>2. Ve a: <code>C:\AMPYME\MICROSERVICIO LECTURA DE EXCEL</code></li>
        <li>3. Ejecuta: <code>python app.py</code> o usa <code>start.bat</code></li>
        <li>4. El servicio debe estar en: <code>http://localhost:5000</code></li>
    </ol>
</div>
<?php else: ?>
<div class="alert alert-success no-auto-hide" data-persist="true">
    <strong>✓ Microservicio conectado:</strong> El servicio está disponible y funcionando.
</div>
<?php endif; ?>

<!-- Sección: Importar archivo único RRHH -->
<div class="excel-import-container" style="margin-bottom: 2rem;">
    <div class="upload-section" style="max-width: 800px; margin: 0 auto;">
        <h3>
            <i class="fas fa-file-excel"></i>
            Importar Archivo Personal RRHH (Un Solo Archivo)
        </h3>
        <p class="section-description">
            <strong>Archivo Excel con:</strong> "CEDULA" (con guiones), "FECHA DE NACIMIENTO", "EDAD", 
            "TIPO DE SANGRE", "POSICIÓN", "POSICIÓN FUNCIONAL", "FECHA DE INICIO", "DIRECCIÓN O SEDE"
        </p>
        <p style="color: #666; font-size: 0.9em; margin: 0.5rem 0;">
            <strong>Nota:</strong> El campo "NOMBRE Y APELLIDO" NO se grabará. Los campos "DIRECCIÓN O SEDE" 
            se dividirán por guion: parte antes del guion → sede_provincia, parte después → Direccion.
        </p>
        
        <div class="drop-zone <?php echo $microservicio_disponible ? '' : 'disabled'; ?>" 
             id="drop-zone-rrhh">
            <input type="file" name="archivo_rrhh" id="archivo_rrhh" 
                   accept=".xls,.xlsx,.csv" class="hidden">
            <div class="drop-zone-content">
                <i class="fas fa-cloud-upload-alt"></i>
                <p class="drop-text">
                    <strong>Arrastra y suelta</strong> el archivo "personal RRHH.xlsx" aquí
                </p>
                <p class="drop-or">o</p>
                <button type="button" onclick="document.getElementById('archivo_rrhh').click()" 
                        class="btn btn-primary"
                        <?php echo $microservicio_disponible ? '' : 'disabled'; ?>>
                    <i class="fas fa-folder-open"></i> Seleccionar Archivo
                </button>
                <p class="drop-info">
                    Formatos: .xls, .xlsx, .csv (máx. 50MB)
                </p>
            </div>
            <div class="file-info" id="info-rrhh">
                <i class="fas fa-file-excel"></i>
                <span id="nombre-rrhh"></span>
            </div>
        </div>
        
        <div id="resultado-rrhh" class="resultado-container hidden">
            <h4>Datos del Archivo:</h4>
            <div class="resultado-stats" id="stats-rrhh"></div>
            <div class="resultado-data" id="contenido-rrhh"></div>
        </div>
        
        <!-- Botón para procesar e importar -->
        <div class="process-section" id="process-section-rrhh" style="display: none; margin-top: 1rem;">
            <button type="button" id="btn-procesar-rrhh" class="btn btn-success btn-large">
                <i class="fas fa-upload"></i> Procesar e Importar a Base de Datos
            </button>
            <div id="proceso-resultado-rrhh" class="mt-3"></div>
        </div>
    </div>
</div>

<hr style="margin: 2rem 0; border: none; border-top: 2px solid #ddd;">

<!-- Sección: Importar Archivo Biométrico -->
<div class="excel-import-container" style="margin-bottom: 2rem;">
    <div class="upload-section" style="max-width: 800px; margin: 0 auto;">
        <h3>
            <i class="fas fa-id-card"></i>
            Importar Archivo Personal Biométrico
        </h3>
        <p class="section-description">
            <strong>Archivo Excel con:</strong> "ID" (sin guiones), "Nombre" (columna B), "Apellido" (columna C)
        </p>
        <p style="color: #666; font-size: 0.9em; margin: 0.5rem 0;">
            <strong>Nota:</strong> Este proceso actualizará solo los campos "nombre" y "apellido" en la tabla funcionarios.
            Se relacionará el "ID" del Excel (sin guiones) con la "cedula" de la BD (puede tener guiones).
            Los registros que no se encuentren se mostrarán en un reporte al final.
        </p>
        
        <div class="drop-zone <?php echo $microservicio_disponible ? '' : 'disabled'; ?>" 
             id="drop-zone-biometrico">
            <input type="file" name="archivo_biometrico" id="archivo_biometrico" 
                   accept=".xls,.xlsx,.csv" class="hidden">
            <div class="drop-zone-content">
                <i class="fas fa-cloud-upload-alt"></i>
                <p class="drop-text">
                    <strong>Arrastra y suelta</strong> el archivo "personal biométrico.xlsx" aquí
                </p>
                <p class="drop-or">o</p>
                <button type="button" onclick="document.getElementById('archivo_biometrico').click()" 
                        class="btn btn-primary"
                        <?php echo $microservicio_disponible ? '' : 'disabled'; ?>>
                    <i class="fas fa-folder-open"></i> Seleccionar Archivo
                </button>
                <p class="drop-info">
                    Formatos: .xls, .xlsx, .csv (máx. 50MB)
                </p>
            </div>
            <div class="file-info" id="info-biometrico">
                <i class="fas fa-file-excel"></i>
                <span id="nombre-biometrico"></span>
            </div>
        </div>
        
        <div id="resultado-biometrico" class="resultado-container hidden">
            <h4>Datos del Archivo:</h4>
            <div class="resultado-stats" id="stats-biometrico"></div>
            <div class="resultado-data" id="contenido-biometrico"></div>
        </div>
        
        <!-- Botón para procesar e importar -->
        <div class="process-section" id="process-section-biometrico" style="display: none; margin-top: 1rem;">
            <button type="button" id="btn-procesar-biometrico" class="btn btn-success btn-large">
                <i class="fas fa-upload"></i> Procesar e Importar Nombres y Apellidos
            </button>
            <div id="proceso-resultado-biometrico" class="mt-3"></div>
        </div>
    </div>
</div>

<script>
const MICROSERVICIO_URL = '<?php echo MICROSERVICIO_URL; ?>';
const BASE_URL = '<?php echo BASE_URL; ?>';
const microservicioDisponible = <?php echo $microservicio_disponible ? 'true' : 'false'; ?>;

// Variables globales para almacenar datos
let datosRRHH = null; // Para el archivo único RRHH
let datosBiometrico = null; // Para el archivo biométrico

// Función para enviar archivo RRHH único al microservicio
async function enviarArchivoRRHH(archivo) {
    if (!microservicioDisponible) {
        alert('El microservicio no está disponible. Por favor, inicia el microservicio Python.');
        return;
    }

    const formData = new FormData();
    formData.append('file', archivo);
    formData.append('header_row', '0');

    const resultadoDiv = document.getElementById('resultado-rrhh');
    const contenidoDiv = document.getElementById('contenido-rrhh');
    const statsDiv = document.getElementById('stats-rrhh');
    
    // Mostrar loading
    resultadoDiv.classList.remove('hidden');
    resultadoDiv.style.display = 'block';
    statsDiv.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Procesando archivo...</div>';
    contenidoDiv.innerHTML = '';

    try {
        const response = await fetch(MICROSERVICIO_URL, {
            method: 'POST',
            body: formData
        });

        // Verificar que la respuesta sea JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const textResponse = await response.text();
            statsDiv.innerHTML = '';
            contenidoDiv.innerHTML = `
                <div class="alert alert-error">
                    <strong>Error:</strong> El microservicio no devolvió JSON.<br>
                    <small>Respuesta: ${textResponse.substring(0, 200)}...</small>
                </div>
            `;
            return;
        }

        // Verificar status HTTP
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success && data.data) {
            // Guardar datos
            datosRRHH = data;
            
            // Mostrar estadísticas
            const totalFilas = data.data ? data.data.length : 0;
            statsDiv.innerHTML = `
                <div class="alert alert-success">
                    <strong>✓ Archivo procesado correctamente</strong><br>
                    <strong>Total de filas:</strong> ${totalFilas}<br>
                    ${data.columns ? `<strong>Columnas detectadas:</strong> ${data.columns.join(', ')}` : ''}
                </div>
            `;

            // Mostrar vista previa de datos (primeras 5 filas)
            if (data.data && data.data.length > 0) {
                let preview = '<div style="max-height: 400px; overflow-y: auto; margin-top: 1rem;"><table class="table" style="width: 100%; font-size: 0.9em;"><thead><tr>';
                
                // Mostrar columnas
                if (data.columns && data.columns.length > 0) {
                    data.columns.forEach(col => {
                        preview += `<th>${col}</th>`;
                    });
                } else if (data.data.length > 0) {
                    // Obtener columnas de la primera fila
                    Object.keys(data.data[0]).forEach(col => {
                        preview += `<th>${col}</th>`;
                    });
                }
                
                preview += '</tr></thead><tbody>';
                
                // Mostrar primeras 5 filas
                data.data.slice(0, 5).forEach(fila => {
                    preview += '<tr>';
                    if (data.columns && data.columns.length > 0) {
                        data.columns.forEach(col => {
                            const valor = fila[col] || '';
                            const strValor = String(valor || '');
                            preview += `<td>${strValor.substring(0, 50)}${strValor.length > 50 ? '...' : ''}</td>`;
                        });
                    } else {
                        Object.values(fila).forEach(valor => {
                            const strValor = String(valor || '');
                            preview += `<td>${strValor.substring(0, 50)}${strValor.length > 50 ? '...' : ''}</td>`;
                        });
                    }
                    preview += '</tr>';
                });
                
                preview += '</tbody></table>';
                
                if (data.data.length > 5) {
                    preview += `<p style="margin-top: 0.5rem; font-size: 0.85em; color: #666;">Mostrando 5 de ${data.data.length} filas</p>`;
                }
                
                preview += '</div>';
                contenidoDiv.innerHTML = preview;
            }

            // Mostrar botón de procesar
            document.getElementById('process-section-rrhh').style.display = 'block';
        } else {
            statsDiv.innerHTML = '';
            contenidoDiv.innerHTML = `
                <div class="alert alert-error">
                    <strong>Error del microservicio:</strong><br>
                    ${data.error || 'Error desconocido'}
                </div>
            `;
            datosRRHH = null;
        }
    } catch (error) {
        statsDiv.innerHTML = '';
        contenidoDiv.innerHTML = `
            <div class="alert alert-error">
                <strong>Error de conexión:</strong> ${error.message}<br>
                <small>Verifica que el microservicio esté corriendo en http://localhost:5000</small>
            </div>
        `;
        console.error('Error al enviar archivo:', error);
        datosRRHH = null;
    }
}

// Función para enviar archivo biométrico al microservicio
async function enviarArchivoBiometrico(archivo) {
    if (!microservicioDisponible) {
        alert('El microservicio no está disponible. Por favor, inicia el microservicio Python.');
        return;
    }

    const formData = new FormData();
    formData.append('file', archivo);
    formData.append('header_row', '1'); // Fila 1 contiene los encabezados (ID, Nombre, Apellido)

    const resultadoDiv = document.getElementById('resultado-biometrico');
    const contenidoDiv = document.getElementById('contenido-biometrico');
    const statsDiv = document.getElementById('stats-biometrico');
    
    // Mostrar loading
    resultadoDiv.classList.remove('hidden');
    resultadoDiv.style.display = 'block';
    statsDiv.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Procesando archivo...</div>';
    contenidoDiv.innerHTML = '';

    try {
        const response = await fetch(MICROSERVICIO_URL, {
            method: 'POST',
            body: formData
        });

        // Verificar que la respuesta sea JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const textResponse = await response.text();
            statsDiv.innerHTML = '';
            contenidoDiv.innerHTML = `
                <div class="alert alert-error">
                    <strong>Error:</strong> El microservicio no devolvió JSON.<br>
                    <small>Respuesta: ${textResponse.substring(0, 200)}...</small>
                </div>
            `;
            return;
        }

        // Verificar status HTTP
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success && data.data) {
            // Guardar datos
            datosBiometrico = data;
            
            // Mostrar estadísticas
            const totalFilas = data.data ? data.data.length : 0;
            statsDiv.innerHTML = `
                <div class="alert alert-success">
                    <strong>✓ Archivo procesado correctamente</strong><br>
                    <strong>Total de filas:</strong> ${totalFilas}<br>
                    ${data.columns ? `<strong>Columnas detectadas:</strong> ${data.columns.join(', ')}` : ''}
                </div>
            `;

            // Mostrar vista previa de datos (primeras 5 filas)
            if (data.data && data.data.length > 0) {
                let preview = '<div style="max-height: 400px; overflow-y: auto; margin-top: 1rem;"><table class="table" style="width: 100%; font-size: 0.9em;"><thead><tr>';
                
                // Mostrar columnas
                if (data.columns && data.columns.length > 0) {
                    data.columns.forEach(col => {
                        preview += `<th>${col}</th>`;
                    });
                } else if (data.data.length > 0) {
                    // Obtener columnas de la primera fila
                    Object.keys(data.data[0]).forEach(col => {
                        preview += `<th>${col}</th>`;
                    });
                }
                
                preview += '</tr></thead><tbody>';
                
                // Mostrar primeras 5 filas
                data.data.slice(0, 5).forEach(fila => {
                    preview += '<tr>';
                    if (data.columns && data.columns.length > 0) {
                        data.columns.forEach(col => {
                            const valor = fila[col] || '';
                            const strValor = String(valor || '');
                            preview += `<td>${strValor.substring(0, 50)}${strValor.length > 50 ? '...' : ''}</td>`;
                        });
                    } else {
                        Object.values(fila).forEach(valor => {
                            const strValor = String(valor || '');
                            preview += `<td>${strValor.substring(0, 50)}${strValor.length > 50 ? '...' : ''}</td>`;
                        });
                    }
                    preview += '</tr>';
                });
                
                preview += '</tbody></table>';
                
                if (data.data.length > 5) {
                    preview += `<p style="margin-top: 0.5rem; font-size: 0.85em; color: #666;">Mostrando 5 de ${data.data.length} filas</p>`;
                }
                
                preview += '</div>';
                contenidoDiv.innerHTML = preview;
            }

            // Mostrar botón de procesar
            document.getElementById('process-section-biometrico').style.display = 'block';
        } else {
            statsDiv.innerHTML = '';
            contenidoDiv.innerHTML = `
                <div class="alert alert-error">
                    <strong>Error del microservicio:</strong><br>
                    ${data.error || 'Error desconocido'}
                </div>
            `;
            datosBiometrico = null;
        }
    } catch (error) {
        statsDiv.innerHTML = '';
        contenidoDiv.innerHTML = `
            <div class="alert alert-error">
                <strong>Error de conexión:</strong> ${error.message}<br>
                <small>Verifica que el microservicio esté corriendo en http://localhost:5000</small>
            </div>
        `;
        console.error('Error al enviar archivo:', error);
        datosBiometrico = null;
    }
}

// Función para procesar archivo biométrico
async function procesarArchivoBiometrico() {
    if (!datosBiometrico) {
        alert('Por favor, carga el archivo primero');
        return;
    }

    const btnProcesar = document.getElementById('btn-procesar-biometrico');
    const resultadoDiv = document.getElementById('proceso-resultado-biometrico');
    
    btnProcesar.disabled = true;
    btnProcesar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    resultadoDiv.innerHTML = '<div class="loading">Procesando e importando nombres y apellidos...</div>';

    try {
        // Enviar datos al servidor para procesar
        const response = await fetch(BASE_URL + '/services/excel/procesar_biometrico.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                excel_data: datosBiometrico
            })
        });

        const resultado = await response.json();

        if (resultado.success) {
            let html = `
                <div class="alert alert-success">
                    <strong>✓ Procesamiento completado:</strong><br>
                    ${resultado.mensaje || 'Archivo procesado correctamente'}
                </div>
            `;
            
            // Mostrar estadísticas detalladas
            if (resultado.estadisticas) {
                const stats = resultado.estadisticas;
                html += `
                    <div class="resultado-stats" style="margin-top: 1rem;">
                        <div class="stat-item"><strong>Total procesados:</strong> ${stats.total_procesados || 0}</div>
                        <div class="stat-item"><strong>✓ Actualizados:</strong> ${stats.actualizados || 0}</div>
                        ${stats.no_encontrados > 0 ? `<div class="stat-item"><strong>⚠ No encontrados:</strong> ${stats.no_encontrados}</div>` : ''}
                        ${stats.errores > 0 ? `<div class="stat-item"><strong>❌ Errores:</strong> ${stats.errores}</div>` : ''}
                    </div>
                `;
            }
            
            // Mostrar reporte de no encontrados
            if (resultado.no_encontrados && resultado.no_encontrados.length > 0) {
                html += `
                    <div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;">
                        <h4 style="margin-top: 0; color: #856404;">
                            <i class="fas fa-exclamation-triangle"></i> Registros No Encontrados en Base de Datos
                        </h4>
                        <p>Los siguientes registros del archivo biométrico no tienen coincidencia en la tabla funcionarios:</p>
                        <table class="table" style="width: 100%; margin-top: 1rem; background: white;">
                            <thead>
                                <tr style="background: #ffc107; color: #000;">
                                    <th style="padding: 8px; text-align: left;">ID</th>
                                    <th style="padding: 8px; text-align: left;">Nombre</th>
                                    <th style="padding: 8px; text-align: left;">Apellido</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                resultado.no_encontrados.forEach(registro => {
                    html += `
                        <tr>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;">${registro.id || '-'}</td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;">${registro.nombre || '-'}</td>
                            <td style="padding: 8px; border-bottom: 1px solid #ddd;">${registro.apellido || '-'}</td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                        <p style="margin-top: 1rem; font-weight: bold; color: #856404;">
                            Total: ${resultado.no_encontrados.length} registro(s) no encontrado(s)
                        </p>
                    </div>
                `;
            }
            
            // Mostrar errores si hay
            if (resultado.errores && resultado.errores.length > 0) {
                html += `
                    <div style="margin-top: 1rem; padding: 1rem; background: #f8d7da; border-radius: 4px; max-height: 300px; overflow-y: auto;">
                        <strong>Errores encontrados (primeros ${resultado.errores.length}):</strong>
                        <ul style="margin-top: 0.5rem; margin-left: 1.5rem; font-size: 0.9em;">
                            ${resultado.errores.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            resultadoDiv.innerHTML = html;
        } else {
            resultadoDiv.innerHTML = `
                <div class="alert alert-error">
                    <strong>Error:</strong> ${resultado.mensaje || resultado.error}
                </div>
            `;
        }
    } catch (error) {
        resultadoDiv.innerHTML = `
            <div class="alert alert-error">
                <strong>Error de conexión:</strong> ${error.message}
            </div>
        `;
    } finally {
        btnProcesar.disabled = false;
        btnProcesar.innerHTML = '<i class="fas fa-upload"></i> Procesar e Importar Nombres y Apellidos';
    }
}

// Función para procesar archivo único RRHH
async function procesarArchivoRRHH() {
    if (!datosRRHH) {
        alert('Por favor, carga el archivo primero');
        return;
    }

    const btnProcesar = document.getElementById('btn-procesar-rrhh');
    const resultadoDiv = document.getElementById('proceso-resultado-rrhh');
    
    btnProcesar.disabled = true;
    btnProcesar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    resultadoDiv.innerHTML = '<div class="loading">Procesando e importando datos...</div>';

    try {
        // Enviar datos al servidor para procesar
        const response = await fetch(BASE_URL + '/services/excel/procesar_rrhh.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                excel_data: datosRRHH
            })
        });

        const resultado = await response.json();

        if (resultado.success) {
            let html = `
                <div class="alert alert-success">
                    <strong>✓ Procesamiento completado:</strong><br>
                    ${resultado.mensaje || 'Archivos procesados correctamente'}
                </div>
            `;
            
            // Mostrar estadísticas detalladas
            if (resultado.estadisticas) {
                const stats = resultado.estadisticas;
                html += `
                    <div class="resultado-stats" style="margin-top: 1rem;">
                        <div class="stat-item"><strong>Total procesados:</strong> ${stats.total_procesados || 0}</div>
                        <div class="stat-item"><strong>✓ Nuevos registros:</strong> ${stats.nuevos || 0}</div>
                        <div class="stat-item"><strong>↻ Actualizados:</strong> ${stats.actualizados || 0}</div>
                        ${stats.errores > 0 ? `<div class="stat-item"><strong>❌ Errores:</strong> ${stats.errores}</div>` : ''}
                    </div>
                `;
            }
            
            // Mostrar errores si hay
            if (resultado.errores && resultado.errores.length > 0) {
                html += `
                    <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: 4px; max-height: 300px; overflow-y: auto;">
                        <strong>Errores encontrados (primeros ${resultado.errores.length}):</strong>
                        <ul style="margin-top: 0.5rem; margin-left: 1.5rem; font-size: 0.9em;">
                            ${resultado.errores.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }
            
            resultadoDiv.innerHTML = html;
        } else {
            resultadoDiv.innerHTML = `
                <div class="alert alert-error">
                    <strong>Error:</strong> ${resultado.mensaje || resultado.error}
                </div>
            `;
        }
    } catch (error) {
        resultadoDiv.innerHTML = `
            <div class="alert alert-error">
                <strong>Error de conexión:</strong> ${error.message}
            </div>
        `;
    } finally {
        btnProcesar.disabled = false;
        btnProcesar.innerHTML = '<i class="fas fa-upload"></i> Procesar e Importar a Base de Datos';
    }
}

// Configurar drag & drop
function configurarDragDrop(dropZoneId, fileInputId, tipo, nombreElemento, infoElemento) {
    const dropZone = document.getElementById(dropZoneId);
    const fileInput = document.getElementById(fileInputId);

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            if (microservicioDisponible) {
                dropZone.classList.add('dragover');
            }
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('dragover');
        });
    });

    dropZone.addEventListener('drop', (e) => {
        if (!microservicioDisponible) return;
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            mostrarInfoArchivo(files[0], nombreElemento, infoElemento);
            if (tipo === 'rrhh') {
                enviarArchivoRRHH(files[0]);
            } else if (tipo === 'biometrico') {
                enviarArchivoBiometrico(files[0]);
            }
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            mostrarInfoArchivo(e.target.files[0], nombreElemento, infoElemento);
            if (tipo === 'rrhh') {
                enviarArchivoRRHH(e.target.files[0]);
            } else if (tipo === 'biometrico') {
                enviarArchivoBiometrico(e.target.files[0]);
            }
        }
    });
}

function mostrarInfoArchivo(archivo, elementoNombre, elementoInfo) {
    if (archivo) {
        elementoNombre.textContent = archivo.name + ' (' + (archivo.size / 1024 / 1024).toFixed(2) + ' MB)';
        elementoInfo.classList.add('show');
    }
}

// Inicializar drag & drop cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Configurar archivo único RRHH
    configurarDragDrop(
        'drop-zone-rrhh',
        'archivo_rrhh',
        'rrhh',
        document.getElementById('nombre-rrhh'),
        document.getElementById('info-rrhh')
    );
    
    // Botón procesar archivo único RRHH
    document.getElementById('btn-procesar-rrhh').addEventListener('click', procesarArchivoRRHH);
    
    // Configurar archivo biométrico
    configurarDragDrop(
        'drop-zone-biometrico',
        'archivo_biometrico',
        'biometrico',
        document.getElementById('nombre-biometrico'),
        document.getElementById('info-biometrico')
    );
    
    // Botón procesar archivo biométrico
    document.getElementById('btn-procesar-biometrico').addEventListener('click', procesarArchivoBiometrico);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
