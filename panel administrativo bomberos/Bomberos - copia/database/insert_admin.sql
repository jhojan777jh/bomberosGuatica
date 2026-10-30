-- Insertar administrador con credenciales específicas
-- Usuario: admin123456789
-- Contraseña: 123456789Yo
-- Rol: admin

-- Generar hash para "123456789Yo" (esto debe hacerse con PHP real)
-- Por ahora, usaremos un hash temporal que deberás actualizar

INSERT INTO usuarios (username, password, email, nombre_completo, rol, rol_id, activo) 
VALUES ('admin123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@bomberos.com', 'Administrador Principal', 'admin', 1, 1)
ON DUPLICATE KEY UPDATE 
password = VALUES(password),
nombre_completo = VALUES(nombre_completo),
rol = VALUES(rol),
rol_id = VALUES(rol_id),
activo = VALUES(activo);

-- Nota: Debes ejecutar el siguiente comando PHP para generar el hash correcto:
-- <?php echo password_hash('123456789Yo', PASSWORD_DEFAULT); ?>
-- Y reemplazar el hash en la consulta anterior con el resultado.