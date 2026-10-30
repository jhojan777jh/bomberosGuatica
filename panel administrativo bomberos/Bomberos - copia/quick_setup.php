<?php
/**
 * Script de configuración rápida del administrador
 * Resuelve problemas comunes de acceso
 */

// Configuración de la base de datos
$host = 'localhost';
$db_name = 'bomberos_db';
$username = 'root';
$password = '';

echo "=== CONFIGURACIÓN RÁPIDA DEL ADMINISTRADOR ===\n\n";

try {
    // 1. Conectar a MySQL
    echo "1. Conectando a MySQL...\n";
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Conexión exitosa\n";
    
    // 2. Crear base de datos si no existe
    echo "\n2. Verificando/creando base de datos...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $db_name");
    echo "✓ Base de datos '$db_name' lista\n";
    
    // 3. Crear tabla de usuarios si no existe
    echo "\n3. Verificando/creando tabla de usuarios...\n";
    $createUsersTable = "
    CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        nombre_completo VARCHAR(100) NOT NULL,
        rol ENUM('admin', 'editor', 'viewer', 'instructor') DEFAULT 'viewer',
        rol_id INT NULL,
        activo BOOLEAN DEFAULT TRUE,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        ultimo_acceso DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($createUsersTable);
    echo "✓ Tabla 'usuarios' creada/verificada\n";
    
    // 4. Crear/actualizar administrador
    echo "\n4. Creando/actualizando administrador...\n";
    
    // Generar hash para la contraseña "123456789Yo"
    $password = '123456789Yo';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Verificar si el usuario ya existe
    $checkQuery = "SELECT id FROM usuarios WHERE username IN ('admin', 'admin123456789')";
    $checkStmt = $pdo->query($checkQuery);
    $existingUser = $checkStmt->fetch();
    
    if ($existingUser) {
        // Actualizar usuario existente
        $updateQuery = "UPDATE usuarios SET 
                      username = 'admin123456789',
                      password = :password,
                      email = 'admin@bomberos.com',
                      nombre_completo = 'Administrador Principal',
                      rol = 'admin',
                      activo = 1
                      WHERE username IN ('admin', 'admin123456789')";
        
        $stmt = $pdo->prepare($updateQuery);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->execute();
        echo "✓ Administrador actualizado\n";
    } else {
        // Crear nuevo administrador
        $insertQuery = "INSERT INTO usuarios 
                      (username, password, email, nombre_completo, rol, activo) 
                      VALUES 
                      ('admin123456789', :password, 'admin@bomberos.com', 'Administrador Principal', 'admin', 1)";
        
        $stmt = $pdo->prepare($insertQuery);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->execute();
        echo "✓ Administrador creado\n";
    }
    
    // 5. Verificar el administrador
    echo "\n5. Verificando administrador...\n";
    $verifyQuery = "SELECT id, username, nombre_completo, rol, activo FROM usuarios WHERE username = 'admin123456789'";
    $verifyStmt = $pdo->query($verifyQuery);
    $admin = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "✓ Administrador verificado:\n";
        echo "  - ID: {$admin['id']}\n";
        echo "  - Usuario: {$admin['username']}\n";
        echo "  - Nombre: {$admin['nombre_completo']}\n";
        echo "  - Rol: {$admin['rol']}\n";
        echo "  - Estado: " . ($admin['activo'] ? 'Activo' : 'Inactivo') . "\n";
        
        // Probar autenticación
        echo "\n6. Probando autenticación...\n";
        $authQuery = "SELECT id, username, password, nombre_completo, rol, activo FROM usuarios WHERE username = 'admin123456789' AND activo = 1";
        $authStmt = $pdo->prepare($authQuery);
        $authStmt->execute();
        $authUser = $authStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($authUser && password_verify($password, $authUser['password'])) {
            echo "✓ Autenticación exitosa\n";
        } else {
            echo "✗ Error en autenticación\n";
        }
    } else {
        echo "✗ Error: No se pudo verificar el administrador\n";
    }
    
    echo "\n=== CONFIGURACIÓN COMPLETADA ===\n";
    echo "\n🎯 CREDENCIALES DE ACCESO:\n";
    echo "URL: admin.html\n";
    echo "Usuario: admin123456789\n";
    echo "Contraseña: 123456789Yo\n";
    echo "Rol: Administrador\n";
    echo "\n✅ Ahora puedes acceder al sistema administrativo\n";
    
} catch(PDOException $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\nSoluciones posibles:\n";
    echo "1. Asegúrate de que MySQL esté corriendo\n";
    echo "2. Verifica que el usuario 'root' tenga contraseña\n";
    echo "3. Ejecuta este script con los permisos correctos\n";
}
?>