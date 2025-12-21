<?php
/**
 * Procesar Reincorporación
 * Sistema RRHH
 */

require_once __DIR__ . '/../../roles_rrhh/middleware/auth_middleware.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../roles_rrhh/classes/Auth.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mostrarMensaje("Método no permitido", 'error');
    redirect(BASE_URL . '/forms/permisos/reincorporacion.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener usuario actual
    $usuarioId = $_SESSION['id_usuario'] ?? null;
    
    // Validar cédula
    if (empty($_POST['cedula'])) {
        throw new Exception("Cédula no proporcionada");
    }
    
    $cedula = trim($_POST['cedula']);
    
    // Verificar que el funcionario existe
    $stmtCheck = $db->prepare("SELECT cedula FROM funcionarios WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);
    $funcionarioBD = $stmtCheck->fetch();
    
    // Si no se encuentra, intentar normalizada
    if (!$funcionarioBD) {
        $cedulaNormalizada = normalizarCedula($cedula);
        $stmtCheck->execute([$cedulaNormalizada]);
        $funcionarioBD = $stmtCheck->fetch();
        if ($funcionarioBD) {
            $cedula = $funcionarioBD['cedula'];
        }
    } else {
        $cedula = $funcionarioBD['cedula'];
    }
    
    if (!$funcionarioBD) {
        throw new Exception("Funcionario no encontrado");
    }
    
    $cedulaParaGuardar = $cedula;
    
    // Validar motivo de ausencia
    if (empty($_POST['motivo_ausencia'])) {
        throw new Exception("El motivo de ausencia es obligatorio");
    }
    
    $motivosValidos = ['Licencia con sueldo', 'Licencia sin sueldo', 'Licencia especial', 'Vacaciones', 'Prestando funciones en otra Institución'];
    $motivoAusencia = trim($_POST['motivo_ausencia']);
    
    if (!in_array($motivoAusencia, $motivosValidos)) {
        throw new Exception("Motivo de ausencia inválido");
    }
    
    // Validar campos requeridos
    if (empty($_POST['puesto'])) {
        throw new Exception("El puesto es obligatorio");
    }
    
    if (empty($_POST['fecha_reincorporacion'])) {
        throw new Exception("La fecha de reincorporación es obligatoria");
    }
    
    $puesto = sanitize($_POST['puesto']);
    $noPosicion = !empty($_POST['no_posicion']) ? (int)$_POST['no_posicion'] : null;
    $unidadAdministrativa = !empty($_POST['unidad_administrativa']) ? sanitize($_POST['unidad_administrativa']) : null;
    $fechaReincorporacion = trim($_POST['fecha_reincorporacion']);
    
    // Validar formato de fecha
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fechaReincorporacion);
    if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fechaReincorporacion) {
        throw new Exception("Fecha de reincorporación inválida");
    }
    
    try {
        // Insertar en la base de datos
        $stmt = $db->prepare("
            INSERT INTO reincorporacion 
            (cedula, motivo_ausencia, puesto, no_posicion, unidad_administrativa, fecha_reincorporacion, usuario_registro, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'activa')
        ");
        
        $stmt->execute([
            $cedulaParaGuardar,
            $motivoAusencia,
            $puesto,
            $noPosicion,
            $unidadAdministrativa,
            $fechaReincorporacion,
            $usuarioId
        ]);
        
        mostrarMensaje("Reincorporación guardada exitosamente", 'success');
        
    } catch (PDOException $e) {
        mostrarMensaje("Error al guardar reincorporación: " . $e->getMessage(), 'error');
    }

} catch (Exception $e) {
    mostrarMensaje("Error: " . $e->getMessage(), 'error');
}

// Redirigir de vuelta al formulario con la cédula
$cedulaParam = isset($_POST['cedula']) ? '?cedula=' . urlencode($_POST['cedula']) : '';
redirect(BASE_URL . '/forms/permisos/reincorporacion.php' . $cedulaParam);
