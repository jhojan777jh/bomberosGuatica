-- Base de datos para el Sistema del Cuerpo de Bomberos
-- Creado: 2026-02-06

CREATE DATABASE IF NOT EXISTS bomberos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bomberos_db;

-- Tabla de roles del sistema
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT NULL,
    permisos JSON NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de usuarios administrativos
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    rol_id INT NULL,
    rol ENUM('admin', 'editor', 'viewer', 'instructor') DEFAULT 'viewer',
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME NULL,
    creado_por INT NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla de tareas administrativas
CREATE TABLE IF NOT EXISTS tareas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    prioridad ENUM('alta', 'media', 'baja') DEFAULT 'media',
    estado ENUM('pendiente', 'en_progreso', 'completada') DEFAULT 'pendiente',
    fecha_limite DATE NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_a INT NULL,
    creado_por INT NOT NULL,
    FOREIGN KEY (asignado_a) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabla de donaciones
CREATE TABLE IF NOT EXISTS donaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_donante VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NULL,
    monto DECIMAL(10,2) NOT NULL,
    tipo_donacion ENUM('unica', 'mensual') DEFAULT 'unica',
    metodo_pago ENUM('tarjeta', 'transferencia', 'efectivo') DEFAULT 'tarjeta',
    estado ENUM('pendiente', 'completada', 'fallida') DEFAULT 'pendiente',
    mensaje TEXT NULL,
    fecha_donacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    referencia_pago VARCHAR(100) NULL
);

-- Tabla de galería de imágenes
CREATE TABLE IF NOT EXISTS galeria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    ruta_imagen VARCHAR(255) NOT NULL,
    categoria ENUM('general', 'capacitacion', 'emergencias', 'equipos') DEFAULT 'general',
    activa BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    orden INT DEFAULT 0
);

-- Tabla de contenido del sitio
CREATE TABLE IF NOT EXISTS contenido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seccion VARCHAR(50) NOT NULL,
    clave VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    tipo_contenido ENUM('texto', 'html', 'imagen') DEFAULT 'texto',
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por INT NULL,
    UNIQUE KEY unique_seccion_clave (seccion, clave),
    FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla de estadísticas
CREATE TABLE IF NOT EXISTS estadisticas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_estadistica VARCHAR(50) NOT NULL,
    valor INT NOT NULL,
    fecha_registro DATE NOT NULL,
    descripcion VARCHAR(200) NULL,
    UNIQUE KEY unique_tipo_fecha (tipo_estadistica, fecha_registro)
);

-- Tabla de contactos/recibidos
CREATE TABLE IF NOT EXISTS contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NULL,
    asunto VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    estado ENUM('nuevo', 'leido', 'respondido') DEFAULT 'nuevo',
    fecha_contacto DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_respuesta DATETIME NULL
);

-- Tabla de inscripciones a capacitaciones
CREATE TABLE IF NOT EXISTS capacitaciones_inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_completo VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    edad INT NOT NULL,
    tipo_capacitacion ENUM('bombero_basico', 'rescate_vehicular', 'incendios_estructurales', 'primeros_auxilios', 'manejo_materiales') NOT NULL,
    fecha_preferida DATE NOT NULL,
    horario_preferido ENUM('matutino', 'vespertino', 'nocturno', 'fin_semana') NOT NULL,
    nivel_experiencia ENUM('principiante', 'intermedio', 'avanzado') DEFAULT 'principiante',
    mensaje TEXT NULL,
    estado ENUM('pendiente', 'confirmada', 'cancelada', 'completada') DEFAULT 'pendiente',
    fecha_inscripcion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_confirmacion DATETIME NULL,
    observaciones_admin TEXT NULL
);

-- Tabla de programación de capacitaciones
CREATE TABLE IF NOT EXISTS capacitaciones_programadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_capacitacion ENUM('bombero_basico', 'rescate_vehicular', 'incendios_estructurales', 'primeros_auxilios', 'manejo_materiales') NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    horario ENUM('matutino', 'vespertino', 'nocturno', 'fin_semana') NOT NULL,
    instructor_asignado INT NULL,
    cupo_maximo INT DEFAULT 20,
    cupo_actual INT DEFAULT 0,
    estado ENUM('programada', 'en_curso', 'finalizada', 'cancelada') DEFAULT 'programada',
    salon VARCHAR(50) NULL,
    observaciones TEXT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_asignado) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- Tabla de disponibilidad del personal
CREATE TABLE IF NOT EXISTS disponibilidad_personal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    fecha DATE NOT NULL,
    turno ENUM('matutino', 'vespertino', 'nocturno', 'completo') NOT NULL,
    estado ENUM('disponible', 'no_disponible', 'capacitacion', 'vacaciones', 'emergencia') DEFAULT 'disponible',
    motivo VARCHAR(200) NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_usuario_fecha_turno (id_usuario, fecha, turno)
);

-- Tabla de asignación de personal a capacitaciones
CREATE TABLE IF NOT EXISTS capacitaciones_personal_asignado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_capacitacion INT NOT NULL,
    id_usuario INT NOT NULL,
    rol ENUM('instructor', 'ayudante', 'coordinador') NOT NULL,
    estado ENUM('asignado', 'confirmado', 'cancelado') DEFAULT 'asignado',
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT NULL,
    FOREIGN KEY (id_capacitacion) REFERENCES capacitaciones_programadas(id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY unique_capacitacion_usuario (id_capacitacion, id_usuario)
);

-- Insertar roles por defecto
INSERT INTO roles (nombre, descripcion, permisos) VALUES
('admin', 'Administrador del sistema con acceso completo', '{"users": ["create", "read", "update", "delete"], "content": ["create", "read", "update", "delete"], "training": ["create", "read", "update", "delete"], "availability": ["create", "read", "update", "delete"], "donations": ["read", "update"], "stats": ["read"]}'),
('editor', 'Editor con permisos de contenido y capacitaciones', '{"content": ["create", "read", "update"], "training": ["create", "read", "update"], "availability": ["read"], "donations": ["read"], "stats": ["read"]}'),
('viewer', 'Lector con permisos de solo lectura', '{"content": ["read"], "training": ["read"], "availability": ["read"], "donations": ["read"], "stats": ["read"]}'),
('instructor', 'Instructor con acceso a capacitaciones y disponibilidad', '{"training": ["read", "update"], "availability": ["read", "update"], "stats": ["read"]}')
ON DUPLICATE KEY UPDATE nombre = nombre;

-- Insertar usuarios por defecto
INSERT INTO usuarios (username, password, email, nombre_completo, rol, rol_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@bomberos.com', 'Administrador del Sistema', 'admin', 1),
('admin123456789', '$2y$10$ABC123ABC123ABC123ABC123ABC123ABC123ABC123ABC123ABC123AB', 'admin@bomberos.com', 'Administrador Principal', 'admin', 1),
('editor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'editor@bomberos.com', 'Editor de Contenido', 'editor', 2),
('instructor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'instructor@bomberos.com', 'Instructor de Capacitaciones', 'instructor', 4)
ON DUPLICATE KEY UPDATE username = username;

-- Insertar contenido inicial del sitio
INSERT INTO contenido (seccion, clave, valor, tipo_contenido) VALUES
('inicio', 'titulo_principal', 'Cuerpo de Bomberos', 'texto'),
('inicio', 'descripcion_principal', 'Protección de la vida, los bienes y el medio ambiente', 'texto'),
('quienes', 'descripcion', 'El Cuerpo de Bomberos es una institución encargada de la gestión integral del riesgo contra incendios, rescate, atención de emergencias y educación preventiva.', 'texto'),
('objetivo', 'descripcion', 'Fortalecer nuestra capacidad operativa mediante la adquisición de radios de intercomunicación y equipos esenciales.', 'texto'),
('contacto', 'direccion', 'Calle 12 #9', 'texto'),
('contacto', 'telefono', '311 429 04 11', 'texto'),
('contacto', 'email', 'Bomberos@bomberos.com', 'texto')
ON DUPLICATE KEY UPDATE seccion = seccion;

-- Insertar estadísticas iniciales
INSERT INTO estadisticas (tipo_estadistica, valor, fecha_registro, descripcion) VALUES
('personal', 45, CURDATE(), 'Total de bomberos activos'),
('vehiculos', 8, CURDATE(), 'Total de vehículos disponibles'),
('emergencias_mes', 156, CURDATE(), 'Emergencias atendidas este mes'),
('capacitaciones_programadas', 12, CURDATE(), 'Capacitaciones programadas')
ON DUPLICATE KEY UPDATE tipo_estadistica = tipo_estadistica;

-- Insertar imágenes iniciales en la galería
INSERT INTO galeria (titulo, descripcion, ruta_imagen, categoria, orden) VALUES
('Cuerpo de Bomberos', 'Compromiso, valor y dedicación al servicio de la comunidad', 'imagenes/bomberos.jpeg', 'general', 1),
('Atención de Emergencias', 'Respuesta rápida y profesional ante situaciones de riesgo', 'imagenes/capacitacion.jpeg', 'emergencias', 2),
('Capacitación Bomberil', 'Formación continua y especializada', 'imagenes/capacitacion.jpeg', 'capacitacion', 3),
('Rescate y Salvamento', 'Técnicas avanzadas de rescate', 'imagenes/bomberos.jpeg', 'general', 4)
ON DUPLICATE KEY UPDATE titulo = titulo;