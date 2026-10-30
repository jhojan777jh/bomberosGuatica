<?php
/**
 * Controlador de gestión de disponibilidad de capacitaciones
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/AuthController.php';

class AvailabilityController extends BaseController {
    private $authController;
    
    public function __construct() {
        parent::__construct();
        $this->authController = new AuthController();
    }
    
    /**
     * Obtener toda la disponibilidad de capacitaciones
     */
    public function getAvailability() {
        $this->authController->requireAuth();
        
        try {
            $query = "SELECT ca.*, cp.tipo_capacitacion, cp.fecha_inicio, cp.fecha_fin, cp.horario,
                            u.nombre_completo as instructor_nombre
                     FROM capacitaciones_programadas ca
                     LEFT JOIN capacitaciones_personal_asignado cpa ON ca.id = cpa.id_capacitacion
                     LEFT JOIN usuarios u ON cpa.id_usuario = u.id
                     WHERE ca.estado IN ('programada', 'en_curso')
                     ORDER BY ca.fecha_inicio ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $availability = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'availability' => $availability
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener disponibilidad: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Crear nueva disponibilidad de capacitación
     */
    public function createAvailability() {
        $this->authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['tipo_capacitacion', 'fecha_inicio', 'fecha_fin', 'horario', 'cupo_maximo']);
        
        $tipoCapacitacion = $this->sanitize($data['tipo_capacitacion']);
        $fechaInicio = $data['fecha_inicio'];
        $fechaFin = $data['fecha_fin'];
        $horario = $this->sanitize($data['horario']);
        $cupoMaximo = (int)$data['cupo_maximo'];
        $instructorAsignado = isset($data['instructor_asignado']) ? (int)$data['instructor_asignado'] : null;
        $salon = isset($data['salon']) ? $this->sanitize($data['salon']) : null;
        $observaciones = isset($data['observaciones']) ? $this->sanitize($data['observaciones']) : null;
        
        // Validar tipo de capacitación
        $validTypes = ['bombero_basico', 'rescate_vehicular', 'incendios_estructurales', 'primeros_auxilios', 'manejo_materiales'];
        if (!in_array($tipoCapacitacion, $validTypes)) {
            $this->sendError('Tipo de capacitación inválido');
        }
        
        // Validar horario
        $validSchedules = ['matutino', 'vespertino', 'nocturno', 'fin_semana'];
        if (!in_array($horario, $validSchedules)) {
            $this->sendError('Horario inválido');
        }
        
        // Validar fechas
        $startDate = DateTime::createFromFormat('Y-m-d', $fechaInicio);
        $endDate = DateTime::createFromFormat('Y-m-d', $fechaFin);
        
        if (!$startDate || !$endDate) {
            $this->sendError('Formato de fecha inválido');
        }
        
        if ($startDate >= $endDate) {
            $this->sendError('La fecha de inicio debe ser anterior a la fecha de fin');
        }
        
        if ($startDate <= new DateTime()) {
            $this->sendError('La fecha de inicio debe ser posterior a hoy');
        }
        
        // Validar cupo máximo
        if ($cupoMaximo < 1 || $cupoMaximo > 50) {
            $this->sendError('El cupo máximo debe estar entre 1 y 50 participantes');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Insertar capacitación programada
            $query = "INSERT INTO capacitaciones_programadas 
                     (tipo_capacitacion, fecha_inicio, fecha_fin, horario, instructor_asignado, cupo_maximo, salon, observaciones) 
                     VALUES (:tipo_capacitacion, :fecha_inicio, :fecha_fin, :horario, :instructor_asignado, :cupo_maximo, :salon, :observaciones)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':tipo_capacitacion', $tipoCapacitacion);
            $stmt->bindParam(':fecha_inicio', $fechaInicio);
            $stmt->bindParam(':fecha_fin', $fechaFin);
            $stmt->bindParam(':horario', $horario);
            $stmt->bindParam(':instructor_asignado', $instructorAsignado);
            $stmt->bindParam(':cupo_maximo', $cupoMaximo);
            $stmt->bindParam(':salon', $salon);
            $stmt->bindParam(':observaciones', $observaciones);
            
            if ($stmt->execute()) {
                $capacitacionId = $this->db->lastInsertId();
                
                // Si hay instructor asignado, crear la asignación
                if ($instructorAsignado) {
                    $assignQuery = "INSERT INTO capacitaciones_personal_asignado 
                                   (id_capacitacion, id_usuario, rol, estado) 
                                   VALUES (:id_capacitacion, :id_usuario, 'instructor', 'asignado')";
                    
                    $assignStmt = $this->db->prepare($assignQuery);
                    $assignStmt->bindParam(':id_capacitacion', $capacitacionId);
                    $assignStmt->bindParam(':id_usuario', $instructorAsignado);
                    $assignStmt->execute();
                }
                
                $this->db->commit();
                
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Disponibilidad de capacitación creada correctamente',
                    'capacitacionId' => $capacitacionId
                ]);
            } else {
                $this->db->rollBack();
                $this->sendError('No se pudo crear la disponibilidad', 500);
            }
        } catch(PDOException $exception) {
            $this->db->rollBack();
            $this->sendError('Error al crear disponibilidad: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar disponibilidad de capacitación
     */
    public function updateAvailability($capacitacionId) {
        $this->authController->requireAdmin();
        
        $data = $this->getRequestData();
        
        // Validar que el ID sea un número
        if (!is_numeric($capacitacionId)) {
            $this->sendError('ID de capacitación inválido');
        }
        
        $capacitacionId = (int)$capacitacionId;
        
        // Verificar que la capacitación exista
        try {
            $checkQuery = "SELECT id FROM capacitaciones_programadas WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $capacitacionId);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                $this->sendError('Capacitación no encontrada', 404);
            }
            
            // Construir consulta de actualización dinámica
            $updateFields = [];
            $params = [];
            
            if (isset($data['fecha_inicio'])) {
                $updateFields[] = "fecha_inicio = :fecha_inicio";
                $params[':fecha_inicio'] = $data['fecha_inicio'];
            }
            
            if (isset($data['fecha_fin'])) {
                $updateFields[] = "fecha_fin = :fecha_fin";
                $params[':fecha_fin'] = $data['fecha_fin'];
            }
            
            if (isset($data['horario'])) {
                $validSchedules = ['matutino', 'vespertino', 'nocturno', 'fin_semana'];
                if (!in_array($data['horario'], $validSchedules)) {
                    $this->sendError('Horario inválido');
                }
                $updateFields[] = "horario = :horario";
                $params[':horario'] = $this->sanitize($data['horario']);
            }
            
            if (isset($data['cupo_maximo'])) {
                $cupoMaximo = (int)$data['cupo_maximo'];
                if ($cupoMaximo < 1 || $cupoMaximo > 50) {
                    $this->sendError('El cupo máximo debe estar entre 1 y 50 participantes');
                }
                $updateFields[] = "cupo_maximo = :cupo_maximo";
                $params[':cupo_maximo'] = $cupoMaximo;
            }
            
            if (isset($data['instructor_asignado'])) {
                $updateFields[] = "instructor_asignado = :instructor_asignado";
                $params[':instructor_asignado'] = (int)$data['instructor_asignado'];
            }
            
            if (isset($data['salon'])) {
                $updateFields[] = "salon = :salon";
                $params[':salon'] = $this->sanitize($data['salon']);
            }
            
            if (isset($data['observaciones'])) {
                $updateFields[] = "observaciones = :observaciones";
                $params[':observaciones'] = $this->sanitize($data['observaciones']);
            }
            
            if (isset($data['estado'])) {
                $validStates = ['programada', 'en_curso', 'finalizada', 'cancelada'];
                if (!in_array($data['estado'], $validStates)) {
                    $this->sendError('Estado inválido');
                }
                $updateFields[] = "estado = :estado";
                $params[':estado'] = $this->sanitize($data['estado']);
            }
            
            if (empty($updateFields)) {
                $this->sendError('No hay campos para actualizar');
            }
            
            $params[':id'] = $capacitacionId;
            
            $query = "UPDATE capacitaciones_programadas SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Disponibilidad actualizada correctamente'
                ]);
            } else {
                $this->sendError('No se pudo actualizar la disponibilidad', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al actualizar disponibilidad: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Eliminar disponibilidad de capacitación
     */
    public function deleteAvailability($capacitacionId) {
        $this->authController->requireAdmin();
        
        if (!is_numeric($capacitacionId)) {
            $this->sendError('ID de capacitación inválido');
        }
        
        $capacitacionId = (int)$capacitacionId;
        
        try {
            $this->db->beginTransaction();
            
            // Verificar que no haya inscripciones confirmadas
            $checkQuery = "SELECT COUNT(*) as inscritos FROM capacitaciones_inscripciones 
                          WHERE tipo_capacitacion = (SELECT tipo_capacitacion FROM capacitaciones_programadas WHERE id = :id) 
                          AND estado IN ('confirmada', 'completada')";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $capacitacionId);
            $checkStmt->execute();
            $result = $checkStmt->fetch();
            
            if ($result['inscritos'] > 0) {
                $this->sendError('No se puede eliminar una capacitación con inscripciones confirmadas');
            }
            
            // Eliminar asignaciones de personal
            $deleteAssignmentsQuery = "DELETE FROM capacitaciones_personal_asignado WHERE id_capacitacion = :id";
            $deleteAssignmentsStmt = $this->db->prepare($deleteAssignmentsQuery);
            $deleteAssignmentsStmt->bindParam(':id', $capacitacionId);
            $deleteAssignmentsStmt->execute();
            
            // Eliminar capacitación programada
            $deleteQuery = "DELETE FROM capacitaciones_programadas WHERE id = :id";
            $deleteStmt = $this->db->prepare($deleteQuery);
            $deleteStmt->bindParam(':id', $capacitacionId);
            
            if ($deleteStmt->execute()) {
                $this->db->commit();
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Disponibilidad eliminada correctamente'
                ]);
            } else {
                $this->db->rollBack();
                $this->sendError('No se pudo eliminar la disponibilidad', 500);
            }
        } catch(PDOException $exception) {
            $this->db->rollBack();
            $this->sendError('Error al eliminar disponibilidad: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener lista de instructores disponibles
     */
    public function getInstructors() {
        $this->authController->requireAuth();
        
        try {
            $query = "SELECT id, username, nombre_completo, email 
                     FROM usuarios 
                     WHERE rol = 'admin' AND activo = 1 
                     ORDER BY nombre_completo ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $instructors = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'instructors' => $instructors
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener instructores: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener estadísticas de disponibilidad
     */
    public function getAvailabilityStats() {
        $this->authController->requireAuth();
        
        try {
            // Capacitaciones por estado
            $statusQuery = "SELECT estado, COUNT(*) as cantidad 
                           FROM capacitaciones_programadas 
                           GROUP BY estado";
            $statusStmt = $this->db->prepare($statusQuery);
            $statusStmt->execute();
            $statusStats = $statusStmt->fetchAll();
            
            // Capacitaciones por tipo
            $typeQuery = "SELECT tipo_capacitacion, COUNT(*) as cantidad 
                         FROM capacitaciones_programadas 
                         GROUP BY tipo_capacitacion";
            $typeStmt = $this->db->prepare($typeQuery);
            $typeStmt->execute();
            $typeStats = $typeStmt->fetchAll();
            
            // Capacitaciones por mes
            $monthlyQuery = "SELECT DATE_FORMAT(fecha_inicio, '%Y-%m') as mes, COUNT(*) as cantidad 
                           FROM capacitaciones_programadas 
                           GROUP BY DATE_FORMAT(fecha_inicio, '%Y-%m') 
                           ORDER BY mes ASC 
                           LIMIT 12";
            $monthlyStmt = $this->db->prepare($monthlyQuery);
            $monthlyStmt->execute();
            $monthlyStats = $monthlyStmt->fetchAll();
            
            // Cupo disponible vs ocupado
            $capacityQuery = "SELECT SUM(cupo_maximo) as cupo_total, SUM(cupo_actual) as cupo_ocupado 
                            FROM capacitaciones_programadas 
                            WHERE estado IN ('programada', 'en_curso')";
            $capacityStmt = $this->db->prepare($capacityQuery);
            $capacityStmt->execute();
            $capacityStats = $capacityStmt->fetch();
            
            $this->sendResponse([
                'success' => true,
                'stats' => [
                    'byStatus' => $statusStats,
                    'byType' => $typeStats,
                    'monthly' => $monthlyStats,
                    'capacity' => $capacityStats
                ]
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener estadísticas: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Asignar personal a capacitación
     */
    public function assignStaff() {
        $this->authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['id_capacitacion', 'id_usuario', 'rol']);
        
        $idCapacitacion = (int)$data['id_capacitacion'];
        $idUsuario = (int)$data['id_usuario'];
        $rol = $this->sanitize($data['rol']);
        
        // Validar rol
        $validRoles = ['instructor', 'ayudante', 'coordinador'];
        if (!in_array($rol, $validRoles)) {
            $this->sendError('Rol inválido');
        }
        
        try {
            // Verificar que la capacitación exista
            $checkQuery = "SELECT id FROM capacitaciones_programadas WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $idCapacitacion);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                $this->sendError('Capacitación no encontrada', 404);
            }
            
            // Verificar que el usuario exista
            $userQuery = "SELECT id FROM usuarios WHERE id = :id AND activo = 1";
            $userStmt = $this->db->prepare($userQuery);
            $userStmt->bindParam(':id', $idUsuario);
            $userStmt->execute();
            
            if (!$userStmt->fetch()) {
                $this->sendError('Usuario no encontrado', 404);
            }
            
            // Verificar que no esté ya asignado
            $assignCheckQuery = "SELECT id FROM capacitaciones_personal_asignado 
                               WHERE id_capacitacion = :id_capacitacion AND id_usuario = :id_usuario";
            $assignCheckStmt = $this->db->prepare($assignCheckQuery);
            $assignCheckStmt->bindParam(':id_capacitacion', $idCapacitacion);
            $assignCheckStmt->bindParam(':id_usuario', $idUsuario);
            $assignCheckStmt->execute();
            
            if ($assignCheckStmt->fetch()) {
                $this->sendError('El personal ya está asignado a esta capacitación');
            }
            
            $query = "INSERT INTO capacitaciones_personal_asignado 
                     (id_capacitacion, id_usuario, rol, estado) 
                     VALUES (:id_capacitacion, :id_usuario, :rol, 'asignado')";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id_capacitacion', $idCapacitacion);
            $stmt->bindParam(':id_usuario', $idUsuario);
            $stmt->bindParam(':rol', $rol);
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Personal asignado correctamente'
                ]);
            } else {
                $this->sendError('No se pudo asignar el personal', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al asignar personal: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener personal asignado a una capacitación
     */
    public function getAssignedStaff($capacitacionId) {
        $this->authController->requireAuth();
        
        if (!is_numeric($capacitacionId)) {
            $this->sendError('ID de capacitación inválido');
        }
        
        $capacitacionId = (int)$capacitacionId;
        
        try {
            $query = "SELECT cpa.*, u.nombre_completo, u.email, u.username 
                     FROM capacitaciones_personal_asignado cpa
                     JOIN usuarios u ON cpa.id_usuario = u.id
                     WHERE cpa.id_capacitacion = :id_capacitacion
                     ORDER BY cpa.rol, u.nombre_completo";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id_capacitacion', $capacitacionId);
            $stmt->execute();
            $staff = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'staff' => $staff
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener personal asignado: ' . $exception->getMessage(), 500);
        }
    }
}