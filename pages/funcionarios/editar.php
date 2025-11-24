<?php
/**
 * Editar Funcionario
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

$pageTitle = 'Editar Funcionario - Sistema RRHH';

if (!isset($_GET['cedula'])) {
    mostrarMensaje("Cédula no proporcionada", 'error');
    redirect(BASE_URL . '/pages/funcionarios/listar.php');
}

$cedula = sanitize($_GET['cedula']);
// Normalizar cédula para búsqueda (puede venir con guiones en la URL)
$cedulaNormalizada = normalizarCedula($cedula);

try {
    $db = Database::getInstance()->getConnection();
    
    // Cargar datos actuales
    $stmt = $db->prepare("SELECT * FROM funcionarios WHERE cedula = ?");
    $stmt->execute([$cedulaNormalizada]);
    $funcionario = $stmt->fetch();
    
    if (!$funcionario) {
        mostrarMensaje("Funcionario no encontrado", 'error');
        redirect(BASE_URL . '/pages/funcionarios/listar.php');
    }
    
    // Procesar actualización
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $edad = !empty($_POST['fecha_nacimiento']) 
            ? calcularEdad($_POST['fecha_nacimiento']) 
            : intval($_POST['edad']);
        
        $stmt = $db->prepare("
            UPDATE funcionarios SET
                nombre = ?, apellido = ?, fecha_nacimiento = ?, edad = ?,
                sangre = ?, no_posicion = ?, posicion_funcional = ?,
                fecha_inicio = ?, sede_provincia = ?, Direccion = ?
            WHERE cedula = ?
        ");
        
        $stmt->execute([
            sanitize($_POST['nombre']),
            sanitize($_POST['apellido']),
            $_POST['fecha_nacimiento'],
            $edad,
            sanitize($_POST['sangre']),
            intval($_POST['no_posicion']),
            sanitize($_POST['posicion_funcional']),
            $_POST['fecha_inicio'],
            sanitize($_POST['sede_provincia']),
            sanitize($_POST['Direccion']),
            $cedulaNormalizada
        ]);
        
        mostrarMensaje("Funcionario actualizado exitosamente", 'success');
        redirect(BASE_URL . '/pages/funcionarios/ver.php?cedula=' . urlencode($cedulaNormalizada));
    }
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        mostrarMensaje("El número de posición ya existe", 'error');
    } else {
        mostrarMensaje("Error al actualizar funcionario: " . $e->getMessage(), 'error');
    }
} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h2>Editar Funcionario</h2>
    <a href="<?php echo BASE_URL; ?>/pages/funcionarios/ver.php?cedula=<?php echo urlencode($funcionario['cedula']); ?>" class="btn">Volver</a>
</div>

<form method="POST" action="" data-validate>
    <div class="form-group">
        <label for="cedula">Cédula</label>
        <input type="text" id="cedula" value="<?php echo htmlspecialchars(formatearCedula($funcionario['cedula'])); ?>" disabled>
        <small>La cédula no se puede modificar</small>
    </div>
    
    <div class="form-group">
        <label for="nombre">Nombre *</label>
        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($funcionario['nombre']); ?>" required maxlength="40">
    </div>
    
    <div class="form-group">
        <label for="apellido">Apellido *</label>
        <input type="text" id="apellido" name="apellido" value="<?php echo htmlspecialchars($funcionario['apellido']); ?>" required maxlength="50">
    </div>
    
    <div class="form-group">
        <label for="fecha_nacimiento">Fecha de Nacimiento *</label>
        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" value="<?php echo $funcionario['fecha_nacimiento']; ?>" required>
    </div>
    
    <div class="form-group">
        <label for="edad">Edad</label>
        <input type="number" id="edad" name="edad" value="<?php echo $funcionario['edad']; ?>" min="18" max="100" readonly>
        <small>Se calcula automáticamente</small>
    </div>
    
    <div class="form-group">
        <label for="sangre">Tipo de Sangre *</label>
        <input type="text" id="sangre" name="sangre" value="<?php echo htmlspecialchars($funcionario['sangre']); ?>" required maxlength="5">
    </div>
    
    <div class="form-group">
        <label for="no_posicion">Número de Posición *</label>
        <input type="number" id="no_posicion" name="no_posicion" value="<?php echo $funcionario['no_posicion']; ?>" required>
    </div>
    
    <div class="form-group">
        <label for="posicion_funcional">Posición Funcional *</label>
        <input type="text" id="posicion_funcional" name="posicion_funcional" value="<?php echo htmlspecialchars($funcionario['posicion_funcional']); ?>" required maxlength="45">
    </div>
    
    <div class="form-group">
        <label for="fecha_inicio">Fecha de Inicio *</label>
        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?php echo $funcionario['fecha_inicio']; ?>" required>
    </div>
    
    <div class="form-group">
        <label for="sede_provincia">Sede/Provincia *</label>
        <input type="text" id="sede_provincia" name="sede_provincia" value="<?php echo htmlspecialchars($funcionario['sede_provincia']); ?>" required maxlength="20">
    </div>
    
    <div class="form-group">
        <label for="Direccion">Dirección *</label>
        <input type="text" id="Direccion" name="Direccion" value="<?php echo htmlspecialchars($funcionario['Direccion']); ?>" required maxlength="100">
    </div>
    
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="<?php echo BASE_URL; ?>/pages/funcionarios/ver.php?cedula=<?php echo urlencode($funcionario['cedula']); ?>" class="btn">Cancelar</a>
    </div>
</form>

<script>
// Calcular edad automáticamente
document.getElementById('fecha_nacimiento').addEventListener('change', function() {
    const fechaNac = new Date(this.value);
    const hoy = new Date();
    const edad = hoy.getFullYear() - fechaNac.getFullYear();
    const mes = hoy.getMonth() - fechaNac.getMonth();
    const edadCalculada = (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) ? edad - 1 : edad;
    document.getElementById('edad').value = edadCalculada;
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

