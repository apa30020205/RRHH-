<?php
/**
 * Crear/Editar Funcionario
 * Módulo de Mantenimiento
 */

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../includes/functions.php';

$modoEdicion = false;
$funcionario = null;
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Si hay búsqueda, intentar encontrar el funcionario
if (!empty($busqueda)) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Buscar por cédula, nombre o apellido
        $stmt = $db->prepare("
            SELECT * FROM funcionarios 
            WHERE cedula LIKE ? OR nombre LIKE ? OR apellido LIKE ?
            LIMIT 1
        ");
        $busquedaLimpia = '%' . $busqueda . '%';
        $stmt->execute([$busquedaLimpia, $busquedaLimpia, $busquedaLimpia]);
        $funcionario = $stmt->fetch();
        
        if ($funcionario) {
            $modoEdicion = true;
        }
    } catch (Exception $e) {
        mostrarMensaje("Error en la búsqueda: " . $e->getMessage(), 'error');
    }
}

// Procesar formulario de creación/edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        $cedula = trim($_POST['cedula']);
        
        if (empty($cedula)) {
            throw new Exception("La cédula es obligatoria");
        }
        
        if (!validarCedula($cedula)) {
            throw new Exception("Cédula inválida. Formato aceptado: numérica (8-1234-5678) o alfanumérica (PE-123456-7).");
        }
        
        $cedulaNormalizada = normalizarCedula($cedula);
        $edad = !empty($_POST['fecha_nacimiento']) 
            ? calcularEdad($_POST['fecha_nacimiento']) 
            : intval($_POST['edad']);
        
        if (isset($_POST['modo']) && $_POST['modo'] === 'editar') {
            // Modo edición
            $cedulaOriginal = sanitize($_POST['cedula_original']);
            
            $stmt = $db->prepare("
                UPDATE funcionarios SET
                    nombre = ?, apellido = ?, fecha_nacimiento = ?, edad = ?,
                    sangre = ?, no_posicion = ?, posicion_funcional = ?,
                    fecha_inicio = ?, sede_provincia = ?, Direccion = ?
                WHERE cedula = ?
            ");
            
            $nombreActualizado = sanitize($_POST['nombre']);
            $apellidoActualizado = sanitize($_POST['apellido']);
            
            $stmt->execute([
                $nombreActualizado,
                $apellidoActualizado,
                $_POST['fecha_nacimiento'] ?: null,
                $edad ?: null,
                sanitize($_POST['sangre']),
                !empty($_POST['no_posicion']) ? intval($_POST['no_posicion']) : null,
                sanitize($_POST['posicion_funcional']),
                $_POST['fecha_inicio'] ?: null,
                sanitize($_POST['sede_provincia']),
                sanitize($_POST['Direccion']),
                $cedulaOriginal
            ]);
            
            // Verificar si se actualizó nombre o apellido y eliminar error si existe
            // Solo eliminar si ambos (nombre Y apellido) están presentes
            if (!empty($nombreActualizado) && !empty($apellidoActualizado)) {
                // Buscar y eliminar registro en errores_importacion_funcionarios si existe
                // Buscar con ambas versiones de la cédula (con y sin guiones) para asegurar que se encuentre
                $cedulaNormalizadaBusqueda = normalizarCedula($cedulaOriginal);
                
                // Intentar eliminar con la cédula original (con guiones si los tiene)
                $stmtError = $db->prepare("DELETE FROM errores_importacion_funcionarios WHERE cedula = ? OR cedula = ?");
                $stmtError->execute([$cedulaOriginal, $cedulaNormalizadaBusqueda]);
                
                if ($stmtError->rowCount() > 0) {
                    mostrarMensaje("Funcionario actualizado exitosamente. Se eliminó el registro de errores porque ahora tiene nombre y apellido.", 'success');
                } else {
                    mostrarMensaje("Funcionario actualizado exitosamente", 'success');
                }
            } else {
                mostrarMensaje("Funcionario actualizado exitosamente", 'success');
            }
        } else {
            // Modo creación
            // Verificar si la cédula ya existe
            $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
            $stmtCheck->execute([$cedulaNormalizada]);
            if ($stmtCheck->fetch()) {
                throw new Exception("La cédula ya está registrada en el sistema");
            }
            
            $stmt = $db->prepare("
                INSERT INTO funcionarios 
                (cedula, nombre, apellido, fecha_nacimiento, edad, sangre, no_posicion, 
                 posicion_funcional, fecha_inicio, sede_provincia, Direccion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $nombreNuevo = sanitize($_POST['nombre']);
            $apellidoNuevo = sanitize($_POST['apellido']);
            
            $stmt->execute([
                $cedulaNormalizada,
                $nombreNuevo,
                $apellidoNuevo,
                $_POST['fecha_nacimiento'] ?: null,
                $edad ?: null,
                sanitize($_POST['sangre']),
                !empty($_POST['no_posicion']) ? intval($_POST['no_posicion']) : null,
                sanitize($_POST['posicion_funcional']),
                $_POST['fecha_inicio'] ?: null,
                sanitize($_POST['sede_provincia']),
                sanitize($_POST['Direccion'])
            ]);
            
            // Verificar si se creó con nombre y apellido y eliminar error si existe
            // Solo eliminar si ambos (nombre Y apellido) están presentes
            if (!empty($nombreNuevo) && !empty($apellidoNuevo)) {
                // Buscar y eliminar registro en errores_importacion_funcionarios si existe
                // Buscar con ambas versiones de la cédula (con y sin guiones) para asegurar que se encuentre
                // La cédula normalizada ya está sin guiones, pero también buscar con formato original si tiene guiones
                $cedulaOriginalBusqueda = trim($_POST['cedula']); // Cédula con formato original (puede tener guiones)
                
                // Intentar eliminar con ambas versiones
                $stmtError = $db->prepare("DELETE FROM errores_importacion_funcionarios WHERE cedula = ? OR cedula = ?");
                $stmtError->execute([$cedulaNormalizada, $cedulaOriginalBusqueda]);
                
                if ($stmtError->rowCount() > 0) {
                    mostrarMensaje("Funcionario creado exitosamente. Se eliminó el registro de errores porque ahora tiene nombre y apellido.", 'success');
                } else {
                    mostrarMensaje("Funcionario creado exitosamente", 'success');
                }
            } else {
                mostrarMensaje("Funcionario creado exitosamente", 'success');
            }
        }
        
        // Limpiar búsqueda y recargar
        redirect(BASE_URL . '/pages/mantenimiento/index.php#crear-editar');
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            mostrarMensaje("La cédula o número de posición ya existe", 'error');
        } else {
            mostrarMensaje("Error: " . $e->getMessage(), 'error');
        }
    } catch (Exception $e) {
        mostrarMensaje($e->getMessage(), 'error');
    }
}
?>

<div class="page-content">
    <!-- Búsqueda -->
    <div class="search-section" style="margin-bottom: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px;">
        <h3>Buscar Funcionario para Editar</h3>
        <form method="GET" action="" style="display: flex; gap: 1rem; align-items: flex-end;">
            <input type="hidden" name="seccion" value="crear-editar">
            <div style="flex: 1;">
                <label for="buscar">Buscar por Cédula, Nombre o Apellido:</label>
                <input type="text" 
                       id="buscar" 
                       name="buscar" 
                       value="<?php echo htmlspecialchars($busqueda); ?>" 
                       placeholder="Ej: 8-1234-5678 o Juan Pérez"
                       style="width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
            <?php if (!empty($busqueda)): ?>
            <a href="<?php echo BASE_URL; ?>/pages/mantenimiento/index.php#crear-editar" class="btn">
                Limpiar
            </a>
            <?php endif; ?>
        </form>
        
        <?php if (!empty($busqueda) && !$funcionario): ?>
        <div class="alert alert-info" style="margin-top: 1rem;">
            No se encontró ningún funcionario con "<?php echo htmlspecialchars($busqueda); ?>".
            Puedes crear uno nuevo usando el formulario de abajo.
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Formulario de Crear/Editar -->
    <div class="form-section">
        <h3><?php echo $modoEdicion ? 'Editar Funcionario' : 'Crear Nuevo Funcionario'; ?></h3>
        
        <?php if ($modoEdicion && $funcionario): ?>
        <div class="alert alert-success" style="margin-bottom: 1rem;">
            <strong>Funcionario encontrado:</strong> <?php echo htmlspecialchars($funcionario['nombre'] ?? ''); ?> <?php echo htmlspecialchars($funcionario['apellido'] ?? ''); ?>
            (<?php echo htmlspecialchars($funcionario['cedula']); ?>)
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" data-validate style="max-width: 800px;">
            <?php if ($modoEdicion): ?>
            <input type="hidden" name="modo" value="editar">
            <input type="hidden" name="cedula_original" value="<?php echo htmlspecialchars($funcionario['cedula']); ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="cedula">Cédula *</label>
                <input type="text" 
                       id="cedula" 
                       name="cedula" 
                       required 
                       maxlength="25" 
                       value="<?php echo $modoEdicion ? htmlspecialchars($funcionario['cedula']) : ''; ?>"
                       placeholder="8-1234-5678 o PE-123456-7" 
                       autocomplete="off"
                       <?php echo $modoEdicion ? 'readonly' : ''; ?>>
                <small>Formato: numérica (8-1234-5678) o alfanumérica (PE-123456-7)</small>
            </div>
            
            <div class="form-group">
                <label for="nombre">Nombre *</label>
                <input type="text" 
                       id="nombre" 
                       name="nombre" 
                       required 
                       maxlength="40"
                       value="<?php echo $modoEdicion ? htmlspecialchars($funcionario['nombre'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="apellido">Apellido *</label>
                <input type="text" 
                       id="apellido" 
                       name="apellido" 
                       required 
                       maxlength="50"
                       value="<?php echo $modoEdicion ? htmlspecialchars($funcionario['apellido'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                <input type="date" 
                       id="fecha_nacimiento" 
                       name="fecha_nacimiento"
                       value="<?php echo $modoEdicion && $funcionario['fecha_nacimiento'] ? $funcionario['fecha_nacimiento'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="edad">Edad</label>
                <input type="number" 
                       id="edad" 
                       name="edad" 
                       min="18" 
                       max="100" 
                       readonly
                       value="<?php echo $modoEdicion ? ($funcionario['edad'] ?? '') : ''; ?>">
                <small>Se calcula automáticamente</small>
            </div>
            
            <div class="form-group">
                <label for="sangre">Tipo de Sangre</label>
                <input type="text" 
                       id="sangre" 
                       name="sangre" 
                       maxlength="5" 
                       placeholder="O+, A-, etc"
                       value="<?php echo $modoEdicion ? htmlspecialchars($funcionario['sangre'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="no_posicion">Número de Posición</label>
                <input type="number" 
                       id="no_posicion" 
                       name="no_posicion"
                       value="<?php echo $modoEdicion ? ($funcionario['no_posicion'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="posicion_funcional">Posición Funcional</label>
                <input type="text" 
                       id="posicion_funcional" 
                       name="posicion_funcional" 
                       maxlength="100"
                       value="<?php echo $modoEdicion ? htmlspecialchars($funcionario['posicion_funcional'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="fecha_inicio">Fecha de Inicio</label>
                <input type="date" 
                       id="fecha_inicio" 
                       name="fecha_inicio"
                       value="<?php echo $modoEdicion && $funcionario['fecha_inicio'] ? $funcionario['fecha_inicio'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="sede_provincia">Sede/Provincia</label>
                <input type="text" 
                       id="sede_provincia" 
                       name="sede_provincia" 
                       maxlength="20"
                       value="<?php echo $modoEdicion ? htmlspecialchars($funcionario['sede_provincia'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="Direccion">Dirección</label>
                <input type="text" 
                       id="Direccion" 
                       name="Direccion" 
                       maxlength="100"
                       value="<?php echo $modoEdicion ? htmlspecialchars($funcionario['Direccion'] ?? '') : ''; ?>">
            </div>
            
            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> <?php echo $modoEdicion ? 'Actualizar' : 'Crear'; ?> Funcionario
                </button>
                <a href="<?php echo BASE_URL; ?>/pages/mantenimiento/index.php#crear-editar" class="btn">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Calcular edad automáticamente cuando cambia la fecha de nacimiento
document.addEventListener('DOMContentLoaded', function() {
    const fechaNacimiento = document.getElementById('fecha_nacimiento');
    const edad = document.getElementById('edad');
    
    if (fechaNacimiento && edad) {
        fechaNacimiento.addEventListener('change', function() {
            if (this.value) {
                const fechaNac = new Date(this.value);
                const hoy = new Date();
                let edadCalculada = hoy.getFullYear() - fechaNac.getFullYear();
                const mes = hoy.getMonth() - fechaNac.getMonth();
                if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) {
                    edadCalculada--;
                }
                edad.value = edadCalculada;
            } else {
                edad.value = '';
            }
        });
    }
});
</script>

