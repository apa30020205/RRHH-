<?php
/**
 * Script para verificar y corregir el usuario admin
 * Ejecutar desde: http://localhost/SISTEMA%20%20RRHH/verificar_admin.php
 */

// Configuración simple
$host = 'localhost';
$user = 'root';
$pass = '';  // Cambiar si tu root tiene contraseña
$dbname = 'rrhh';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verificar Admin</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0; }
        .info { background: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>🔐 Verificar y Corregir Usuario Admin</h1>
    
    <?php
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Paso 1: Verificar usuario admin
        echo '<div class="info">';
        echo '<h2>Paso 1: Estado Actual del Usuario Admin</h2>';
        $stmt = $pdo->prepare("SELECT id_usuario, username, nombre_completo, rol, activo, LEFT(password_hash, 30) as hash_preview FROM usuarios WHERE username = 'admin'");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo '<table>';
            echo '<tr><th>Campo</th><th>Valor</th></tr>';
            echo '<tr><td>ID</td><td>' . htmlspecialchars($admin['id_usuario']) . '</td></tr>';
            echo '<tr><td>Username</td><td>' . htmlspecialchars($admin['username']) . '</td></tr>';
            echo '<tr><td>Nombre</td><td>' . htmlspecialchars($admin['nombre_completo']) . '</td></tr>';
            echo '<tr><td>Rol</td><td><strong>' . htmlspecialchars($admin['rol']) . '</strong></td></tr>';
            echo '<tr><td>Activo</td><td>' . ($admin['activo'] ? '✓ Sí' : '✗ No') . '</td></tr>';
            echo '<tr><td>Hash (primeros 30)</td><td><code>' . htmlspecialchars($admin['hash_preview']) . '...</code></td></tr>';
            echo '</table>';
        } else {
            echo '<div class="error">Usuario admin NO encontrado</div>';
            exit;
        }
        echo '</div>';
        
        // Paso 2: Obtener hash completo y probar contraseña
        echo '<div class="info">';
        echo '<h2>Paso 2: Probar Contraseña "admin123"</h2>';
        $stmt2 = $pdo->prepare("SELECT password_hash FROM usuarios WHERE username = 'admin'");
        $stmt2->execute();
        $hashData = $stmt2->fetch(PDO::FETCH_ASSOC);
        $hashActual = $hashData['password_hash'];
        
        $passwordTest = 'admin123';
        $verificacion = password_verify($passwordTest, $hashActual);
        
        if ($verificacion) {
            echo '<div class="success">';
            echo '<strong>✓ La contraseña "admin123" es CORRECTA</strong><br>';
            echo 'El hash funciona correctamente.';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<strong>✗ La contraseña "admin123" NO coincide</strong><br>';
            echo 'El hash no funciona. Vamos a generar uno nuevo.';
            echo '</div>';
            
            // Paso 3: Generar nuevo hash y actualizar
            echo '<div class="info">';
            echo '<h2>Paso 3: Generar Nuevo Hash y Actualizar</h2>';
            
            $nuevoHash = password_hash($passwordTest, PASSWORD_BCRYPT);
            
            $stmtUpdate = $pdo->prepare("
                UPDATE usuarios 
                SET password_hash = ?, 
                    rol = 'administrador',
                    activo = 1,
                    fecha_actualizacion = NOW()
                WHERE username = 'admin'
            ");
            $stmtUpdate->execute([$nuevoHash]);
            
            echo '<div class="success">';
            echo '<strong>✓ Contraseña actualizada</strong><br>';
            echo 'Filas afectadas: ' . $stmtUpdate->rowCount() . '<br>';
            echo '</div>';
            
            // Verificar nuevamente
            $verificacionFinal = password_verify($passwordTest, $nuevoHash);
            if ($verificacionFinal) {
                echo '<div class="success">';
                echo '<strong>✓ Verificación final: La contraseña funciona correctamente</strong>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
        
        // Paso 4: Verificar rol
        echo '<div class="info">';
        echo '<h2>Paso 4: Verificar Rol</h2>';
        $stmt3 = $pdo->prepare("SELECT rol FROM usuarios WHERE username = 'admin'");
        $stmt3->execute();
        $rolData = $stmt3->fetch(PDO::FETCH_ASSOC);
        
        if ($rolData['rol'] === 'administrador') {
            echo '<div class="success">';
            echo '<strong>✓ El rol es "administrador" - CORRECTO</strong>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<strong>✗ El rol es "' . htmlspecialchars($rolData['rol']) . '" - INCORRECTO</strong><br>';
            echo 'Actualizando rol a "administrador"...';
            echo '</div>';
            
            $stmtRol = $pdo->prepare("UPDATE usuarios SET rol = 'administrador' WHERE username = 'admin'");
            $stmtRol->execute();
            
            echo '<div class="success">';
            echo '<strong>✓ Rol actualizado a "administrador"</strong>';
            echo '</div>';
        }
        echo '</div>';
        
        // Paso 5: Instrucciones
        echo '<div class="info">';
        echo '<h2>Paso 5: Instrucciones</h2>';
        echo '<p><strong>1. Cierra sesión completamente:</strong></p>';
        echo '<ul>';
        echo '<li>Ve a: <a href="roles_rrhh/pages/logout.php">Cerrar Sesión</a></li>';
        echo '<li>O cierra todas las pestañas del navegador</li>';
        echo '</ul>';
        echo '<p><strong>2. Inicia sesión nuevamente:</strong></p>';
        echo '<ul>';
        echo '<li>Usuario: <code>admin</code></li>';
        echo '<li>Contraseña: <code>admin123</code></li>';
        echo '</ul>';
        echo '<p><strong>3. URL de Login:</strong></p>';
        echo '<p><a href="roles_rrhh/pages/login.php">Ir al Login</a></p>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="error">';
        echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
        echo '</div>';
    }
    ?>
</body>
</html>

