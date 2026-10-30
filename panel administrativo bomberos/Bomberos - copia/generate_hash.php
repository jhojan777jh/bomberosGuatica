<?php
// Script para generar el hash de la contraseña "123456789Yo"
$password = "123456789Yo";
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Contraseña: " . $password . "\n";
echo "Hash: " . $hash . "\n";
echo "Verificación: " . (password_verify($password, $hash) ? "Correcta" : "Incorrecta") . "\n";
?>