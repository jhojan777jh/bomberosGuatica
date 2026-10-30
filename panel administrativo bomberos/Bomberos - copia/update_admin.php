<?php
/**
 * Script rápido para actualizar el administrador existente
 * Ejecutar este archivo después de tener la base de datos funcionando
 */

// Configuración
$host = 'localhost';
$db_name = 'bomberos_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Hash para "123456789Yo"
    $newPassword = '123456789Yo';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    echo "Actualizando administrador...\n";
    echo "Nueva contraseña: 123456789Yo\n";
    echo "Hash: $hashedPassword\n\n";
    
    // Actualizar el usuario admin existente
    $query = "UPDATE usuarios SET 
              password = :password,
              username = 'admin123456789'
              WHERE username = 'admin' OR username = 'admin123456789'";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':password', $hashedPassword);
    
    if ($stmt->execute()) {
        echo "✓ Administrador actualizado exitosamente\n\n";
        echo "=== NUEVAS CREDENCIALES ===\n";
        echo "Usuario: admin123456789\n";
        echo "Contraseña: 123456789Yo\n";
        echo "========================\n\n";
        echo "Acceso: admin.html\n";
    } else {
        echo "✗ Error al actualizar\n";
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Asegúrate de que la base de datos exista y esté configurada.\n";
}
?>