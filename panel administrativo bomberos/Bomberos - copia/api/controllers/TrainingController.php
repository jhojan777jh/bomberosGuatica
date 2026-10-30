<?php
/**
 * Controlador de gestión de capacitaciones
 */

require_once __DIR__ . '/BaseController.php';


class TrainingController extends BaseController {
    
    /**
     * Registrar nueva inscripción a capacitación
     */
    public function register() {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['name', 'email', 'phone', 'age', 'trainingType', 'preferredDate', 'preferredTime']);
        
        $name = $this->sanitize($data['name']);
        $email = $this->sanitize($data['email']);
        $phone = $this->sanitize($data['phone']);
        $age = (int)$data['age'];
        $trainingType = $this->sanitize($data['trainingType']);
        $preferredDate = $data['preferredDate'];
        $preferredTime = $this->sanitize($data['preferredTime']);
        $experienceLevel = isset($data['experienceLevel']) ? $this->sanitize($data['experienceLevel']) : 'principiante';
        $message = isset($data['message']) ? $this->sanitize($data['message']) : null;
        
        // Validar edad
        if ($age < 18 || $age > 65) {
            $this->sendError('La edad debe estar entre 18 y 65 años');
        }
        
        // Validar tipo de capacitación
        $validTypes = ['bombero_basico', 'rescate_vehicular', 'incendios_estructurales', 'primeros_auxilios', 'manejo_materiales'];
        if (!in_array($trainingType, $validTypes)) {
            $this->sendError('Tipo de capacitación inválido');
        }
        
        // Validar horario
        $validTimes = ['matutino', 'vespertino', 'nocturno', 'fin_semana'];
        if (!in_array($preferredTime, $validTimes)) {
            $this->sendError('Horario preferido inválido');
        }
        
        // Validar nivel de experiencia
        $validLevels = ['principiante', 'intermedio', 'avanzado'];
        if (!in_array($experienceLevel, $validLevels)) {
            $this->sendError('Nivel de experiencia inválido');
        }
        
        // Validar formato de fecha
        $dateValidation = DateTime::createFromFormat('Y-m-d', $preferredDate);
        if (!$dateValidation || $dateValidation->format('Y-m-d') !== $preferredDate) {
            $this->sendError('Formato de fecha inválido');
        }
        
        // Validar que la fecha no sea anterior a hoy
        $today = new DateTime();
        $preferredDateTime = new DateTime($preferredDate);
        if ($preferredDateTime < $today) {
            $this->sendError('La fecha preferida no puede ser anterior a hoy');
        }
        
        try {
            // Verificar si ya existe una inscripción pendiente para el mismo email y tipo de capacitación
            $checkQuery = "SELECT id FROM capacitaciones_inscripciones 
                          WHERE email = :email AND tipo_capacitacion = :trainingType AND estado = 'pendiente'";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->bindParam(':trainingType', $trainingType);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                $this->sendError('Ya tienes una inscripción pendiente para este tipo de capacitación');
            }
            
            $query = "INSERT INTO capacitaciones_inscripciones 
                     (nombre_completo, email, telefono, edad, tipo_capacitacion, fecha_preferida, horario_preferido, nivel_experiencia, mensaje) 
                     VALUES (:name, :email, :phone, :age, :trainingType, :preferredDate, :preferredTime, :experienceLevel, :message)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':age', $age);
            $stmt->bindParam(':trainingType', $trainingType);
            $stmt->bindParam(':preferredDate', $preferredDate);
            $stmt->bindParam(':preferredTime', $preferredTime);
            $stmt->bindParam(':experienceLevel', $experienceLevel);
            $stmt->bindParam(':message', $message);
            
            if ($stmt->execute()) {
                $registrationId = $this->db->lastInsertId();
                
                // Enviar email de confirmación (simulado)
                $this->sendConfirmationEmail($email, $name, $trainingType, $registrationId);
                
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Inscripción a capacitación registrada correctamente',
                    'registrationId' => $registrationId
                ]);
            } else {
                $this->sendError('No se pudo registrar la inscripción', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al registrar inscripción: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener todas las inscripciones (solo admin)
     */
    public function getRegistrations() {
        $authController = new AuthController();
        $authController->requireAdmin();
        
        try {
            $query = "SELECT * FROM capacitaciones_inscripciones ORDER BY fecha_inscripcion DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $registrations = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'registrations' => $registrations
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener inscripciones: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar estado de inscripción
     */
    public function updateRegistrationStatus($registrationId) {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['status']);
        
        $status = $this->sanitize($data['status']);
        $observations = isset($data['observations']) ? $this->sanitize($data['observations']) : null;
        
        // Validar estado
        $validStatuses = ['pendiente', 'confirmada', 'cancelada', 'completada'];
        if (!in_array($status, $validStatuses)) {
            $this->sendError('Estado inválido');
        }
        
        try {
            $query = "UPDATE capacitaciones_inscripciones 
                     SET estado = :status, observaciones_admin = :observations";
            
            if ($status === 'confirmada') {
                $query .= ", fecha_confirmacion = NOW()";
            }
            
            $query .= " WHERE id = :registrationId";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':observations', $observations);
            $stmt->bindParam(':registrationId', $registrationId);
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Estado de inscripción actualizado correctamente'
                ]);
            } else {
                $this->sendError('No se pudo actualizar el estado', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al actualizar estado: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener estadísticas de capacitaciones
     */
    public function getTrainingStats() {
        try {
            // Total inscripciones
            $query = "SELECT 
                        COUNT(*) as total_inscripciones,
                        COUNT(CASE WHEN estado = 'confirmada' THEN 1 END) as confirmadas,
                        COUNT(CASE WHEN estado = 'completada' THEN 1 END) as completadas,
                        COUNT(CASE WHEN estado = 'cancelada' THEN 1 END) as canceladas
                      FROM capacitaciones_inscripciones";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            // Inscripciones por tipo de capacitación
            $typeQuery = "SELECT 
                            tipo_capacitacion,
                            COUNT(*) as cantidad,
                            COUNT(CASE WHEN estado = 'confirmada' THEN 1 END) as confirmadas
                          FROM capacitaciones_inscripciones 
                          GROUP BY tipo_capacitacion
                          ORDER BY cantidad DESC";
            
            $typeStmt = $this->db->prepare($typeQuery);
            $typeStmt->execute();
            $typeStats = $typeStmt->fetchAll();
            
            // Inscripciones por mes
            $monthlyQuery = "SELECT 
                                DATE_FORMAT(fecha_inscripcion, '%Y-%m') as mes,
                                COUNT(*) as cantidad
                              FROM capacitaciones_inscripciones 
                              GROUP BY DATE_FORMAT(fecha_inscripcion, '%Y-%m')
                              ORDER BY mes DESC
                              LIMIT 12";
            
            $monthlyStmt = $this->db->prepare($monthlyQuery);
            $monthlyStmt->execute();
            $monthlyStats = $monthlyStmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'stats' => $stats,
                'byType' => $typeStats,
                'monthly' => $monthlyStats
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener estadísticas: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Enviar email de confirmación (simulado)
     */
    private function sendConfirmationEmail($email, $name, $trainingType, $registrationId) {
        // En un entorno real, aquí se integraría con un servicio de email
        // como PHPMailer, SendGrid, etc.
        
        $trainingNames = [
            'bombero_basico' => 'Bombero Básico',
            'rescate_vehicular' => 'Rescate Vehicular',
            'incendios_estructurales' => 'Incendios Estructurales',
            'primeros_auxilios' => 'Primeros Auxilios',
            'manejo_materiales' => 'Manejo de Materiales Peligrosos'
        ];
        
        $trainingName = $trainingNames[$trainingType] ?? $trainingType;
        
        $to = $email;
        $subject = "Confirmación de Inscripción - Capacitación Bomberil";
        $message = "
        <html>
        <head>
            <title>Confirmación de Inscripción</title>
        </head>
        <body>
            <h2>¡Gracias por tu interés en nuestras capacitaciones!</h2>
            <p>Estimado/a $name,</p>
            <p>Hemos recibido tu inscripción para el curso de <strong>$trainingName</strong> con éxito.</p>
            <p><strong>ID de Inscripción:</strong> $registrationId</p>
            <p>Nos pondremos en contacto contigo pronto para confirmar la fecha y horario exactos de la capacitación.</p>
            <p><strong>Información importante:</strong></p>
            <ul>
                <li>Deberás presentar constancia médica de buena salud</li>
                <li>El curso tiene una duración de 40 horas</li>
                <li>Se entregará material didáctico y certificación</li>
            </ul>
            <p>Atentamente,<br>Departamento de Capacitación<br>Cuerpo de Bomberos</p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: capacitaciones@bomberos.com" . "\r\n";
        
        // mail($to, $subject, $message, $headers);
        
        return true;
    }
    
    /**
     * Programar nueva capacitación
     */
    public function scheduleTraining() {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['trainingType', 'startDate', 'endDate', 'shift', 'maxCapacity']);
        
        $trainingType = $this->sanitize($data['trainingType']);
        $startDate = $data['startDate'];
        $endDate = $data['endDate'];
        $shift = $this->sanitize($data['shift']);
        $instructor = isset($data['instructor']) ? (int)$data['instructor'] : null;
        $maxCapacity = (int)$data['maxCapacity'];
        $room = isset($data['room']) ? $this->sanitize($data['room']) : null;
        $status = isset($data['status']) ? $this->sanitize($data['status']) : 'programada';
        $observations = isset($data['observations']) ? $this->sanitize($data['observations']) : null;
        
        // Validar tipo de capacitación
        $validTypes = ['bombero_basico', 'rescate_vehicular', 'incendios_estructurales', 'primeros_auxilios', 'manejo_materiales'];
        if (!in_array($trainingType, $validTypes)) {
            $this->sendError('Tipo de capacitación inválido');
        }
        
        // Validar horario
        $validShifts = ['matutino', 'vespertino', 'nocturno', 'fin_semana'];
        if (!in_array($shift, $validShifts)) {
            $this->sendError('Horario inválido');
        }
        
        // Validar estado
        $validStatuses = ['programada', 'en_curso', 'finalizada', 'cancelada'];
        if (!in_array($status, $validStatuses)) {
            $this->sendError('Estado inválido');
        }
        
        // Validar fechas
        $startValidation = DateTime::createFromFormat('Y-m-d', $startDate);
        $endValidation = DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$startValidation || !$endValidation) {
            $this->sendError('Formato de fecha inválido');
        }
        
        if ($endValidation < $startValidation) {
            $this->sendError('La fecha de fin no puede ser anterior a la de inicio');
        }
        
        // Validar cupo máximo
        if ($maxCapacity < 5 || $maxCapacity > 50) {
            $this->sendError('El cupo máximo debe estar entre 5 y 50 participantes');
        }
        
        try {
            $query = "INSERT INTO capacitaciones_programadas 
                     (tipo_capacitacion, fecha_inicio, fecha_fin, horario, instructor_asignado, cupo_maximo, salon, estado, observaciones) 
                     VALUES (:trainingType, :startDate, :endDate, :shift, :instructor, :maxCapacity, :room, :status, :observations)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':trainingType', $trainingType);
            $stmt->bindParam(':startDate', $startDate);
            $stmt->bindParam(':endDate', $endDate);
            $stmt->bindParam(':shift', $shift);
            $stmt->bindParam(':instructor', $instructor);
            $stmt->bindParam(':maxCapacity', $maxCapacity);
            $stmt->bindParam(':room', $room);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':observations', $observations);
            
            if ($stmt->execute()) {
                $trainingId = $this->db->lastInsertId();
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Capacitación programada correctamente',
                    'trainingId' => $trainingId
                ]);
            } else {
                $this->sendError('No se pudo programar la capacitación', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al programar capacitación: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener capacitaciones programadas
     */
    public function getScheduledTrainings() {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        try {
            $query = "SELECT cp.*, u.nombre_completo as instructor_nombre 
                     FROM capacitaciones_programadas cp
                     LEFT JOIN usuarios u ON cp.instructor_asignado = u.id
                     ORDER BY cp.fecha_inicio DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $trainings = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'trainings' => $trainings
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener capacitaciones programadas: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Guardar disponibilidad de personal
     */
    public function saveAvailability() {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['staffId', 'date', 'shift', 'status']);
        
        $staffId = (int)$data['staffId'];
        $date = $data['date'];
        $shift = $this->sanitize($data['shift']);
        $status = $this->sanitize($data['status']);
        $reason = isset($data['reason']) ? $this->sanitize($data['reason']) : null;
        
        // Validar turno
        $validShifts = ['matutino', 'vespertino', 'nocturno', 'completo'];
        if (!in_array($shift, $validShifts)) {
            $this->sendError('Turno inválido');
        }
        
        // Validar estado
        $validStatuses = ['disponible', 'no_disponible', 'capacitacion', 'vacaciones', 'emergencia'];
        if (!in_array($status, $validStatuses)) {
            $this->sendError('Estado inválido');
        }
        
        try {
            $query = "INSERT INTO disponibilidad_personal 
                     (id_usuario, fecha, turno, estado, motivo) 
                     VALUES (:staffId, :date, :shift, :status, :reason)
                     ON DUPLICATE KEY UPDATE 
                     estado = :status, motivo = :reason";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':staffId', $staffId);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':shift', $shift);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':reason', $reason);
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Disponibilidad guardada correctamente'
                ]);
            } else {
                $this->sendError('No se pudo guardar la disponibilidad', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al guardar disponibilidad: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener disponibilidad de personal
     */
    public function getAvailability() {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        $shift = isset($_GET['shift']) ? $_GET['shift'] : 'matutino';
        
        try {
            $query = "SELECT dp.*, u.nombre_completo, u.rol 
                     FROM disponibilidad_personal dp
                     JOIN usuarios u ON dp.id_usuario = u.id
                     WHERE dp.fecha = :date AND dp.turno = :shift
                     ORDER BY u.nombre_completo";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':shift', $shift);
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
     * Asignar personal a capacitación
     */
    public function assignStaffToTraining() {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['trainingId', 'staffId', 'role']);
        
        $trainingId = (int)$data['trainingId'];
        $staffId = (int)$data['staffId'];
        $role = $this->sanitize($data['role']);
        $observations = isset($data['observations']) ? $this->sanitize($data['observations']) : null;
        
        // Validar rol
        $validRoles = ['instructor', 'ayudante', 'coordinador'];
        if (!in_array($role, $validRoles)) {
            $this->sendError('Rol inválido');
        }
        
        try {
            $query = "INSERT INTO capacitaciones_personal_asignado 
                     (id_capacitacion, id_usuario, rol, observaciones) 
                     VALUES (:trainingId, :staffId, :role, :observations)
                     ON DUPLICATE KEY UPDATE 
                     rol = :role, observaciones = :observations";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':trainingId', $trainingId);
            $stmt->bindParam(':staffId', $staffId);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':observations', $observations);
            
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
     * Obtener asignaciones de personal
     */
    public function getStaffAssignments() {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        try {
            $query = "SELECT cpa.*, u.nombre_completo, cp.tipo_capacitacion, cp.fecha_inicio
                     FROM capacitaciones_personal_asignado cpa
                     JOIN usuarios u ON cpa.id_usuario = u.id
                     JOIN capacitaciones_programadas cp ON cpa.id_capacitacion = cp.id
                     ORDER BY cp.fecha_inicio DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $assignments = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'assignments' => $assignments
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener asignaciones: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener lista de personal para selects
     */
    public function getStaffList() {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        try {
            $query = "SELECT id, username, nombre_completo, rol 
                     FROM usuarios 
                     WHERE activo = 1 
                     ORDER BY nombre_completo";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $staff = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'staff' => $staff
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener personal: ' . $exception->getMessage(), 500);
        }
    }
}
?>