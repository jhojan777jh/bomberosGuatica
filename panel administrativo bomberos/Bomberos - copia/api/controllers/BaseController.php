<?php
/**
 * Clase base para todos los controladores de la API
 */

require_once __DIR__ . '/../config/config.php';

class BaseController {
    protected $db;
    
    public function __construct() {
        require_once __DIR__ . '/../config/Database.php';
        $database = new Database();
        $this->db = $database->getConnection();
        
        if (!$this->db) {
            $this->sendError('Error de conexión a la base de datos', 500);
        }
    }
    
    /**
     * Enviar respuesta JSON
     */
    protected function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Enviar respuesta de error
     */
    protected function sendError($message, $statusCode = 400) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => true,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Obtener datos de la solicitud
     */
    protected function getRequestData() {
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? trim($_SERVER['CONTENT_TYPE']) : '';
        
        if ($contentType === 'application/json') {
            $json = file_get_contents('php://input');
            return json_decode($json, true) ?: [];
        }
        
        return $_POST;
    }
    
    /**
     * Validar datos requeridos
     */
    protected function validateRequired($data, $requiredFields) {
        $missing = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->sendError('Campos requeridos faltantes: ' . implode(', ', $missing), 422);
        }
        
        return true;
    }
    
    /**
     * Limpiar y sanitizar datos
     */
    protected function sanitize($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generar token CSRF
     */
    protected function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verificar token CSRF
     */
    protected function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            $this->sendError('Token CSRF inválido', 403);
        }
        return true;
    }
}
?>