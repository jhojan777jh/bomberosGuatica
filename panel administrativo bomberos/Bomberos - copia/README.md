# Sistema de Gestión del Cuerpo de Bomberos

## 🚋 Descripción General

Sistema completo de gestión administrativa para el Cuerpo de Bomberos, incluyendo gestión de usuarios, capacitaciones, disponibilidad, donaciones y contenido del sitio web.

## 🎯 Características Principales

### 🔐 **Sistema de Autenticación**
- Login seguro con roles y permisos
- Gestión de usuarios y roles
- Registro de nuevos usuarios
- Middleware de autenticación por recursos

### 👥 **Gestión de Usuarios**
- Crear, editar, eliminar usuarios
- Asignación de roles personalizados
- Control de permisos granular
- Estadísticas de usuarios

### 📅 **Gestión de Capacitaciones**
- Programación de cursos
- Control de cupos
- Asignación de instructores
- Seguimiento de inscripciones

### 📊 **Disponibilidad de Capacitaciones**
- Calendario de capacitaciones
- Gestión de fechas y horarios
- Asignación de personal
- Estadísticas en tiempo real

### 💰 **Gestión de Donaciones**
- Procesamiento de donaciones
- Seguimiento de estados
- Reportes y estadísticas

### 📝 **Gestión de Contenido**
- Edición de contenido del sitio
- Gestión de galería
- Actualización de información

## 🏗️ **Arquitectura del Sistema**

### **Backend (PHP)**
- **API RESTful** con endpoints bien estructurados
- **Base de Datos MySQL** con relaciones optimizadas
- **Sesiones seguras** con manejo de estados
- **Middleware de autenticación** por roles y permisos
- **Validaciones robustas** en todos los controladores

### **Frontend (HTML/JavaScript)**
- **Bootstrap 5** para diseño responsivo
- **JavaScript moderno** con async/await
- **Gráficos dinámicos** con Chart.js
- **Interfaces intuitivas** con UX optimizada
- **Notificaciones en tiempo real**

## 📁 **Estructura de Archivos**

```
Bomberos - copia/
├── api/
│   ├── index.php                 # Router principal
│   ├── config/
│   │   ├── config.php           # Configuración general
│   │   └── Database.php        # Conexión a BD
│   └── controllers/
│       ├── AuthController.php   # Autenticación
│       ├── UserController.php    # Gestión de usuarios
│       ├── AvailabilityController.php # Disponibilidad
│       ├── TrainingController.php # Capacitaciones
│       ├── DonationController.php # Donaciones
│       ├── ContentController.php # Contenido
│       └── BaseController.php   # Clase base
├── database/
│   └── schema.sql               # Estructura de BD
├── admin.html                   # Login principal
├── admin-users.html            # Gestión de usuarios
├── admin-availability.html     # Gestión de disponibilidad
├── register.html                # Registro de usuarios
├── dashboard.html               # Dashboard de voluntariado
├── index.html                   # Sitio público
└── imagenes/                    # Recursos gráficos
```

## Instalación

### 1. Configurar Base de Datos

```sql
-- Crear base de datos
mysql -u root -p < database/schema.sql
```

### 2. Configurar Conexión

Editar `api/config/Database.php` con tus credenciales:

```php
private $host = 'localhost';
private $db_name = 'bomberos_db';
private $username = 'tu_usuario';
private $password = 'tu_contraseña';
```

### 3. Configurar Servidor Web

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ api/index.php/$1 [L]
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /api/index.php?$query_string;
}
```

### 4. Permisos

```bash
chmod 755 api/
chmod 755 uploads/
```

## API Endpoints

### Autenticación
- `POST /api/auth/login` - Iniciar sesión
- `POST /api/auth/logout` - Cerrar sesión
- `POST /api/auth/check` - Verificar estado

### Contenido
- `GET /api/content/get` - Obtener contenido
- `POST /api/content/update` - Actualizar contenido
- `GET /api/content/stats` - Obtener estadísticas

### Tareas
- `GET /api/tasks/get` - Listar tareas
- `POST /api/tasks/create` - Crear tarea
- `PUT /api/tasks/update/{id}` - Actualizar tarea
- `DELETE /api/tasks/delete/{id}` - Eliminar tarea

### Donaciones
- `POST /api/donations/process` - Procesar donación
- `GET /api/donations/get` - Listar donaciones (admin)
- `GET /api/donations/stats` - Estadísticas de donaciones

## 🔑 **Credenciales de Acceso**

### **Usuarios por Defecto**
| Usuario | Contraseña | Rol | Descripción |
|---------|------------|-----|-------------|
| **admin123456789** | **123456789Yo** | admin | Administrador completo (NUEVO) |
| admin | password | admin | Administrador completo |
| editor | password | editor | Gestión de contenido |
| instructor | password | instructor | Capacitaciones |

### **🚀 Crear Administrador Principal**

Para crear el administrador con las credenciales especificadas:

1. **Ejecutar el script PHP:**
   ```bash
   php create_admin.php
   ```

2. **O ejecutar manualmente el SQL:**
   ```sql
   -- Primero genera el hash con PHP:
   <?php echo password_hash('123456789Yo', PASSWORD_DEFAULT); ?>
   
   -- Luego actualiza la base de datos con el hash generado
   ```

### **🎯 Acceso Rápido**
- **URL:** `admin.html`
- **Usuario:** `admin123456789`
- **Contraseña:** `123456789Yo`
- **Rol:** Administrador (acceso completo)

## 📖 **Guía de Uso**

### **1. Acceso al Sistema**
1. Abrir `admin.html` en el navegador
2. Iniciar sesión con las credenciales
3. Se mostrará el panel administrativo

### **2. Gestión de Usuarios**
1. Ir a la pestaña "Usuarios"
2. Hacer clic en "Ir a Gestión de Usuarios"
3. Crear, editar o eliminar usuarios
4. Configurar roles y permisos

### **3. Gestión de Capacitaciones**
1. Ir a la pestaña "Disponibilidad"
2. Hacer clic en "Ir a Gestión de Disponibilidad"
3. Programar nuevas capacitaciones
4. Asignar instructores y personal

### **4. Registro de Nuevos Usuarios**
1. En la página de login, hacer clic en "Registrarse"
2. Completar el formulario de registro
3. Se creará la cuenta con el rol seleccionado

*Importante: Cambia las credenciales en producción*

## Seguridad

- Contraseñas hasheadas con bcrypt
- Tokens CSRF para formularios
- Validación de entrada de datos
- Prevención de SQL Injection con PDO
- Sesiones seguras

## Tecnologías

- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend:** PHP 8+, MySQL
- **API:** RESTful con JSON
- **Seguridad:** bcrypt, PDO, CSRF tokens

## Soporte

Para soporte técnico o reportar problemas, contacta al equipo de desarrollo.

---

© 2026 Cuerpo de Bomberos - Todos los derechos reservados