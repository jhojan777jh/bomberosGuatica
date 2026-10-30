<?php
/**
 * Controlador de gestión de usuarios y roles del sistema
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/AuthController.php';

class UserController extends BaseController {
    private $authController;
    
    public function __construct() {
        parent::__construct();
        $this->authController = new AuthController();
    }
    
    /**
     * Obtener todos los usuarios
     */
    public function getUsers() {
        $this->authController->requireAuth();
        
        try {
            $query = "SELECT u.*, r.nombre as rol_nombre, r.descripcion as rol_descripcion,
                            uc.nombre_completo as creado_por_nombre
                     FROM usuarios u
                     LEFT JOIN roles r ON u.rol_id = r.id
                     LEFT JOIN usuarios uc ON u.creado_por = uc.id
                     ORDER BY u.fecha_creacion DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            // Eliminar información sensible
            $users = array_map(function($user) {
                unset($user['password']);
                return $user;
            }, $users);
            
            $this->sendResponse([
                'success' => true,
                'users' => $users
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener usuarios: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Crear nuevo usuario
     */
    public function createUser() {
        $this->authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['username', 'password', 'email', 'nombre_completo', 'rol']);
        
        $username = $this->sanitize($data['username']);
        $password = $data['password'];
        $email = $this->sanitize($data['email']);
        $nombreCompleto = $this->sanitize($data['nombre_completo']);
        $rol = $this->sanitize($data['rol']);
        $rolId = isset($data['rol_id']) ? (int)$data['rol_id'] : null;
        $activo = isset($data['activo']) ? (bool)$data['activo'] : true;
        
        // Validaciones
        if (strlen($username) < 3) {
            $this->sendError('El nombre de usuario debe tener al menos 3 caracteres');
        }
        
        if (strlen($password) < 6) {
            $this->sendError('La contraseña debe tener al menos 6 caracteres');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendError('El correo electrónico no es válido');
        }
        
        $validRoles = ['admin', 'editor', 'viewer', 'instructor'];
        if (!in_array($rol, $validRoles)) {
            $this->sendError('Rol inválido');
        }
        
        // Verificar si ya existe el username o email
        try {
            $checkQuery = "SELECT id FROM usuarios WHERE username = :username OR email = :email";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':username', $username);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                $this->sendError('El nombre de usuario o correo electrónico ya está en uso');
            }
            
            // Si se especificó rol_id, verificar que exista
            if ($rolId) {
                $roleCheckQuery = "SELECT id FROM roles WHERE id = :rol_id AND activo = 1";
                $roleCheckStmt = $this->db->prepare($roleCheckQuery);
                $roleCheckStmt->bindParam(':rol_id', $rolId);
                $roleCheckStmt->execute();
                
                if (!$roleCheckStmt->fetch()) {
                    $this->sendError('El rol especificado no existe o no está activo');
                }
            }
            
            // Crear usuario
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $currentUserId = $this->authController->requireAuth();
            
            $query = "INSERT INTO usuarios 
                     (username, password, email, nombre_completo, rol, rol_id, activo, creado_por) 
                     VALUES (:username, :password, :email, :nombre_completo, :rol, :rol_id, :activo, :creado_por)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':nombre_completo', $nombreCompleto);
            $stmt->bindParam(':rol', $rol);
            $stmt->bindParam(':rol_id', $rolId);
            $stmt->bindParam(':activo', $activo);
            $stmt->bindParam(':creado_por', $currentUserId);
            
            if ($stmt->execute()) {
                $userId = $this->db->lastInsertId();
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Usuario creado correctamente',
                    'userId' => $userId
                ]);
            } else {
                $this->sendError('No se pudo crear el usuario', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al crear usuario: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar usuario
     */
    public function updateUser($userId) {
        $this->authController->requireAdmin();
        
        if (!is_numeric($userId)) {
            $this->sendError('ID de usuario inválido');
        }
        
        $userId = (int)$userId;
        $data = $this->getRequestData();
        
        // Verificar que el usuario exista
        try {
            $checkQuery = "SELECT id FROM usuarios WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $userId);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                $this->sendError('Usuario no encontrado', 404);
            }
            
            // Construir consulta de actualización dinámica
            $updateFields = [];
            $params = [];
            
            if (isset($data['username'])) {
                $username = $this->sanitize($data['username']);
                if (strlen($username) < 3) {
                    $this->sendError('El nombre de usuario debe tener al menos 3 caracteres');
                }
                $updateFields[] = "username = :username";
                $params[':username'] = $username;
            }
            
            if (isset($data['email'])) {
                $email = $this->sanitize($data['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->sendError('El correo electrónico no es válido');
                }
                $updateFields[] = "email = :email";
                $params[':email'] = $email;
            }
            
            if (isset($data['nombre_completo'])) {
                $updateFields[] = "nombre_completo = :nombre_completo";
                $params[':nombre_completo'] = $this->sanitize($data['nombre_completo']);
            }
            
            if (isset($data['rol'])) {
                $validRoles = ['admin', 'editor', 'viewer', 'instructor'];
                if (!in_array($data['rol'], $validRoles)) {
                    $this->sendError('Rol inválido');
                }
                $updateFields[] = "rol = :rol";
                $params[':rol'] = $this->sanitize($data['rol']);
            }
            
            if (isset($data['rol_id'])) {
                $rolId = (int)$data['rol_id'];
                if ($rolId > 0) {
                    $roleCheckQuery = "SELECT id FROM roles WHERE id = :rol_id AND activo = 1";
                    $roleCheckStmt = $this->db->prepare($roleCheckQuery);
                    $roleCheckStmt->bindParam(':rol_id', $rolId);
                    $roleCheckStmt->execute();
                    
                    if (!$roleCheckStmt->fetch()) {
                        $this->sendError('El rol especificado no existe o no está activo');
                    }
                }
                $updateFields[] = "rol_id = :rol_id";
                $params[':rol_id'] = $rolId > 0 ? $rolId : null;
            }
            
            if (isset($data['activo'])) {
                $updateFields[] = "activo = :activo";
                $params[':activo'] = (bool)$data['activo'];
            }
            
            if (isset($data['password']) && !empty($data['password'])) {
                if (strlen($data['password']) < 6) {
                    $this->sendError('La contraseña debe tener al menos 6 caracteres');
                }
                $updateFields[] = "password = :password";
                $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                $this->sendError('No hay campos para actualizar');
            }
            
            $params[':id'] = $userId;
            
            $query = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Usuario actualizado correctamente'
                ]);
            } else {
                $this->sendError('No se pudo actualizar el usuario', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al actualizar usuario: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Eliminar usuario
     */
    public function deleteUser($userId) {
        $this->authController->requireAdmin();
        
        if (!is_numeric($userId)) {
            $this->sendError('ID de usuario inválido');
        }
        
        $userId = (int)$userId;
        $currentUserId = $this->authController->requireAuth();
        
        // No permitir eliminarse a sí mismo
        if ($userId === $currentUserId) {
            $this->sendError('No puedes eliminar tu propio usuario');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Verificar que el usuario exista
            $checkQuery = "SELECT id FROM usuarios WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $userId);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                $this->db->rollBack();
                $this->sendError('Usuario no encontrado', 404);
            }
            
            // Eliminar asignaciones de capacitaciones
            $deleteAssignmentsQuery = "DELETE FROM capacitaciones_personal_asignado WHERE id_usuario = :id";
            $deleteAssignmentsStmt = $this->db->prepare($deleteAssignmentsQuery);
            $deleteAssignmentsStmt->bindParam(':id', $userId);
            $deleteAssignmentsStmt->execute();
            
            // Eliminar usuario
            $deleteQuery = "DELETE FROM usuarios WHERE id = :id";
            $deleteStmt = $this->db->prepare($deleteQuery);
            $deleteStmt->bindParam(':id', $userId);
            
            if ($deleteStmt->execute()) {
                $this->db->commit();
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Usuario eliminado correctamente'
                ]);
            } else {
                $this->db->rollBack();
                $this->sendError('No se pudo eliminar el usuario', 500);
            }
        } catch(PDOException $exception) {
            $this->db->rollBack();
            $this->sendError('Error al eliminar usuario: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener todos los roles
     */
    public function getRoles() {
        $this->authController->requireAuth();
        
        try {
            $query = "SELECT * FROM roles WHERE activo = 1 ORDER BY nombre ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $roles = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'roles' => $roles
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener roles: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Crear nuevo rol
     */
    public function createRole() {
        $this->authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['nombre', 'descripcion']);
        
        $nombre = $this->sanitize($data['nombre']);
        $descripcion = $this->sanitize($data['descripcion']);
        $permisos = isset($data['permisos']) ? json_encode($data['permisos']) : null;
        
        // Validar nombre
        if (strlen($nombre) < 3) {
            $this->sendError('El nombre del rol debe tener al menos 3 caracteres');
        }
        
        try {
            // Verificar si ya existe el rol
            $checkQuery = "SELECT id FROM roles WHERE nombre = :nombre";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':nombre', $nombre);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                $this->sendError('El nombre del rol ya está en uso');
            }
            
            $query = "INSERT INTO roles (nombre, descripcion, permisos) VALUES (:nombre, :descripcion, :permisos)";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':nombre', $nombre);
            $stmt->bindParam(':descripcion', $descripcion);
            $stmt->bindParam(':permisos', $permisos);
            
            if ($stmt->execute()) {
                $roleId = $this->db->lastInsertId();
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Rol creado correctamente',
                    'roleId' => $roleId
                ]);
            } else {
                $this->sendError('No se pudo crear el rol', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al crear rol: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar rol
     */
    public function updateRole($roleId) {
        $this->authController->requireAdmin();
        
        if (!is_numeric($roleId)) {
            $this->sendError('ID de rol inválido');
        }
        
        $roleId = (int)$roleId;
        $data = $this->getRequestData();
        
        try {
            // Verificar que el rol exista
            $checkQuery = "SELECT id FROM roles WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $roleId);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                $this->sendError('Rol no encontrado', 404);
            }
            
            $updateFields = [];
            $params = [];
            
            if (isset($data['nombre'])) {
                $nombre = $this->sanitize($data['nombre']);
                if (strlen($nombre) < 3) {
                    $this->sendError('El nombre del rol debe tener al menos 3 caracteres');
                }
                $updateFields[] = "nombre = :nombre";
                $params[':nombre'] = $nombre;
            }
            
            if (isset($data['descripcion'])) {
                $updateFields[] = "descripcion = :descripcion";
                $params[':descripcion'] = $this->sanitize($data['descripcion']);
            }
            
            if (isset($data['permisos'])) {
                $updateFields[] = "permisos = :permisos";
                $params[':permisos'] = json_encode($data['permisos']);
            }
            
            if (isset($data['activo'])) {
                $updateFields[] = "activo = :activo";
                $params[':activo'] = (bool)$data['activo'];
            }
            
            if (empty($updateFields)) {
                $this->sendError('No hay campos para actualizar');
            }
            
            $params[':id'] = $roleId;
            
            $query = "UPDATE roles SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Rol actualizado correctamente'
                ]);
            } else {
                $this->sendError('No se pudo actualizar el rol', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al actualizar rol: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Eliminar rol
     */
    public function deleteRole($roleId) {
        $this->authController->requireAdmin();
        
        if (!is_numeric($roleId)) {
            $this->sendError('ID de rol inválido');
        }
        
        $roleId = (int)$roleId;
        
        try {
            // Verificar que no haya usuarios con este rol
            $checkQuery = "SELECT COUNT(*) as count FROM usuarios WHERE rol_id = :rol_id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':rol_id', $roleId);
            $checkStmt->execute();
            $result = $checkStmt->fetch();
            
            if ($result['count'] > 0) {
                $this->sendError('No se puede eliminar un rol que está asignado a usuarios');
            }
            
            // Eliminar rol
            $deleteQuery = "DELETE FROM roles WHERE id = :id";
            $deleteStmt = $this->db->prepare($deleteQuery);
            $deleteStmt->bindParam(':id', $roleId);
            
            if ($deleteStmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Rol eliminado correctamente'
                ]);
            } else {
                $this->sendError('No se pudo eliminar el rol', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al eliminar rol: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener perfil del usuario actual
     */
    public function getProfile() {
        $userId = $this->authController->requireAuth();
        
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
            
            $this->sendResponse([
                'success' => true,
                'user' => $user
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener perfil: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar perfil del usuario actual
     */
    public function updateProfile() {
        $userId = $this->authController->requireAuth();
        
        $data = $this->getRequestData();
        
        try {
            $updateFields = [];
            $params = [];
            
            if (isset($data['nombre_completo'])) {
                $updateFields[] = "nombre_completo = :nombre_completo";
                $params[':nombre_completo'] = $this->sanitize($data['nombre_completo']);
            }
            
            if (isset($data['email'])) {
                $email = $this->sanitize($data['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->sendError('El correo electrónico no es válido');
                }
                $updateFields[] = "email = :email";
                $params[':email'] = $email;
            }
            
            if (isset($data['password_actual']) && isset($data['password_nueva'])) {
                // Verificar contraseña actual
                $passwordQuery = "SELECT password FROM usuarios WHERE id = :id";
                $passwordStmt = $this->db->prepare($passwordQuery);
                $passwordStmt->bindParam(':id', $userId);
                $passwordStmt->execute();
                $user = $passwordStmt->fetch();
                
                if (!password_verify($data['password_actual'], $user['password'])) {
                    $this->sendError('La contraseña actual es incorrecta');
                }
                
                if (strlen($data['password_nueva']) < 6) {
                    $this->sendError('La nueva contraseña debe tener al menos 6 caracteres');
                }
                
                $updateFields[] = "password = :password";
                $params[':password'] = password_hash($data['password_nueva'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                $this->sendError('No hay campos para actualizar');
            }
            
            $params[':id'] = $userId;
            
            $query = "UPDATE usuarios SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Perfil actualizado correctamente'
                ]);
            } else {
                $this->sendError('No se pudo actualizar el perfil', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al actualizar perfil: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Verificar permisos del usuario
     */
    public function checkPermission($resource, $action) {
        $userId = $this->authController->requireAuth();
        
        try {
            $query = "SELECT u.rol, r.permisos 
                     FROM usuarios u
                     LEFT JOIN roles r ON u.rol_id = r.id
                     WHERE u.id = :id AND u.activo = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->sendError('Usuario no encontrado o inactivo', 404);
            }
            
            // Admin tiene todos los permisos
            if ($user['rol'] === 'admin') {
                $this->sendResponse([
                    'success' => true,
                    'hasPermission' => true
                ]);
            }
            
            // Verificar permisos en el rol
            if ($user['permisos']) {
                $permisos = json_decode($user['permisos'], true);
                if (isset($permisos[$resource]) && in_array($action, $permisos[$resource])) {
                    $this->sendResponse([
                        'success' => true,
                        'hasPermission' => true
                    ]);
                }
            }
            
            $this->sendResponse([
                'success' => true,
                'hasPermission' => false
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al verificar permisos: ' . $exception->getMessage(), 500);
        }
    }
}