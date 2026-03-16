<?php
/**
 * Valírica — Configuración de Base de Datos (PLANTILLA)
 *
 * INSTRUCCIONES:
 * 1. Copia este archivo como "config.php"
 * 2. Reemplaza los valores con tus credenciales reales de cPanel
 * 3. NUNCA subas config.php al repositorio (está en .gitignore)
 */

define('DB_HOST', 'localhost');       // Generalmente 'localhost' en cPanel
define('DB_USER', 'tu_usuario_db');   // Usuario de la base de datos
define('DB_PASS', 'tu_contraseña');   // Contraseña de la base de datos
define('DB_NAME', 'tu_nombre_db');    // Nombre de la base de datos

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log('DB connection failed: ' . $conn->connect_error);
    http_response_code(503);
    exit('Error de conexión a la base de datos. Por favor intenta más tarde.');
}

$conn->set_charset('utf8mb4');
