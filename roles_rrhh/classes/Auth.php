<?php
/**
 * Clase Auth - Manejo de Autenticación
 * Módulo: roles_rrhh
 * Sistema RRHH
 */

class Auth {
    /**
     * Iniciar sesión
     * @param string $username
     * @param string $password
     * @return array ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public static function login($username, $password) {
        require_once __DIR__ . '/../../classes/Database.php';
        
        try {
            $db = Database::getInstance()->getConnection();
            
            // Buscar usuario
            $stmt = $db->prepare("
                SELECT id_usuario, username, password_hash, nombre_completo, email, rol, activo
                FROM usuarios 
                WHERE username = ? AND activo = 1
            ");
            $stmt->execute([$username]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado o inactivo'
                ];
            }
            
            // Verificar contraseña
            if (!password_verify($password, $usuario['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Contraseña incorrecta'
                ];
            }
            
            // Actualizar último acceso
            $stmtUpdate = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = ?");
            $stmtUpdate->execute([$usuario['id_usuario']]);
            
            // Iniciar sesión
            session_start();
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['username'] = $usuario['username'];
            $_SESSION['nombre_completo'] = $usuario['nombre_completo'];
            $_SESSION['rol'] = $usuario['rol'];
            $_SESSION['autenticado'] = true;
            
            return [
                'success' => true,
                'message' => 'Sesión iniciada correctamente',
                'user' => [
                    'id' => $usuario['id_usuario'],
                    'username' => $usuario['username'],
                    'nombre_completo' => $usuario['nombre_completo'],
                    'rol' => $usuario['rol']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al iniciar sesión: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Cerrar sesión
     */
    public static function logout() {
        session_start();
        session_unset();
        session_destroy();
    }
    
    /**
     * Verificar si el usuario está autenticado
     * @return bool
     */
    public static function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['autenticado']) && $_SESSION['autenticado'] === true;
    }
    
    /**
     * Obtener usuario actual
     * @return array|null
     */
    public static function getCurrentUser() {
        if (!self::isAuthenticated()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['usuario_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'nombre_completo' => $_SESSION['nombre_completo'] ?? null,
            'rol' => $_SESSION['rol'] ?? null
        ];
    }
    
    /**
     * Verificar si el usuario es administrador
     * @return bool
     */
    public static function isAdmin() {
        if (!self::isAuthenticated()) {
            return false;
        }
        return isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador';
    }
    
    /**
     * Verificar si el usuario es usuario normal
     * @return bool
     */
    public static function isUsuario() {
        if (!self::isAuthenticated()) {
            return false;
        }
        return isset($_SESSION['rol']) && $_SESSION['rol'] === 'usuario';
    }
    
    /**
     * Requerir autenticación (redirige si no está autenticado)
     */
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            header('Location: ' . BASE_URL . '/roles_rrhh/pages/login.php');
            exit();
        }
    }
    
    /**
     * Requerir rol de administrador (redirige si no es admin)
     */
    public static function requireAdmin() {
        self::requireAuth();
        if (!self::isAdmin()) {
            header('Location: ' . BASE_URL . '/pages/index.php');
            mostrarMensaje('No tienes permisos para realizar esta acción', 'error');
            exit();
        }
    }
}
?>

