<?php
/**
 * Configuración principal de la API de Discogs
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

// Configuración de la API
define('API_VERSION', '1.0.0');
define('API_BASE_URL', 'http://dwoosync.com/');
define('API_RATE_LIMIT_ENABLED', true);
define('API_CACHE_ENABLED', true);

// Configuración de Discogs
define('DISCOGS_API_URL', 'https://api.discogs.com/');
define('DISCOGS_USER_AGENT', 'DiscogsImporter/1.0.0');
define('DISCOGS_API_KEY', 'RHwdshtjSotKMFGXAwRe');
define('DISCOGS_API_SECRET', 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ');

// Configuración de seguridad
define('API_SECRET_KEY', 'tu_clave_secreta_muy_segura_aqui_2024');
define('JWT_SECRET', 'tu_jwt_secret_muy_seguro_aqui_2024');
define('ENCRYPTION_KEY', 'tu_clave_de_encriptacion_32_caracteres');

// Configuración de logs
define('LOG_LEVEL', 'info'); // debug, info, warning, error
define('LOG_FILE', __DIR__ . '/../logs/api.log');
define('MAX_LOG_RETENTION_DAYS', 30);

// Configuración de caché
define('CACHE_DEFAULT_TTL', 3600); // 1 hora
define('CACHE_CLEANUP_INTERVAL', 86400); // 24 horas

// Configuración de rate limiting
define('RATE_LIMIT_FREE_HOUR', 100);
define('RATE_LIMIT_FREE_DAY', 1000);
define('RATE_LIMIT_FREE_MONTH', 10000);

define('RATE_LIMIT_PREMIUM_HOUR', 500);
define('RATE_LIMIT_PREMIUM_DAY', 5000);
define('RATE_LIMIT_PREMIUM_MONTH', 50000);

define('RATE_LIMIT_ENTERPRISE_HOUR', 2000);
define('RATE_LIMIT_ENTERPRISE_DAY', 20000);
define('RATE_LIMIT_ENTERPRISE_MONTH', 200000);

// Configuración de CORS
define('CORS_ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:8080'
]);

// Configuración de timezone
date_default_timezone_set('America/Mexico_City');

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOG_FILE);

// Configuración de memoria y tiempo
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

// Headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Configuración de CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, CORS_ALLOWED_ORIGINS)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Access-Control-Max-Age: 86400');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
