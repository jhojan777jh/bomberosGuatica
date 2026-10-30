<?php
/**
 * Controlador de gestión de tareas
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/AuthController.php';

class TaskController extends BaseController {
    private $authController;
    
    public function __construct() {
        parent::__construct();
        $this->authController = new AuthController();
    }
    
    /**
     * Obtener todas las tareas
     */
    public function getTasks() {
        $this->authController->requireAuth();
        
        try {
            $query = "SELECT t.*, u.nombre_completo as asignado_nombre, c.nombre_completo as creador_nombre
                     FROM tareas t
                     LEFT JOIN usuarios u ON t.asignado_a = u.id
                     LEFT JOIN usuarios c ON t.creado_por = c.id
                     ORDER BY t.fecha_creacion DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $tasks = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'tasks' => $tasks
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener tareas: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Crear nueva tarea
     */
    public function createTask() {
        $userId = $this->authController->requireAuth();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['titulo', 'descripcion', 'prioridad', 'fecha_limite']);
        
        $title = $this->sanitize($data['titulo']);
        $description = $this->sanitize($data['descripcion']);
        $priority = $this->sanitize($data['prioridad']);
        $deadline = $data['fecha_limite'];
        $assignedTo = isset($data['asignado_a']) ? (int)$data['asignado_a'] : null;
        
        // Validar prioridad
        $validPriorities = ['alta', 'media', 'baja'];
        if (!in_array($priority, $validPriorities)) {
            $this->sendError('Prioridad inválida');
        }
        
        try {
$query = "INSERT INTO tareas (titulo, descripcion, prioridad, fecha_limite, asignado_a, creado_por) 
                      VALUES (:titulo, :descripcion, :prioridad, :fecha_limite, :asignado_a, :creado_por)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':titulo', $title);
            $stmt->bindParam(':descripcion', $description);
            $stmt->bindParam(':prioridad', $priority);
            $stmt->bindParam(':fecha_limite', $deadline);
            $stmt->bindParam(':asignado_a', $assignedTo);
            $stmt->bindParam(':creado_por', $userId);
            
            if ($stmt->execute()) {
                $taskId = $this->db->lastInsertId();
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Tarea creada correctamente',
                    'taskId' => $taskId
                ]);
            } else {
                $this->sendError('No se pudo crear la tarea', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al crear tarea: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar tarea
     */
    public function updateTask($taskId) {
        $userId = $this->authController->requireAuth();
        
        $data = $this->getRequestData();
        
        // Validar que el ID sea un número
        if (!is_numeric($taskId)) {
            $this->sendError('ID de tarea inválido');
        }
        
        try {
            $setParts = [];
            $params = [];
            
            // Campos actualizables
            if (isset($data['title'])) {
                $setParts[] = "titulo = :title";
                $params[':title'] = $this->sanitize($data['title']);
            }
            
            if (isset($data['description'])) {
                $setParts[] = "descripcion = :description";
                $params[':description'] = $this->sanitize($data['description']);
            }
            
            if (isset($data['priority'])) {
                $validPriorities = ['alta', 'media', 'baja'];
                if (!in_array($data['priority'], $validPriorities)) {
                    $this->sendError('Prioridad inválida');
                }
                $setParts[] = "prioridad = :priority";
                $params[':priority'] = $this->sanitize($data['priority']);
            }
            
            if (isset($data['deadline'])) {
                $setParts[] = "fecha_limite = :deadline";
                $params[':deadline'] = $data['deadline'];
            }
            
            if (isset($data['status'])) {
                $validStatuses = ['pendiente', 'en_progreso', 'completada'];
                if (!in_array($data['status'], $validStatuses)) {
                    $this->sendError('Estado inválido');
                }
                $setParts[] = "estado = :status";
                $params[':status'] = $this->sanitize($data['status']);
            }
            
            if (isset($data['assignedTo'])) {
                $setParts[] = "asignado_a = :assignedTo";
                $params[':assignedTo'] = (int)$data['assignedTo'];
            }
            
            if (empty($setParts)) {
                $this->sendError('No hay campos para actualizar');
            }
            
            $params[':taskId'] = (int)$taskId;
            
            $query = "UPDATE tareas SET " . implode(', ', $setParts) . " WHERE id = :taskId";
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Tarea actualizada correctamente'
                ]);
            } else {
                $this->sendError('No se pudo actualizar la tarea', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al actualizar tarea: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Eliminar tarea
     */
    public function deleteTask($taskId) {
        $userId = $this->authController->requireAuth();
        
        // Validar que el ID sea un número
        if (!is_numeric($taskId)) {
            $this->sendError('ID de tarea inválido');
        }
        
        try {
            $query = "DELETE FROM tareas WHERE id = :taskId";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':taskId', $taskId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Tarea eliminada correctamente'
                ]);
            } else {
                $this->sendError('No se pudo eliminar la tarea', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al eliminar tarea: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener usuarios para asignar tareas
     */
    public function getUsers() {
        $this->authController->requireAuth();
        
        try {
            $query = "SELECT id, username, nombre_completo, rol 
                     FROM usuarios 
                     WHERE activo = 1 
                     ORDER BY nombre_completo";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'users' => $users
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener usuarios: ' . $exception->getMessage(), 500);
        }
    }
}
?>