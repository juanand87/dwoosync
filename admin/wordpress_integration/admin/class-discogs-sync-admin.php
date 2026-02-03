<?php
/**
 * Clase de administración del plugin Discogs Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class Discogs_Sync_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
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
    
    public function admin_init() {
        // Registrar configuraciones
        register_setting('discogs_sync_settings', 'discogs_sync_api_url');
        register_setting('discogs_sync_settings', 'discogs_sync_license_key');
        register_setting('discogs_sync_settings', 'discogs_sync_domain');
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Discogs Sync - Configuración</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('discogs_sync_settings'); ?>
                <?php do_settings_sections('discogs_sync_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">URL de la API</th>
                        <td>
                            <input type="url" name="discogs_sync_api_url" value="<?php echo esc_attr(get_option('discogs_sync_api_url')); ?>" class="regular-text" />
                            <p class="description">URL de la API de dwoosync (ej: https://api.dwoosync.com)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Clave de Licencia</th>
                        <td>
                            <input type="text" name="discogs_sync_license_key" value="<?php echo esc_attr(get_option('discogs_sync_license_key')); ?>" class="regular-text" />
                            <p class="description">Tu clave de licencia de dwoosync</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Dominio</th>
                        <td>
                            <input type="text" name="discogs_sync_domain" value="<?php echo esc_attr(get_option('discogs_sync_domain', $_SERVER['HTTP_HOST'])); ?>" class="regular-text" />
                            <p class="description">Dominio registrado en tu licencia</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="card">
                <h2>Instrucciones de Uso</h2>
                <ol>
                    <li>Configura la URL de la API, tu clave de licencia y el dominio</li>
                    <li>Guarda la configuración</li>
                    <li>Ve a la página de productos de WooCommerce</li>
                    <li>Busca productos de Discogs usando el metabox "Discogs Sync"</li>
                    <li>Importa los productos seleccionados</li>
                </ol>
            </div>
        </div>
        <?php
    }
}



