<?php
/**
 * Clase principal del plugin Discogs Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class Discogs_Sync {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
    }
    
    public function init() {
        // Inicializar el plugin
        $this->load_textdomain();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('discogs-sync', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        // Encolar scripts del frontend si es necesario
    }
    
    public function admin_enqueue_scripts() {
        // Encolar scripts del admin
        wp_enqueue_script('discogs-sync-admin', DISCogs_SYNC_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), DISCogs_SYNC_VERSION, true);
        wp_enqueue_style('discogs-sync-admin', DISCogs_SYNC_PLUGIN_URL . 'admin/css/admin.css', array(), DISCogs_SYNC_VERSION);
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Discogs Sync',
            'Discogs Sync',
            'manage_options',
            'discogs-sync',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        // Incluir la página de configuración existente
        include_once DISCogs_SYNC_PLUGIN_DIR . 'wdi-plugin-config.php';
        wdi_admin_page();
    }
}



