<?php
// ============================================
// admin/config.php
// ============================================

// Detectar entorno automáticamente
$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])
        || str_starts_with($_SERVER['SERVER_ADDR'] ?? '', '192.168.');

if ($isLocal) {
    // Base de datos local XAMPP
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'u249540203_antal24');
    define('SITE_URL', 'http://localhost/antal24');
} else {
    // Base de datos Producción (Hostinger)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u249540203_adminantal');
    define('DB_PASS', 'Antal2026!!');
    define('DB_NAME', 'u249540203_antal24');
    define('SITE_URL', 'https://antal24.com');
}

// Contraseña para acceder al dashboard
define('ADMIN_PASSWORD', 'antal2026admin');