<?php
/**
 * Router principal de la API
 */

require_once __DIR__ . '/config/config.php';

// Obtener método y ruta
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = dirname($_SERVER['PHP_SELF']);

// Remover query string y base path
$requestUri = strtok($requestUri, '?');
$route = str_replace($basePath, '', $requestUri);
$route = trim($route, '/');

// Dividir ruta en partes
$parts = explode('/', $route);
$controller = isset($parts[0]) ? $parts[0] : '';
$action = isset($parts[1]) ? $parts[1] : '';
$param = isset($parts[2]) ? $parts[2] : null;

// Mapeo de controladores
$controllerMap = [
    'auth' => 'AuthController',
    'content' => 'ContentController',
    'tasks' => 'TaskController',
    'donations' => 'DonationController',
    'training' => 'TrainingController',
    'availability' => 'AvailabilityController',
    'users' => 'UserController'
];

// Función para enviar respuesta 404
function send404() {
    http_response_code(404);
    echo json_encode([
        'error' => true,
        'message' => 'Endpoint no encontrado'
    ]);
    exit;
}

// Función para enviar respuesta 405
function send405() {
    http_response_code(405);
    echo json_encode([
        'error' => true,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Enrutamiento
if (empty($controller)) {
    // Endpoint raíz
    echo json_encode([
        'message' => 'API del Cuerpo de Bomberos',
        'version' => '1.0.0',
        'endpoints' => [
            'auth' => ['login', 'logout', 'check'],
            'content' => ['get', 'update', 'stats'],
            'tasks' => ['get', 'create', 'update', 'delete', 'users'],
            'donations' => ['process', 'get', 'update-status', 'stats'],
            'training' => ['register', 'get', 'update-status', 'stats'],
            'availability' => ['get', 'create', 'update', 'delete', 'instructors', 'stats', 'assign-staff'],
            'users' => ['get', 'create', 'update', 'delete', 'roles', 'profile', 'permission']
        ]
    ]);
} elseif (isset($controllerMap[$controller])) {
    // Cargar controlador
    $controllerClass = $controllerMap[$controller];
    require_once __DIR__ . "/controllers/{$controllerClass}.php";
    
    $controllerInstance = new $controllerClass();
    
    // Mapeo de acciones
    $actionMap = [
        'auth' => [
            'POST' => [
                'login' => 'login',
                'logout' => 'logout',
                'check' => 'checkAuth'
            ]
        ],
        'content' => [
            'GET' => [
                'get' => 'getContent',
                'stats' => 'getStatistics'
            ],
            'POST' => [
                'update' => 'updateContent',
                'stats' => 'updateStatistics'
            ]
        ],
        'tasks' => [
            'GET' => [
                'get' => 'getTasks',
                'users' => 'getUsers'
            ],
            'POST' => [
                'create' => 'createTask'
            ],
            'PUT' => [
                'update' => 'updateTask'
            ],
            'DELETE' => [
                'delete' => 'deleteTask'
            ]
        ],
        'donations' => [
            'POST' => [
                'process' => 'processDonation'
            ],
            'GET' => [
                'get' => 'getDonations',
                'stats' => 'getDonationStats'
            ],
            'PUT' => [
                'update-status' => 'updateDonationStatus'
            ]
        ],
        'training' => [
            'POST' => [
                'register' => 'register',
                'schedule' => 'scheduleTraining',
                'availability' => 'saveAvailability',
                'assign-staff' => 'assignStaffToTraining'
            ],
            'GET' => [
                'get' => 'getRegistrations',
                'stats' => 'getTrainingStats',
                'scheduled' => 'getScheduledTrainings',
                'availability' => 'getAvailability',
                'assignments' => 'getStaffAssignments',
                'staff' => 'getStaffList'
            ],
            'PUT' => [
                'update-status' => 'updateRegistrationStatus'
            ]
        ],
        'availability' => [
            'GET' => [
                'get' => 'getAvailability',
                'instructors' => 'getInstructors',
                'stats' => 'getAvailabilityStats',
                'staff' => 'getAssignedStaff'
            ],
            'POST' => [
                'create' => 'createAvailability',
                'assign-staff' => 'assignStaff'
            ],
            'PUT' => [
                'update' => 'updateAvailability'
            ],
            'DELETE' => [
                'delete' => 'deleteAvailability'
            ]
        ],
        'users' => [
            'GET' => [
                'get' => 'getUsers',
                'roles' => 'getRoles',
                'profile' => 'getProfile',
                'permission' => 'checkPermission'
            ],
            'POST' => [
                'create' => 'createUser',
                'create-role' => 'createRole'
            ],
            'PUT' => [
                'update' => 'updateUser',
                'update-role' => 'updateRole',
                'profile' => 'updateProfile'
            ],
            'DELETE' => [
                'delete' => 'deleteUser',
                'delete-role' => 'deleteRole'
            ]
        ]
    ];
    
    // Verificar si existe el método para el controlador
    if (isset($actionMap[$controller][$method])) {
        $methodActions = $actionMap[$controller][$method];
        
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
?>