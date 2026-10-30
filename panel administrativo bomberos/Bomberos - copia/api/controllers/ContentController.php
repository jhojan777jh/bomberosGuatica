<?php
/**
 * Controlador de gestión de contenido
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/AuthController.php';

class ContentController extends BaseController {
    private $authController;
    
    public function __construct() {
        parent::__construct();
        $this->authController = new AuthController();
    }
    
    /**
     * Obtener contenido del sitio
     */
    public function getContent() {
        try {
            $section = isset($_GET['section']) ? $this->sanitize($_GET['section']) : null;
            
            $query = "SELECT seccion, clave, valor, tipo_contenido, fecha_actualizacion 
                     FROM contenido";
            
            if ($section) {
                $query .= " WHERE seccion = :section";
            }
            
            $query .= " ORDER BY seccion, clave";
            
            $stmt = $this->db->prepare($query);
            
            if ($section) {
                $stmt->bindParam(':section', $section);
            }
            
            $stmt->execute();
            $content = $stmt->fetchAll();
            
            // Organizar por secciones
            $organizedContent = [];
            foreach ($content as $item) {
                $organizedContent[$item['seccion']][$item['clave']] = [
                    'value' => $item['valor'],
                    'type' => $item['tipo_contenido'],
                    'updated' => $item['fecha_actualizacion']
                ];
            }
            
            $this->sendResponse([
                'success' => true,
                'content' => $organizedContent
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener contenido: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar contenido
     */
    public function updateContent() {
        $this->authController->requireAuth();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['section', 'key', 'value']);
        
        $section = $this->sanitize($data['section']);
        $key = $this->sanitize($data['key']);
        $value = $data['value'];
        $type = isset($data['type']) ? $this->sanitize($data['type']) : 'texto';
        
        try {
            $query = "UPDATE contenido 
                     SET valor = :value, tipo_contenido = :type, fecha_actualizacion = NOW() 
                     WHERE seccion = :section AND clave = :key";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':section', $section);
            $stmt->bindParam(':key', $key);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':type', $type);
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Contenido actualizado correctamente'
                ]);
            } else {
                $this->sendError('No se pudo actualizar el contenido', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al actualizar contenido: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Obtener estadísticas
     */
    public function getStatistics() {
        try {
            $query = "SELECT tipo_estadistica, valor, fecha_registro, descripcion 
                     FROM estadisticas 
                     ORDER BY fecha_registro DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $statistics = $stmt->fetchAll();
            
            // Organizar por tipo
            $organizedStats = [];
            foreach ($statistics as $stat) {
                $organizedStats[$stat['tipo_estadistica']] = [
                    'value' => $stat['valor'],
                    'date' => $stat['fecha_registro'],
                    'description' => $stat['descripcion']
                ];
            }
            
            $this->sendResponse([
                'success' => true,
                'statistics' => $organizedStats
            ]);
        } catch(PDOException $exception) {
            $this->sendError('Error al obtener estadísticas: ' . $exception->getMessage(), 500);
        }
    }
    
    /**
     * Actualizar estadísticas
     */
    public function updateStatistics() {
        $this->authController->requireAdmin();
        
        $data = $this->getRequestData();
        $this->validateRequired($data, ['type', 'value']);
        
        $type = $this->sanitize($data['type']);
        $value = (int)$data['value'];
        $description = isset($data['description']) ? $this->sanitize($data['description']) : null;
        
        try {
            $query = "INSERT INTO estadisticas (tipo_estadistica, valor, fecha_registro, descripcion) 
                     VALUES (:type, :value, CURDATE(), :description)
                     ON DUPLICATE KEY UPDATE 
                     valor = :value, descripcion = :description";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':description', $description);
            
            if ($stmt->execute()) {
                $this->sendResponse([
                    'success' => true,
                    'message' => 'Estadística actualizada correctamente'
                ]);
            } else {
                $this->sendError('No se pudo actualizar la estadística', 500);
            }
        } catch(PDOException $exception) {
            $this->sendError('Error al actualizar estadística: ' . $exception->getMessage(), 500);
        }
    }
}
?>