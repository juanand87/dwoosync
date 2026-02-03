<?php
/**
 * Archivo de configuración del plugin Discogs Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

// Configuración por defecto
define('DISCogs_SYNC_DEFAULT_API_URL', 'https://api.dwoosync.com');
define('DISCogs_SYNC_DEFAULT_LICENSE_KEY', '');
define('DISCogs_SYNC_DEFAULT_DOMAIN', $_SERVER['HTTP_HOST'] ?? 'localhost');

// Obtener configuración de la base de datos
function discogs_sync_get_config($key, $default = '') {
    return get_option('discogs_sync_' . $key, $default);
}

// Guardar configuración
function discogs_sync_save_config($key, $value) {
    return update_option('discogs_sync_' . $key, $value);
}



