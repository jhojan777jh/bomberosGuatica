<?php
/**
 * Controlador de donaciones
 */

require_once __DIR__ . '/BaseController.php';

class DonationController extends BaseController {
    
    /**
     * Procesar nueva donación
     */
    public function processDonation() {
        $data = $this->getRequestData();
        $this->validateRequired($data, ['name', 'email', 'amount']);
        
        $name = $this->sanitize($data['name']);
        $email = $this->sanitize($data['email']);
        $phone = isset($data['phone']) ? $this->sanitize($data['phone']) : null;
        $amount = (float)$data['amount'];
        $type = isset($data['type']) ? $this->sanitize($data['type']) : 'unica';
        $method = isset($data['method']) ? $this->sanitize($data['method']) : 'tarjeta';
        $message = isset($data['message']) ? $this->sanitize($data['message']) : null;
        
        // Validar tipo de donación
        $validTypes = ['unica', 'mensual'];
        if (!in_array($type, $validTypes)) {
            $this->sendError('Tipo de donación inválido');
        }
        
        // Validar método de pago
        $validMethods = ['tarjeta', 'transferencia', 'efectivo'];
        if (!in_array($method, $validMethods)) {
            $this->sendError('Método de pago inválido');
        }
        
        try {
            $query = "INSERT INTO donaciones (nombre_donante, email, telefono, monto, tipo_donacion, metodo_pago, mensaje, estado) 
                     VALUES (:name, :email, :phone, :amount, :type, :method, :message, 'pendiente')";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':method', $method);
            $stmt->bindParam(':message', $message);
            
            if ($stmt->execute()) {
                $donationId = $this->db->lastInsertId();
                
                // Enviar email de confirmación (simulado)
                $this->sendConfirmationEmail($email, $name, $amount, $donationId);
                
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Donación procesada correctamente',
                    'donationId' => $donationId
                ]);
            } else {
                $this->sendError('No se pudo procesar la donación', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al procesar donación: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener todas las donaciones (solo admin)
     */
    public function getDonations() {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        try {
            $query = "SELECT * FROM donaciones ORDER BY fecha_donacion DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $donations = $stmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'donations' => $donations
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener donaciones: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar estado de donación
     */
    public function updateDonationStatus($donationId) {
        
        $authController = new AuthController();
        $authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['status']);
        
        $status = $this->sanitize($data['status']);
        $reference = isset($data['reference']) ? $this->sanitize($data['reference']) : null;
        
        // Validar estado
        $validStatuses = ['pendiente', 'completada', 'fallida'];
        if (!in_array($status, $validStatuses)) {
            $this->sendError('Estado inválido');
        }
        
        try {
            $query = "UPDATE donaciones 
                     SET estado = :status, referencia_pago = :reference 
                     WHERE id = :donationId";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':reference', $reference);
            $stmt->bindParam(':donationId', $donationId);
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Estado de donación actualizado correctamente'
                ]);
            } else {
                $this->sendError('No se pudo actualizar el estado', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al actualizar estado: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener estadísticas de donaciones
     */
    public function getDonationStats() {
        try {
            // Total donaciones
            $query = "SELECT 
                        COUNT(*) as total_donaciones,
                        SUM(monto) as total_recaudado,
                        AVG(monto) as promedio_donacion,
                        COUNT(CASE WHEN estado = 'completada' THEN 1 END) as donaciones_completadas
                      FROM donaciones";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            // Donaciones por mes
            $monthlyQuery = "SELECT 
                               DATE_FORMAT(fecha_donacion, '%Y-%m') as mes,
                               COUNT(*) as cantidad,
                               SUM(monto) as total
                             FROM donaciones 
                             WHERE estado = 'completada'
                             GROUP BY DATE_FORMAT(fecha_donacion, '%Y-%m')
                             ORDER BY mes DESC
                             LIMIT 12";
            
            $monthlyStmt = $this->db->prepare($monthlyQuery);
            $monthlyStmt->execute();
            $monthlyStats = $monthlyStmt->fetchAll();
            
            $this->sendResponse([
                'success' => true,
                'stats' => $stats,
                'monthly' => $monthlyStats
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener estadísticas: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Enviar email de confirmación (simulado)
     */
    private function sendConfirmationEmail($email, $name, $amount, $donationId) {
        // En un entorno real, aquí se integraría con un servicio de email
        // como PHPMailer, SendGrid, etc.
        
        $to = $email;
        $subject = "Confirmación de Donación - Cuerpo de Bomberos";
        $message = "
        <html>
        <head>
            <title>Confirmación de Donación</title>
        </head>
        <body>
            <h2>¡Gracias por tu donación!</h2>
            <p>Estimado/a $name,</p>
            <p>Hemos recibido tu donación de $amount con éxito.</p>
            <p><strong>ID de Donación:</strong> $donationId</p>
            <p>Tu contribución nos ayudará a continuar protegiendo vidas y bienes en nuestra comunidad.</p>
            <p>Atentamente,<br>Cuerpo de Bomberos</p>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: noreply@bomberos.com" . "\r\n";
        
        // mail($to, $subject, $message, $headers);
        
        return true;
    }
}
?>