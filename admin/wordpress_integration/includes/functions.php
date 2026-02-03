<?php
/**
 * Funciones auxiliares del plugin Discogs Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Obtener la URL de la API configurada
 */
function discogs_sync_get_api_url() {
    return discogs_sync_get_config('api_url', DISCogs_SYNC_DEFAULT_API_URL);
}

/**
 * Obtener la clave de licencia configurada
 */
function discogs_sync_get_license_key() {
    return discogs_sync_get_config('license_key', DISCogs_SYNC_DEFAULT_LICENSE_KEY);
}

/**
 * Obtener el dominio configurado
 */
function discogs_sync_get_domain() {
    return discogs_sync_get_config('domain', DISCogs_SYNC_DEFAULT_DOMAIN);
}

/**
 * Validar si la configuración está completa
 */
function discogs_sync_is_configured() {
    $api_url = discogs_sync_get_api_url();
    $license_key = discogs_sync_get_license_key();
    $domain = discogs_sync_get_domain();
    
    return !empty($api_url) && !empty($license_key) && !empty($domain);
}

/**
 * Log de errores del plugin
 */
function discogs_sync_log($message, $level = 'info') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Discogs Sync] ' . $level . ': ' . $message);
    }
}



