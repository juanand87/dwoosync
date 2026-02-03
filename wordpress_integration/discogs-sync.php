<?php
/**
 * Plugin Name: Discogs Sync
 * Plugin URI: https://www.dwoosync.com
 * Description: Plugin para sincronizar productos de Discogs con WooCommerce
 * Version: 1.0.0
 * Author: dwoosync
 * License: GPL v2 or later
 * Text Domain: discogs-sync
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('DISCogs_SYNC_VERSION', '1.0.0');
define('DISCogs_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DISCogs_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Incluir archivos necesarios
require_once DISCogs_SYNC_PLUGIN_DIR . 'includes/class-discogs-sync.php';
require_once DISCogs_SYNC_PLUGIN_DIR . 'admin/class-discogs-sync-admin.php';
require_once DISCogs_SYNC_PLUGIN_DIR . 'includes/discogs-metabox.php';
require_once DISCogs_SYNC_PLUGIN_DIR . 'wdi-ajax-handler.php';
require_once DISCogs_SYNC_PLUGIN_DIR . 'includes/real-time-warning-integration.php';

// Inicializar el plugin
function discogs_sync_init() {
    new Discogs_Sync();
}
add_action('plugins_loaded', 'discogs_sync_init');

// Hook de activación
register_activation_hook(__FILE__, 'discogs_sync_activate');
function discogs_sync_activate() {
    // Crear tablas necesarias si no existen
    discogs_sync_create_tables();
}

// Hook de desactivación
register_deactivation_hook(__FILE__, 'discogs_sync_deactivate');
function discogs_sync_deactivate() {
    // Limpiar datos temporales si es necesario
}

// Crear tablas de la base de datos
function discogs_sync_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabla para configuración del plugin
    $table_name = $wpdb->prefix . 'discogs_sync_config';
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        config_key varchar(100) NOT NULL,
        config_value text NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY config_key (config_key)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}



