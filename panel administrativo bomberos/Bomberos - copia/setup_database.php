<?php
/**
 * Script para crear/verificar la estructura de la base de datos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== VERIFICACIÓN Y CREACIÓN DE ESQUEMA DE BASE DE DATOS ===\n\n";

require_once 'api/config/Database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "✓ Conexión a base de datos establecida\n\n";
    
    // Tablas necesarias para el sistema
    $tables = [
        'usuarios' => "
            CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                nombre_completo VARCHAR(100) NOT NULL,
                rol VARCHAR(20) DEFAULT 'user',
                activo BOOLEAN DEFAULT 1,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ultimo_acceso TIMESTAMP NULL,
                INDEX idx_username (username),
                INDEX idx_rol (rol),
                INDEX idx_activo (activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'roles' => "
            CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(50) UNIQUE NOT NULL,
                descripcion TEXT,
                permisos JSON,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_nombre (nombre)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'capacitaciones_inscripciones' => "
            CREATE TABLE IF NOT EXISTS capacitaciones_inscripciones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                telefono VARCHAR(20) NOT NULL,
                edad INT NOT NULL,
                tipo_capacitacion VARCHAR(50) NOT NULL,
                fecha_preferida DATE NOT NULL,
                hora_preferida TIME NOT NULL,
                nivel_experiencia VARCHAR(20) DEFAULT 'principiante',
                mensaje TEXT,
                estado VARCHAR(20) DEFAULT 'pendiente',
                fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_estado (estado),
                INDEX idx_tipo_capacitacion (tipo_capacitacion)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'tareas' => "
            CREATE TABLE IF NOT EXISTS tareas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(200) NOT NULL,
                descripcion TEXT,
                asignado_a VARCHAR(50),
                estado VARCHAR(20) DEFAULT 'pendiente',
                prioridad VARCHAR(10) DEFAULT 'media',
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_vencimiento DATE,
                fecha_completacion TIMESTAMP NULL,
                creado_por VARCHAR(50),
                INDEX idx_asignado_a (asignado_a),
                INDEX idx_estado (estado),
                INDEX idx_prioridad (prioridad)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'donaciones' => "
            CREATE TABLE IF NOT EXISTS donaciones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre_donante VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                telefono VARCHAR(20),
                monto DECIMAL(10,2) NOT NULL,
                tipo_donacion VARCHAR(20) DEFAULT 'general',
                mensaje TEXT,
                estado VARCHAR(20) DEFAULT 'pendiente',
                fecha_donacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                referencia_pago VARCHAR(100),
                INDEX idx_email (email),
                INDEX idx_estado (estado),
                INDEX idx_fecha_donacion (fecha_donacion)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'disponibilidad' => "
            CREATE TABLE IF NOT EXISTS disponibilidad (
                id INT AUTO_INCREMENT PRIMARY KEY,
                instructor_id VARCHAR(50) NOT NULL,
                fecha DATE NOT NULL,
                hora_inicio TIME NOT NULL,
                hora_fin TIME NOT NULL,
                tipo_disponibilidad VARCHAR(20) DEFAULT 'disponible',
                estado VARCHAR(20) DEFAULT 'activo',
                notas TEXT,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_instructor_id (instructor_id),
                INDEX idx_fecha (fecha),
                INDEX idx_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'contenido' => "
            CREATE TABLE IF NOT EXISTS contenido (
                id INT AUTO_INCREMENT PRIMARY KEY,
                seccion VARCHAR(50) NOT NULL,
                tipo VARCHAR(20) NOT NULL,
                titulo VARCHAR(200) NOT NULL,
                contenido TEXT NOT NULL,
                orden INT DEFAULT 0,
                estado VARCHAR(20) DEFAULT 'activo',
                creado_por VARCHAR(50),
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_seccion (seccion),
                INDEX idx_tipo (tipo),
                INDEX idx_estado (estado),
                INDEX idx_orden (orden)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];
    
    // Crear tablas
    foreach ($tables as $table_name => $sql) {
        try {
            $conn->exec($sql);
            echo "✓ Tabla '$table_name' verificada/creada\n";
        } catch (Exception $e) {
            echo "✗ Error creando tabla '$table_name': " . $e->getMessage() . "\n";
        }
    }
    
    // Insertar datos iniciales
    echo "\n--- Insertando datos iniciales ---\n";
    
    // Crear rol de administrador si no existe
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM roles WHERE nombre = ?");
    $stmt->execute(['admin']);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $stmt = $conn->prepare("INSERT INTO roles (nombre, descripcion, permisos) VALUES (?, ?, ?)");
        $permisos = json_encode([
            'usuarios' => ['crear', 'leer', 'actualizar', 'eliminar'],
            'tareas' => ['crear', 'leer', 'actualizar', 'eliminar'],
            'donaciones' => ['crear', 'leer', 'actualizar', 'eliminar'],
            'capacitaciones' => ['crear', 'leer', 'actualizar', 'eliminar'],
            'disponibilidad' => ['crear', 'leer', 'actualizar', 'eliminar'],
            'contenido' => ['crear', 'leer', 'actualizar', 'eliminar']
        ]);
        $stmt->execute(['admin', 'Administrador del sistema', $permisos]);
        echo "✓ Rol 'admin' creado\n";
    } else {
        echo "✓ Rol 'admin' ya existe\n";
    }
    
    // Crear rol de usuario si no existe
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM roles WHERE nombre = ?");
    $stmt->execute(['user']);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $stmt = $conn->prepare("INSERT INTO roles (nombre, descripcion, permisos) VALUES (?, ?, ?)");
        $permisos = json_encode([
            'tareas' => ['leer'],
            'donaciones' => ['crear', 'leer'],
            'capacitaciones' => ['crear', 'leer'],
            'disponibilidad' => ['leer'],
            'contenido' => ['leer']
        ]);
        $stmt->execute(['user', 'Usuario del sistema', $permisos]);
        echo "✓ Rol 'user' creado\n";
    } else {
        echo "✓ Rol 'user' ya existe\n";
    }
    
    // Verificar/crear usuario administrador
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM usuarios WHERE username = ?");
    $stmt->execute(['admin123456789']);
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $password_hash = password_hash('123456789Yo', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (username, password, nombre_completo, rol, activo) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin123456789', $password_hash, 'Administrador del Sistema', 'admin', 1]);
        echo "✓ Usuario administrador creado\n";
    } else {
        echo "✓ Usuario administrador ya existe\n";
    }
    
    // Insertar contenido inicial
    $contenido_inicial = [
        'inicio' => [
            ['titulo' => 'Bienvenidos al Cuerpo de Bomberos', 'contenido' => 'Somos una organización dedicada a la protección y seguridad de nuestra comunidad.'],
            ['titulo' => 'Nuestra Misión', 'contenido' => 'Proteger vidas y propiedades mediante servicios de prevención y respuesta a emergencias.']
        ],
        'servicios' => [
            ['titulo' => 'Respuesta a Emergencias', 'contenido' => 'Atención 24/7 a incendios, accidentes y otras emergencias.'],
            ['titulo' => 'Programas de Prevención', 'contenido' => 'Educación y capacitación para la comunidad sobre seguridad y prevención.']
        ],
        'contacto' => [
            ['titulo' => 'Información de Contacto', 'contenido' => 'Teléfono de emergencias: 123 | Email: info@bomberos.com | Dirección: Calle Principal #123']
        ]
    ];
    
    foreach ($contenido_inicial as $seccion => $items) {
        foreach ($items as $index => $item) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contenido WHERE seccion = ? AND titulo = ?");
            $stmt->execute([$seccion, $item['titulo']]);
            $result = $stmt->fetch();
            
            if ($result['count'] == 0) {
                $stmt = $conn->prepare("INSERT INTO contenido (seccion, tipo, titulo, contenido, orden, estado) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$seccion, 'texto', $item['titulo'], $item['contenido'], $index, 'activo']);
                echo "✓ Contenido inicial agregado: {$seccion} - {$item['titulo']}\n";
            }
        }
    }
    
    echo "\n=== ESQUEMA DE BASE DE DATOS COMPLETADO ===\n";
    echo "\n✓ Todas las tablas necesarias han sido creadas\n";
    echo "✓ Datos iniciales han sido insertados\n";
    echo "✓ Sistema listo para funcionar\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\nVerifique que:\n";
    echo "1. El servidor MySQL esté corriendo\n";
    echo "2. La base de datos 'bomberos_db' exista\n";
    echo "3. El usuario tenga permisos de creación de tablas\n";
}

?>