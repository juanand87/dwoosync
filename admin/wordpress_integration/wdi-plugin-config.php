<?php
/**
 * Configuración del plugin de WordPress
 * Este archivo debe ser incluido en el plugin principal
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registrar opciones del plugin
 */
function wdi_register_settings() {
    register_setting('wdi_settings', 'wdi_api_url');
    register_setting('wdi_settings', 'wdi_license_key');
    register_setting('wdi_settings', 'wdi_domain');
    register_setting('wdi_settings', 'wdi_discogs_api_key');
    register_setting('wdi_settings', 'wdi_discogs_api_secret');
}

add_action('admin_init', 'wdi_register_settings');

/**
 * Página de configuración del plugin
 */
function wdi_admin_page() {
    ?>
    <div class="wrap">
        <h1>Configuración de Discogs Importer</h1>
        
        <form method="post" action="options.php">
            <?php settings_fields('wdi_settings'); ?>
            <?php do_settings_sections('wdi_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">URL de la API</th>
                    <td>
                        <input type="url" name="wdi_api_url" value="<?php echo esc_attr(get_option('wdi_api_url', 'http://localhost/api_discogs/api/index.php')); ?>" class="regular-text" />
                        <p class="description">URL base de la API de Discogs Importer</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">License Key</th>
                    <td>
                        <input type="text" name="wdi_license_key" value="<?php echo esc_attr(get_option('wdi_license_key')); ?>" class="regular-text" />
                        <button type="button" id="validate-license" class="button">Validar Licencia</button>
                        <div id="license-validation-status"></div>
                        <p class="description">Tu clave de licencia para acceder a la API</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Dominio</th>
                    <td>
                        <input type="text" name="wdi_domain" value="<?php echo esc_attr(get_option('wdi_domain', get_site_url())); ?>" class="regular-text" />
                        <p class="description">Dominio registrado para esta licencia</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Discogs API Key</th>
                    <td>
                        <input type="text" name="wdi_discogs_api_key" value="<?php echo esc_attr(get_option('wdi_discogs_api_key')); ?>" class="regular-text" />
                        <p class="description">Tu clave de API de Discogs</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">Discogs API Secret</th>
                    <td>
                        <input type="text" name="wdi_discogs_api_secret" value="<?php echo esc_attr(get_option('wdi_discogs_api_secret')); ?>" class="regular-text" />
                        <p class="description">Tu secreto de API de Discogs</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <hr>
        
        <h2>Probar Conexión</h2>
        <button type="button" id="test-api-connection" class="button">Probar Conexión</button>
        <div id="api-connection-status"></div>
        
        <hr>
        
        <h2>Crear Suscripción</h2>
        <form id="create-subscription-form">
            <table class="form-table">
                <tr>
                    <th scope="row">Email</th>
                    <td><input type="email" id="sub_email" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row">Nombre</th>
                    <td><input type="text" id="sub_first_name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row">Apellido</th>
                    <td><input type="text" id="sub_last_name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row">Plan</th>
                    <td>
                        <select id="sub_plan_type">
                            <option value="free">Gratuito</option>
                            <option value="premium">Premium</option>
                            <option value="enterprise">Enterprise</option>
                        </select>
                    </td>
                </tr>
            </table>
            <button type="button" id="create-subscription" class="button button-primary">Crear Suscripción</button>
            <div id="subscription-status"></div>
        </form>
    </div>
    
    <script>
    // Pasar variables a JavaScript
    window.wdi_api_admin = {
        nonce: '<?php echo wp_create_nonce('wdi_test_api_connection'); ?>'
    };
    </script>
    <?php
}

/**
 * Agregar página de administración
 */
function wdi_add_admin_menu() {
    add_options_page(
        'Discogs Importer',
        'Discogs Importer',
        'manage_options',
        'wdi-settings',
        'wdi_admin_page'
    );
}

add_action('admin_menu', 'wdi_add_admin_menu');

/**
 * Encolar scripts de administración
 */
function wdi_admin_scripts($hook) {
    if ($hook !== 'settings_page_wdi-settings') {
        return;
    }
    
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'wdi_api_admin', [
        'nonce' => wp_create_nonce('wdi_test_api_connection')
    ]);
}

add_action('admin_enqueue_scripts', 'wdi_admin_scripts');
?>





