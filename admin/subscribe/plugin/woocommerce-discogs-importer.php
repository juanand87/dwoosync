<?php
/**
 * Plugin Name:       DWooSync
 * Plugin URI:        https://example.com/
 * Description:       Importa datos de productos desde la API de Discogs directamente a WooCommerce.
 * Version:           1.0.0
 * Author:            Tu Nombre Aquí
 * Author URI:        https://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       dwoosync
 * Domain Path:       /languages
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Si este archivo es llamado directamente, abortar.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Declara la compatibilidad con el Almacenamiento de Pedidos de Alto Rendimiento (HPOS).
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

/**
 * Comprobar si WooCommerce está activado.
 */
function wdi_check_woocommerce_active() {
    // Verificar si WooCommerce está activo de forma más robusta
    $active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
    if ( in_array( 'woocommerce/woocommerce.php', $active_plugins ) ||
         class_exists( 'WooCommerce' ) ) {

        // Cargar la clase principal del plugin de forma segura
        add_action( 'plugins_loaded', 'wdi_init_plugin' );
    } else {
        // Mostrar una notificación de error si WooCommerce no está activo.
        add_action( 'admin_notices', 'wdi_woocommerce_not_active_notice' );
    }
}

/**
 * Muestra una notificación en el admin si WooCommerce no está activo.
 */
function wdi_woocommerce_not_active_notice() {
    ?>
    <div class="error">
        <p><?php _e( '<strong>Discogs Importer</strong> requiere que WooCommerce esté instalado y activo. Por favor, active WooCommerce.', 'dwoosync' ); ?></p>
    </div>
    <?php
}

/**
 * Función para inicializar el plugin de forma segura.
 */
function wdi_init_plugin() {
    // Verificar que WooCommerce esté realmente cargado
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    // Cargar los archivos necesarios
    $core_file_path = __DIR__ . '/includes/class-wdi-core.php';
    if ( file_exists( $core_file_path ) ) {
        require_once $core_file_path;

        // Cargar la clase API client
        $api_client_path = __DIR__ . '/wordpress_integration/class-wdi-api-client.php';
        if ( file_exists( $api_client_path ) ) {
            require_once $api_client_path;
        }

        // Verificar que la clase existe antes de instanciarla
        if ( class_exists( 'WDI_Core' ) ) {
            $plugin = new WDI_Core();
        }
        
    }
}

/**
 * Cargar el dominio de texto del plugin
 */
function wdi_load_plugin_textdomain() {
    // Obtener el idioma seleccionado en la configuración
    $options = get_option( 'dwoosync_options' );
    $selected_language = isset( $options['plugin_language'] ) ? $options['plugin_language'] : 'es_ES';
    
    $domain = 'dwoosync';
    
    // Debug: verificar idioma seleccionado
    error_log( '[WDI Language Debug] Intentando cargar idioma: ' . $selected_language );
    
    // Forzar recarga del textdomain
    unload_textdomain( $domain );
    
    $loaded = false;
    
    // Cargar archivo .mo para ambos idiomas
    $plugin_dir = plugin_dir_path( __FILE__ );
    $languages_dir = $plugin_dir . 'languages/';
    $mo_file = $languages_dir . 'discogs-importer-' . $selected_language . '.mo';
    
    error_log( '[WDI Language Debug] Archivo MO: ' . $mo_file );
    error_log( '[WDI Language Debug] Archivo existe: ' . ( file_exists( $mo_file ) ? 'SÍ' : 'NO' ) );
    
    if ( file_exists( $mo_file ) ) {
        $loaded = load_textdomain( $domain, $mo_file );
        error_log( '[WDI Language Debug] Textdomain cargado: ' . ( $loaded ? 'SÍ' : 'NO' ) );
    }
    
    // Si no se pudo cargar el archivo específico, intentar el fallback
    if ( !$loaded ) {
        error_log( '[WDI Language Debug] Intentando fallback...' );
        $loaded = load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        error_log( '[WDI Language Debug] Fallback cargado: ' . ( $loaded ? 'SÍ' : 'NO' ) );
    }
    
    return $loaded;
}

/**
 * Aplicar traducciones en inglés usando el filtro gettext
 */
function wdi_apply_english_translations() {
    // Obtener el idioma seleccionado en la configuración
    $options = get_option( 'dwoosync_options' );
    $selected_language = isset( $options['plugin_language'] ) ? $options['plugin_language'] : 'es_ES';
    
    // Solo aplicar si es inglés
    if ( $selected_language === 'en_US' ) {
        // Traducciones en inglés
        $english_translations = array(
            'Configuración API' => 'API Configuration',
            'Plantillas y Etiquetas' => 'Templates and Tags',
            'Spotify' => 'Spotify',
            'Opciones Avanzadas' => 'Advanced Options',
            'Categorías' => 'Categories',
            'Configuración de la API de Discogs' => 'Discogs API Configuration',
            'Introduce tus credenciales de la API de Discogs. Puedes obtenerlas desde el portal de desarrolladores de Discogs.' => 'Enter your Discogs API credentials. You can get them from the Discogs developer portal.',
            'Discogs API Key' => 'Discogs API Key',
            'Tu clave de API de Discogs.' => 'Your Discogs API key.',
            'Discogs API Secret' => 'Discogs API Secret',
            'Tu secreto de API de Discogs.' => 'Your Discogs API secret.',
            'Configuración del Servidor API' => 'API Server Configuration',
            'Configuración para conectar con el servidor API de Discogs Importer.' => 'Configuration to connect with the Discogs Importer API server.',
            'URL del Servidor API' => 'API Server URL',
            'URL del servidor API de Discogs Importer.' => 'Discogs Importer API server URL.',
            'Información del Servidor API' => 'API Server Information',
            'El servidor API se configura automáticamente en el entorno de producción.' => 'The API server is automatically configured in the production environment.',
            'Estado de Conexión' => 'Connection Status',
            'Conexión exitosa con la API' => 'Successful connection with the API',
            'Entorno de producción' => 'Production environment',
            'La URL del servidor API se configura automáticamente.' => 'The API server URL is configured automatically.',
            'Token de Acceso' => 'Access Token',
            'Token de acceso para autenticación con el servidor API.' => 'Access token for authentication with the API server.',
            'Configuración de Plantillas' => 'Template Configuration',
            'Personaliza las plantillas y etiquetas para los productos importados.' => 'Customize templates and tags for imported products.',
            'Plantilla de Título' => 'Title Template',
            'Plantilla para el título del producto.' => 'Template for the product title.',
            'Plantilla de Descripción' => 'Description Template',
            'Plantilla para la descripción del producto.' => 'Template for the product description.',
            'Etiquetas por Defecto' => 'Default Tags',
            'Etiquetas que se agregarán automáticamente a todos los productos importados.' => 'Tags that will be automatically added to all imported products.',
            'Configuración de Spotify' => 'Spotify Configuration',
            'Configuración para la integración con Spotify.' => 'Configuration for Spotify integration.',
            'Habilitar Integración con Spotify' => 'Enable Spotify Integration',
            'Activar la integración con Spotify para mostrar información adicional.' => 'Enable Spotify integration to show additional information.',
            'Client ID de Spotify' => 'Spotify Client ID',
            'ID del cliente de Spotify para la API.' => 'Spotify client ID for the API.',
            'Client Secret de Spotify' => 'Spotify Client Secret',
            'Secreto del cliente de Spotify para la API.' => 'Spotify client secret for the API.',
            'Opciones Avanzadas' => 'Advanced Options',
            'Configuraciones avanzadas del plugin.' => 'Advanced plugin settings.',
            'Habilitar Debug' => 'Enable Debug',
            'Activar el modo debug para obtener más información en los logs.' => 'Enable debug mode to get more information in logs.',
            'Límite de Importaciones' => 'Import Limit',
            'Número máximo de importaciones por día.' => 'Maximum number of imports per day.',
            'Tiempo de Espera' => 'Timeout',
            'Tiempo de espera para las peticiones a la API (en segundos).' => 'Timeout for API requests (in seconds).',
            'Idioma del Plugin' => 'Plugin Language',
            'Selecciona el idioma para la interfaz del plugin.' => 'Select the language for the plugin interface.',
            'Guardar Cambios' => 'Save Changes',
            'Guardar la configuración del plugin.' => 'Save plugin configuration.',
            'Configuración guardada correctamente.' => 'Configuration saved successfully.',
            'Error al guardar la configuración.' => 'Error saving configuration.',
            'Categorías de Vinilo' => 'Vinyl Categories',
            'Selecciona las categorías que se asignarán automáticamente a los productos de vinilo.' => 'Select the categories that will be automatically assigned to vinyl products.',
            'Categorías de CD' => 'CD Categories',
            'Selecciona las categorías que se asignarán automáticamente a los productos de CD.' => 'Select the categories that will be automatically assigned to CD products.',
            'Categorías de Cassette' => 'Cassette Categories',
            'Selecciona las categorías que se asignarán automáticamente a los productos de cassette.' => 'Select the categories that will be automatically assigned to cassette products.',
            'Todos los años' => 'All years',
            'Probar Conexión' => 'Test Connection',
            'Probar conexión con Discogs.com' => 'Test connection with Discogs.com',
            'Probar Licencia' => 'Test License',
            'Probar Credenciales de API' => 'Test API Credentials'
        );
        
        // Registrar el filtro gettext con prioridad alta
        add_filter( 'gettext', function( $translated_text, $text, $domain ) use ( $english_translations ) {
            if ( $domain === 'dwoosync' && isset( $english_translations[ $text ] ) ) {
                error_log( '[WDI Language Debug] Traduciendo: "' . $text . '" → "' . $english_translations[ $text ] . '"' );
                return $english_translations[ $text ];
            }
            return $translated_text;
        }, 5, 3 );
        
        error_log( '[WDI Language Debug] Traducciones en inglés aplicadas' );
    }
}

// Cargar idioma del plugin en cada carga de página
add_action( 'plugins_loaded', 'wdi_load_plugin_textdomain', 1 );

// Recargar idioma cuando se actualice la configuración
add_action( 'update_option_dwoosync_options', function() {
    wdi_load_plugin_textdomain();
}, 10, 2 );

// Hook para aplicar traducciones en inglés con prioridad muy alta
add_action( 'init', 'wdi_apply_english_translations', 0 );

// Iniciar la comprobación.
wdi_check_woocommerce_active();
