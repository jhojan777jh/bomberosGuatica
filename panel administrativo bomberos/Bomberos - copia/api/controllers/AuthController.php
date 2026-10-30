<?php
/**
 * Controlador de autenticación
 */

require_once __DIR__ . '/BaseController.php';

class AuthController extends BaseController {
    
    /**
     * Iniciar sesión
     */
    public function login() {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['username', 'password']);
        
        $username = $this->sanitize($data['username']);
        $password = $data['password'];
        
        try {
            $query = "SELECT id, username, password, nombre_completo, rol, activo 
                     FROM usuarios 
                     WHERE username = :username AND activo = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Actualizar último acceso
                $updateQuery = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id";
                $updateStmt = $this->db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();
                
// Crear sesión
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nombre'] = $user['nombre_completo'];
                $_SESSION['rol'] = $user['rol'];
                $_SESSION['login_time'] = time();
                
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Inicio de sesión exitoso',
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'nombre' => $user['nombre_completo'],
                        'rol' => $user['rol']
                    ]
                ]);
            } else {
                $this->sendError('Usuario o contraseña incorrectos', 401);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error en el servidor: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        
        $this->sendResponse([
            'success' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
    
    /**
     * Verificar estado de autenticación
     */
    public function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            $this->sendResponse([
                'authenticated' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username'],
                    'nombre' => $_SESSION['nombre'],
                    'rol' => $_SESSION['rol']
                ]
            ]);
        } else {
            $this->sendResponse([
                'authenticated' => false
            ]);
        }
    }
    
    /**
     * Middleware para verificar autenticación
     */
    public function requireAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            $this->sendError('No autorizado - Se requiere inicio de sesión', 401);
        }
        
        // Verificar que el usuario esté activo
        try {
            $query = "SELECT activo FROM usuarios WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $_SESSION['user_id']);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if (!$user || !$user['activo']) {
                session_destroy();
                $this->sendError('Usuario inactivo o no encontrado', 401);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error de autenticación', 500);
        }
        
        return $_SESSION['user_id'];
    }
    
    /**
     * Middleware para verificar rol de administrador
     */
    public function requireAdmin() {
        $userId = $this->requireAuth();
        
        if ($_SESSION['rol'] !== 'admin') {
            $this->sendError('No autorizado - Se requiere rol de administrador', 403);
        }
        
        return $userId;
    }
    
    /**
     * Middleware para verificar rol específico
     */
    public function requireRole($requiredRole) {
        $userId = $this->requireAuth();
        
        if ($_SESSION['rol'] !== $requiredRole) {
            $this->sendError("No autorizado - Se requiere rol de {$requiredRole}", 403);
        }
        
        return $userId;
    }
    
    /**
     * Middleware para verificar permisos específicos
     */
    public function requirePermission($resource, $action) {
        $userId = $this->requireAuth();
        
        // Admin tiene todos los permisos
        if ($_SESSION['rol'] === 'admin') {
            return $userId;
        }
        
        try {
            $query = "SELECT r.permisos 
                     FROM usuarios u
                     LEFT JOIN roles r ON u.rol = r.nombre
                     WHERE u.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if ($user && $user['permisos']) {
                $permisos = json_decode($user['permisos'], true);
                if (isset($permisos[$resource]) && in_array($action, $permisos[$resource])) {
                    return $userId;
                }
            }
            
            $this->sendError('No autorizado - Permisos insuficientes', 403);
        } catch(PDOException $exception) {
            $this->sendError('Error de verificación de permisos', 500);
        }
    }
    
    /**
     * Obtener información del usuario actual
     */
    public function getCurrentUser() {
        $userId = $this->requireAuth();
        
        try {
            $query = "SELECT u.*, r.nombre as rol_nombre, r.descripcion as rol_descripcion, r.permisos
                     FROM usuarios u
                     LEFT JOIN roles r ON u.rol_id = r.id
                     WHERE u.id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->sendError('Usuario no encontrado', 404);
            }
            
            // Eliminar información sensible
            unset($user['password']);
            
            return $user;
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener información del usuario', 500);
        }
    }
}
?>