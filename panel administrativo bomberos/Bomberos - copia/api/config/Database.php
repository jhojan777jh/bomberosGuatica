<?php
/**
 * Configuración de la base de datos - Versión corregida
 * Sistema del Cuerpo de Bomberos
 */

// Constantes de conexión a la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'bomberos_db');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_CHARSET', 'utf8mb4');

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASSWORD;
    private $charset = DB_CHARSET;
    
    public $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            return $this->conn;
            
        } catch(PDOException $exception) {
            error_log("Error de conexión a la base de datos: " . $exception->getMessage());
            
            // En modo desarrollo, mostrar el error (si la constante está definida)
            if (defined('ENVIRONMENT') && constant('ENVIRONMENT') === 'development') {
                throw new Exception("Error de conexión: " . $exception->getMessage());
            }
            
            return null;
        }
    }
    
    /**
     * Verificar si la conexión está activa
     */
    public function isConnected() {
        return $this->conn !== null;
    }
    
    /**
     * Obtener el último error de PDO
     */
    public function getLastError() {
        if ($this->conn) {
            return $this->conn->errorInfo();
        }
        return null;
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        if ($this->conn) {
            return $this->conn->beginTransaction();
        }
        return false;
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        if ($this->conn) {
            return $this->conn->commit();
        }
        return false;
    }
    
    /**
     * Revertir transacción
     */
    public function rollBack() {
        if ($this->conn) {
            return $this->conn->rollBack();
        }
        return false;
    }
}

?>