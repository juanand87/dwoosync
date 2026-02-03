<?php
/**
 * Configuración de base de datos para la API de Discogs
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

// Prevenir acceso directo
if (!defined('API_ACCESS')) {
    die('Acceso directo no permitido');
}

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'dwoosync_bd');
define('DB_USER', 'dwoosync_user');
define('DB_PASS', '5802863aA$$');
define('DB_CHARSET', 'utf8mb4');

// Crear conexión PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Error de conexión a la base de datos: ' . $e->getMessage());
    die('Error de conexión a la base de datos');
}
?>

