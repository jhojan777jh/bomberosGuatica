<?php
/**
 * Script para crear el administrador con las credenciales especificadas
 * Usuario: admin123456789
 * Contraseña: 123456789Yo
 */

// Configuración de la base de datos
$host = 'localhost';
$db_name = 'bomberos_db';
$username = 'root';
$password = '';

try {
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Generar hash para la contraseña
    $plainPassword = '123456789Yo';
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    
    echo "=== Creando Administrador ===\n";
    echo "Usuario: admin123456789\n";
    echo "Contraseña: 123456789Yo\n";
    echo "Hash generado: " . $hashedPassword . "\n";
    echo "Verificación: " . (password_verify($plainPassword, $hashedPassword) ? "✓ Correcta" : "✗ Incorrecta") . "\n\n";
    
    // Verificar si el usuario ya existe
    $checkQuery = "SELECT id FROM usuarios WHERE username = 'admin123456789'";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute();
    $existingUser = $checkStmt->fetch();
    
    if ($existingUser) {
        // Actualizar usuario existente
        $query = "UPDATE usuarios SET 
                  password = :password, 
                  nombre_completo = 'Administrador Principal', 
                  rol = 'admin', 
                  rol_id = 1, 
                  activo = 1 
                  WHERE username = 'admin123456789'";
        
        echo "Actualizando usuario existente...\n";
    } else {
        // Insertar nuevo usuario
        $query = "INSERT INTO usuarios 
                  (username, password, email, nombre_completo, rol, rol_id, activo, fecha_creacion) 
                  VALUES 
                  ('admin123456789', :password, 'admin@bomberos.com', 'Administrador Principal', 'admin', 1, 1, NOW())";
        
        echo "Creando nuevo usuario...\n";
    }
    
    // Ejecutar la consulta
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':password', $hashedPassword);
    
    if ($stmt->execute()) {
        echo "✓ Administrador creado/actualizado exitosamente\n";
        
        // Verificar el usuario creado
        $verifyQuery = "SELECT id, username, nombre_completo, rol, activo, fecha_creacion FROM usuarios WHERE username = 'admin123456789'";
        $verifyStmt = $pdo->prepare($verifyQuery);
        $verifyStmt->execute();
        $user = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "\n=== Datos del Administrador Creado ===\n";
            echo "ID: " . $user['id'] . "\n";
            echo "Usuario: " . $user['username'] . "\n";
            echo "Nombre: " . $user['nombre_completo'] . "\n";
            echo "Rol: " . $user['rol'] . "\n";
            echo "Estado: " . ($user['activo'] ? 'Activo' : 'Inactivo') . "\n";
            echo "Fecha de creación: " . $user['fecha_creacion'] . "\n";
        }
        
        echo "\n=== Instrucciones de Acceso ===\n";
        echo "1. Abre admin.html en tu navegador\n";
        echo "2. Usuario: admin123456789\n";
        echo "3. Contraseña: 123456789Yo\n";
        echo "4. Tendrás acceso completo como administrador\n";
        
    } else {
        echo "✗ Error al crear/actualizar el administrador\n";
        echo "Error: " . implode(', ', $stmt->errorInfo()) . "\n";
    }
    
} catch(PDOException $e) {
    echo "✗ Error de conexión a la base de datos: " . $e->getMessage() . "\n";
    echo "Asegúrate de que la base de datos 'bomberos_db' exista y que las credenciales sean correctas.\n";
}
?>