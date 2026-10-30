<?php
/**
 * Configuración general del sistema
 */

define('SITE_URL', 'http://localhost/bomberos/');
define('API_URL', SITE_URL . 'api/');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Iniciar sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);

// Zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de CORS para API (solo para solicitudes API)
if (strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    
    // Manejar solicitudes OPTIONS preflight
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit(0);
    }
}
?>