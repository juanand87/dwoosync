<?php
/**
 * La clase principal del plugin.
 *
 * @since      1.0.0
 * @package    Discogs_Importer
 * @subpackage Discogs_Importer/includes
 * @author     Tu Nombre Aquí <email@example.com>
 */
class WDI_Core {

    /**
     * El identificador único de este plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    El nombre o identificador del plugin.
     */
    protected $plugin_name;

    /**
     * La versión actual del plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    La versión actual del plugin.
     */
    protected $version;

    /**
     * Define la funcionalidad principal del plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->version = '1.0.0';
        $this->plugin_name = 'discogs-importer';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Carga las dependencias requeridas por el plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // Cargar archivos de forma segura
        $admin_file = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wdi-admin.php';
        if ( file_exists( $admin_file ) ) {
            require_once $admin_file;
        }
    }

    /**
     * Registra todos los hooks relacionados con la funcionalidad del área de administración.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        // Verificar que la clase WDI_Admin existe antes de usarla
        if ( ! class_exists( 'WDI_Admin' ) ) {
            return;
        }
        
        $plugin_admin = new WDI_Admin( $this->get_plugin_name(), $this->get_version() );
        
        // Hook para añadir el menú de administración
        add_action( 'admin_menu', array( $plugin_admin, 'add_admin_menu' ) );
        // Hook para registrar los ajustes
        add_action( 'admin_init', array( $plugin_admin, 'register_settings' ) );

        // Hook para añadir la meta box a los productos
        add_action( 'add_meta_boxes', array( $plugin_admin, 'add_product_meta_box' ) );
        // Hook para cargar assets (CSS/JS) en el admin
        add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_admin_assets' ) );

        // Hooks para el callback de AJAX
        add_action( 'wp_ajax_wdi_fetch_discogs_data', array( $plugin_admin, 'search_discogs_data_callback' ) );
        add_action( 'wp_ajax_wdi_import_discogs_release', array( $plugin_admin, 'import_discogs_release_callback' ) );
        add_action( 'wp_ajax_wdi_search_product_categories', array( $plugin_admin, 'search_product_categories_callback' ) );
        
        // Hook para guardar los metadatos del producto (como la imagen)
        add_action( 'woocommerce_process_product_meta', array( $plugin_admin, 'save_product_meta_data' ) );
        add_action( 'save_post', array( $plugin_admin, 'save_product_meta_data' ) );
    }

    /**
     * Registra todos los hooks relacionados con la funcionalidad del área pública.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        // Cargar JavaScript del widget de Spotify en el frontend
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
    }
    
    /**
     * Carga scripts y estilos para el frontend
     */
    public function enqueue_public_scripts() {
        // Cargar JS del widget de Spotify
        wp_enqueue_script(
            'wdi_spotify_widget_script',
            plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/wdi-spotify-widget.js',
            array( 'jquery' ),
            $this->version . '_' . time(), // Forzar recarga
            true
        );
        
        // Cargar JS del reproductor completo
        wp_enqueue_script(
            'wdi_spotify_player_script',
            plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/wdi-spotify-player.js',
            array( 'jquery' ),
            $this->version . '_' . time(), // Forzar recarga
            true
        );
        
        // Cargar JS del reproductor real de Spotify
        wp_enqueue_script(
            'wdi_spotify_real_player_script',
            plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/wdi-spotify-real-player.js',
            array( 'jquery' ),
            $this->version . '_' . time(), // Forzar recarga
            true
        );
        
        // Cargar JS del reproductor demo de Spotify (sin login)
        wp_enqueue_script(
            'wdi_spotify_demo_player_script',
            plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/wdi-spotify-demo-player.js',
            array( 'jquery' ),
            $this->version . '_' . time(), // Forzar recarga
            true
        );
    }

    /**
     * Devuelve el nombre del plugin.
     *
     * @since     1.0.0
     * @return    string    El nombre del plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Devuelve la versión del plugin.
     *
     * @since     1.0.0
     * @return    string    La versión del plugin.
     */
    public function get_version() {
        return $this->version;
    }


}
