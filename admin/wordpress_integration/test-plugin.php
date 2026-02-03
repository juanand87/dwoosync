<?php
/**
 * Archivo de prueba para verificar el funcionamiento del plugin
 * Este archivo debe ser eliminado en producción
 */

// Simular WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../');
}

// Simular funciones de WordPress
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        echo "Hook registrado: $hook\n";
    }
}

if (!function_exists('add_meta_box')) {
    function add_meta_box($id, $title, $callback, $screen, $context, $priority) {
        echo "Metabox agregado: $id\n";
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'test_nonce_' . $action;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        $options = [
            'wdi_api_url' => 'http://localhost/api_discogs/api/index.php',
            'wdi_license_key' => 'test_license_key',
            'wdi_domain' => 'localhost'
        ];
        return $options[$option] ?? $default;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://localhost/wp-admin/' . $path;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        echo "Script encolado: $handle\n";
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
        echo "Estilo encolado: $handle\n";
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://localhost/wp-content/plugins/discogs-sync/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename($file);
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) {
        echo "Textdomain cargado: $domain\n";
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        return 'http://localhost';
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return 'test_nonce_' . $action;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        echo "Hook de activación registrado\n";
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        echo "Hook de desactivación registrado\n";
    }
}

if (!function_exists('add_options_page')) {
    function add_options_page($page_title, $menu_title, $capability, $menu_slug, $function = '') {
        echo "Página de opciones agregada: $menu_slug\n";
    }
}

if (!function_exists('add_meta_box')) {
    function add_meta_box($id, $title, $callback, $screen, $context, $priority) {
        echo "Metabox agregado: $id\n";
    }
}

if (!function_exists('wp_ajax_wdi_fetch_discogs_data')) {
    function wp_ajax_wdi_fetch_discogs_data() {
        echo "Acción AJAX registrada: wdi_fetch_discogs_data\n";
    }
}

if (!function_exists('wp_ajax_wdi_test_api_connection')) {
    function wp_ajax_wdi_test_api_connection() {
        echo "Acción AJAX registrada: wdi_test_api_connection\n";
    }
}

if (!function_exists('wp_ajax_wdi_validate_license')) {
    function wp_ajax_wdi_validate_license() {
        echo "Acción AJAX registrada: wdi_validate_license\n";
    }
}

if (!function_exists('wp_ajax_wdi_create_subscription')) {
    function wp_ajax_wdi_create_subscription() {
        echo "Acción AJAX registrada: wdi_create_subscription\n";
    }
}

echo "=== PRUEBA DEL PLUGIN DISCOGS SYNC ===\n\n";

// Incluir el plugin
require_once 'discogs-sync.php';

echo "\n=== PLUGIN CARGADO EXITOSAMENTE ===\n";
echo "El metabox se mostrará en la página de edición de productos de WooCommerce\n";
echo "El mensaje de advertencia del período de gracia se mostrará cuando corresponda\n";
?>
