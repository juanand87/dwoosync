<?php
/**
 * La funcionalidad del plugin específica del área de administración.
 *
 * @since      1.0.0
 * @package    Discogs_Importer
 * @subpackage Discogs_Importer/admin
 * @author     Tu Nombre Aquí <email@example.com>
 */

// Cargar configuración de API
require_once plugin_dir_path(__FILE__) . '../config/api-config.php';

// Cargar clase de seguimiento de uso
require_once plugin_dir_path(__FILE__) . '../includes/class-wdi-usage-tracker.php';

class WDI_Admin {

    /**
     * El ID de este plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    El ID de este plugin.
     */
    private $plugin_name;

    /**
     * La versión de este plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    La versión actual de este plugin.
     */
    private $version;
    
    /**
     * Instancia del tracker de uso
     *
     * @since    1.0.0
     * @access   private
     * @var      WDI_Usage_Tracker    $usage_tracker    Tracker de uso de suscripciones.
     */
    private $usage_tracker;

    /**
     * Opciones anteriores para comparar cambios.
     *
     * @since    1.0.0
     * @access   protected
     * @var      array    $old_options    Las opciones anteriores.
     */
    protected $old_options;

    /**
     * Inicializa la clase y establece sus propiedades.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       El nombre del plugin.
     * @param      string    $version    La versión del plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Registrar shortcode de Spotify
        add_shortcode( 'discogsync_spotify', array( $this, 'discogsync_spotify_shortcode' ) );
        
        // Hook para cambiar el nombre de la pestaña de descripción
        add_filter( 'woocommerce_product_tabs', array( $this, 'rename_description_tab' ) );
        
        // Hook para prueba de licencia
        add_action( 'wp_ajax_test_license_connection', array( $this, 'test_license_connection_callback' ) );
        
        // Hook para prueba de API de Discogs
        add_action( 'wp_ajax_test_discogs_api_connection', array( $this, 'test_discogs_api_connection_callback' ) );
        
        // Hook para prueba de conexión de la API
        add_action( 'wp_ajax_wdi_test_api_connection', array( $this, 'test_api_connection_callback' ) );
        
        // Hook para cambio de idioma
        add_action( 'wp_ajax_wdi_save_language_change', array( $this, 'ajax_save_language_change' ) );
    }

    /**
     * Registra los menús para el área de administración.
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce', // Slug del menú padre (WooCommerce)
            __( 'DWooSync', 'dwoosync' ), // Título de la página
            __( 'DWooSync', 'dwoosync' ), // Título del menú
            'manage_options', // Capacidad requerida
            $this->plugin_name, // Slug del menú
            array( $this, 'display_settings_page' ) // Función para mostrar el contenido
        );
    }

    /**
     * Muestra la página de ajustes del plugin.
     *
     * @since    1.0.0
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <!-- Sistema de Pestañas -->
            <div class="wdi-tabs-wrapper">
                <nav class="wdi-tabs-nav">
                    <a href="#tab-api" class="wdi-tab-link active" data-tab="api">
                        <span class="dashicons dashicons-admin-network"></span>
                        <?php _e( 'Configuración API', 'dwoosync' ); ?>
                    </a>
                    <a href="#tab-templates" class="wdi-tab-link" data-tab="templates">
                        <span class="dashicons dashicons-edit"></span>
                        <?php _e( 'Plantillas y Etiquetas', 'dwoosync' ); ?>
                    </a>
                    <a href="#tab-spotify" class="wdi-tab-link" data-tab="spotify">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php _e( 'Spotify', 'dwoosync' ); ?>
                    </a>
                    <a href="#tab-categories" class="wdi-tab-link" data-tab="categories">
                        <span class="dashicons dashicons-category"></span>
                        <?php _e( 'Categorías', 'dwoosync' ); ?>
                    </a>
                    <a href="#tab-options" class="wdi-tab-link" data-tab="options">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e( 'Opciones Avanzadas', 'dwoosync' ); ?>
                    </a>
                </nav>

                <form action="options.php" method="post" class="wdi-tabs-content">
                    <?php settings_fields( $this->plugin_name . '_options' ); ?>
                    
                    <!-- Pestaña 1: Configuración API -->
                    <div id="tab-api" class="wdi-tab-panel active">
                        <h2><?php _e( 'Configuración de la API de Discogs', 'dwoosync' ); ?></h2>
                        <p class="description"><?php _e( 'Introduce tus credenciales de la API de Discogs. Puedes obtenerlas desde el portal de desarrolladores de Discogs.', 'dwoosync' ); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo $this->plugin_name; ?>_options[api_key]"><?php _e( 'Discogs API Key', 'dwoosync' ); ?></label>
                                </th>
                                <td>
                                    <?php $this->render_api_key_field(); ?>
                                    <p class="description"><?php _e( 'Tu clave de API de Discogs.', 'dwoosync' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo $this->plugin_name; ?>_options[api_secret]"><?php _e( 'Discogs API Secret', 'dwoosync' ); ?></label>
                                </th>
                                <td>
                                    <?php $this->render_api_secret_field(); ?>
                                    <p class="description"><?php _e( 'Tu secreto de API de Discogs.', 'dwoosync' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <?php 
                        // Detectar si estamos en localhost
                        $is_localhost = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                                       strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
                                       strpos($_SERVER['HTTP_HOST'], '::1') !== false);
                        ?>
                        
                        <h3><?php _e( 'Configuración del Servidor API', 'dwoosync' ); ?></h3>
                        <p class="description"><?php _e( 'Configuración para conectar con el servidor API de Discogs Importer.', 'dwoosync' ); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo $this->plugin_name; ?>_api_options[api_url]"><?php _e( 'URL del Servidor API', 'dwoosync' ); ?></label>
                                </th>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php $this->render_api_url_field(); ?>
                                        <button type="button" id="test-api-connection" class="button"><?php _e( 'Probar conexión con Discogs.com', 'dwoosync' ); ?></button>
                                    </div>
                                    <span id="api-connection-status" style="display: block; margin-top: 5px;"></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo $this->plugin_name; ?>_api_options[license_key]"><?php _e( 'Clave de Licencia', 'dwoosync' ); ?></label>
                                </th>
                                <td>
                                    <?php $this->render_license_key_field(); ?>
                                    <p class="description"><?php _e( 'Tu clave de licencia para acceder al servidor API.', 'dwoosync' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php _e( 'Fecha de Renovación', 'dwoosync' ); ?></label>
                                </th>
                                <td>
                                    <?php $this->render_renewal_date_field(); ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Pestaña 2: Plantillas y Etiquetas -->
                    <div id="tab-templates" class="wdi-tab-panel">
                        <h2><?php _e( 'Plantillas y Etiquetas', 'dwoosync' ); ?></h2>
                        <p class="description"><?php _e( 'Configura cómo se generan las descripciones de productos y las etiquetas.', 'dwoosync' ); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="wdi_short_desc_template"><?php _e( 'Plantilla de Descripción Corta', 'dwoosync' ); ?></label>
                                </th>
                                <td>
                                    <?php $this->render_short_desc_template_field(); ?>
                                    <p class="description"><?php _e( 'Haz clic en los botones para insertar shortcodes en la posición del cursor:', 'dwoosync' ); ?></p>
                                    
                                    <div class="shortcode-buttons" style="margin: 10px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
                                        <h4 style="margin-top: 0; font-size: 14px;"><?php _e( 'Shortcodes Disponibles:', 'dwoosync' ); ?></h4>
                                        <button type="button" class="shortcode-btn" data-shortcode="[title]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[title]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[artist]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[artist]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[year]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[year]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[label]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[label]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[format]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[format]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[country]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[country]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[released]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[released]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[genre]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[genre]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[style]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[style]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[notes]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[notes]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[tracklist]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[tracklist]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[discogsync_spotify]" data-target="wdi_short_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #1db954; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[discogsync_spotify]</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="wdi_long_desc_template"><?php _e( 'Plantilla de Descripción Larga', 'dwoosync' ); ?></label>
                                </th>
                                <td>
                                    <?php $this->render_long_desc_template_field(); ?>
                                    <p class="description"><?php _e( 'Plantilla para la descripción completa del producto. Haz clic en los botones para insertar shortcodes:', 'dwoosync' ); ?></p>
                                    
                                    <div class="shortcode-buttons" style="margin: 10px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
                                        <h4 style="margin-top: 0; font-size: 14px;"><?php _e( 'Shortcodes Disponibles:', 'dwoosync' ); ?></h4>
                                        <button type="button" class="shortcode-btn" data-shortcode="[title]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[title]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[artist]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[artist]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[year]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[year]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[label]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[label]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[format]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[format]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[country]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[country]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[released]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[released]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[genre]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[genre]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[style]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[style]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[notes]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[notes]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[tracklist]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #0073aa; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[tracklist]</button>
                                        <button type="button" class="shortcode-btn" data-shortcode="[discogsync_spotify]" data-target="wdi_long_desc_template" style="display: inline-block; margin: 3px; padding: 6px 10px; background: #1db954; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">[discogsync_spotify]</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e( 'Importar Estilos como Etiquetas', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_import_styles_as_tags_field(); ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Pestaña 3: Spotify -->
                    <div id="tab-spotify" class="wdi-tab-panel">
                        <h2><?php _e( 'Spotify', 'dwoosync' ); ?></h2>
                        <p class="description"><?php _e( 'Configura e integra automáticamente widgets de Spotify en tus productos y páginas.', 'dwoosync' ); ?></p>
                        
                        <?php
                        // Verificar el tipo de plan del suscriptor
                        $plan_type = $this->get_subscriber_plan_type();
                        
                        if ($plan_type !== 'enterprise') {
                            // Mostrar mensaje de upgrade para planes free/premium
                            ?>
                            <div class="wdi-upgrade-message" style="background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 30px; margin: 20px 0; text-align: center;">
                                <h3 style="margin-top: 0; color: #0073aa;"><?php _e( 'Integración con Spotify', 'dwoosync' ); ?></h3>
                                <p style="font-size: 16px; margin: 15px 0;"><?php _e( 'La integración con Spotify está disponible solo en el Plan +Spotify.', 'dwoosync' ); ?></p>
                                <p style="margin: 15px 0;"><?php _e( 'Mejora tu plan para acceder a esta funcionalidad y mostrar reproductores de Spotify en tus productos.', 'dwoosync' ); ?></p>
                                
                                <!-- Imagen de demostración -->
                                <div style="margin: 20px 0;">
                                    <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/spotify-widget.png'; ?>" 
                                         alt="<?php _e( 'Widget de Spotify', 'dwoosync' ); ?>" 
                                         style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                                </div>
                                
                                <!-- Botón Mejorar Plan -->
                                <a href="https://www.dwoosync.com" target="_blank" 
                                   style="display: inline-block; background: #1db954; color: white; padding: 12px 24px; border-radius: 25px; text-decoration: none; font-weight: bold; font-size: 16px; transition: all 0.3s ease; margin-top: 15px;">
                                    <?php _e( 'Mejorar Plan', 'dwoosync' ); ?>
                                </a>
                            </div>
                            <?php
                        } else {
                            // Mostrar configuración normal para plan enterprise
                            ?>
                        <div class="wdi-shortcode-info" style="background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 20px; margin: 20px 0;">
                            <h3 style="margin-top: 0; color: #0073aa;"><?php _e( 'Shortcode Disponible', 'dwoosync' ); ?></h3>
                            <p><strong><?php _e( 'Uso básico:', 'dwoosync' ); ?></strong></p>
                            <code style="background: #fff; padding: 10px; display: block; border: 1px solid #ddd; border-radius: 3px; margin: 10px 0; font-family: monospace;">[discogsync_spotify]</code>
                            
                            <p><strong><?php _e( 'Con ancho personalizado:', 'dwoosync' ); ?></strong></p>
                            <code style="background: #fff; padding: 10px; display: block; border: 1px solid #ddd; border-radius: 3px; margin: 10px 0; font-family: monospace;">[discogsync_spotify width="400"]</code>
                            
                            <h4><?php _e( 'Parámetros disponibles:', 'dwoosync' ); ?></h4>
                            <ul style="margin-left: 20px;">
                                <li><strong>width:</strong> <?php _e( 'Ancho del widget (por defecto: 500px)', 'dwoosync' ); ?></li>
                            </ul>
                            <p class="description"><?php _e( 'El alto se ajusta automáticamente. Tamaño estándar: 500px de ancho, alto automático.', 'dwoosync' ); ?></p>
                        </div>
                        
                        <div class="wdi-shortcode-examples" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">
                            <h3><?php _e( 'Casos de Uso', 'dwoosync' ); ?></h3>
                            
                            <h4><?php _e( '1. En páginas de producto WooCommerce', 'dwoosync' ); ?></h4>
                            <p><?php _e( 'El shortcode detecta automáticamente el título del producto y busca el álbum en Spotify.', 'dwoosync' ); ?></p>
                            
                            <h4><?php _e( '2. En posts y páginas', 'dwoosync' ); ?></h4>
                            <p><?php _e( 'Si el post tiene metadatos de Discogs guardados, los usará para la búsqueda.', 'dwoosync' ); ?></p>
                            
                            <h4><?php _e( '3. En cualquier parte del sitio', 'discogsync_spotify' ); ?></h4>
                            <p><?php _e( 'Puedes usar el shortcode en cualquier parte donde necesites mostrar un widget de Spotify.', 'dwoosync' ); ?></p>
                        </div>
                        
                        <div class="wdi-shortcode-note" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
                            <h4 style="margin-top: 0; color: #856404;"><?php _e( 'Nota Importante', 'dwoosync' ); ?></h4>
                            <p style="margin-bottom: 0; color: #856404;"><?php _e( 'El shortcode funciona mejor cuando se usa en páginas de producto WooCommerce o en posts que contengan información de Discogs. En otros contextos, puede mostrar un mensaje de "No disponible".', 'dwoosync' ); ?></p>
                        </div>
                            <?php
                        }
                        ?>
                        
                        <?php if ($plan_type === 'enterprise') { ?>
                        <h3><?php _e( 'Configuración del Reproductor', 'dwoosync' ); ?></h3>
                        <p class="description"><?php _e( 'Personaliza la apariencia del reproductor de Spotify.', 'dwoosync' ); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e( 'Diseño del Reproductor', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_spotify_design_field(); ?>
                                </td>
                            </tr>
                            <tr id="spotify-width-row" style="display: none;">
                                <th scope="row"><?php _e( 'Ancho del Reproductor', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_spotify_width_field(); ?>
                                </td>
                            </tr>
                            <!-- Campo de alto oculto para diseño compacto (alto fijo compacto) -->
                            <tr id="spotify-height-row" style="display: none;">
                                <th scope="row"><?php _e( 'Alto del Reproductor', 'dwoosync' ); ?></th>
                                <td>
                                    <p class="description"><?php _e( 'El diseño compacto usa un alto fijo optimizado (173px) con el estilo por defecto de Spotify.', 'dwoosync' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        <?php } ?>
                        
                        <?php if ($plan_type === 'enterprise') { ?>
                        <div class="wdi-spotify-preview" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">
                            <h3><?php _e( 'Vista Previa', 'dwoosync' ); ?></h3>
                            <p><?php _e( 'Esta es una vista previa de cómo se verá el reproductor:', 'dwoosync' ); ?></p>
                            
                            <?php
                            // Obtener configuración actual
                            $options = get_option( $this->plugin_name . '_options' );
                            $design = isset( $options['spotify_design'] ) ? esc_attr( $options['spotify_design'] ) : 'standard';
                            $width = isset( $options['spotify_width'] ) ? intval( $options['spotify_width'] ) : 400;
                            ?>
                            
                            <div style="text-align: center; margin: 20px 0;">
                                <?php if ($design === 'compact'): ?>
                                    <!-- Diseño compacto -->
                                    <iframe src="https://open.spotify.com/embed/album/4LH4d3cOWNN3VUlFbwXUCC" 
                                        width="<?php echo $width; ?>" 
                                        height="173" 
                                        frameborder="0" 
                                        allowtransparency="true" 
                                        allow="encrypted-media">
                                    </iframe>
                                <?php else: ?>
                                    <!-- Diseño estándar -->
                                    <iframe src="https://open.spotify.com/embed/album/4LH4d3cOWNN3VUlFbwXUCC" 
                                        width="500" 
                                        height="380" 
                                        frameborder="0" 
                                        allowtransparency="true" 
                                        allow="encrypted-media" 
                                        style="border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
                                    </iframe>
                                <?php endif; ?>
                            </div>
                            
                            <p class="description">
                                <strong><?php _e( 'Tamaño estándar:', 'dwoosync' ); ?></strong> 500px de ancho, alto automático | 
                                <strong><?php _e( 'Diseño:', 'dwoosync' ); ?></strong> <?php echo $design === 'compact' ? __( 'Compacto', 'dwoosync' ) : __( 'Estándar', 'dwoosync' ); ?>
                            </p>
                        </div>
                        <?php } ?>
                        
                    </div>

                    <!-- Pestaña 4: Categorías -->
                    <div id="tab-categories" class="wdi-tab-panel">
                        <h2><?php _e( 'Categorías Automáticas', 'dwoosync' ); ?></h2>
                        <p class="description"><?php _e( 'Configura las categorías que se asignarán automáticamente según el formato del producto importado.', 'dwoosync' ); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e( 'Categorías para Vinilo', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_vinyl_categories_field(); ?>
                                    <p class="description"><?php _e( 'Selecciona hasta 2 categorías que se asignarán automáticamente a los productos de vinilo.', 'dwoosync' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e( 'Categorías para CD', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_cd_categories_field(); ?>
                                    <p class="description"><?php _e( 'Selecciona hasta 2 categorías que se asignarán automáticamente a los productos de CD.', 'dwoosync' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e( 'Categorías para Cassette', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_cassette_categories_field(); ?>
                                    <p class="description"><?php _e( 'Selecciona hasta 2 categorías que se asignarán automáticamente a los productos de cassette.', 'dwoosync' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="wdi-categories-info" style="background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 20px; margin: 20px 0;">
                            <h3 style="margin-top: 0; color: #0073aa;"><?php _e( 'Cómo Funciona', 'dwoosync' ); ?></h3>
                            <ul style="margin-left: 20px;">
                                <li><?php _e( 'Cuando importes un producto, el sistema detectará automáticamente el formato seleccionado (Vinilo, CD o Cassette).', 'dwoosync' ); ?></li>
                                <li><?php _e( 'Se asignarán automáticamente las categorías configuradas para ese formato específico.', 'dwoosync' ); ?></li>
                                <li><?php _e( 'Esta configuración es opcional - si no configuras categorías, el producto se importará sin categorías automáticas.', 'dwoosync' ); ?></li>
                                <li><?php _e( 'Puedes cambiar las categorías manualmente después de la importación si es necesario.', 'dwoosync' ); ?></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Pestaña 5: Opciones Avanzadas -->
                    <div id="tab-options" class="wdi-tab-panel">
                        <h2><?php _e( 'Opciones Avanzadas', 'dwoosync' ); ?></h2>
                        <p class="description"><?php _e( 'Configura opciones adicionales para el importador.', 'dwoosync' ); ?></p>
                        
                        <h3><?php _e( 'Formato en el Título', 'dwoosync' ); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e( 'Anteponer Formato al Título', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_prepend_format_field(); ?>
                                </td>
                            </tr>
                        </table>

                        <h3><?php _e( 'Personalización de Pestañas', 'dwoosync' ); ?></h3>
                        <p class="description"><?php _e( 'Personaliza las pestañas de la página del producto.', 'dwoosync' ); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e( 'Cambiar Pestaña "Descripción" por "Listado de Canciones"', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_rename_description_tab_field(); ?>
                                </td>
                            </tr>
                        </table>

                        <h3><?php _e( 'Idioma del Plugin', 'dwoosync' ); ?></h3>
                        <p class="description"><?php _e( 'Selecciona el idioma para la interfaz del plugin.', 'dwoosync' ); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e( 'Idioma del Plugin', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_plugin_language_field(); ?>
                                </td>
                            </tr>
                        </table>

                        <h3><?php _e( 'Acciones Rápidas en Editor', 'dwoosync' ); ?></h3>
                        <p class="description"><?php _e( 'Activa o desactiva los campos de acceso rápido que aparecen en la caja del importador dentro de la página de edición de productos.', 'dwoosync' ); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e( 'Botón Publicar', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_show_publish_button_field(); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e( 'Campo de Precio', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_show_price_field(); ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e( 'Selector de Categorías', 'dwoosync' ); ?></th>
                                <td>
                                    <?php $this->render_show_category_selector_field(); ?>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p class="submit">
                        <?php submit_button( __( 'Guardar Cambios', 'dwoosync' ), 'primary', 'submit', false ); ?>
                    </p>
            </form>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            console.log('[Discogs Tabs] JavaScript directo cargado');
            
            // Funcionalidad de pestañas
            $('.wdi-tab-link').on('click', function(e) {
                e.preventDefault();
                console.log('[Discogs Tabs] Click en pestaña');
                
                var targetTab = $(this).data('tab');
                var targetPanel = $('#tab-' + targetTab);
                
                // Remover clase active de todos los enlaces y paneles
                $('.wdi-tab-link').removeClass('active');
                $('.wdi-tab-panel').removeClass('active');
                
                // Añadir clase active al enlace y panel seleccionados
                $(this).addClass('active');
                targetPanel.addClass('active');
                
                console.log('[Discogs Tabs] Pestaña cambiada a: ' + targetTab);
            });
            
            // Probar conexión con la API
            $('#test-api-connection').on('click', function() {
                const button = $(this);
                const status = $('#api-connection-status');
                
                button.prop('disabled', true).text('<?php _e( 'Probando...', 'dwoosync' ); ?>');
                status.html('<span class="spinner is-active"></span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wdi_test_api_connection',
                        nonce: '<?php echo wp_create_nonce('wdi_api_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                        } else {
                            status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        status.html('<span style="color: red;">✗ <?php _e( 'Error de conexión', 'dwoosync' ); ?></span>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e( 'Probar conexión con Discogs.com', 'dwoosync' ); ?>');
                    }
                });
            });
            
            // Funcionalidad de campos de Spotify
            function toggleSpotifyFields() {
                var isCompact = $('input[name="dwoosync_options[spotify_design]"]:checked').val() === 'compact';
                console.log('[Discogs Tabs] Diseño seleccionado:', isCompact ? 'compact' : 'standard');
                if (isCompact) {
                    $('#spotify-width-row').show();
                    $('#spotify-height-row').show();
                } else {
                    $('#spotify-width-row').hide();
                    $('#spotify-height-row').hide();
                }
            }
            
            // Ejecutar al cargar la página
            toggleSpotifyFields();
            
            // Ejecutar cuando cambie el radio button
            $('input[name="dwoosync_options[spotify_design]"]').change(toggleSpotifyFields);
            
                // Asegurar que todos los campos estén visibles cuando se envía el formulario
                $('form.wdi-tabs-content').on('submit', function(e) {
                    console.log('[Discogs Tabs] Formulario enviado - mostrando todos los campos');
                    $('.wdi-tab-panel').each(function() {
                        $(this).css({
                            'position': 'static',
                            'left': 'auto',
                            'top': 'auto',
                            'visibility': 'visible',
                            'opacity': '1',
                            'display': 'block'
                        });
                    });
                });
                
                // Funcionalidad de botones de shortcodes
                $('.shortcode-btn').on('click', function(e) {
                    e.preventDefault();
                    console.log('[Discogs Shortcodes] Botón clickeado');
                    
                    var shortcode = $(this).data('shortcode');
                    var targetField = $(this).data('target');
                    console.log('[Discogs Shortcodes] Shortcode:', shortcode, 'Target:', targetField);
                    
                    // Verificar si TinyMCE está disponible
                    if (typeof tinymce !== 'undefined' && tinymce.get(targetField)) {
                        console.log('[Discogs Shortcodes] Usando TinyMCE para:', targetField);
                        var editor = tinymce.get(targetField);
                        if (editor) {
                            // Insertar en TinyMCE
                            editor.insertContent(shortcode);
                            editor.focus();
                            console.log('[Discogs Shortcodes] Shortcode insertado en TinyMCE:', shortcode);
                        }
                    } else {
                        // Fallback para textarea normal
                        var textarea = $('#' + targetField);
                        console.log('[Discogs Shortcodes] Textarea encontrado:', textarea.length);
                        
                        if (textarea.length) {
                            // Obtener posición del cursor
                            var start = textarea[0].selectionStart;
                            var end = textarea[0].selectionEnd;
                            var text = textarea.val();
                            
                            console.log('[Discogs Shortcodes] Posición cursor:', start, 'a', end);
                            console.log('[Discogs Shortcodes] Texto actual:', text);
                            
                            // Insertar shortcode en la posición del cursor
                            var newText = text.substring(0, start) + shortcode + text.substring(end);
                            textarea.val(newText);
                            
                            // Mover cursor después del shortcode insertado
                            var newCursorPos = start + shortcode.length;
                            textarea[0].setSelectionRange(newCursorPos, newCursorPos);
                            
                            // Enfocar el textarea
                            textarea.focus();
                            
                            console.log('[Discogs Shortcodes] Shortcode insertado:', shortcode, 'en campo:', targetField);
                            console.log('[Discogs Shortcodes] Nuevo texto:', newText);
                        } else {
                            console.log('[Discogs Shortcodes] ERROR: No se encontró el textarea con ID:', targetField);
                        }
                    }
                });
            });
            </script>
        <?php
    }

    /**
     * Registra las secciones y campos de ajustes.
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // Registrar un grupo de ajustes
        register_setting(
            $this->plugin_name . '_options', // group name
            $this->plugin_name . '_options', // option name
            array( $this, 'sanitize_options' ) // sanitize callback
        );

        // Registrar opciones de la API
        register_setting(
            $this->plugin_name . '_api_options', // group name
            $this->plugin_name . '_api_options', // option name
            array( $this, 'sanitize_api_options' ) // sanitize callback
        );
        
        // Registrar campos de plantillas
        add_settings_field(
            'short_desc_template',
            __('Plantilla de Descripción Corta', 'dwoosync'),
            array( $this, 'render_short_desc_template_field' ),
            $this->plugin_name . '_options'
        );

        add_settings_field(
            'long_desc_template',
            __('Plantilla de Descripción Larga', 'dwoosync'),
            array( $this, 'render_long_desc_template_field' ),
            $this->plugin_name . '_options'
        );
        
        add_settings_field(
            'import_styles_as_tags',
            __('Importar Estilos como Etiquetas', 'dwoosync'),
            array( $this, 'render_import_styles_as_tags_field' ),
            $this->plugin_name . '_options'
        );
        
        // Configuración del shortcode de Spotify
        add_settings_field(
            'spotify_design',
            __('Diseño del Reproductor de Spotify', 'dwoosync'),
            array( $this, 'render_spotify_design_field' ),
            $this->plugin_name . '_options'
        );
        
        add_settings_field(
            'spotify_width',
            __('Ancho del Reproductor de Spotify', 'dwoosync'),
            array( $this, 'render_spotify_width_field' ),
            $this->plugin_name . '_options'
        );
        
        add_settings_field(
            'spotify_height',
            __('Alto del Reproductor de Spotify', 'dwoosync'),
            array( $this, 'render_spotify_height_field' ),
            $this->plugin_name . '_options'
        );
        
        // Campo de idioma del plugin
        add_settings_field(
            'plugin_language',
            __('Idioma del Plugin', 'dwoosync'),
            array( $this, 'render_plugin_language_field' ),
            $this->plugin_name . '_options'
        );
    }


    /**
     * Renderiza el campo para la API Key.
     */
    public function render_api_key_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $api_key = isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : '';
        echo "<input type='text' name='{$this->plugin_name}_options[api_key]' value='{$api_key}' class='regular-text' id='discogs_api_key_field'>";
        echo "<button type='button' id='test_discogs_api_connection' class='button button-secondary' style='margin-left: 10px;'>" . __( 'Probar Credenciales de API', 'dwoosync' ) . "</button>";
        echo "<div id='discogs_api_test_result' style='margin-top: 10px; display: none;'></div>";
    }

    /**
     * Renderiza el campo para la API Secret.
     */
    public function render_api_secret_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $api_secret = isset( $options['api_secret'] ) ? esc_attr( $options['api_secret'] ) : '';
        echo "<input type='text' name='{$this->plugin_name}_options[api_secret]' value='{$api_secret}' class='regular-text' id='discogs_api_secret_field'>";
    }

    /**
     * Renderiza el campo para la URL de la API.
     */
    public function render_api_url_field() {
        $options = get_option( $this->plugin_name . '_options' );
        
        // Detectar si estamos en localhost
        $is_localhost = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
                        strpos($_SERVER['HTTP_HOST'], '::1') !== false);
        
        if ($is_localhost) {
            // En localhost: usar configuración editable
            $default_url = function_exists('wdi_get_api_url') ? wdi_get_api_url() : 'http://localhost/api_discogs/api/index.php?endpoint=';
            $api_url = isset( $options['api_url'] ) ? esc_attr( $options['api_url'] ) : $default_url;
            echo "<input type='url' name='{$this->plugin_name}_options[api_url]' value='{$api_url}' class='regular-text'>";
        } else {
            // En producción: usar URL fija no editable
            $production_url = 'https://dwoosync.com/api/index.php?endpoint=';
            echo "<input type='text' value='" . esc_attr($production_url) . "' class='regular-text' readonly style='background-color: #f9f9f9; color: #666;'>";
        }
    }

    /**
     * Renderiza el campo para la clave de licencia.
     */
    public function render_license_key_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $license_key = isset( $options['license_key'] ) ? esc_attr( $options['license_key'] ) : '';
        echo "<input type='text' name='{$this->plugin_name}_options[license_key]' value='{$license_key}' class='regular-text' id='license_key_field'>";
        echo "<button type='button' id='test_license_connection' class='button button-secondary' style='margin-left: 10px;'>" . __( 'Probar Licencia', 'dwoosync' ) . "</button>";
        echo "<div id='license_test_result' style='margin-top: 10px; display: none;'></div>";
    }

    /**
     * Renderiza el campo para mostrar la fecha de renovación.
     */
    public function render_renewal_date_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $license_key = isset( $options['license_key'] ) ? esc_attr( $options['license_key'] ) : '';
        
        if (empty($license_key)) {
            echo "<div style='color: #666; font-style: italic;'>Ingresa una clave de licencia para ver la fecha de renovación</div>";
            return;
        }
        
        try {
            // Obtener configuración de la API
            $api_url = isset( $options['api_url'] ) ? esc_attr( $options['api_url'] ) : 'http://localhost/api_discogs/api/index.php';
            $domain = get_site_url();
            
            // Validar licencia a través de la API centralizada
            $response = wp_remote_post($api_url . 'validate_license', [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'license_key' => $license_key,
                    'domain' => $domain
                ]),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                echo "<div style='color: #d63638; font-weight: bold;'>❌ Error de conexión: " . $response->get_error_message() . "</div>";
                return;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                echo "<div style='color: #d63638; font-weight: bold;'>❌ " . ($data['message'] ?? 'Licencia inválida') . "</div>";
                return;
            }
            
            // Obtener información de la licencia desde la respuesta
            $expires_at = $data['expires_at'] ?? null;
            $status = $data['status'] ?? 'unknown';
            
            if (!$expires_at) {
                echo "<div style='color: #666; font-style: italic;'>Fecha de expiración no configurada</div>";
                return;
            }
            
            // Verificar si está expirada
            $current_date = date('Y-m-d H:i:s');
            $is_expired = $expires_at < $current_date;
            
            // Formatear fecha para mostrar
            $formatted_date = date('d/m/Y H:i', strtotime($expires_at));
            
            if ($is_expired) {
                echo "<div style='color: #d63638; font-weight: bold; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;'>";
                echo "❌ <strong>License Expired:</strong> " . $formatted_date . "<br>";
                echo "<a href='https://www.dwoosync.com' target='_blank' style='color: #d63638; text-decoration: underline; font-weight: bold;'>Renew your subscription here: https://www.dwoosync.com</a>";
                echo "</div>";
            } else {
                echo "<div style='color: #00a32a; font-weight: bold; padding: 10px; background: #f0f6fc; border: 1px solid #00a32a; border-radius: 4px;'>";
                echo "✅ <strong>Active License:</strong> " . $formatted_date;
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='color: #d63638; font-weight: bold;'>❌ Error checking license: " . esc_html($e->getMessage()) . "</div>";
        }
    }


    /**
     * Renderiza el campo para la plantilla de Descripción Corta.
     */
    public function render_short_desc_template_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $content = isset( $options['short_desc_template'] ) ? $options['short_desc_template'] : "<strong>Artista:</strong> [artist]\n<strong>Formato:</strong> [format] ([year])";
        
        wp_editor( $content, 'wdi_short_desc_template', array(
            'textarea_name' => $this->plugin_name . '_options[short_desc_template]',
            'media_buttons' => false,
            'textarea_rows' => 5,
            'teeny'         => true,
            'quicktags'     => false
        ) );
    }

    /**
     * Renderiza el campo para la plantilla de Descripción Larga.
     */
    public function render_long_desc_template_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $content = isset( $options['long_desc_template'] ) ? $options['long_desc_template'] : "<h3>Lista de Canciones</h3>\n[tracklist]\n<hr>\n<strong>Sello:</strong> [label]\n<strong>País:</strong> [country]";
        
        wp_editor( $content, 'wdi_long_desc_template', array(
            'textarea_name' => $this->plugin_name . '_options[long_desc_template]',
            'media_buttons' => true,
            'textarea_rows' => 10
        ) );
    }

    // --- INICIO: Función para renderizar el nuevo campo ---
    /**
     * Renderiza el campo para la opción de anteponer el formato.
     */
    public function render_prepend_format_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $checked = isset( $options['prepend_format_to_title'] ) && $options['prepend_format_to_title'] === '1';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '_options[prepend_format_to_title]" value="1"' . checked( $checked, true, false ) . '>';
        echo ' ' . __( 'Anteponer el formato (ej. "Vinilo") al título del producto al importar.', 'dwoosync' ) . '</label>';
    }
    // --- FIN: Función para renderizar el nuevo campo ---

    // --- INICIO: Función para renderizar el campo de estilos como etiquetas ---
    public function render_import_styles_as_tags_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $checked = isset( $options['import_styles_as_tags'] ) && $options['import_styles_as_tags'] === '1';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '_options[import_styles_as_tags]" value="1"' . checked( $checked, true, false ) . '>';
        echo ' ' . __( 'Importar los "Estilos" de Discogs como etiquetas de producto en WooCommerce.', 'dwoosync' ) . '</label>';
    }
    // --- FIN: Función para renderizar el campo de estilos como etiquetas ---

    public function render_show_publish_button_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $checked = isset( $options['show_publish_button'] ) && $options['show_publish_button'] === '1';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '_options[show_publish_button]" value="1"' . checked( $checked, true, false ) . '>';
        echo ' ' . __( 'Mostrar el botón "Publicar" junto al de "Buscar".', 'dwoosync' ) . '</label>';
    }
    public function render_show_price_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $checked = isset( $options['show_price_field'] ) && $options['show_price_field'] === '1';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '_options[show_price_field]" value="1"' . checked( $checked, true, false ) . '>';
        echo ' ' . __( 'Mostrar un campo para introducir el precio rápidamente.', 'dwoosync' ) . '</label>';
    }
    public function render_show_category_selector_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $checked = isset( $options['show_category_selector'] ) && $options['show_category_selector'] === '1';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '_options[show_category_selector]" value="1"' . checked( $checked, true, false ) . '>';
        echo ' ' . __( 'Mostrar un selector de categorías con buscador.', 'dwoosync' ) . '</label>';
    }
    
    public function render_rename_description_tab_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $checked = isset( $options['rename_description_tab'] ) && $options['rename_description_tab'] === '1';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '_options[rename_description_tab]" value="1"' . checked( $checked, true, false ) . '>';
        echo ' ' . __( 'Cambiar el nombre de la pestaña "Descripción" por "Listado de Canciones" en las páginas de producto.', 'dwoosync' ) . '</label>';
    }
    // --- FIN: Funciones para renderizar la nueva sección ---

    // --- INICIO: Función para traducir formatos ---
    /**
     * Traduce los nombres de formatos de Discogs.
     * @param string $format El nombre del formato en inglés.
     * @return string El nombre del formato traducido.
     */
    private function translate_format_name($format) {
        $translations = array(
            'Vinyl'    => 'Vinilo',
            'CD'       => 'CD',
            'Cassette' => 'Cassette',
        );
        return isset($translations[$format]) ? $translations[$format] : ucfirst($format);
    }
    // --- FIN: Función para traducir formatos ---

    /**
     * Sanitiza los valores de las opciones antes de guardarlos.
     *
     * @param array $input Contiene los valores de las opciones a sanitizar.
     * @return array Array sanitizado.
     */
    public function sanitize_options( $input ) {
        error_log('[Discogs Options Debug] Input recibido: ' . print_r($input, true));
        
        // Guardar opciones anteriores para comparar
        $this->old_options = get_option($this->plugin_name . '_options', array());
        
        $sanitized_input = array();
        if ( isset( $input['api_key'] ) ) {
            $sanitized_input['api_key'] = sanitize_text_field( $input['api_key'] );
        }
        if ( isset( $input['api_secret'] ) ) {
            $sanitized_input['api_secret'] = sanitize_text_field( $input['api_secret'] );
        }
        // Sanitizar los campos de plantilla permitiendo HTML
        if ( isset( $input['short_desc_template'] ) ) {
            $sanitized_input['short_desc_template'] = wp_kses_post( $input['short_desc_template'] );
            error_log('[Discogs Options Debug] Plantilla corta guardada: ' . $sanitized_input['short_desc_template']);
        }
        if ( isset( $input['long_desc_template'] ) ) {
            $sanitized_input['long_desc_template'] = wp_kses_post( $input['long_desc_template'] );
            error_log('[Discogs Options Debug] Plantilla larga guardada: ' . $sanitized_input['long_desc_template']);
        }
        
        // Sanitizar campos de la API (movidos de api_options)
        if ( isset( $input['api_url'] ) ) {
            $sanitized_input['api_url'] = esc_url_raw( $input['api_url'] );
        }
        if ( isset( $input['license_key'] ) ) {
            $sanitized_input['license_key'] = sanitize_text_field( $input['license_key'] );
        }
        
        // --- INICIO: Sanitizar el nuevo campo ---
        $sanitized_input['prepend_format_to_title'] = ( isset( $input['prepend_format_to_title'] ) && '1' === $input['prepend_format_to_title'] ) ? '1' : '0';
        $sanitized_input['import_styles_as_tags'] = ( isset( $input['import_styles_as_tags'] ) && '1' === $input['import_styles_as_tags'] ) ? '1' : '0';
        // --- FIN: Sanitizar el nuevo campo ---
        
        // --- INICIO: Sanitizar campos de acciones rápidas ---
        $sanitized_input['show_publish_button'] = ( isset( $input['show_publish_button'] ) && '1' === $input['show_publish_button'] ) ? '1' : '0';
        $sanitized_input['show_price_field'] = ( isset( $input['show_price_field'] ) && '1' === $input['show_price_field'] ) ? '1' : '0';
        $sanitized_input['show_category_selector'] = ( isset( $input['show_category_selector'] ) && '1' === $input['show_category_selector'] ) ? '1' : '0';
        $sanitized_input['rename_description_tab'] = ( isset( $input['rename_description_tab'] ) && '1' === $input['rename_description_tab'] ) ? '1' : '0';
        // --- FIN: Sanitizar campos de acciones rápidas ---
        
        // --- INICIO: Sanitizar campos de Spotify ---
        if ( isset( $input['spotify_design'] ) ) {
            $sanitized_input['spotify_design'] = sanitize_text_field( $input['spotify_design'] );
            // Validar valores permitidos
            if ( !in_array( $sanitized_input['spotify_design'], array( 'standard', 'compact' ) ) ) {
                $sanitized_input['spotify_design'] = 'standard';
            }
        }
        if ( isset( $input['spotify_width'] ) ) {
            $sanitized_input['spotify_width'] = intval( $input['spotify_width'] );
            // Validar rango
            if ( $sanitized_input['spotify_width'] < 200 ) $sanitized_input['spotify_width'] = 200;
            if ( $sanitized_input['spotify_width'] > 800 ) $sanitized_input['spotify_width'] = 800;
        }
        if ( isset( $input['spotify_height'] ) ) {
            $sanitized_input['spotify_height'] = intval( $input['spotify_height'] );
            // Validar rango
            if ( $sanitized_input['spotify_height'] < 200 ) $sanitized_input['spotify_height'] = 200;
            if ( $sanitized_input['spotify_height'] > 600 ) $sanitized_input['spotify_height'] = 600;
        }
        // --- FIN: Sanitizar campos de Spotify ---
        
        // --- INICIO: Sanitizar campo de idioma ---
        if ( isset( $input['plugin_language'] ) ) {
            $allowed_languages = array('es_ES', 'en_US');
            $sanitized_input['plugin_language'] = in_array($input['plugin_language'], $allowed_languages) ? $input['plugin_language'] : 'es_ES';
        }
        // --- FIN: Sanitizar campo de idioma ---

        // --- INICIO: Sanitizar opciones de categorías automáticas ---
        if ( isset( $input['vinyl_categories'] ) && is_array( $input['vinyl_categories'] ) ) {
            $sanitized_input['vinyl_categories'] = array_map( 'intval', $input['vinyl_categories'] );
        }
        
        if ( isset( $input['cd_categories'] ) && is_array( $input['cd_categories'] ) ) {
            $sanitized_input['cd_categories'] = array_map( 'intval', $input['cd_categories'] );
        }
        
        if ( isset( $input['cassette_categories'] ) && is_array( $input['cassette_categories'] ) ) {
            $sanitized_input['cassette_categories'] = array_map( 'intval', $input['cassette_categories'] );
        }
        // --- FIN: Sanitizar opciones de categorías automáticas ---

        error_log('[Discogs Options Debug] Output sanitizado: ' . print_r($sanitized_input, true));
        
        // --- INICIO: Recargar idioma si cambió ---
        if (isset($input['plugin_language']) && isset($sanitized_input['plugin_language'])) {
            $old_language = isset($this->old_options['plugin_language']) ? $this->old_options['plugin_language'] : 'es_ES';
            $new_language = $sanitized_input['plugin_language'];
            
            if ($old_language !== $new_language) {
                error_log('[Discogs Language Debug] Idioma cambiado de ' . $old_language . ' a ' . $new_language);
                
                // Recargar el idioma inmediatamente
                add_action('admin_init', function() use ($new_language) {
                    $domain = 'dwoosync';
                    $plugin_dir = plugin_dir_path(dirname(__FILE__));
                    $languages_dir = $plugin_dir . 'languages/';
                    $mo_file = $languages_dir . 'discogs-importer-' . $new_language . '.mo';
                    
                    unload_textdomain($domain);
                    if (file_exists($mo_file)) {
                        load_textdomain($domain, $mo_file);
                        error_log('[Discogs Language Debug] Idioma recargado: ' . $new_language);
                    }
                }, 1);
            }
        }
        // --- FIN: Recargar idioma si cambió ---
        
        return $sanitized_input;
    }

    /**
     * Sanitiza las opciones de la API.
     *
     * @since    1.0.0
     * @param    array    $input    Las opciones de entrada.
     * @return   array    Las opciones sanitizadas.
     */
    public function sanitize_api_options( $input ) {
        $sanitized_input = array();
        
        if ( isset( $input['api_url'] ) ) {
            $sanitized_input['api_url'] = esc_url_raw( $input['api_url'] );
        }
        
        if ( isset( $input['license_key'] ) ) {
            $sanitized_input['license_key'] = sanitize_text_field( $input['license_key'] );
        }

        return $sanitized_input;
    }

    /**
     * Añade la meta box en la página de edición de productos de WooCommerce.
     *
     * @since    1.0.0
     */
    public function add_product_meta_box( $post_type ) {
        // Solo añadir la meta box para el tipo de post 'product'
        if ( 'product' === $post_type ) {
            add_meta_box(
                'wdi_discogs_importer_meta_box', // ID
                __( 'Discogs Importer', 'dwoosync' ), // Título
                array( $this, 'render_product_meta_box' ), // Función que renderiza el contenido
                'product', // Pantalla donde se mostrará ('product' para WooCommerce)
                'side', // Contexto (side, normal, advanced)
                'high' // Prioridad (high, core, default, low)
            );
        }
    }

    /**
     * Renderiza el contenido de la meta box del producto.
     *
     * @since    1.0.0
     */
    public function render_product_meta_box() {
        // Usamos un nonce para seguridad
        wp_nonce_field( $this->plugin_name . '_meta_box_nonce', $this->plugin_name . '_nonce' );
        $options = get_option( $this->plugin_name . '_options' );
        ?>

        <div id="dwoosync-wrapper">
            <p>
                <label for="discogs_release_id"><?php _e( 'ID de Lanzamiento, Catálogo o Matriz:', 'dwoosync' ); ?></label>
                <input type="text" id="discogs_release_id" name="discogs_release_id" class="widefat" placeholder="<?php _e( 'Ej: R_157861', 'dwoosync' ); ?>" />
            </p>
            <div id="discogs-format-filter">
                <label><input type="radio" name="discogs_format" value="Vinyl" checked="checked"> <?php _e( 'Vinilo', 'dwoosync' ); ?></label>
                <label style="margin-left: 10px;"><input type="radio" name="discogs_format" value="CD"> <?php _e( 'CD', 'dwoosync' ); ?></label>
                <label style="margin-left: 10px;"><input type="radio" name="discogs_format" value="Cassette"> <?php _e( 'Cassette', 'dwoosync' ); ?></label>
            </div>
            <div id="discogs-year-filter" style="margin-top: 10px;">
                <label for="discogs_year_range"><?php _e( 'Filtrar por año (opcional):', 'dwoosync' ); ?></label>
                <select id="discogs_year_range" name="discogs_year_range" style="width: 100%; margin-top: 5px;">
                    <option value=""><?php _e( 'Todos los años', 'dwoosync' ); ?></option>
                    <option value="2020-2024"><?php _e( '2020-2024', 'dwoosync' ); ?></option>
                    <option value="2010-2019"><?php _e( '2010-2019', 'dwoosync' ); ?></option>
                    <option value="2000-2009"><?php _e( '2000-2009', 'dwoosync' ); ?></option>
                    <option value="1990-1999"><?php _e( '1990-1999', 'dwoosync' ); ?></option>
                    <option value="1980-1989"><?php _e( '1980-1989', 'dwoosync' ); ?></option>
                    <option value="1970-1979"><?php _e( '1970-1979', 'dwoosync' ); ?></option>
                    <option value="1960-1969"><?php _e( '1960-1969', 'dwoosync' ); ?></option>
                    <option value="1950-1959"><?php _e( '1950-1959', 'dwoosync' ); ?></option>
                    <option value="-1949"><?php _e( 'Antes de 1950', 'dwoosync' ); ?></option>
                </select>
            </div>
            
            <p>
                <button type="button" id="fetch-discogs-data" class="button button-primary"><?php _e( 'Buscar', 'dwoosync' ); ?></button>
                <?php if ( isset( $options['show_publish_button'] ) && $options['show_publish_button'] === '1' ) : ?>
                <button type="button" id="publish-from-importer" class="button button-primary" style="display: none; margin-left: 10px;"><?php _e( 'Publicar', 'dwoosync' ); ?></button>
                <?php endif; ?>
                <span class="spinner"></span>
            </p>

            <!-- INICIO: Contenedores para campos rápidos -->
            <div id="wdi-quick-fields-wrapper" style="display: none; margin-top: 15px; border-top: 1px solid #ddd; padding-top: 15px;">
                <?php if ( isset( $options['show_price_field'] ) && $options['show_price_field'] === '1' ) : ?>
                <p>
                    <label for="wdi-quick-price" style="font-weight: bold;"><?php _e( 'Precio:', 'dwoosync' ); ?></label>
                    <input type="text" id="wdi-quick-price" class="widefat" placeholder="<?php _e( 'Ej: 19.99', 'dwoosync' ); ?>" style="margin-top: 5px;" />
                </p>
                <?php endif; ?>

                <?php if ( isset( $options['show_category_selector'] ) && $options['show_category_selector'] === '1' ) : ?>
                <p>
                    <label for="wdi-quick-categories" style="font-weight: bold;"><?php _e( 'Categorías:', 'dwoosync' ); ?></label>
                    <select id="wdi-quick-categories" multiple="multiple" class="widefat" data-placeholder="<?php _e( 'Buscar y seleccionar categorías...', 'dwoosync' ); ?>" style="width: 100%; margin-top: 5px;"></select>
                </p>
                <?php endif; ?>
            </div>
            <!-- FIN: Contenedores para campos rápidos -->
            
            <!-- INICIO: Contenedor para la previsualización de la imagen -->
            <div id="discogs-preview-wrapper" style="display: none; margin-top: 15px; text-align: center; border-top: 1px solid #ddd; padding-top: 15px;">
                <img id="discogs-preview-image" src="" alt="<?php _e( 'Previsualización de la portada', 'dwoosync' ); ?>" style="max-width: 100%; height: auto; border: 1px solid #ddd;" />
                <p id="discogs-preview-text" style="font-size: 12px; color: #666; margin-top: 5px;"></p>
            </div>
            <!-- FIN: Contenedor para la previsualización de la imagen -->

            <!-- Estructura de la ventana modal -->
            <div id="wdi-modal-overlay" style="display: none;">
                <div id="wdi-modal-content">
                    <div id="wdi-modal-header">
                        <h2><?php _e( 'Resultados de Búsqueda de Discogs', 'dwoosync' ); ?></h2>
                        <button type="button" id="wdi-modal-close" class="button-link"><span class="dashicons dashicons-no-alt"></span></button>
                    </div>
                    <div id="wdi-modal-body">
                        <!-- Filtro de país que se llenará dinámicamente -->
                        <div id="discogs-country-filter-wrapper" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; border: 1px solid #ddd; display: none;">
                            <label for="discogs_country_filter" style="font-weight: bold; display: block; margin-bottom: 8px;"><?php _e( 'Filtrar por país:', 'dwoosync' ); ?></label>
                            <select id="discogs_country_filter" name="discogs_country_filter" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px;" disabled>
                                <option value=""><?php _e( 'Primero busca por ID', 'dwoosync' ); ?></option>
                            </select>
                        </div>
                        <div id="dwoosync-results"></div>
                    </div>
                </div>
            </div>

            <input type="hidden" id="discogs_gallery_to_import" name="_discogs_gallery_to_import" value="" />
        </div>
        <?php
    }

    /**
     * Carga los scripts y estilos para el área de administración.
     *
     * @since    1.0.0
     */
    public function enqueue_admin_assets( $hook ) {
        // Debug: ver qué hook se está usando
        error_log('[Discogs Admin Debug] Hook recibido: ' . $hook);
        
        // Solo cargar en admin
        if ( !is_admin() ) {
            return;
        }
        
        // Cargar en la página de edición de productos
        if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
            global $post;
            if ( 'product' !== $post->post_type ) {
                return;
            }
        }
        
        error_log('[Discogs Admin Debug] Cargando scripts para hook: ' . $hook);

        // Cargar CSS
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url( __FILE__ ) . 'css/wdi-admin.css',
            array(),
            time(),
            'all'
        );

        // Cargar JS principal (solo en páginas de productos)
        if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
        $script_handle = 'wdi_admin_script';

        wp_enqueue_script(
            $script_handle,
            plugin_dir_url( __FILE__ ) . 'js/wdi-admin.js',
            array( 'jquery', 'wc-enhanced-select', 'suggest' ), // <-- AÑADIR DEPENDENCIA
            time(),
            true // Cargar en el footer
        );

        // Obtener el idioma seleccionado del plugin
        $options = get_option($this->plugin_name . '_options');
        $plugin_language = isset($options['plugin_language']) ? $options['plugin_language'] : 'es_ES';
        
        // Localizar script con traducciones
        wp_localize_script( $script_handle, 'wdi_admin_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'country_label' => __( 'País:', 'dwoosync' ),
            'year_label' => __( 'Año:', 'dwoosync' ),
            'warning_label' => __( 'Advertencia:', 'dwoosync' ),
            'plugin_language' => $plugin_language === 'en_US' ? 'en' : 'es',
            'nonce' => wp_create_nonce( 'wdi_admin_nonce' )
        ) );
        }
        
        // Cargar JS de pestañas (siempre en admin)
        wp_enqueue_script(
            'wdi_admin_tabs_script',
            plugin_dir_url( __FILE__ ) . 'js/wdi-admin-tabs.js',
            array( 'jquery' ),
            time(),
            true
        );
        error_log('[Discogs Admin Debug] JavaScript de pestañas cargado para hook: ' . $hook);
        
        // Añadir JavaScript inline para asegurar que las pestañas funcionen
        wp_add_inline_script( 'wdi_admin_tabs_script', '
            jQuery(document).ready(function($) {
                console.log("[Discogs Tabs Debug] JavaScript inline cargado");
                
                // --- INICIO: Funcionalidad de Pestañas de Configuración ---
                $(".wdi-tab-link").on("click", function(e) {
                    e.preventDefault();
                    console.log("[Discogs Tabs Debug] Click en pestaña");
                    
                    var targetTab = $(this).data("tab");
                    var targetPanel = $("#tab-" + targetTab);
                    
                    // Remover clase active de todos los enlaces y paneles
                    $(".wdi-tab-link").removeClass("active");
                    $(".wdi-tab-panel").removeClass("active");
                    
                    // Añadir clase active al enlace y panel seleccionados
                    $(this).addClass("active");
                    targetPanel.addClass("active");
                    
                    console.log("[Discogs Tabs Debug] Pestaña cambiada a: " + targetTab);
                });
                // --- FIN: Funcionalidad de Pestañas de Configuración ---
                
                // --- INICIO: Funcionalidad de Campos de Spotify ---
                // Función para mostrar/ocultar campos de ancho y alto
                function toggleSpotifyFields() {
                    var isCompact = $("input[name=\"dwoosync_options[spotify_design]\"]:checked").val() === "compact";
                    console.log("[Discogs Tabs Debug] Diseño seleccionado:", isCompact ? "compact" : "standard");
                    if (isCompact) {
                        $("#spotify-width-row").show();
                        $("#spotify-height-row").show();
                    } else {
                        $("#spotify-width-row").hide();
                        $("#spotify-height-row").hide();
                    }
                }
                
                // Ejecutar al cargar la página
                toggleSpotifyFields();
                
                // Ejecutar cuando cambie el radio button
                $("input[name=\"dwoosync_options[spotify_design]\"]").change(toggleSpotifyFields);
                // --- FIN: Funcionalidad de Campos de Spotify ---
                
                // Asegurar que todos los campos estén visibles cuando se envía el formulario
                $("form.wdi-tabs-content").on("submit", function(e) {
                    console.log("[Discogs Tabs Debug] Formulario enviado - mostrando todos los campos");
                    $(".wdi-tab-panel").each(function() {
                        $(this).css({
                            "position": "static",
                            "left": "auto",
                            "top": "auto",
                            "visibility": "visible",
                            "opacity": "1",
                            "display": "block"
                        });
                    });
                });
            });
        ' );
        
        // Cargar JS del widget de Spotify (en todas las páginas)
        wp_enqueue_script(
            'wdi_spotify_widget_script',
            plugin_dir_url( __FILE__ ) . 'js/wdi-spotify-widget.js',
            array( 'jquery' ),
            time() . '_' . rand(1000, 9999), // Forzar recarga
            true
        );
        
        // BORRÓN Y CUENTA NUEVA: Sistema de seguridad AJAX (solo en páginas de productos)
        if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
        wp_localize_script(
            $script_handle,
            'wdi_secure_ajax', // Nuevo nombre de objeto JS
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'wdi_seguridad_total' ), // Nuevo nombre de nonce
                'search_categories_nonce' => wp_create_nonce( 'wdi_search_cats_nonce' ) // Nonce para el buscador de categorías
            )
        );
        }
        
        // Agregar JavaScript para prueba de licencia y API de Discogs (en todas las páginas de admin)
        wp_add_inline_script( 'jquery', '
            jQuery(document).ready(function($) {
                // Prueba de licencia
                $("#test_license_connection").on("click", function() {
                    var licenseKey = $("#license_key_field").val();
                    var resultDiv = $("#license_test_result");
                    var button = $(this);
                    
                    if (!licenseKey) {
                        resultDiv.html("<div style=\"color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;\">❌ Por favor, ingresa una clave de licencia primero.</div>").show();
                        return;
                    }
                    
                    button.prop("disabled", true).text("Probando...");
                    resultDiv.html("<div style=\"color: #0073aa; padding: 10px; background: #f0f6fc; border: 1px solid #0073aa; border-radius: 4px;\">🔄 Probando conexión...</div>").show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "test_license_connection",
                            license_key: licenseKey,
                            nonce: "' . wp_create_nonce('test_license_nonce') . '"
                        },
                        success: function(response) {
                            if (response.success) {
                                if (response.data.status === "active") {
                                    resultDiv.html("<div style=\"color: #00a32a; padding: 10px; background: #f0f6fc; border: 1px solid #00a32a; border-radius: 4px;\">✅ Valid and active license</div>").show();
                                } else if (response.data.status === "expired") {
                                    resultDiv.html("<div style=\"color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;\">❌ License Expired. <a href=\"https://www.dwoosync.com\" target=\"_blank\" style=\"color: #d63638; text-decoration: underline;\">Renew here</a></div>").show();
                                } else {
                                    resultDiv.html("<div style=\"color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;\">❌ Invalid or inactive license. <a href=\"https://www.dwoosync.com\" target=\"_blank\" style=\"color: #d63638; text-decoration: underline;\">Pay license here</a></div>").show();
                                }
                            } else {
                                // Manejar diferentes tipos de errores
                                if (response.data && response.data.error === "DOMAIN_MISMATCH") {
                                    resultDiv.html("<div style=\\"color: #f59e0b; padding: 10px; background: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px;\\">⚠️ Connection successful, but domain does not match. Registered domain: " + (response.data.registered_domain || "N/A") + ". <a href=\\"https://www.dwoosync.com\\" target=\\"_blank\\" style=\\"color: #f59e0b; text-decoration: underline;\\">Contact support to update domain</a></div>").show();
                                } else if (response.data && response.data.error === "LICENSE_NOT_FOUND") {
                                    resultDiv.html("<div style=\"color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;\">❌ Invalid or expired license. <a href=\"https://www.dwoosync.com\" target=\"_blank\" style=\"color: #d63638; text-decoration: underline;\">Pay license here</a></div>").show();
                                } else {
                                    resultDiv.html("<div style=\"color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;\">❌ Error: " + (response.data.message || response.data) + "</div>").show();
                                }
                            }
                        },
                        error: function() {
                            resultDiv.html("<div style=\"color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;\">❌ Error de conexión. Verifica tu configuración.</div>").show();
                        },
                        complete: function() {
                            button.prop("disabled", false).text("Probar Conexión");
                        }
                    });
                });
                
                // Prueba de API de Discogs
                $("#test_discogs_api_connection").on("click", function() {
                    var apiKey = $("#discogs_api_key_field").val();
                    var apiSecret = $("#discogs_api_secret_field").val();
                    var resultDiv = $("#discogs_api_test_result");
                    var button = $(this);
                    
                    if (!apiKey || !apiSecret) {
                        resultDiv.html("<div style=\"color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;\">❌ Por favor, ingresa tanto la API Key como el Secret de Discogs.</div>").show();
                        return;
                    }
                    
                    button.prop("disabled", true).text("Probando...");
                    resultDiv.html("<div style=\"color: #0073aa; padding: 10px; background: #f0f6fc; border: 1px solid #0073aa; border-radius: 4px;\">🔄 Probando conexión OAuth con Discogs API...</div>").show();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "test_discogs_api_connection",
                            api_key: apiKey,
                            api_secret: apiSecret,
                            nonce: "' . wp_create_nonce('test_discogs_api_nonce') . '"
                        },
                        success: function(response) {
                            if (response.success) {
                                resultDiv.html("<div style=\"color: #00a32a; padding: 10px; background: #f0f6fc; border: 1px solid #00a32a; border-radius: 4px;\">✅ Conexión OAuth con Discogs API exitosa</div>").show();
                            } else {
                                resultDiv.html("<div style=\"color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;\">❌ Error: " + response.data + "</div>").show();
                            }
                        },
                        error: function() {
                            resultDiv.html("<div style=\"color: #d63638; padding: 10px; background: #fcf0f1; border: 1px solid #d63638; border-radius: 4px;\">❌ Error de conexión. Verifica tu configuración.</div>").show();
                        },
                        complete: function() {
                            button.prop("disabled", false).text("Probar Conexión");
                        }
                    });
                });
            });
        ');
    }

    /**
     * El callback para nuestra petición AJAX.
     * Busca los datos en Discogs.
     *
     * @since    1.0.0
     */
    public function search_discogs_data_callback() {
        error_log('[Discogs Search Debug] Iniciando búsqueda...');
        
        check_ajax_referer( 'wdi_seguridad_total', 'nonce' );
        
        if ( ! current_user_can( 'edit_products' ) ) { 
            error_log('[Discogs Search Debug] Usuario sin permisos');
            wp_send_json_error( array( 'message' => 'No tienes permisos.' ), 403 ); 
        }
        
        if ( ! isset( $_POST['release_id'] ) || empty( $_POST['release_id'] ) ) { 
            wp_send_json_error( array( 'message' => 'Término de búsqueda no proporcionado.' ), 400 ); 
        }

        $search_term = sanitize_text_field( $_POST['release_id'] );
        $selected_format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'Vinyl';
        $selected_country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';

        error_log('[Discogs Search Debug] Término de búsqueda: ' . $search_term);
        error_log('[Discogs Search Debug] Formato: ' . $selected_format);
        error_log('[Discogs Search Debug] País: ' . $selected_country);
        error_log('[Discogs Search Debug] POST completo: ' . print_r($_POST, true));
        error_log('[Discogs Search Debug] País vacío? ' . (empty($selected_country) ? 'SÍ' : 'NO'));

        // Obtener configuración de la API
        $discogs_options = get_option( $this->plugin_name . '_options' );
        
        error_log('[Discogs Search Debug] Discogs Options: ' . print_r($discogs_options, true));
        
        // Usar configuración centralizada si no hay configuración en el panel
        $api_url = $discogs_options['api_url'] ?? (function_exists('wdi_get_api_url') ? wdi_get_api_url() : '');
        $license_key = $discogs_options['license_key'] ?? '';
        $discogs_api_key = $discogs_options['api_key'] ?? '';
        $discogs_api_secret = $discogs_options['api_secret'] ?? '';

        if ( empty( $api_url ) || empty( $license_key ) || empty( $discogs_api_key ) || empty( $discogs_api_secret ) ) {
            error_log('[Discogs Search Debug] Configuración incompleta - API URL: ' . $api_url . ', License: ' . $license_key . ', Discogs Key: ' . $discogs_api_key);
            wp_send_json_error( array( 'message' => 'Configuración de API incompleta. Verifica la configuración en el panel de administración.' ), 400 );
        }

        // Inicializar tracking para búsquedas
        $this->init_usage_tracking(0); // 0 para búsquedas (no hay product_id específico)
        
        // Validar licencia antes de hacer la búsqueda
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $license_validation = $this->validate_license_with_domain($license_key, $domain, $api_url);
        
        if (!$license_validation['valid']) {
            if ($license_validation['error'] === 'DOMAIN_MISMATCH') {
                wp_send_json_error( array( 
                    'message' => 'El dominio actual no corresponde a la suscripción contratada. Por favor, contacta soporte para actualizar el dominio registrado.',
                    'error' => 'DOMAIN_MISMATCH',
                    'registered_domain' => $license_validation['registered_domain'] ?? 'N/A'
                ), 403 );
            } elseif ($license_validation['error'] === 'LICENSE_NOT_FOUND') {
                wp_send_json_error( array( 
                    'message' => 'Invalid or expired license. You must purchase a valid license at: <a href="https://www.discogsync.com" target="_blank" style="color: #0073aa; text-decoration: underline;">www.discogsync.com</a>',
                    'error' => 'LICENSE_NOT_FOUND'
                ), 403 );
            } else {
                wp_send_json_error( array( 
                    'message' => 'Error de validación: ' . $license_validation['message'],
                    'error' => $license_validation['error']
                ), 403 );
            }
        }

        try {
            // Realizar búsqueda usando nuestra API intermediaria (con validación de licencia)
            $filters = array();
            if ( !empty( $selected_format ) ) {
                $filters['format'] = $selected_format;
            }
            // No enviar filtro de país a la API para obtener todos los países disponibles
            // El filtro de país se aplicará después en el frontend

            error_log('[Discogs Search Debug] Filtros: ' . print_r($filters, true));
            error_log('[Discogs Search Debug] Realizando búsqueda...');

            // Usar nuestra API intermediaria para la búsqueda
            $search_result = $this->search_via_api($search_term, $filters, $license_key, $discogs_api_key, $discogs_api_secret, $api_url);
            
            // Contabilizar llamada API de búsqueda
            if ($this->usage_tracker) {
                $this->usage_tracker->increment_api_calls(0, 'search', 'masters');
            }
            
            error_log('[Discogs Search Debug] Resultado de búsqueda: ' . print_r($search_result, true));

            if ( isset($search_result['error']) ) {
                // Contabilizar llamada API fallida
                if ($this->usage_tracker) {
                    $this->usage_tracker->increment_api_calls(0, 'search', 'masters');
                }
                
                // Si es un error de licencia, mostrar mensaje específico
                if (strpos($search_result['error'], 'Licencia inválida') !== false || strpos($search_result['error'], 'expirada') !== false) {
                    wp_send_json_error( array( 'message' => $search_result['error'] ), 403 );
                } else {
                    wp_send_json_error( array( 'message' => 'Error en la búsqueda: ' . $search_result['error'] ), 500 );
                }
            }

            $results = $search_result['results'] ?? [];
            
            error_log('[Discogs Search Debug] Resultados recibidos: ' . count($results));
            error_log('[Discogs Search Debug] País seleccionado: "' . $selected_country . '"');
            
            // Aplicar filtro de país en el frontend si está especificado
            if ( !empty( $selected_country ) ) {
                // Convertir nombre traducido de vuelta al código original
                $country_mapping = array(
                    'Estados Unidos' => 'US',
                    'Reino Unido' => 'UK',
                    'Alemania' => 'Germany',
                    'Francia' => 'France',
                    'Italia' => 'Italy',
                    'España' => 'Spain',
                    'Canadá' => 'Canada',
                    'Australia' => 'Australia',
                    'Japón' => 'Japan',
                    'Países Bajos' => 'Netherlands',
                    'Bélgica' => 'Belgium',
                    'Suecia' => 'Sweden',
                    'Noruega' => 'Norway',
                    'Dinamarca' => 'Denmark',
                    'Finlandia' => 'Finland',
                    'Suiza' => 'Switzerland',
                    'Austria' => 'Austria',
                    'Portugal' => 'Portugal',
                    'Brasil' => 'Brazil',
                    'México' => 'Mexico',
                    'Argentina' => 'Argentina',
                    'Chile' => 'Chile',
                    'Colombia' => 'Colombia',
                    'Perú' => 'Peru',
                    'Uruguay' => 'Uruguay',
                    'Venezuela' => 'Venezuela'
                );
                
                $country_code = isset($country_mapping[$selected_country]) ? $country_mapping[$selected_country] : $selected_country;
                
                error_log('[Discogs Search Debug] Aplicando filtro de país: ' . $selected_country . ' -> ' . $country_code);
                error_log('[Discogs Search Debug] Resultados antes del filtro: ' . count($results));
                
                $results = array_filter( $results, function( $result ) use ( $country_code ) {
                    $matches = $result['country'] === $country_code;
                    if ($matches) {
                        error_log('[Discogs Search Debug] Resultado coincide con país: ' . $result['title'] . ' - ' . $result['country']);
                    }
                    return $matches;
                });
                
                error_log('[Discogs Search Debug] Resultados después del filtro: ' . count($results));
            }
            
            // Mantener todos los resultados sin deduplicación
            
            // Formatear resultados para compatibilidad con el frontend existente
        $formatted_results = array();
            foreach ( $results as $result ) {
            $formatted_results[] = array(
                    'id' => $result['id'],
                    'title' => $result['title'],
                    'thumb' => $result['thumb'],
                    'catno' => $result['catno'] ?? '',
                    'year' => $result['year'] ?? '',
                    'country' => $result['country'] ?? '',
                );
            }

            // Calcular estadísticas de países
            $country_stats = null;
            
            // Siempre usar country_counts de la API para mantener lista completa
            if ( isset($search_result['country_counts']) && !empty($search_result['country_counts']) ) {
                $country_stats = array();
                foreach ( $search_result['country_counts'] as $code => $count ) {
                    // Si hay un país seleccionado y es este país, usar el conteo real de resultados mostrados
                    if ( !empty( $selected_country ) && $code === $selected_country ) {
                        $actual_count = count($results);
                    } else {
                        $actual_count = $count;
                    }
                    
                    $country_stats[] = array(
                        'code' => $code, 
                        'name' => $this->get_country_name($code), 
                        'count' => $actual_count
                    );
                }
                usort($country_stats, function($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
            } else {
                // Fallback: contar países en los resultados actuales
                $stats = array();
                foreach ( $results as $result ) {
                    if ( !empty( $result['country'] ) ) {
                        $country = $result['country'];
                        if ( !isset( $stats[$country] ) ) $stats[$country] = 0;
                        $stats[$country]++;
                    }
                }
                $country_stats = array();
                foreach ( $stats as $code => $count ) {
                    $country_stats[] = array(
                        'code' => $code, 
                        'name' => $this->get_country_name($code), 
                        'count' => $count
                    );
                }
                usort($country_stats, function($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
            }


        // --- INICIO: Obtener categorías sugeridas basadas en el formato ---
        $suggested_categories = $this->get_suggested_categories_for_format($selected_format);
        // --- FIN: Obtener categorías sugeridas basadas en el formato ---

        // Preparar respuesta
        $response_data = array(
            'results' => $formatted_results,
                'country_stats' => $country_stats,
            'suggested_categories' => $suggested_categories,
            'selected_format' => $selected_format
        );
        
        // Incluir warning si la licencia está en período de gracia
        if (isset($license_validation['warning'])) {
            $response_data['warning'] = $license_validation['warning'];
        }
        
        wp_send_json_success($response_data);

        } catch ( Exception $e ) {
            error_log( 'Error en búsqueda Discogs: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => 'Error interno del servidor.' ), 500 );
        }
    }

    /**
     * El callback para la importación de un lanzamiento específico.
     *
     * @since    1.0.0
     */
    public function import_discogs_release_callback() {
        try {
            check_ajax_referer( 'wdi_seguridad_total', 'nonce' ); // Usar el nuevo nonce

            if ( ! current_user_can( 'edit_products' ) ) {
                wp_send_json_error( array( 'message' => 'No tienes permisos para realizar esta acción.' ), 403 );
            }

            if ( ! isset( $_POST['release_id'] ) || empty( $_POST['release_id'] ) ) {
                wp_send_json_error( array( 'message' => 'No se ha proporcionado un ID de lanzamiento.' ), 400 );
            }
            // --- INICIO DE LA MODIFICACIÓN ---
            if ( ! isset( $_POST['post_id'] ) || empty( $_POST['post_id'] ) ) {
                wp_send_json_error( array( 'message' => 'No se ha proporcionado un ID de producto.' ), 400 );
            }
            $release_id = intval( $_POST['release_id'] );
            $post_id = intval( $_POST['post_id'] );
            // --- FIN DE LA MODIFICACIÓN ---
            
            error_log('[Discogs Import Debug] Iniciando importación para release_id: ' . $release_id . ', post_id: ' . $post_id);
            
            // Inicializar contabilización de uso
            $this->init_usage_tracking($post_id);

            // La validación de límites ahora se hace en la API intermediaria
            // No necesitamos validar localmente para mayor seguridad

        $options = get_option( $this->plugin_name . '_options' );
        $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';
        $api_secret = isset( $options['api_secret'] ) ? $options['api_secret'] : '';
        $license_key = isset( $options['license_key'] ) ? $options['license_key'] : '';

        if ( empty( $api_key ) || empty( $api_secret ) ) {
            wp_send_json_error( array( 'message' => 'Las credenciales de la API de Discogs no están configuradas.' ), 400 );
        }

        // Validar licencia antes de hacer la importación
        if ( empty( $license_key ) ) {
            wp_send_json_error( array( 'message' => 'La clave de licencia no está configurada.' ), 400 );
        }

        // Obtener configuración de la API
        $discogs_options = get_option( $this->plugin_name . '_options' );
        $api_url = $discogs_options['api_url'] ?? (function_exists('wdi_get_api_url') ? wdi_get_api_url() : '');
        
        if (empty($api_url)) {
            wp_send_json_error( array( 'message' => 'URL de API no configurada.' ), 400 );
        }

        // Validar licencia antes de hacer la importación
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $license_validation = $this->validate_license_with_domain($license_key, $domain, $api_url);
        
        if (!$license_validation['valid']) {
            if ($license_validation['error'] === 'DOMAIN_MISMATCH') {
                wp_send_json_error( array( 
                    'message' => 'El dominio actual no corresponde a la suscripción contratada. Por favor, contacta soporte para actualizar el dominio registrado.',
                    'error' => 'DOMAIN_MISMATCH'
                ), 403 );
            } elseif ($license_validation['error'] === 'LICENSE_NOT_FOUND') {
                wp_send_json_error( array( 
                    'message' => 'Invalid or expired license. You must purchase a valid license at: <a href="https://www.discogsync.com" target="_blank" style="color: #0073aa; text-decoration: underline;">www.discogsync.com</a>',
                    'error' => 'LICENSE_NOT_FOUND'
                ), 403 );
            } else {
                wp_send_json_error( array( 
                    'message' => 'Error de validación: ' . $license_validation['message'],
                    'error' => $license_validation['error']
                ), 403 );
            }
        }

        // Usar nuestra API intermediaria para obtener detalles del release
        error_log('[Discogs Import Debug] Llamando a get_release_via_api con release_id: ' . $release_id);
        $release_data = $this->get_release_via_api($release_id, $license_key, $api_key, $api_secret, $api_url);
        error_log('[Discogs Import Debug] Respuesta de get_release_via_api: ' . print_r($release_data, true));
        
        if (isset($release_data['error'])) {
            error_log('[Discogs Import Debug] Error en get_release_via_api: ' . $release_data['error']);
            wp_send_json_error( array( 'message' => $release_data['error'] ), 500 );
        }

        // El endpoint release ya actualiza api_calls_count automáticamente

        $data = $release_data;

        error_log('[Discogs Import Debug] Datos recibidos de la API: ' . print_r($data, true));

        // --- Procesar Plantillas ---
        $options_templates = get_option( $this->plugin_name . '_options' ); // Recargar opciones por si acaso
        $short_desc_template = isset( $options_templates['short_desc_template'] ) ? $options_templates['short_desc_template'] : "<strong>Artista:</strong> [artist]\n<strong>Formato:</strong> [format] ([year])";
        $long_desc_template = isset( $options_templates['long_desc_template'] ) ? $options_templates['long_desc_template'] : "<h3>Lista de Canciones</h3>\n[tracklist]\n<hr>\n<strong>Sello:</strong> [label]\n<strong>País:</strong> [country]";

        $processed_short_desc = $this->process_description_template( $short_desc_template, $data );
        $processed_long_desc = $this->process_description_template( $long_desc_template, $data );

        error_log('[Discogs Import Debug] Descripción corta procesada: ' . $processed_short_desc);
        error_log('[Discogs Import Debug] Descripción larga procesada: ' . $processed_long_desc);

        // --- Guardar Título y Descripciones ---
        $product_title = (isset($data['artists'][0]['name']) ? $data['artists'][0]['name'] : '') . ' - ' . (isset($data['title']) ? $data['title'] : '');
        
        // --- INICIO: Lógica para anteponer el formato ---
        if ( isset( $options_templates['prepend_format_to_title'] ) && $options_templates['prepend_format_to_title'] === '1' ) {
            $format_from_api = isset($data['formats'][0]['name']) ? $data['formats'][0]['name'] : '';
            if ( ! empty( $format_from_api ) ) {
                $translated_format = $this->translate_format_name($format_from_api);
                $product_title = $translated_format . ' ' . $product_title;
            }
        }
        // --- FIN: Lógica para anteponer el formato ---

        $product_data_to_update = array(
            'ID'           => $post_id,
            'post_title'   => $product_title,
            'post_content' => $processed_long_desc,
            'post_excerpt' => $processed_short_desc,
        );
        
        wp_update_post( $product_data_to_update, true );

        // --- INICIO: Lógica para importar estilos como etiquetas ---
        $import_tags_option = isset( $options_templates['import_styles_as_tags'] ) ? $options_templates['import_styles_as_tags'] : '0';
        error_log('[Discogs Tags Debug] La opción de importar etiquetas está en: ' . $import_tags_option);

        if ( $import_tags_option === '1' ) {
            error_log('[Discogs Tags Debug] Entrando al bloque para importar etiquetas.');
            if ( ! empty( $data['styles'] ) && is_array( $data['styles'] ) ) {
                $styles_to_import = $data['styles'];
                error_log('[Discogs Tags Debug] Estilos para importar: ' . print_r($styles_to_import, true));
                
                $result = wp_set_post_terms( $post_id, $styles_to_import, 'product_tag', false ); // Cambiado a 'false' para reemplazar
                
                if ( is_wp_error( $result ) ) {
                    error_log('[Discogs Tags Debug] ERROR al guardar las etiquetas: ' . $result->get_error_message());
                } else {
                    error_log('[Discogs Tags Debug] Etiquetas guardadas con éxito. Resultado: ' . print_r($result, true));
                }
            } else {
                error_log('[Discogs Tags Debug] No se encontraron estilos en los datos de la API para este lanzamiento.');
            }
        }
        // --- FIN: Lógica para importar estilos como etiquetas ---

        // --- INICIO: Lógica para asignar categorías automáticas por formato ---
        // Verificar si el usuario seleccionó categorías específicas
        if ( isset( $_POST['selected_categories'] ) && ! empty( $_POST['selected_categories'] ) ) {
            $selected_categories = array_map( 'intval', $_POST['selected_categories'] );
            error_log('[Discogs Categories Debug] Usando categorías seleccionadas por el usuario: ' . print_r($selected_categories, true));
            $this->assign_user_selected_categories( $post_id, $selected_categories );
        } else {
            // Fallback: usar detección automática basada en formato
            error_log('[Discogs Categories Debug] No hay categorías seleccionadas, usando detección automática');
            $this->assign_automatic_categories( $post_id, $data );
        }
        // --- FIN: Lógica para asignar categorías automáticas por formato ---

        // --- INICIO: Lógica para importar imágenes ---
        if ( ! empty( $data['images'] ) && is_array( $data['images'] ) ) {
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );

            $image_urls = array_column( $data['images'], 'uri' );
            $gallery_ids = array();
            $featured_image_id = null;

            foreach ( $image_urls as $index => $image_url ) {
                $tmp = download_url( $image_url );
                if ( ! is_wp_error( $tmp ) ) {
                    $file_array = array(
                        'name'     => 'discogs-image-' . $post_id . '-' . $index . '.jpg',
                        'tmp_name' => $tmp
                    );
                    
                    $attachment_id = media_handle_sideload( $file_array, $post_id );

                    if ( ! is_wp_error( $attachment_id ) ) {
                        if ( $index === 0 ) { // La primera imagen es la destacada
                            set_post_thumbnail( $post_id, $attachment_id );
                            $featured_image_id = $attachment_id; // Guardar el ID
                        } else {
                            $gallery_ids[] = $attachment_id;
                        }
                    }
                    @unlink( $tmp );
                }
            }

            if ( ! empty( $gallery_ids ) ) {
                update_post_meta( $post_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
            }
        }
        // --- FIN: Lógica para importar imágenes ---

        $featured_image_url = isset($featured_image_id) ? wp_get_attachment_thumb_url($featured_image_id) : '';

        $formatted_data = array(
            'title'             => $product_title,
            'artist'            => isset($data['artists'][0]['name']) ? $data['artists'][0]['name'] : '',
            'images'            => isset($data['images']) ? array_column($data['images'], 'uri') : array(),
            'short_description' => $processed_short_desc,
            'long_description'  => $processed_long_desc,
            'featured_image_id' => isset($featured_image_id) ? $featured_image_id : null,
            'featured_image_url'=> $featured_image_url,
            'tags'              => isset($styles_to_import) ? $styles_to_import : array(),
        );

        // Contabilizar sincronización exitosa
        error_log('[Discogs Import Debug] Iniciando track_sync_operation para product_id: ' . $post_id);
        $track_result = $this->track_sync_operation($post_id, 'manual', 'import');
        error_log('[Discogs Import Debug] Resultado de track_sync_operation: ' . ($track_result ? 'true' : 'false'));
        
        // Verificar si track_sync_operation retornó un error de límite excedido
        if (is_array($track_result) && isset($track_result['error']) && $track_result['error'] === 'USAGE_LIMIT_EXCEEDED') {
            wp_send_json_error( array( 
                'message' => $track_result['message'],
                'error' => 'USAGE_LIMIT_REACHED',
                'limit' => $track_result['limit'],
                'current' => $track_result['current']
            ), 429 );
        }

        wp_send_json_success( $formatted_data );
        
        } catch (Exception $e) {
            error_log('[Discogs Import Debug] Error fatal en importación: ' . $e->getMessage());
            error_log('[Discogs Import Debug] Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error( array( 'message' => 'Error interno del servidor: ' . $e->getMessage() ), 500 );
        }
    }

    // --- INICIO: Nuevo callback para buscar categorías ---
    /**
     * El callback para la búsqueda de categorías de producto.
     *
     * @since    2.0.0
     */
    public function search_product_categories_callback() {
        check_ajax_referer( 'wdi_search_cats_nonce', 'nonce' ); // <-- Cambiado a 'nonce'

        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => 'No tienes permisos.' ), 403 );
            return;
        }

        $search_term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';
        error_log('[Discogs Category Debug] Término de búsqueda: ' . $search_term);
        $results = array();

        $args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'number'     => 20,
        );

        if ( ! empty( $search_term ) ) {
            $args['name__like'] = $search_term;
        }

        $terms = get_terms($args);
        error_log('[Discogs Category Debug] Argumentos para get_terms: ' . print_r($args, true));

        if ( is_wp_error( $terms ) ) {
            error_log('[Discogs Category Debug] WP_Error al obtener términos: ' . $terms->get_error_message());
        } elseif ( empty( $terms ) ) {
            error_log('[Discogs Category Debug] get_terms() no devolvió ningún resultado.');
        } else {
            error_log('[Discogs Category Debug] Términos encontrados: ' . count($terms));
            foreach ( $terms as $term ) {
                $results[] = array(
                    'id'   => $term->term_id,
                    'text' => $term->name,
                );
            }
        }

        wp_send_json( array( 'results' => $results ) );
    }
    // --- FIN: Nuevo callback para buscar categorías ---

    /**
     * Procesa una plantilla de descripción reemplazando los shortcodes con datos de Discogs.
     *
     * @param string $template La plantilla con shortcodes.
     * @param array $data Los datos de la API de Discogs para un lanzamiento.
     * @return string La plantilla procesada con los datos reales.
     */
    private function process_description_template( $template, $data ) {
        // Helper para convertir arrays a strings
        $array_to_string = function($arr, $key) {
            if (!empty($arr) && is_array($arr)) {
                return implode(', ', array_column($arr, $key));
            }
            return '';
        };

        // Reemplazos de shortcodes simples
        $replacements = array(
            '[title]'    => isset($data['title']) ? $data['title'] : '',
            '[artist]'   => isset($data['artists'][0]['name']) ? $data['artists'][0]['name'] : '',
            '[year]'     => isset($data['year']) ? $data['year'] : '',
            '[label]'    => $array_to_string(isset($data['labels']) ? $data['labels'] : [], 'name'),
            '[format]'   => $array_to_string(isset($data['formats']) ? $data['formats'] : [], 'name'),
            '[country]'  => isset($data['country']) ? $data['country'] : '',
            '[released]' => isset($data['released_formatted']) ? $data['released_formatted'] : '',
            '[genre]'    => isset($data['genres']) ? implode(', ', $data['genres']) : '',
            '[style]'    => isset($data['styles']) ? implode(', ', $data['styles']) : '',
            '[notes]'    => isset($data['notes']) ? nl2br($data['notes']) : '',
        );

        $processed_template = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Reemplazo especial para el tracklist
        if (strpos($processed_template, '[tracklist]') !== false) {
            $tracklist_html = '';
            if (!empty($data['tracklist']) && is_array($data['tracklist'])) {
                $tracklist_html .= '<ol>';
                foreach ($data['tracklist'] as $track) {
                    $duration = !empty($track['duration']) ? ' (' . $track['duration'] . ')' : '';
                    $tracklist_html .= '<li>' . esc_html($track['title']) . $duration . '</li>';
                }
                $tracklist_html .= '</ol>';
            }
            $processed_template = str_replace('[tracklist]', $tracklist_html, $processed_template);
        }

        // Convertir saltos de línea a HTML para respetar el formato
        $processed_template = nl2br($processed_template);

        return $processed_template;
    }

    /**
     * Shortcode para mostrar widget de Spotify automáticamente
     * Uso: [discogsync_spotify]
     */
    public function discogsync_spotify_shortcode($atts) {
        // Tamaño estándar: 500px de ancho, alto automático
        $atts = shortcode_atts(array(
            'width' => '500'
        ), $atts);
        
        // Verificar el tipo de plan del suscriptor
        $plan_type = $this->get_subscriber_plan_type();
        
        // Solo permitir Spotify para plan enterprise
        if ($plan_type !== 'enterprise') {
            return '<div class="discogsync-spotify-widget" style="padding: 20px; text-align: center; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <p>La integración con Spotify está disponible solo en el Plan +Spotify. <a href="https://www.dwoosync.com" target="_blank" style="color: #0073aa; text-decoration: underline;">Mejorar plan aquí</a></p>
                    </div>';
        }
        
        // Detectar contexto actual y extraer información del disco
        $disc_info = $this->detect_disc_info();
        
        if (!$disc_info) {
            return '<div class="discogsync-spotify-widget" style="padding: 20px; text-align: center; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <p>No se pudo detectar información del disco para mostrar en Spotify.</p>
                    </div>';
        }
        
        // Buscar en Spotify
        $spotify_data = $this->search_spotify($disc_info);
        
        if (!$spotify_data) {
            return '<div class="discogsync-spotify-widget" style="padding: 20px; text-align: center; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        <p>Disco no encontrado en Spotify: <strong>' . esc_html($disc_info['title']) . '</strong></p>
                    </div>';
        }
        
        // Generar widget de Spotify
        return $this->generate_spotify_widget($spotify_data, $atts);
    }
    
    /**
     * Detecta información del disco basada en el contexto actual
     */
    private function detect_disc_info() {
        // 1. Si estamos en una página de producto WooCommerce
        if (function_exists('is_product') && is_product()) {
            global $product;
            if ($product) {
                $title = $product->get_name();
                return array(
                    'title' => $title,
                    'context' => 'woocommerce_product'
                );
            }
        }
        
        // 2. Si estamos en un post/página con metadatos de Discogs
        if (is_single() || is_page()) {
            $post_id = get_the_ID();
            $discogs_title = get_post_meta($post_id, 'discogs_title', true);
            $discogs_artist = get_post_meta($post_id, 'discogs_artist', true);
            
            if ($discogs_title) {
                return array(
                    'title' => $discogs_title,
                    'artist' => $discogs_artist,
                    'context' => 'post_meta'
                );
            }
        }
        
        // 3. Si estamos en el admin de WordPress (para testing)
        if (is_admin()) {
            return array(
                'title' => 'Pink Floyd - Dark Side of the Moon',
                'artist' => 'Pink Floyd',
                'context' => 'admin_test'
            );
        }
        
        // 4. Si hay un producto global disponible (para testing)
        global $product;
        if ($product && method_exists($product, 'get_name')) {
            return array(
                'title' => $product->get_name(),
                'context' => 'global_product'
            );
        }
        
        // 5. Fallback para contextos de prueba
        if (isset($product) && is_object($product) && property_exists($product, 'name')) {
            return array(
                'title' => $product->name,
                'context' => 'fallback_product'
            );
        }
        
        // 4. Si estamos en el frontend, intentar detectar desde el contexto
        if (is_front_page() || is_home()) {
            // En la página principal, mostrar un disco de ejemplo
            return array(
                'title' => 'Pink Floyd - Wish You Were Here',
                'artist' => 'Pink Floyd',
                'context' => 'frontend_example'
            );
        }
        
        // 5. Si estamos en una página de categoría o archivo
        if (is_category() || is_archive()) {
            return array(
                'title' => 'Pink Floyd - Animals',
                'artist' => 'Pink Floyd',
                'context' => 'archive_example'
            );
        }
        
        // 6. Fallback final - siempre devolver datos de ejemplo para testing
        return array(
            'title' => 'Pink Floyd - Dark Side of the Moon',
            'artist' => 'Pink Floyd',
            'context' => 'fallback_example'
        );
    }
    
    /**
     * Obtiene un token de acceso de Spotify
     */
    private function get_spotify_token() {
        $client_id = '1b60339de498414b8bc26de183077da4';
        $client_secret = '8f34c1053c2a4d4a804e66894262d1f7';
        
        $url = 'https://accounts.spotify.com/api/token';
        $data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret
        );
        
        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            )
        );
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === FALSE) {
            return false;
        }
        
        $response = json_decode($result, true);
        return isset($response['access_token']) ? $response['access_token'] : false;
    }
    
    /**
     * Busca álbumes en Spotify usando su API
     */
    private function search_spotify_albums($query, $token) {
        $url = 'https://api.spotify.com/v1/search?q=' . urlencode($query) . '&type=album&limit=5';
        
        $options = array(
            'http' => array(
                'header' => "Authorization: Bearer $token\r\n",
                'method' => 'GET'
            )
        );
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === FALSE) {
            return false;
        }
        
        return json_decode($result, true);
    }
    
    /**
     * Obtiene las canciones de un álbum de Spotify
     */
    private function get_spotify_album_tracks($album_id, $token) {
        $url = 'https://api.spotify.com/v1/albums/' . $album_id . '/tracks?market=US';
        
        $options = array(
            'http' => array(
                'header' => "Authorization: Bearer $token\r\n",
                'method' => 'GET'
            )
        );
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === FALSE) {
            return false;
        }
        
        return json_decode($result, true);
    }

    /**
     * Busca el disco en Spotify usando su API
     */
    private function search_spotify($disc_info) {
        $title = $disc_info['title'];
        $artist = $disc_info['artist'] ?? '';
        
        // Si no tenemos artista separado, intentar extraerlo del título
        if (empty($artist)) {
            // Buscar patrones comunes de separación: "Artista – Título", "Artista - Título", "Artista: Título"
            // Usar múltiples patrones para diferentes tipos de separadores
            $patterns = array(
                '/^(.+?)\s*[–—]\s*(.+)$/u',  // Guión largo y em dash
                '/^(.+?)\s*-\s*(.+)$/',      // Guión normal
                '/^(.+?)\s*:\s*(.+)$/',      // Dos puntos
                '/^(.+?)\s*\|\s*(.+)$/'      // Barra vertical
            );
            
            $found = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $title, $matches)) {
                    $artist = trim($matches[1]);
                    $title = trim($matches[2]);
                    $found = true;
                    break;
                }
            }
            
            // Si no hay separador, usar el título completo como búsqueda
            if (!$found) {
                $search_query = $title;
            }
        }
        
        // Si tenemos artista y título separados, optimizar la búsqueda
        if (!empty($artist) && !empty($title)) {
            $search_query = $artist . ' ' . $title;
        } elseif (!empty($artist)) {
            $search_query = $artist;
        } elseif (!empty($title)) {
            $search_query = $title;
        } else {
            return false;
        }
        
        // Limpiar la consulta de búsqueda
        $search_query = trim($search_query);
        
        // Remover términos de formato que no son útiles para la búsqueda en Spotify
        $format_terms = array(
            'Vinilo', 'Vinyl', 'CD', 'Cassette', 'Cassette Tape',
            'LP', 'EP', 'Single', '7"', '12"', '10"',
            'Album', 'Álbum', 'Record', 'Disco'
        );
        
        foreach ($format_terms as $term) {
            $search_query = preg_replace('/\b' . preg_quote($term, '/') . '\b/i', '', $search_query);
        }
        
        // Limpiar espacios múltiples y espacios al inicio/final
        $search_query = preg_replace('/\s+/', ' ', trim($search_query));
        
        // Si no hay consulta, retornar false
        if (empty($search_query)) {
            return false;
        }
        
        // Intentar buscar en Spotify usando la API real
        $token = $this->get_spotify_token();
        if ($token) {
            $search_results = $this->search_spotify_albums($search_query, $token);
            if ($search_results && isset($search_results['albums']['items'][0])) {
                $album = $search_results['albums']['items'][0];
                $tracks = $this->get_spotify_album_tracks($album['id'], $token);
                
                return array(
                    'title' => $title,
                    'artist' => $artist ?: 'Unknown Artist',
                    'search_query' => $search_query,
                    'spotify_album' => $album,
                    'spotify_tracks' => $tracks ? $tracks['items'] : array(),
                    'has_spotify_data' => true
                );
            }
        }
        
        // Fallback: retornar datos básicos si no se puede conectar a Spotify
        return array(
            'title' => $title,
            'artist' => $artist ?: 'Unknown Artist',
            'search_query' => $search_query,
            'has_spotify_data' => false
        );
    }
    
    /**
     * Genera el widget HTML de Spotify
     */
    public function generate_spotify_widget($spotify_data, $atts) {
        $width = intval($atts['width']);
        
        // Obtener configuración de diseño
        $options = get_option( $this->plugin_name . '_options' );
        $design = isset( $options['spotify_design'] ) ? esc_attr( $options['spotify_design'] ) : 'standard';
        
        // Usar el widget de búsqueda de Spotify con mejor configuración
        $search_query = urlencode($spotify_data['search_query']);
        
        if (isset($spotify_data['has_spotify_data']) && $spotify_data['has_spotify_data'] && isset($spotify_data['spotify_album'])) {
            // Widget con iframe oficial de Spotify
            $album = $spotify_data['spotify_album'];
            $spotify_url = $album['external_urls']['spotify'];
            
            // Aplicar estilos según el diseño seleccionado
            if ($design === 'compact') {
                // Diseño compacto - ancho configurable, alto fijo compacto
                $compact_width = isset( $options['spotify_width'] ) ? intval( $options['spotify_width'] ) : 400;
                
                $iframe_url = 'https://open.spotify.com/embed/album/' . basename($spotify_url);
                $widget_html = '<iframe src="' . $iframe_url . '" 
                    width="' . $compact_width . '" 
                    height="173" 
                    frameborder="0" 
                    allowtransparency="true" 
                    allow="encrypted-media">
                </iframe>';
            } else {
                // Diseño estándar - usar ancho del shortcode y alto fijo
                $iframe_url = 'https://open.spotify.com/embed/album/' . basename($spotify_url);
                $widget_html = '<iframe src="' . $iframe_url . '" 
                    width="' . $width . '" 
                    height="380" 
                    frameborder="0" 
                    allowtransparency="true" 
                    allow="encrypted-media" 
                    style="border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
                </iframe>';
            }
        } else {
            // Widget de fallback sin datos de Spotify
            $widget_html = '
            <div class="discogsync-spotify-widget" style="margin: 20px 0; background: #191414; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.3);">
                <div style="background: linear-gradient(135deg, #1db954, #1ed760); padding: 20px; color: white;">
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <div style="width: 60px; height: 60px; background: #333; border-radius: 4px; margin-right: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                            🎵
                        </div>
                        <div>
                            <h3 style="margin: 0; font-size: 18px; color: white;">' . esc_html($spotify_data['title']) . '</h3>
                            <p style="margin: 5px 0 0 0; color: #b3b3b3; font-size: 14px;">' . esc_html($spotify_data['artist']) . '</p>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <a href="https://open.spotify.com/search/' . $search_query . '" 
                           target="_blank" 
                           style="display: inline-block; background: white; color: #1db954; padding: 12px 24px; border-radius: 25px; text-decoration: none; font-weight: bold; font-size: 14px; transition: all 0.3s ease;">
                            ▶️ Buscar en Spotify
                        </a>
                    </div>
                </div>
            </div>';
        }
        
        return $widget_html;
    }

    /**
     * Convierte código de país a nombre legible.
     */
    private function get_country_name( $country_code ) {
        $countries = array(
            'US' => 'Estados Unidos',
            'UK' => 'Reino Unido', 
            'DE' => 'Alemania',
            'FR' => 'Francia',
            'IT' => 'Italia',
            'ES' => 'España',
            'CA' => 'Canadá',
            'AU' => 'Australia',
            'JP' => 'Japón',
            'NL' => 'Países Bajos',
            'BE' => 'Bélgica',
            'SE' => 'Suecia',
            'NO' => 'Noruega',
            'DK' => 'Dinamarca',
            'FI' => 'Finlandia',
            'CH' => 'Suiza',
            'AT' => 'Austria',
            'PT' => 'Portugal',
            'BR' => 'Brasil',
            'MX' => 'México',
            'AR' => 'Argentina',
            'Europe' => 'Europe',
            'Germany' => 'Alemania',
            'France' => 'Francia',
            'Italy' => 'Italia',
            'Canada' => 'Canadá'
        );
        
        return isset( $countries[$country_code] ) ? $countries[$country_code] : $country_code;
    }

    /**
     * Guarda los metadatos personalizados cuando se guarda un producto.
     * Específicamente, gestiona la importación de la imagen de Discogs.
     *
     * @since    1.0.0
     * @param int $post_id El ID del post que se está guardando.
     */
    public function save_product_meta_data( $post_id ) {
        // 0. Verificar que es un producto
        if ( get_post_type( $post_id ) !== 'product' ) {
            return;
        }
        
        // 1. Verificación de seguridad (Nonce)
        if ( ! isset( $_POST[ $this->plugin_name . '_nonce' ] ) || ! wp_verify_nonce( $_POST[ $this->plugin_name . '_nonce' ], $this->plugin_name . '_meta_box_nonce' ) ) {
            return;
        }

        // 2. Comprobar si es un autoguardado
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // 3. Comprobar permisos de usuario
        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }
        
        // 4. Comprobar si tenemos una URL de imagen para importar
        if ( isset( $_POST['_discogs_gallery_to_import'] ) && ! empty( $_POST['_discogs_gallery_to_import'] ) ) {
            
            $image_urls_str = sanitize_text_field( $_POST['_discogs_gallery_to_import'] );
            $image_urls = array_map('esc_url_raw', explode(',', $image_urls_str));
            
            // Cargar los archivos necesarios de WordPress
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            
            $gallery_ids = array();
            $featured_image_id = null;
            
            foreach ( $image_urls as $index => $image_url ) {
                // Usar media_handle_sideload de forma más simple
                $tmp = download_url( $image_url );
                if ( is_wp_error( $tmp ) ) {
                    continue;
                }

                $file_array = array(
                    'name' => 'discogs-image-' . $index . '.jpg',
                    'tmp_name' => $tmp
                );

                $attachment_id = media_handle_sideload( $file_array, $post_id );
                @unlink( $tmp );
                
                if ( ! is_wp_error( $attachment_id ) ) {
                    if ( $index === 0 ) {
                        // Primera imagen como destacada
                        $featured_image_id = $attachment_id;
                        set_post_thumbnail( $post_id, $attachment_id );
                    } else {
                        // El resto a la galería
                        $gallery_ids[] = $attachment_id;
                    }
                }
            }

            // Usar el método directo de WooCommerce para la galería
            if ( ! empty( $gallery_ids ) && function_exists( 'wc_get_product' ) ) {
                try {
                    $product = wc_get_product( $post_id );
                    if ( $product ) {
                        // Limpiar galería existente y establecer nueva
                        $product->set_gallery_image_ids( $gallery_ids );
                        $product->save();
                    }
                } catch ( Exception $e ) {
                    // Fallback manual si WooCommerce API falla
                    update_post_meta( $post_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
                }
            }
        }
    }
    
    /**
     * Renderiza el campo para el diseño del reproductor de Spotify
     */
    public function render_spotify_design_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $design = isset( $options['spotify_design'] ) ? esc_attr( $options['spotify_design'] ) : 'standard';
        
        echo '<fieldset>';
        echo '<label><input type="radio" name="' . $this->plugin_name . '_options[spotify_design]" value="standard" ' . checked( $design, 'standard', false ) . '> ' . __( 'Diseño Estándar', 'dwoosync' ) . '</label><br>';
        echo '<label><input type="radio" name="' . $this->plugin_name . '_options[spotify_design]" value="compact" ' . checked( $design, 'compact', false ) . '> ' . __( 'Diseño Compacto', 'dwoosync' ) . '</label>';
        echo '</fieldset>';
        
        echo '<p class="description">';
        echo '<strong>' . __( 'Diseño Estándar:', 'dwoosync' ) . '</strong> ' . __( 'Bordes redondeados grandes (12px) y sombra pronunciada. Usa ancho del shortcode y alto fijo (380px).', 'dwoosync' ) . '<br>';
        echo '<strong>' . __( 'Diseño Compacto:', 'dwoosync' ) . '</strong> ' . __( 'Estilo por defecto de Spotify. Permite configurar ancho personalizado, alto fijo compacto (173px).', 'dwoosync' );
        echo '</p>';
    }
    
    /**
     * Renderiza el campo para el ancho del reproductor de Spotify
     */
    public function render_spotify_width_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $width = isset( $options['spotify_width'] ) ? esc_attr( $options['spotify_width'] ) : '400';
        echo "<input type='number' name='{$this->plugin_name}_options[spotify_width]' value='{$width}' class='small-text' min='200' max='800'>";
        echo '<p class="description">' . __( 'Ancho del reproductor en píxeles (200-800px).', 'dwoosync' ) . '</p>';
    }
    
    /**
     * Renderiza el campo para el alto del reproductor de Spotify
     */
    public function render_spotify_height_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $height = isset( $options['spotify_height'] ) ? esc_attr( $options['spotify_height'] ) : '380';
        echo "<input type='number' name='{$this->plugin_name}_options[spotify_height]' value='{$height}' class='small-text' min='200' max='600'>";
        echo '<p class="description">' . __( 'Alto del reproductor en píxeles (200-600px).', 'dwoosync' ) . '</p>';
    }
    
    /**
     * Renderiza el campo para el tema del reproductor de Spotify
     */
    public function render_spotify_theme_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $theme = isset( $options['spotify_theme'] ) ? esc_attr( $options['spotify_theme'] ) : 'light';
        echo "<select name='{$this->plugin_name}_options[spotify_theme]'>";
        echo "<option value='light'" . selected( $theme, 'light', false ) . ">" . __( 'Claro', 'dwoosync' ) . "</option>";
        echo "<option value='dark'" . selected( $theme, 'dark', false ) . ">" . __( 'Oscuro', 'dwoosync' ) . "</option>";
        echo "</select>";
        echo '<p class="description">' . __( 'Tema visual del reproductor.', 'dwoosync' ) . '</p>';
    }
    
    /**
     * Renderiza el campo para la reproducción automática
     */
    public function render_spotify_autoplay_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $checked = isset( $options['spotify_autoplay'] ) && $options['spotify_autoplay'] === '1';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '_options[spotify_autoplay]" value="1"' . checked( $checked, true, false ) . '>';
        echo ' ' . __( 'Iniciar reproducción automáticamente al cargar el reproductor.', 'dwoosync' ) . '</label>';
    }
    
    /**
     * Renderiza el campo para el diseño compacto
     */
    public function render_spotify_compact_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $checked = isset( $options['spotify_compact'] ) && $options['spotify_compact'] === '1';
        echo '<label><input type="checkbox" name="' . $this->plugin_name . '_options[spotify_compact]" value="1"' . checked( $checked, true, false ) . '>';
        echo ' ' . __( 'Usar diseño compacto del reproductor (estilos optimizados).', 'dwoosync' ) . '</label>';
        echo '<p class="description">' . __( 'El diseño compacto usa los mismos campos de ancho y alto pero con estilos más eficientes (bordes más pequeños, sombra reducida). Al activar esta opción, se habilitará el campo de alto del reproductor.', 'dwoosync' ) . '</p>';
    }
    
    /**
     * Cambia el nombre de la pestaña "Descripción" por "Listado de Canciones" si está activo
     */
    public function rename_description_tab( $tabs ) {
        $options = get_option( $this->plugin_name . '_options' );
        
        if ( isset( $options['rename_description_tab'] ) && $options['rename_description_tab'] === '1' ) {
            if ( isset( $tabs['description'] ) ) {
                $tabs['description']['title'] = __( 'Listado de Canciones', 'dwoosync' );
            }
        }
        
        return $tabs;
    }
    
    /**
     * Inicializar el tracking de uso para un producto
     */
    private function init_usage_tracking($product_id) {
        try {
            // Obtener información de la licencia
            $options = get_option('dwoosync_options');
            $license_key = $options['license_key'] ?? '';
            
            error_log('[WDI_Admin] Inicializando usage tracking para product_id: ' . $product_id);
            error_log('[WDI_Admin] License key encontrada: ' . $license_key);
            
            if (empty($license_key)) {
                error_log('[WDI_Admin] No se encontró license_key para tracking');
                return;
            }
            
            // Obtener subscriber_id desde la base de datos directamente (sin validar licencia)
            $subscriber_id = $this->get_subscriber_id_direct($license_key);
            
            error_log('[WDI_Admin] Subscriber ID obtenido: ' . $subscriber_id);
            
            if (!$subscriber_id) {
                error_log('[WDI_Admin] No se pudo obtener subscriber_id para tracking - licencia no válida');
                // No inicializar tracker si no hay subscriber_id válido
                $this->usage_tracker = null;
                return;
            }
            
            // Inicializar tracker
            $this->usage_tracker = new WDI_Usage_Tracker($subscriber_id, $license_key);
            error_log('[WDI_Admin] Usage tracker inicializado correctamente');
            
        } catch (Exception $e) {
            error_log('[WDI_Admin] Error al inicializar usage tracking: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener subscriber_id directamente desde la base de datos (sin validar licencia)
     */
    private function get_subscriber_id_direct($license_key) {
        try {
            // Obtener configuración de la API
            $discogs_options = get_option($this->plugin_name . '_options');
            $api_url = $discogs_options['api_url'] ?? '';
            
            if (empty($api_url)) {
                error_log('[WDI_Admin] API URL no configurada para get_subscriber_id_direct');
                return null;
            }
            
            // Hacer llamada directa a la API para obtener subscriber_id sin validar licencia
            error_log('[WDI_Admin] Llamando a get_subscriber_id endpoint: ' . $api_url . 'get_subscriber_id');
            $response = wp_remote_post($api_url . 'get_subscriber_id', [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'license_key' => $license_key
                ]),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                error_log('[WDI_Admin] Error de conexión en get_subscriber_id_direct: ' . $response->get_error_message());
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                error_log('[WDI_Admin] Error obteniendo subscriber_id: ' . ($data['message'] ?? 'Error desconocido'));
                return null;
            }
            
            // Retornar subscriber_id desde la respuesta
            return $data['subscriber_id'] ?? null;
            
        } catch (Exception $e) {
            error_log('[WDI_Admin] Error al obtener subscriber_id directo: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener subscriber_id desde la licencia
     */
    private function get_subscriber_id_from_license($license_key) {
        try {
            // Obtener configuración de la API
            $options = get_option( $this->plugin_name . '_options' );
            $api_url = isset( $options['api_url'] ) ? esc_attr( $options['api_url'] ) : 'http://localhost/api_discogs/api/index.php';
            $domain = get_site_url();
            
            // Validar licencia a través de la API centralizada
            $response = wp_remote_post($api_url . 'validate_license', [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'license_key' => $license_key,
                    'domain' => $domain
                ]),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                error_log('[WDI_Admin] Error de conexión: ' . $response->get_error_message());
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                error_log('[WDI_Admin] Licencia no válida: ' . ($data['message'] ?? 'Error desconocido'));
                return null;
            }
            
            // Obtener subscriber_id desde la respuesta
            return $data['subscriber_id'] ?? null;
            
        } catch (Exception $e) {
            error_log('[WDI_Admin] Error al obtener subscriber_id: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Contabilizar sincronización
     */
    private function track_sync_operation($product_id, $sync_type = 'manual', $fields_updated = null) {
        if ($this->usage_tracker) {
            return $this->usage_tracker->increment_sync_count($product_id, $sync_type, $fields_updated);
        }
        return true; // Si no hay usage_tracker, asumir éxito
    }
    
    /**
     * Obtener tipo de plan del suscriptor
     */
    private function get_subscriber_plan_type() {
        try {
            // Obtener configuración de la API
            $options = get_option( $this->plugin_name . '_options' );
            $api_url = isset( $options['api_url'] ) ? esc_attr( $options['api_url'] ) : 'http://localhost/api_discogs/api/index.php';
            $license_key = $this->get_license_key_from_options();
            $domain = get_site_url();
            
            if (empty($license_key)) {
                return 'free';
            }
            
            // Validar licencia a través de la API centralizada
            $response = wp_remote_post($api_url . 'validate_license', [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'license_key' => $license_key,
                    'domain' => $domain
                ]),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                error_log('[WDI_Admin] Error de conexión: ' . $response->get_error_message());
                return 'free';
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                error_log('[WDI_Admin] Error validando licencia: ' . ($data['message'] ?? 'Error desconocido'));
                return 'free';
            }
            
            // Obtener plan_type desde la respuesta
            return $data['plan_type'] ?? 'free';
            
        } catch (Exception $e) {
            error_log('[WDI_Admin] Error al obtener tipo de plan: ' . $e->getMessage());
            return 'free';
        }
    }
    
    /**
     * Obtener límite de sincronizaciones para el plan
     */
    private function get_usage_limit_from_license() {
        try {
            error_log('[WDI_Admin] DEBUG: Obteniendo usage_limit desde licencia');
            
            // Obtener configuración de la API
            $discogs_options = get_option($this->plugin_name . '_options');
            $api_url = $discogs_options['api_url'] ?? '';
            $license_key = $discogs_options['license_key'] ?? '';
            
            error_log('[WDI_Admin] DEBUG: api_url: ' . $api_url);
            error_log('[WDI_Admin] DEBUG: license_key: ' . $license_key);
            
            if (empty($api_url) || empty($license_key)) {
                error_log('[WDI_Admin] DEBUG: API URL o license_key vacíos - retornando 10');
                return 10; // Valor por defecto
            }
            
            // Llamar al endpoint validate_license para obtener usage_limit
            $response = wp_remote_post($api_url . 'validate_license', [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'license_key' => $license_key,
                    'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost'
                ]),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                error_log('[WDI_Admin] DEBUG: Error en wp_remote_post: ' . $response->get_error_message());
                return 10; // Valor por defecto en caso de error
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            error_log('[WDI_Admin] DEBUG: Respuesta de validate_license: ' . json_encode($data));
            
            if ($data && $data['success'] && isset($data['usage_limit'])) {
                error_log('[WDI_Admin] DEBUG: usage_limit encontrado: ' . $data['usage_limit']);
                return intval($data['usage_limit']);
            }
            
            error_log('[WDI_Admin] DEBUG: usage_limit no encontrado en respuesta - retornando 10');
            return 10; // Valor por defecto
            
        } catch (Exception $e) {
            error_log('[WDI_Admin] Error obteniendo usage_limit: ' . $e->getMessage());
            return 10; // Valor por defecto
        }
    }
    
    /**
     * Obtener license key de las opciones
     */
    private function get_license_key_from_options() {
        $options = get_option($this->plugin_name . '_options');
        return $options['license_key'] ?? '';
    }
    
    /**
     * Callback para prueba de conexión de licencia
     */
    public function test_license_connection_callback() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'test_license_nonce')) {
            wp_send_json_error('Nonce inválido');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error('Clave de licencia vacía');
        }
        
        try {
            // Obtener configuración de la API
            $discogs_options = get_option( $this->plugin_name . '_options' );
            $api_url = $discogs_options['api_url'] ?? (function_exists('wdi_get_api_url') ? wdi_get_api_url() : '');
            
            if (empty($api_url)) {
                wp_send_json_error('URL de API no configurada');
            }
            
            // Obtener el dominio actual
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            // Hacer llamada de prueba a nuestra API intermediaria
            $test_url = $api_url . 'validate_license';
            
            $response = wp_remote_post($test_url, array(
                'body' => array(
                    'license_key' => $license_key,
                    'domain' => $domain
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('Error de conexión: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && isset($data['success']) && $data['success']) {
                wp_send_json_success(array(
                    'status' => 'active',
                    'license' => $data['license'],
                    'message' => 'Licencia válida y activa'
                ));
            } else {
                // Manejar diferentes tipos de errores
                $error_code = $data['error'] ?? 'UNKNOWN_ERROR';
                $message = $data['message'] ?? 'Error desconocido';
                $registered_domain = $data['registered_domain'] ?? null;
                
                if ($error_code === 'DOMAIN_MISMATCH') {
                    wp_send_json_error(array(
                        'error' => 'DOMAIN_MISMATCH',
                        'message' => 'Conexión exitosa, pero el dominio no corresponde. Dominio registrado: ' . $registered_domain . '. Contacta soporte para actualizar el dominio.',
                        'registered_domain' => $registered_domain
                    ));
                } elseif ($error_code === 'LICENSE_NOT_FOUND') {
                    wp_send_json_error(array(
                        'error' => 'LICENSE_NOT_FOUND',
                        'message' => 'Licencia inválida o expirada'
                    ));
                } else {
                    wp_send_json_error($message);
                }
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Error de conexión: ' . $e->getMessage());
        }
    }
    
    /**
     * Callback para prueba de conexión de API de Discogs
     */
    public function test_discogs_api_connection_callback() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'test_discogs_api_nonce')) {
            wp_send_json_error('Nonce inválido');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        $api_secret = sanitize_text_field($_POST['api_secret']);
        
        if (empty($api_key) || empty($api_secret)) {
            wp_send_json_error('API Key y Secret requeridos');
        }
        
        try {
            // Obtener configuración de la API
            $discogs_options = get_option( $this->plugin_name . '_options' );
            $api_url = $discogs_options['api_url'] ?? (function_exists('wdi_get_api_url') ? wdi_get_api_url() : '');
            
            if (empty($api_url)) {
                wp_send_json_error('URL de API no configurada');
            }
            
            // Hacer llamada de prueba a nuestra API intermediaria
            $test_url = $api_url . 'test-discogs-oauth-connection';
            
            $response = wp_remote_post($test_url, array(
                'body' => array(
                    'api_key' => $api_key,
                    'api_secret' => $api_secret
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('Error de conexión: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && isset($data['success']) && $data['success']) {
                wp_send_json_success('Conexión OAuth con Discogs API exitosa');
            } else {
                $error_message = isset($data['message']) ? $data['message'] : 'Error desconocido';
                wp_send_json_error('Error en la API: ' . $error_message);
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Callback para prueba de conexión de la API
     */
    public function test_api_connection_callback() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wdi_api_nonce')) {
            wp_send_json_error('Nonce inválido');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        try {
            // Obtener configuración de la API
            $discogs_options = get_option( $this->plugin_name . '_options' );
            $api_url = $discogs_options['api_url'] ?? '';
            $license_key = $discogs_options['license_key'] ?? '';
            $discogs_api_key = $discogs_options['api_key'] ?? '';
            $discogs_api_secret = $discogs_options['api_secret'] ?? '';
            
            if (empty($api_url)) {
                wp_send_json_error('URL de API no configurada');
            }
            
            if (empty($license_key)) {
                wp_send_json_error('Clave de licencia no configurada');
            }
            
            if (empty($discogs_api_key) || empty($discogs_api_secret)) {
                wp_send_json_error('Credenciales de Discogs API no configuradas');
            }
            
            // Obtener el dominio actual
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            // Hacer llamada de prueba a nuestra API intermediaria
            $test_url = $api_url . 'health';
            
            $response = wp_remote_get($test_url, array(
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('Error de conexión: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && isset($data['success']) && $data['success']) {
                wp_send_json_success(array(
                    'message' => __('Conexión exitosa con la API', 'dwoosync'),
                    'data' => $data
                ));
            } else {
                $error_message = isset($data['message']) ? $data['message'] : __('Error desconocido', 'dwoosync');
                wp_send_json_error(array(
                    'message' => __('Error de conexión: ', 'dwoosync') . $error_message
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Validar licencia con dominio usando la API intermediaria
     */
    private function validate_license_with_domain($license_key, $domain, $api_url) {
        try {
            $test_url = $api_url . 'validate_license';
            
            $response = wp_remote_post($test_url, array(
                'body' => array(
                    'license_key' => $license_key,
                    'domain' => $domain
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return [
                    'valid' => false,
                    'error' => 'CONNECTION_ERROR',
                    'message' => 'Error de conexión: ' . $response->get_error_message()
                ];
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && isset($data['success']) && $data['success']) {
                $result = [
                    'valid' => true,
                    'license' => $data['license']
                ];
                
                // Incluir warning si está en período de gracia
                if (isset($data['warning'])) {
                    $result['warning'] = $data['warning'];
                }
                
                return $result;
            } else {
                return [
                    'valid' => false,
                    'error' => $data['error'] ?? 'UNKNOWN_ERROR',
                    'message' => $data['message'] ?? 'Error desconocido',
                    'registered_domain' => $data['registered_domain'] ?? null
                ];
            }
            
        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => 'INTERNAL_ERROR',
                'message' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Realizar búsqueda a través de nuestra API intermediaria
     */
    private function search_via_api($search_term, $filters, $license_key, $discogs_api_key, $discogs_api_secret, $api_url) {
        try {
            $search_url = $api_url . 'search';
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            $response = wp_remote_get($search_url, array(
                'body' => array_merge([
                    'q' => $search_term,
                    'license_key' => $license_key,
                    'domain' => $domain,
                    'discogs_api_key' => $discogs_api_key,
                    'discogs_api_secret' => $discogs_api_secret
                ], $filters),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return [
                    'error' => 'Error de conexión: ' . $response->get_error_message()
                ];
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                $error_message = $data['message'] ?? 'Error desconocido en la búsqueda';
                
                // Manejar errores específicos de licencia
                if (isset($data['error'])) {
                    if ($data['error'] === 'DOMAIN_MISMATCH') {
                        $error_message = 'El dominio actual no corresponde a la suscripción contratada. Por favor, contacta soporte para actualizar el dominio registrado.';
                    } elseif ($data['error'] === 'LICENSE_NOT_FOUND') {
                        $error_message = __('Invalid or expired license. You must purchase a valid license at: www.discogsync.com', 'dwoosync');
                    }
                }
                
                return [
                    'error' => $error_message
                ];
            }
            
            return $data;
            
        } catch (Exception $e) {
            return [
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener detalles de release a través de nuestra API intermediaria
     */
    private function get_release_via_api($release_id, $license_key, $discogs_api_key, $discogs_api_secret, $api_url) {
        try {
            $release_url = $api_url . 'release';
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            
            error_log('[Discogs Import Debug] URL de release: ' . $release_url);
            error_log('[Discogs Import Debug] Parámetros: release_id=' . $release_id . ', domain=' . $domain);
            
            $query_params = [
                'release_id' => $release_id,
                'license_key' => $license_key,
                'domain' => $domain,
                'discogs_api_key' => $discogs_api_key,
                'discogs_api_secret' => $discogs_api_secret,
                'from_import' => 'true'  // Indicar que viene del proceso de importación
            ];
            
            $full_url = $release_url . '&' . http_build_query($query_params);
            error_log('[Discogs Import Debug] URL completa: ' . $full_url);
            
            $response = wp_remote_get($full_url, array(
                'timeout' => 30
            ));
            
            error_log('[Discogs Import Debug] Respuesta HTTP recibida');
            
            if (is_wp_error($response)) {
                error_log('[Discogs Import Debug] Error de WP: ' . $response->get_error_message());
                return [
                    'error' => 'Error de conexión: ' . $response->get_error_message()
                ];
            }
            
            $body = wp_remote_retrieve_body($response);
            $http_code = wp_remote_retrieve_response_code($response);
            error_log('[Discogs Import Debug] Código HTTP: ' . $http_code);
            error_log('[Discogs Import Debug] Body de respuesta: ' . substr($body, 0, 500) . '...');
            
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                $error_message = $data['message'] ?? 'Error desconocido al obtener detalles del release';
                
                // Manejar errores específicos de licencia
                if (isset($data['error'])) {
                    if ($data['error'] === 'DOMAIN_MISMATCH') {
                        $error_message = 'El dominio actual no corresponde a la suscripción contratada. Por favor, contacta soporte para actualizar el dominio registrado.';
                    } elseif ($data['error'] === 'LICENSE_NOT_FOUND') {
                        $error_message = __('Invalid or expired license. You must purchase a valid license at: www.discogsync.com', 'dwoosync');
                    }
                }
                
                return [
                    'error' => $error_message
                ];
            }
            
            return $data['data'] ?? $data;
            
        } catch (Exception $e) {
            return [
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Renderiza el campo para el idioma del plugin
     */
    public function render_plugin_language_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $language = isset( $options['plugin_language'] ) ? esc_attr( $options['plugin_language'] ) : 'es_ES';
        echo "<select name='{$this->plugin_name}_options[plugin_language]' id='plugin_language_select'>";
        echo "<option value='es_ES'" . selected( $language, 'es_ES', false ) . ">" . __( 'Español', 'dwoosync' ) . "</option>";
        echo "<option value='en_US'" . selected( $language, 'en_US', false ) . ">" . __( 'English', 'dwoosync' ) . "</option>";
        echo "</select>";
        echo '<p class="description">' . __( 'Selecciona el idioma para la interfaz del plugin.', 'dwoosync' ) . '</p>';
        
        // JavaScript para manejar el cambio de idioma
        echo '<script>
        jQuery(document).ready(function($) {
            $("#plugin_language_select").on("change", function() {
                var selectedLanguage = $(this).val();
                var currentLanguage = "' . $language . '";
                
                if (selectedLanguage !== currentLanguage) {
                    if (confirm("' . __( '¿Recargar la página para aplicar el cambio de idioma?', 'dwoosync' ) . '")) {
                        // Guardar el idioma seleccionado antes de recargar
                        var formData = {
                            action: "wdi_save_language_change",
                            nonce: "' . wp_create_nonce('wdi_language_change') . '",
                            language: selectedLanguage
                        };
                        
                        $.post(ajaxurl, formData, function(response) {
                            if (response.success) {
                                window.location.reload();
                            } else {
                                alert("' . __( 'Error al cambiar el idioma. Inténtalo de nuevo.', 'dwoosync' ) . '");
                                // Restaurar el valor anterior
                                $("#plugin_language_select").val(currentLanguage);
                            }
                        }).fail(function() {
                            alert("' . __( 'Error de conexión. Inténtalo de nuevo.', 'dwoosync' ) . '");
                            $("#plugin_language_select").val(currentLanguage);
                        });
                    } else {
                        // Restaurar el valor anterior si el usuario cancela
                        $("#plugin_language_select").val(currentLanguage);
                    }
                }
            });
        });
        </script>';
    }
    
    /**
     * Renderiza el campo para categorías de vinilo
     */
    public function render_vinyl_categories_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $vinyl_categories = isset( $options['vinyl_categories'] ) ? $options['vinyl_categories'] : array();
        
        // Obtener todas las categorías de WooCommerce
        $categories = get_terms( array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ) );
        
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        
        // Primer dropdown
        echo '<select name="' . $this->plugin_name . '_options[vinyl_categories][0]" style="min-width: 200px;">';
        echo '<option value="">' . __( 'Seleccionar categoría 1', 'dwoosync' ) . '</option>';
        foreach ( $categories as $category ) {
            $selected = ( isset( $vinyl_categories[0] ) && $vinyl_categories[0] == $category->term_id ) ? 'selected' : '';
            echo '<option value="' . $category->term_id . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
        }
        echo '</select>';
        
        // Segundo dropdown
        echo '<select name="' . $this->plugin_name . '_options[vinyl_categories][1]" style="min-width: 200px;">';
        echo '<option value="">' . __( 'Seleccionar categoría 2', 'dwoosync' ) . '</option>';
        foreach ( $categories as $category ) {
            $selected = ( isset( $vinyl_categories[1] ) && $vinyl_categories[1] == $category->term_id ) ? 'selected' : '';
            echo '<option value="' . $category->term_id . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
        }
        echo '</select>';
        
        echo '</div>';
    }
    
    /**
     * Renderiza el campo para categorías de CD
     */
    public function render_cd_categories_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $cd_categories = isset( $options['cd_categories'] ) ? $options['cd_categories'] : array();
        
        // Obtener todas las categorías de WooCommerce
        $categories = get_terms( array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ) );
        
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        
        // Primer dropdown
        echo '<select name="' . $this->plugin_name . '_options[cd_categories][0]" style="min-width: 200px;">';
        echo '<option value="">' . __( 'Seleccionar categoría 1', 'dwoosync' ) . '</option>';
        foreach ( $categories as $category ) {
            $selected = ( isset( $cd_categories[0] ) && $cd_categories[0] == $category->term_id ) ? 'selected' : '';
            echo '<option value="' . $category->term_id . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
        }
        echo '</select>';
        
        // Segundo dropdown
        echo '<select name="' . $this->plugin_name . '_options[cd_categories][1]" style="min-width: 200px;">';
        echo '<option value="">' . __( 'Seleccionar categoría 2', 'dwoosync' ) . '</option>';
        foreach ( $categories as $category ) {
            $selected = ( isset( $cd_categories[1] ) && $cd_categories[1] == $category->term_id ) ? 'selected' : '';
            echo '<option value="' . $category->term_id . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
        }
        echo '</select>';
        
        echo '</div>';
    }
    
    /**
     * Renderiza el campo para categorías de cassette
     */
    public function render_cassette_categories_field() {
        $options = get_option( $this->plugin_name . '_options' );
        $cassette_categories = isset( $options['cassette_categories'] ) ? $options['cassette_categories'] : array();
        
        // Obtener todas las categorías de WooCommerce
        $categories = get_terms( array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ) );
        
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        
        // Primer dropdown
        echo '<select name="' . $this->plugin_name . '_options[cassette_categories][0]" style="min-width: 200px;">';
        echo '<option value="">' . __( 'Seleccionar categoría 1', 'dwoosync' ) . '</option>';
        foreach ( $categories as $category ) {
            $selected = ( isset( $cassette_categories[0] ) && $cassette_categories[0] == $category->term_id ) ? 'selected' : '';
            echo '<option value="' . $category->term_id . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
        }
        echo '</select>';
        
        // Segundo dropdown
        echo '<select name="' . $this->plugin_name . '_options[cassette_categories][1]" style="min-width: 200px;">';
        echo '<option value="">' . __( 'Seleccionar categoría 2', 'dwoosync' ) . '</option>';
        foreach ( $categories as $category ) {
            $selected = ( isset( $cassette_categories[1] ) && $cassette_categories[1] == $category->term_id ) ? 'selected' : '';
            echo '<option value="' . $category->term_id . '" ' . $selected . '>' . esc_html( $category->name ) . '</option>';
        }
        echo '</select>';
        
        echo '</div>';
    }
    
    /**
     * Asigna categorías automáticamente según el formato del producto
     */
    private function assign_automatic_categories( $post_id, $discogs_data ) {
        error_log('[Discogs Categories Debug] ===== INICIO assign_automatic_categories =====');
        error_log('[Discogs Categories Debug] post_id: ' . $post_id);
        error_log('[Discogs Categories Debug] discogs_data: ' . print_r($discogs_data, true));
        
        // Obtener configuración de categorías
        $options = get_option( $this->plugin_name . '_options' );
        error_log('[Discogs Categories Debug] Opciones del plugin: ' . print_r($options, true));
        
        // Determinar el formato del producto
        $format = $this->detect_product_format( $discogs_data );
        
        if ( empty( $format ) ) {
            error_log('[Discogs Categories Debug] No se pudo detectar el formato del producto');
            return;
        }
        
        error_log('[Discogs Categories Debug] Formato detectado: ' . $format);
        
        // Obtener las categorías configuradas para este formato
        $categories_to_assign = array();
        
        switch ( strtolower( $format ) ) {
            case 'vinyl':
                error_log('[Discogs Categories Debug] Procesando formato VINYL');
                if ( isset( $options['vinyl_categories'] ) && is_array( $options['vinyl_categories'] ) ) {
                    $categories_to_assign = array_filter( $options['vinyl_categories'] );
                    error_log('[Discogs Categories Debug] Categorías de vinilo encontradas: ' . print_r($categories_to_assign, true));
                } else {
                    error_log('[Discogs Categories Debug] No se encontraron categorías de vinilo en las opciones');
                }
                break;
            case 'cd':
                error_log('[Discogs Categories Debug] Procesando formato CD');
                if ( isset( $options['cd_categories'] ) && is_array( $options['cd_categories'] ) ) {
                    $categories_to_assign = array_filter( $options['cd_categories'] );
                    error_log('[Discogs Categories Debug] Categorías de CD encontradas: ' . print_r($categories_to_assign, true));
                } else {
                    error_log('[Discogs Categories Debug] No se encontraron categorías de CD en las opciones');
                }
                break;
            case 'cassette':
                error_log('[Discogs Categories Debug] Procesando formato CASSETTE');
                if ( isset( $options['cassette_categories'] ) && is_array( $options['cassette_categories'] ) ) {
                    $categories_to_assign = array_filter( $options['cassette_categories'] );
                    error_log('[Discogs Categories Debug] Categorías de cassette encontradas: ' . print_r($categories_to_assign, true));
                } else {
                    error_log('[Discogs Categories Debug] No se encontraron categorías de cassette en las opciones');
                }
                break;
            default:
                error_log('[Discogs Categories Debug] Formato no reconocido: ' . $format);
                break;
        }
        
        if ( empty( $categories_to_assign ) ) {
            error_log('[Discogs Categories Debug] No hay categorías configuradas para el formato: ' . $format);
            return;
        }
        
        error_log('[Discogs Categories Debug] Categorías a asignar: ' . print_r($categories_to_assign, true));
        
        // Asignar las categorías al producto
        $result = wp_set_post_terms( $post_id, $categories_to_assign, 'product_cat', false );
        
        if ( is_wp_error( $result ) ) {
            error_log('[Discogs Categories Debug] ERROR al asignar categorías: ' . $result->get_error_message());
        } else {
            error_log('[Discogs Categories Debug] Categorías asignadas exitosamente: ' . print_r($result, true));
        }
        
        error_log('[Discogs Categories Debug] ===== FIN assign_automatic_categories =====');
    }
    
    /**
     * Detecta el formato del producto desde los datos de Discogs
     */
    private function detect_product_format( $discogs_data ) {
        error_log('[Discogs Format Debug] ===== INICIO detect_product_format =====');
        error_log('[Discogs Format Debug] discogs_data: ' . print_r($discogs_data, true));
        
        if ( ! isset( $discogs_data['formats'] ) || ! is_array( $discogs_data['formats'] ) ) {
            error_log('[Discogs Format Debug] No se encontraron formatos en los datos de Discogs');
            return null;
        }
        
        error_log('[Discogs Format Debug] Formatos encontrados: ' . print_r($discogs_data['formats'], true));
        
        // Tomar el primer formato disponible
        $format_data = $discogs_data['formats'][0] ?? null;
        
        if ( ! $format_data || ! isset( $format_data['name'] ) ) {
            error_log('[Discogs Format Debug] No se pudo obtener el nombre del formato');
            return null;
        }
        
        $format_name = strtolower( $format_data['name'] );
        error_log('[Discogs Format Debug] Nombre del formato (lowercase): ' . $format_name);
        
        // Mapear nombres de formatos de Discogs a nuestros formatos
        if ( strpos( $format_name, 'vinyl' ) !== false || strpos( $format_name, 'lp' ) !== false ) {
            error_log('[Discogs Format Debug] Formato detectado como VINYL');
            return 'vinyl';
        } elseif ( strpos( $format_name, 'cd' ) !== false ) {
            error_log('[Discogs Format Debug] Formato detectado como CD');
            return 'cd';
        } elseif ( strpos( $format_name, 'cassette' ) !== false || strpos( $format_name, 'tape' ) !== false ) {
            error_log('[Discogs Format Debug] Formato detectado como CASSETTE');
            return 'cassette';
        }
        
        // Fallback: devolver el formato original si no coincide con nuestros tipos
        error_log('[Discogs Format Debug] Formato no reconocido, devolviendo: ' . $format_name);
        return $format_name;
    }
    
    /**
     * Obtiene las categorías sugeridas basadas en el formato seleccionado
     */
    private function get_suggested_categories_for_format($format) {
        error_log('[Discogs Categories Debug] ===== INICIO get_suggested_categories_for_format =====');
        error_log('[Discogs Categories Debug] Formato recibido: ' . $format);
        
        // Obtener configuración de categorías
        $options = get_option($this->plugin_name . '_options');
        error_log('[Discogs Categories Debug] Opciones del plugin: ' . print_r($options, true));
        
        $suggested_categories = array();
        
        // Mapear el formato seleccionado a nuestras categorías configuradas
        switch (strtolower($format)) {
            case 'vinyl':
                if (isset($options['vinyl_categories']) && is_array($options['vinyl_categories'])) {
                    $category_ids = array_filter($options['vinyl_categories']);
                    error_log('[Discogs Categories Debug] IDs de categorías de vinilo: ' . print_r($category_ids, true));
                    
                    foreach ($category_ids as $cat_id) {
                        if (!empty($cat_id)) {
                            $term = get_term($cat_id, 'product_cat');
                            if ($term && !is_wp_error($term)) {
                                $suggested_categories[] = array(
                                    'id' => $term->term_id,
                                    'name' => $term->name,
                                    'slug' => $term->slug
                                );
                                error_log('[Discogs Categories Debug] Categoría de vinilo encontrada: ' . $term->name . ' (ID: ' . $term->term_id . ')');
                            }
                        }
                    }
                }
                break;
                
            case 'cd':
                if (isset($options['cd_categories']) && is_array($options['cd_categories'])) {
                    $category_ids = array_filter($options['cd_categories']);
                    error_log('[Discogs Categories Debug] IDs de categorías de CD: ' . print_r($category_ids, true));
                    
                    foreach ($category_ids as $cat_id) {
                        if (!empty($cat_id)) {
                            $term = get_term($cat_id, 'product_cat');
                            if ($term && !is_wp_error($term)) {
                                $suggested_categories[] = array(
                                    'id' => $term->term_id,
                                    'name' => $term->name,
                                    'slug' => $term->slug
                                );
                                error_log('[Discogs Categories Debug] Categoría de CD encontrada: ' . $term->name . ' (ID: ' . $term->term_id . ')');
                            }
                        }
                    }
                }
                break;
                
            case 'cassette':
                if (isset($options['cassette_categories']) && is_array($options['cassette_categories'])) {
                    $category_ids = array_filter($options['cassette_categories']);
                    error_log('[Discogs Categories Debug] IDs de categorías de cassette: ' . print_r($category_ids, true));
                    
                    foreach ($category_ids as $cat_id) {
                        if (!empty($cat_id)) {
                            $term = get_term($cat_id, 'product_cat');
                            if ($term && !is_wp_error($term)) {
                                $suggested_categories[] = array(
                                    'id' => $term->term_id,
                                    'name' => $term->name,
                                    'slug' => $term->slug
                                );
                                error_log('[Discogs Categories Debug] Categoría de cassette encontrada: ' . $term->name . ' (ID: ' . $term->term_id . ')');
                            }
                        }
                    }
                }
                break;
        }
        
        error_log('[Discogs Categories Debug] Categorías sugeridas finales: ' . print_r($suggested_categories, true));
        error_log('[Discogs Categories Debug] ===== FIN get_suggested_categories_for_format =====');
        
        return $suggested_categories;
    }
    
    /**
     * Asigna las categorías seleccionadas por el usuario
     */
    private function assign_user_selected_categories( $post_id, $selected_categories ) {
        error_log('[Discogs Categories Debug] ===== INICIO assign_user_selected_categories =====');
        error_log('[Discogs Categories Debug] post_id: ' . $post_id);
        error_log('[Discogs Categories Debug] selected_categories: ' . print_r($selected_categories, true));
        
        if ( empty( $selected_categories ) ) {
            error_log('[Discogs Categories Debug] No hay categorías seleccionadas');
            return;
        }
        
        // Verificar que las categorías existen
        $valid_categories = array();
        foreach ( $selected_categories as $cat_id ) {
            $term = get_term( $cat_id, 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) {
                $valid_categories[] = $cat_id;
                error_log('[Discogs Categories Debug] Categoría válida: ' . $term->name . ' (ID: ' . $cat_id . ')');
            } else {
                error_log('[Discogs Categories Debug] Categoría inválida o no encontrada: ' . $cat_id);
            }
        }
        
        if ( empty( $valid_categories ) ) {
            error_log('[Discogs Categories Debug] No hay categorías válidas para asignar');
            return;
        }
        
        // Asignar las categorías al producto
        $result = wp_set_post_terms( $post_id, $valid_categories, 'product_cat', false );
        
        if ( is_wp_error( $result ) ) {
            error_log('[Discogs Categories Debug] ERROR al asignar categorías seleccionadas: ' . $result->get_error_message());
        } else {
            error_log('[Discogs Categories Debug] Categorías seleccionadas asignadas exitosamente: ' . print_r($result, true));
        }
        
        error_log('[Discogs Categories Debug] ===== FIN assign_user_selected_categories =====');
    }
    
    /**
     * AJAX callback para guardar el cambio de idioma
     */
    public function ajax_save_language_change() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wdi_language_change')) {
            wp_send_json_error('Nonce inválido');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Sin permisos');
        }
        
        $new_language = sanitize_text_field($_POST['language']);
        $allowed_languages = array('es_ES', 'en_US');
        
        if (!in_array($new_language, $allowed_languages)) {
            wp_send_json_error('Idioma no válido');
        }
        
        // Obtener opciones actuales
        $options = get_option($this->plugin_name . '_options', array());
        
        // Actualizar el idioma
        $options['plugin_language'] = $new_language;
        
        // Guardar las opciones
        $result = update_option($this->plugin_name . '_options', $options);
        
        if ($result) {
            // Recargar el idioma inmediatamente
            $domain = 'dwoosync';
            $plugin_dir = plugin_dir_path(dirname(__FILE__));
            $languages_dir = $plugin_dir . 'languages/';
            $mo_file = $languages_dir . $domain . '-' . $new_language . '.mo';
            
            unload_textdomain($domain);
            if (file_exists($mo_file)) {
                load_textdomain($domain, $mo_file);
            }
            
            wp_send_json_success(array(
                'message' => __('Idioma cambiado correctamente', 'dwoosync'),
                'language' => $new_language
            ));
        } else {
            wp_send_json_error('Error al guardar el idioma');
        }
    }
    
    
}
