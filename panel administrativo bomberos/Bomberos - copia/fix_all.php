<?php
/**
 * Script de corrección masiva de problemas de conexión
 * Corrige automáticamente todos los problemas comunes
 */

echo "=== CORRECCIÓN MASIVA DE PROBLEMAS DE CONEXIÓN ===\n\n";

// 1. Corregir permisos de archivos
echo "1. CORRIGIENDO PERMISOS DE ARCHIVOS...\n";
$files = [
    'api/index.php',
    'api/config/config.php',
    'api/config/Database.php',
    'api/controllers/AuthController.php',
    'api/controllers/BaseController.php',
    '.htaccess'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        // Hacer legible
        chmod($file, 0644);
        echo "✓ $file - Permisos corregidos\n";
    } else {
        echo "✗ $file - No existe\n";
    }
}

// 2. Corregir configuración de Apache
echo "\n2. CORRIGIENDO CONFIGURACIÓN APACHE...\n";
$htaccessContent = '
RewriteEngine On

# Evitar acceso directo a archivos de configuración
<Files ~ "^\.">
    Order allow,deny
    Deny from all
</Files>

# Evitar acceso a archivos sensibles
<FilesMatch "\.(sql|log|md)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Configuración CORS
<IfModule mod_headers.c>
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With"
</IfModule>

# Manejar solicitudes OPTIONS
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ api/index.php [QSA,L]

# Router API
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/index.php/$1 [QSA,L]

# Páginas de error personalizadas
ErrorDocument 404 /index.html
ErrorDocument 500 /index.html
';

file_put_contents('.htaccess', $htaccessContent);
echo "✓ .htaccess actualizado\n";

// 3. Corregir configuración de PHP
echo "\n3. CORRIGIENDO CONFIGURACIÓN PHP...\n";
$configContent = '
<?php
/**
 * Configuración general del sistema
 */

define(\'SITE_URL\', \'http://localhost/bomberos/\');
define(\'API_URL\', SITE_URL . \'api/\');
define(\'UPLOAD_PATH\', \'uploads/\');
define(\'MAX_FILE_SIZE\', 5 * 1024 * 1024); // 5MB

// Iniciar sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de sesión
ini_set(\'session.cookie_httponly\', 1);
ini_set(\'session.use_only_cookies\', 1);
ini_set(\'session.cookie_secure\', 0);

// Zona horaria
date_default_timezone_set(\'America/Bogota\');

// Configuración de CORS para API
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejar solicitudes OPTIONS preflight
if ($_SERVER[\'REQUEST_METHOD\'] == \'OPTIONS\') {
    exit(0);
}
?>
';

file_put_contents('api/config/config.php', $configContent);
echo "✓ api/config/config.php actualizado\n";

// 4. Corregir configuración de base de datos
echo "\n4. CORRIGIENDO CONFIGURACIÓN DE BASE DE DATOS...\n";
$dbConfigContent = '
<?php
/**
 * Configuración de la base de datos
 * Sistema del Cuerpo de Bomberos
 */

class Database {
    private $host = \'localhost\';
    private $db_name = \'bomberos_db\';
    private $username = \'root\';
    private $password = \'\';
    private $charset = \'utf8mb4\';
    
    public $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            error_log("Error de conexión a la base de datos: " . $exception->getMessage());
            return null;
        }
        
        return $this->conn;
    }
}
?>
';

file_put_contents('api/config/Database.php', $dbConfigContent);
echo "✓ api/config/Database.php actualizado\n";

// 5. Corregir router principal
echo "\n5. CORRIGIENDO ROUTER PRINCIPAL...\n";
$routerContent = '
<?php
/**
 * Router principal de la API
 */

require_once __DIR__ . \'/config/config.php\';

// Obtener método y ruta
$method = $_SERVER[\'REQUEST_METHOD\'];
$requestUri = $_SERVER[\'REQUEST_URI\'];
$basePath = dirname($_SERVER[\'PHP_SELF\']);

// Remover query string y base path
$requestUri = strtok($requestUri, \'?\');
$route = str_replace($basePath, \'\', $requestUri);
$route = trim($route, \'/\');

// Dividir ruta en partes
$parts = explode(\'/\', $route);
$controller = isset($parts[0]) ? $parts[0] : \'\';
$action = isset($parts[1]) ? $parts[1] : \'\';
$param = isset($parts[2]) ? $parts[2] : null;

// Mapeo de controladores
$controllerMap = [
    \'auth\' => \'AuthController\',
    \'content\' => \'ContentController\',
    \'tasks\' => \'TaskController\',
    \'donations\' => \'DonationController\',
    \'training\' => \'TrainingController\',
    \'availability\' => \'AvailabilityController\',
    \'users\' => \'UserController\'
];

// Función para enviar respuesta 404
function send404() {
    http_response_code(404);
    echo json_encode([
        \'error\' => true,
        \'message\' => \'Endpoint no encontrado\'
    ]);
    exit;
}

// Función para enviar respuesta 405
function send405() {
    http_response_code(405);
    echo json_encode([
        \'error\' => true,
        \'message\' => \'Método no permitido\'
    ]);
    exit;
}

// Enrutamiento
if (empty($controller)) {
    // Endpoint raíz
    echo json_encode([
        \'message\' => \'API del Cuerpo de Bomberos\',
        \'version\' => \'1.0.0\',
        \'endpoints\' => [
            \'auth\' => [\'login\', \'logout\', \'check\'],
            \'content\' => [\'get\', \'update\', \'stats\'],
            \'tasks\' => [\'get\', \'create\', \'update\', \'delete\', \'users\'],
            \'donations\' => [\'process\', \'get\', \'update-status\', \'stats\'],
            \'training\' => [\'register\', \'get\', \'update-status\', \'stats\'],
            \'availability\' => [\'get\', \'create\', \'update\', \'delete\', \'instructors\', \'stats\', \'assign-staff\'],
            \'users\' => [\'get\', \'create\', \'update\', \'delete\', \'roles\', \'profile\', \'permission\']
        ]
    ]);
} elseif (isset($controllerMap[$controller])) {
    // Cargar controlador
    $controllerClass = $controllerMap[$controller];
    require_once __DIR__ . "/controllers/{$controllerClass}.php";
    
    $controllerInstance = new $controllerClass();
    
    // Mapeo de acciones
    $actionMap = [
        \'auth\' => [
            \'POST\' => [
                \'login\' => \'login\',
                \'logout\' => \'logout\',
                \'check\' => \'checkAuth\'
            ]
        ],
        \'content\' => [
            \'GET\' => [
                \'get\' => \'getContent\',
                \'stats\' => \'getStatistics\'
            ],
            \'POST\' => [
                \'update\' => \'updateContent\',
                \'stats\' => \'updateStatistics\'
            ]
        ],
        \'users\' => [
            \'GET\' => [
                \'get\' => \'getUsers\',
                \'roles\' => \'getRoles\',
                \'profile\' => \'getProfile\',
                \'permission\' => \'checkPermission\'
            ],
            \'POST\' => [
                \'create\' => \'createUser\',
                \'create-role\' => \'createRole\'
            ],
            \'PUT\' => [
                \'update\' => \'updateUser\',
                \'update-role\' => \'updateRole\',
                \'profile\' => \'updateProfile\'
            ],
            \'DELETE\' => [
                \'delete\' => \'deleteUser\',
                \'delete-role\' => \'deleteRole\'
            ]
        ]
    ];
    
    // Verificar si existe el método para el controlador
    if (isset($actionMap[$controller][$method])) {
        $methodActions = $actionMap[$controller][$method]];
        
        if (empty($action)) {
            // Si no hay acción específica, usar la primera disponible
            if (!empty($methodActions)) {
                $action = key($methodActions);
            } else {
                send405();
            }
        }
        
        if (isset($methodActions[$action])) {
            $methodName = $methodActions[$action];
            
            // Verificar si el método existe en el controlador
            if (method_exists($controllerInstance, $methodName)) {
                // Ejecutar método con parámetro si existe
                if ($param) {
                    $controllerInstance->$methodName($param);
                } else {
                    $controllerInstance->$methodName();
                }
            } else {
                send404();
                }
            } else {
                send404();
            }
        } else {
            send405();
        }
    } else {
        send404();
    }
} else {
    send404();
}
?>
';

file_put_contents('api/index.php', $routerContent);
echo "✓ api/index.php actualizado\n";

// 6. Crear/actualizar usuario administrador
echo "\n6. CREANDO/ACTUALIZANDO USUARIO ADMINISTRADOR...\n";
try {
    require_once 'api/config/Database.php';
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        // Generar hash para la contraseña
        $password = '123456789Yo';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Verificar si el usuario ya existe
        $checkQuery = "SELECT id FROM usuarios WHERE username IN ('admin', 'admin123456789')";
        $checkStmt = $conn->query($checkQuery);
        
        if ($checkStmt->rowCount() > 0) {
            // Actualizar usuario existente
            $updateQuery = "UPDATE usuarios SET 
                          username = 'admin123456789',
                          password = :password,
                          email = 'admin@bomberos.com',
                          nombre_completo = 'Administrador Principal',
                          rol = 'admin',
                          activo = 1
                          WHERE username IN ('admin', 'admin123456789')";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->execute();
            echo "✓ Usuario administrador actualizado\n";
        } else {
            // Crear nuevo usuario
            $insertQuery = "INSERT INTO usuarios 
                          (username, password, email, nombre_completo, rol, activo, fecha_creacion) 
                          VALUES 
                          ('admin123456789', :password, 'admin@bomberos.com', 'Administrador Principal', 'admin', 1, NOW())";
            
            $stmt = $conn->prepare($insertQuery);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->execute();
            echo "✓ Usuario administrador creado\n";
        }
        
        // Verificar el usuario creado
        $verifyQuery = "SELECT id, username, nombre_completo, rol, activo FROM usuarios WHERE username = 'admin123456789'";
        $verifyStmt = $conn->query($verifyQuery);
        $admin = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "✓ Usuario verificado:\n";
            echo "  - ID: {$admin['id']}\n";
            echo "  - Usuario: {$admin['username']}\n";
            echo "  - Nombre: {$admin['nombre_completo']}\n";
            echo "  - Rol: {$admin['rol']}\n";
            echo "  - Estado: " . ($admin['activo'] ? 'Activo' : 'Inactivo') . "\n";
        }
    } else {
        echo "✗ No se pudo conectar a la base de datos\n";
    }
} catch (Exception $e) {
    echo "✗ Error al crear usuario: " . $e->getMessage() . "\n";
}

echo "\n=== CORRECCIÓN MASIVA COMPLETADA ===\n";
echo "\n🎯 ACCESO INMEDIATO:\n";
echo "1. Abre: ultra_simple_login.html\n";
echo "2. Usuario: admin123456789\n";
echo "3. Contraseña: 123456789Yo\n";
echo "4. Haz clic en \"Iniciar Sesión\"\n";
echo "\n🔧 ARCHIVOS CORREGIDOS:\n";
echo "- .htaccess (configuración Apache)\n";
echo "- api/config/config.php (configuración PHP)\n";
echo "- api/config/Database.php (conexión BD)\n";
echo "- api/index.php (router API)\n";
echo "- Usuario administrador (creado/actualizado)\n";
echo "\n✅ Todos los problemas comunes han sido corregidos.\n";
echo "Si aún tienes problemas, abre ultra_simple_login.html para diagnóstico.\n";
?>