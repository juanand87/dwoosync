<?php
/**
 * Configuración de la API en el panel de administración
 * 
 * @package Discogs_Importer
 * @version 1.0.0
 */

// No permitir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class WDI_API_Settings {
    private $plugin_name;
    private $api_client;

    public function __construct($plugin_name) {
        $this->plugin_name = $plugin_name;
        $this->api_client = new WDI_API_Client();
        
        add_action('admin_init', array($this, 'register_api_settings'));
        add_action('wp_ajax_wdi_test_api_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_wdi_validate_license', array($this, 'validate_license'));
        add_action('wp_ajax_wdi_create_subscription', array($this, 'create_subscription'));
    }

    /**
     * Registrar configuraciones de la API
     */
    public function register_api_settings() {
        register_setting(
            $this->plugin_name . '_api_options',
            $this->plugin_name . '_api_options',
            array($this, 'sanitize_api_options')
        );

        // Sección de configuración de API
        add_settings_section(
            $this->plugin_name . '_api_section',
            __('Configuración de la API', 'discogs-importer'),
            array($this, 'render_api_section_text'),
            $this->plugin_name . '_api_options'
        );

        // Campo URL de la API
        add_settings_field(
            'api_url',
            __('URL de la API', 'discogs-importer'),
            array($this, 'render_api_url_field'),
            $this->plugin_name . '_api_options',
            $this->plugin_name . '_api_section'
        );

        // Campo License Key
        add_settings_field(
            'license_key',
            __('License Key', 'discogs-importer'),
            array($this, 'render_license_key_field'),
            $this->plugin_name . '_api_options',
            $this->plugin_name . '_api_section'
        );

        // Campo Domain
        add_settings_field(
            'domain',
            __('Dominio', 'discogs-importer'),
            array($this, 'render_domain_field'),
            $this->plugin_name . '_api_options',
            $this->plugin_name . '_api_section'
        );

        // Campo Discogs API Key
        add_settings_field(
            'discogs_api_key',
            __('Discogs API Key', 'discogs-importer'),
            array($this, 'render_discogs_api_key_field'),
            $this->plugin_name . '_api_options',
            $this->plugin_name . '_api_section'
        );

        // Campo Discogs API Secret
        add_settings_field(
            'discogs_api_secret',
            __('Discogs API Secret', 'discogs-importer'),
            array($this, 'render_discogs_api_secret_field'),
            $this->plugin_name . '_api_options',
            $this->plugin_name . '_api_section'
        );
    }

    /**
     * Renderizar descripción de la sección
     */
    public function render_api_section_text() {
        echo '<p>' . __('Configura la conexión con la API de Discogs y tu licencia de suscripción.', 'discogs-importer') . '</p>';
    }

    /**
     * Renderizar campo URL de la API
     */
    public function render_api_url_field() {
        $options = get_option($this->plugin_name . '_api_options', array());
        $value = isset($options['api_url']) ? $options['api_url'] : 'http://localhost/api_discogs/api/';
        ?>
        <input type="url" 
               id="api_url" 
               name="<?php echo $this->plugin_name; ?>_api_options[api_url]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description"><?php _e('URL base de la API de Discogs (ej: http://localhost/api_discogs/api/)', 'discogs-importer'); ?></p>
        <button type="button" id="test-api-connection" class="button"><?php _e('Probar Conexión', 'discogs-importer'); ?></button>
        <span id="api-connection-status"></span>
        <?php
    }

    /**
     * Renderizar campo License Key
     */
    public function render_license_key_field() {
        $options = get_option($this->plugin_name . '_api_options', array());
        $value = isset($options['license_key']) ? $options['license_key'] : '';
        ?>
        <input type="password" 
               id="license_key" 
               name="<?php echo $this->plugin_name; ?>_api_options[license_key]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description"><?php _e('Tu clave de licencia para acceder a la API', 'discogs-importer'); ?></p>
        <button type="button" id="validate-license" class="button"><?php _e('Validar Licencia', 'discogs-importer'); ?></button>
        <span id="license-validation-status"></span>
        <?php
    }

    /**
     * Renderizar campo Domain
     */
    public function render_domain_field() {
        $options = get_option($this->plugin_name . '_api_options', array());
        $value = isset($options['domain']) ? $options['domain'] : $_SERVER['HTTP_HOST'] ?? '';
        ?>
        <input type="text" 
               id="domain" 
               name="<?php echo $this->plugin_name; ?>_api_options[domain]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description"><?php _e('Dominio registrado para esta licencia', 'discogs-importer'); ?></p>
        <?php
    }

    /**
     * Renderizar campo Discogs API Key
     */
    public function render_discogs_api_key_field() {
        $options = get_option($this->plugin_name . '_api_options', array());
        $value = isset($options['discogs_api_key']) ? $options['discogs_api_key'] : '';
        ?>
        <input type="text" 
               id="discogs_api_key" 
               name="<?php echo $this->plugin_name; ?>_api_options[discogs_api_key]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description"><?php _e('Tu clave de API de Discogs (obténla desde <a href="https://www.discogs.com/settings/developers" target="_blank">aquí</a>)', 'discogs-importer'); ?></p>
        <?php
    }

    /**
     * Renderizar campo Discogs API Secret
     */
    public function render_discogs_api_secret_field() {
        $options = get_option($this->plugin_name . '_api_options', array());
        $value = isset($options['discogs_api_secret']) ? $options['discogs_api_secret'] : '';
        ?>
        <input type="password" 
               id="discogs_api_secret" 
               name="<?php echo $this->plugin_name; ?>_api_options[discogs_api_secret]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" />
        <p class="description"><?php _e('Tu secreto de API de Discogs', 'discogs-importer'); ?></p>
        <?php
    }

    /**
     * Sanitizar opciones de la API
     */
    public function sanitize_api_options($input) {
        $sanitized = array();
        
        if (isset($input['api_url'])) {
            $sanitized['api_url'] = esc_url_raw($input['api_url']);
        }
        
        if (isset($input['license_key'])) {
            $sanitized['license_key'] = sanitize_text_field($input['license_key']);
        }
        
        if (isset($input['domain'])) {
            $sanitized['domain'] = sanitize_text_field($input['domain']);
        }
        
        if (isset($input['discogs_api_key'])) {
            $sanitized['discogs_api_key'] = sanitize_text_field($input['discogs_api_key']);
        }
        
        if (isset($input['discogs_api_secret'])) {
            $sanitized['discogs_api_secret'] = sanitize_text_field($input['discogs_api_secret']);
        }
        
        return $sanitized;
    }

    /**
     * Probar conexión con la API
     */
    public function test_api_connection() {
        check_ajax_referer('wdi_api_nonce', 'nonce');
        
        $options = get_option($this->plugin_name . '_api_options', array());
        $api_url = isset($options['api_url']) ? $options['api_url'] : 'http://localhost/api_discogs/api/';
        
        $this->api_client->set_api_url($api_url);
        
        $response = $this->api_client->make_request('health');
        
        if ($response['success']) {
            wp_send_json_success(array(
                'message' => __('Conexión exitosa con la API', 'discogs-importer'),
                'data' => $response
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Error de conexión: ', 'discogs-importer') . $response['error']
            ));
        }
    }

    /**
     * Validar licencia
     */
    public function validate_license() {
        check_ajax_referer('wdi_api_nonce', 'nonce');
        
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');
        $domain = sanitize_text_field($_POST['domain'] ?? '');
        
        if (empty($license_key)) {
            wp_send_json_error(array(
                'message' => __('License key requerida', 'discogs-importer')
            ));
        }
        
        $this->api_client->set_license_key($license_key);
        $this->api_client->set_domain($domain);
        
        $response = $this->api_client->validate_license();
        
        if ($response['success'] && $response['valid']) {
            wp_send_json_success(array(
                'message' => __('Licencia válida', 'discogs-importer'),
                'data' => $response['license']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Licencia inválida: ', 'discogs-importer') . ($response['error'] ?? 'Error desconocido')
            ));
        }
    }

    /**
     * Crear suscripción
     */
    public function create_subscription() {
        check_ajax_referer('wdi_api_nonce', 'nonce');
        
        $data = array(
            'email' => sanitize_email($_POST['email'] ?? ''),
            'domain' => sanitize_text_field($_POST['domain'] ?? ''),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'plan_type' => sanitize_text_field($_POST['plan_type'] ?? 'free')
        );
        
        $required_fields = ['email', 'domain', 'first_name', 'last_name'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Campo requerido: %s', 'discogs-importer'), $field)
                ));
            }
        }
        
        $response = $this->api_client->create_subscription($data);
        
        if ($response['success']) {
            // Guardar la nueva license key
            $options = get_option($this->plugin_name . '_api_options', array());
            $options['license_key'] = $response['license_key'];
            update_option($this->plugin_name . '_api_options', $options);
            
            wp_send_json_success(array(
                'message' => __('Suscripción creada exitosamente', 'discogs-importer'),
                'data' => $response
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Error creando suscripción: ', 'discogs-importer') . ($response['error'] ?? 'Error desconocido')
            ));
        }
    }

    /**
     * Renderizar pestaña de configuración de API
     */
    public function render_api_tab() {
        ?>
        <div id="tab-api" class="wdi-tab-panel">
            <h2><?php _e('Configuración de la API', 'discogs-importer'); ?></h2>
            <p class="description"><?php _e('Configura la conexión con la API de Discogs y tu licencia de suscripción.', 'discogs-importer'); ?></p>
            
            <form method="post" action="options.php">
                <?php settings_fields($this->plugin_name . '_api_options'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_url"><?php _e('URL de la API', 'discogs-importer'); ?></label>
                        </th>
                        <td>
                            <?php $this->render_api_url_field(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="license_key"><?php _e('License Key', 'discogs-importer'); ?></label>
                        </th>
                        <td>
                            <?php $this->render_license_key_field(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="domain"><?php _e('Dominio', 'discogs-importer'); ?></label>
                        </th>
                        <td>
                            <?php $this->render_domain_field(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="discogs_api_key"><?php _e('Discogs API Key', 'discogs-importer'); ?></label>
                        </th>
                        <td>
                            <?php $this->render_discogs_api_key_field(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="discogs_api_secret"><?php _e('Discogs API Secret', 'discogs-importer'); ?></label>
                        </th>
                        <td>
                            <?php $this->render_discogs_api_secret_field(); ?>
                        </td>
                    </tr>
                </table>
                
                <h3><?php _e('Crear Nueva Suscripción', 'discogs-importer'); ?></h3>
                <p class="description"><?php _e('Si no tienes una licencia, puedes crear una nueva suscripción aquí.', 'discogs-importer'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sub_email"><?php _e('Email', 'discogs-importer'); ?></label>
                        </th>
                        <td>
                            <input type="email" id="sub_email" name="sub_email" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sub_first_name"><?php _e('Nombre', 'discogs-importer'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sub_first_name" name="sub_first_name" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sub_last_name"><?php _e('Apellido', 'discogs-importer'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sub_last_name" name="sub_last_name" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sub_plan_type"><?php _e('Plan', 'discogs-importer'); ?></label>
                        </th>
                        <td>
                            <select id="sub_plan_type" name="sub_plan_type" class="regular-text">
                                <option value="free"><?php _e('Gratuito', 'discogs-importer'); ?></option>
                                <option value="premium"><?php _e('Premium', 'discogs-importer'); ?></option>
                                <option value="enterprise"><?php _e('Enterprise', 'discogs-importer'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button type="button" id="create-subscription" class="button button-primary">
                                <?php _e('Crear Suscripción', 'discogs-importer'); ?>
                            </button>
                            <span id="subscription-status"></span>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php submit_button(__('Guardar Configuración', 'discogs-importer'), 'primary', 'submit', false); ?>
                </p>
            </form>
        </div>
        <?php
    }
}
