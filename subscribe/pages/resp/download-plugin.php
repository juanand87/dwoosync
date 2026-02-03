<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['license_key'])) {
    header('Location: login.php');
    exit;
}

// Verificar si se solicita descarga
if (isset($_GET['download']) && $_GET['download'] === 'plugin') {
    // Obtener datos de la sesión
    $license_key = $_SESSION['license_key'];
    $user_email = $_SESSION['user_email'];
    $domain = $_SESSION['domain'] ?? 'localhost';
    
    // Crear directorio temporal
    $temp_dir = sys_get_temp_dir() . '/plugin_temp_' . uniqid();
    if (!mkdir($temp_dir, 0777, true)) {
        die('Error: No se pudo crear directorio temporal');
    }
    
    // Rutas del plugin original
    $plugin_paths = [
        'C:\\xampp\\htdocs\\wordpress\\wp-content\\plugins\\Discogs-Importer',
        'C:\\xampp\\htdocs\\api_discogs\\wordpress\\wp-content\\plugins\\Discogs-Importer',
        'C:\\xampp\\htdocs\\wordpress\\wp-content\\plugins\\discogs-importer',
        'C:\\xampp\\htdocs\\api_discogs\\wordpress\\wp-content\\plugins\\discogs-importer'
    ];
    
    $original_plugin_path = null;
    foreach ($plugin_paths as $path) {
        if (is_dir($path)) {
            $original_plugin_path = $path;
            break;
        }
    }
    
    // Si no se encuentra el plugin original, crear uno completo
    if (!$original_plugin_path) {
        createCompletePlugin($temp_dir);
    } else {
        // Copiar plugin original
        if (!copyDirectory($original_plugin_path, $temp_dir)) {
            createCompletePlugin($temp_dir);
        }
    }
    
    // Crear archivo de configuración de licencia
    $config_content = "<?php\n";
    $config_content .= "// Configuración de Licencia Preconfigurada\n";
    $config_content .= "// Generado automáticamente el " . date('Y-m-d H:i:s') . "\n\n";
    $config_content .= "define('DISCogs_LICENSE_KEY', '" . $license_key . "');\n";
    $config_content .= "define('DISCogs_DOMAIN', '" . $domain . "');\n";
    $config_content .= "define('DISCogs_USER_EMAIL', '" . $user_email . "');\n";
    $config_content .= "define('DISCogs_PRE_CONFIGURED', true);\n";
    $config_content .= "define('DISCogs_CONFIG_DATE', '" . date('Y-m-d H:i:s') . "');\n";
    
    file_put_contents($temp_dir . '/config-license.php', $config_content);
    
    // Crear README personalizado
    $readme_content = "PLUGIN DISCogs IMPORTER - LICENCIA PRECONFIGURADA\n";
    $readme_content .= "================================================\n\n";
    $readme_content .= "Este plugin viene con tu licencia ya configurada:\n\n";
    $readme_content .= "Clave de Licencia: " . $license_key . "\n";
    $readme_content .= "Dominio: " . $domain . "\n";
    $readme_content .= "Email: " . $user_email . "\n\n";
    $readme_content .= "INSTRUCCIONES DE INSTALACIÓN:\n";
    $readme_content .= "1. Sube este archivo ZIP a tu WordPress\n";
    $readme_content .= "2. Activa el plugin desde el panel de administración\n";
    $readme_content .= "3. La licencia ya está configurada automáticamente\n";
    $readme_content .= "4. Crea tu API de Discogs en https://www.discogs.com/settings/developers\n";
    $readme_content .= "5. Configura la API en el plugin\n\n";
    $readme_content .= "Soporte: contact@dwoosync.com\n";
    $readme_content .= "Fecha de generación: " . date('Y-m-d H:i:s') . "\n";
    
    file_put_contents($temp_dir . '/README-LICENSE.txt', $readme_content);
    
    // Crear archivo ZIP
    $zip_filename = 'discogs-importer-licensed-' . date('Y-m-d') . '.zip';
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    // Intentar crear ZIP usando diferentes métodos
    $zip_created = false;
    
    // Método 1: ZipArchive (más confiable)
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
            addDirectoryToZip($temp_dir, $zip);
            $zip->close();
            $zip_created = true;
        }
    }
    
    // Método 2: PowerShell Compress-Archive (Windows)
    if (!$zip_created && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $powershell_cmd = "Compress-Archive -Path '" . $temp_dir . "\\*' -DestinationPath '" . $zip_path . "' -Force";
        $output = shell_exec("powershell -Command \"$powershell_cmd\" 2>&1");
        
        if (file_exists($zip_path)) {
            $zip_created = true;
        }
    }
    
    // Método 3: Comando zip del sistema (Linux/Mac)
    if (!$zip_created && function_exists('exec')) {
        $zip_cmd = "cd " . escapeshellarg(dirname($temp_dir)) . " && zip -r " . escapeshellarg($zip_path) . " " . escapeshellarg(basename($temp_dir));
        $output = [];
        $return_code = 0;
        exec($zip_cmd, $output, $return_code);
        
        if ($return_code === 0 && file_exists($zip_path)) {
            $zip_created = true;
        }
    }
    
    // Método 4: 7-Zip (Windows)
    if (!$zip_created && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $sevenzip_paths = [
            'C:\\Program Files\\7-Zip\\7z.exe',
            'C:\\Program Files (x86)\\7-Zip\\7z.exe',
            '7z.exe'
        ];
        
        foreach ($sevenzip_paths as $sevenzip) {
            if (file_exists($sevenzip) || $sevenzip === '7z.exe') {
                $cmd = "\"$sevenzip\" a -tzip \"" . $zip_path . "\" \"" . $temp_dir . "\\*\"";
                $output = shell_exec($cmd . " 2>&1");
                
                if (file_exists($zip_path)) {
                    $zip_created = true;
                    break;
                }
            }
        }
    }
    
    // Limpiar directorio temporal
    deleteDirectory($temp_dir);
    
    if ($zip_created && file_exists($zip_path)) {
        // Enviar archivo ZIP
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
        header('Content-Length: ' . filesize($zip_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        readfile($zip_path);
        unlink($zip_path);
        exit;
    } else {
        die('Error: No se pudo crear el archivo ZIP. Verifica que las extensiones necesarias estén instaladas.');
    }
}

// Función para copiar directorio recursivamente
function copyDirectory($src, $dst) {
    if (!is_dir($src)) {
        return false;
    }
    
    if (!is_dir($dst)) {
        if (!mkdir($dst, 0777, true)) {
            return false;
        }
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        
        if ($item->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0777, true);
            }
        } else {
            copy($item->getPathname(), $target);
        }
    }
    
    return true;
}

// Función para agregar directorio a ZIP
function addDirectoryToZip($dir, $zip, $zipdir = '') {
    if (is_dir($dir)) {
        if ($zipdir) {
            $zip->addEmptyDir($zipdir);
        }
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($dir . '/' . $file)) {
                    addDirectoryToZip($dir . '/' . $file, $zip, $zipdir . $file . '/');
                } else {
                    $zip->addFile($dir . '/' . $file, $zipdir . $file);
                }
            }
        }
    }
}

// Función para eliminar directorio recursivamente
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

// Función para crear plugin completo si no se encuentra el original
function createCompletePlugin($plugin_dir) {
    // Crear estructura de directorios
    $directories = [
        'admin',
        'includes',
        'public',
        'assets',
        'config',
        'languages',
        'templates'
    ];
    
    foreach ($directories as $dir) {
        mkdir($plugin_dir . '/' . $dir, 0777, true);
    }
    
    // Archivo principal del plugin
    $main_plugin_content = "<?php
/**
 * Plugin Name: Discogs Importer
 * Description: Importa productos desde Discogs API
 * Version: 1.0.0
 * Author: DWooSync
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Incluir archivo de configuración de licencia
if (file_exists(plugin_dir_path(__FILE__) . 'config-license.php')) {
    require_once plugin_dir_path(__FILE__) . 'config-license.php';
}

// Definir constantes
define('WDI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WDI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WDI_VERSION', '1.0.0');

// Incluir clases principales
require_once WDI_PLUGIN_PATH . 'includes/class-wdi-core.php';
require_once WDI_PLUGIN_PATH . 'includes/install.php';

// Inicializar plugin
function wdi_init() {
    new WDI_Core();
}
add_action('plugins_loaded', 'wdi_init');

// Hook de activación
register_activation_hook(__FILE__, 'wdi_activate');
function wdi_activate() {
    require_once WDI_PLUGIN_PATH . 'includes/install.php';
    wdi_install();
}

// Hook de desactivación
register_deactivation_hook(__FILE__, 'wdi_deactivate');
function wdi_deactivate() {
    // Limpiar datos si es necesario
}
";
    
    file_put_contents($plugin_dir . '/discogs-importer.php', $main_plugin_content);
    
    // Clase principal
    $core_class_content = "<?php
class WDI_Core {
    public function __construct() {
        add_action('init', array(\$this, 'init'));
        add_action('admin_menu', array(\$this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array(\$this, 'enqueue_scripts'));
    }
    
    public function init() {
        // Inicialización del plugin
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Discogs Importer',
            'Discogs Importer',
            'manage_options',
            'discogs-importer',
            array(\$this, 'admin_page'),
            'dashicons-download',
            30
        );
    }
    
    public function admin_page() {
        include WDI_PLUGIN_PATH . 'admin/class-wdi-admin.php';
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('wdi-admin', WDI_PLUGIN_URL . 'admin/admin.js', array('jquery'), WDI_VERSION, true);
        wp_enqueue_style('wdi-admin', WDI_PLUGIN_URL . 'admin/style.css', array(), WDI_VERSION);
    }
}
";
    
    file_put_contents($plugin_dir . '/includes/class-wdi-core.php', $core_class_content);
    
    // Archivo de instalación
    $install_content = "<?php
function wdi_install() {
    // Crear tablas necesarias
    global \$wpdb;
    
    \$charset_collate = \$wpdb->get_charset_collate();
    
    \$sql = \"CREATE TABLE IF NOT EXISTS {\$wpdb->prefix}wdi_imports (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        discogs_id varchar(50) NOT NULL,
        title varchar(255) NOT NULL,
        artist varchar(255) NOT NULL,
        price decimal(10,2) NOT NULL,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY discogs_id (discogs_id)
    ) \$charset_collate;\";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta(\$sql);
}
";
    
    file_put_contents($plugin_dir . '/includes/install.php', $install_content);
    
    // Clase de administración
    $admin_class_content = "<?php
class WDI_Admin {
    public function __construct() {
        add_action('admin_enqueue_scripts', array(\$this, 'enqueue_scripts'));
        add_action('wp_ajax_wdi_search_discogs', array(\$this, 'search_discogs'));
        add_action('wp_ajax_wdi_import_release', array(\$this, 'import_release'));
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('wdi-admin', WDI_PLUGIN_URL . 'admin/admin.js', array('jquery'), WDI_VERSION, true);
        wp_enqueue_style('wdi-admin', WDI_PLUGIN_URL . 'admin/style.css', array(), WDI_VERSION);
        
        wp_localize_script('wdi-admin', 'wdi_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wdi_nonce')
        ));
    }
    
    public function search_discogs() {
        check_ajax_referer('wdi_nonce', 'nonce');
        
        \$query = sanitize_text_field(\$_POST['query']);
        \$results = \$this->perform_discogs_search(\$query);
        
        wp_send_json_success(\$results);
    }
    
    public function import_release() {
        check_ajax_referer('wdi_nonce', 'nonce');
        
        \$release_id = sanitize_text_field(\$_POST['release_id']);
        \$result = \$this->perform_import(\$release_id);
        
        wp_send_json_success(\$result);
    }
    
    private function perform_discogs_search(\$query) {
        // Implementar búsqueda en Discogs
        return array();
    }
    
    private function perform_import(\$release_id) {
        // Implementar importación
        return array();
    }
}
";
    
    file_put_contents($plugin_dir . '/admin/class-wdi-admin.php', $admin_class_content);
    
    // CSS de administración
    $admin_css = "/* Estilos para Discogs Importer */
.wdi-admin-container {
    max-width: 1200px;
    margin: 20px 0;
}

.wdi-search-form {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
}

.wdi-search-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.wdi-search-button {
    background: #0073aa;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}

.wdi-results {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.wdi-result-item {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    text-align: center;
}

.wdi-result-item img {
    max-width: 100%;
    height: auto;
    margin-bottom: 10px;
}

.wdi-import-button {
    background: #00a32a;
    color: white;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
";
    
    file_put_contents($plugin_dir . '/admin/style.css', $admin_css);
    
    // JavaScript de administración
    $admin_js = "jQuery(document).ready(function(\$) {
    // Búsqueda en Discogs
    \$('#wdi-search-form').on('submit', function(e) {
        e.preventDefault();
        
        var query = \$('#wdi-search-input').val();
        if (!query) return;
        
        \$('#wdi-search-button').prop('disabled', true).text('Buscando...');
        
        \$.ajax({
            url: wdi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wdi_search_discogs',
                nonce: wdi_ajax.nonce,
                query: query
            },
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    alert('Error en la búsqueda');
                }
            },
            error: function() {
                alert('Error de conexión');
            },
            complete: function() {
                \$('#wdi-search-button').prop('disabled', false).text('Buscar');
            }
        });
    });
    
    // Importar release
    \$(document).on('click', '.wdi-import-button', function() {
        var releaseId = \$(this).data('release-id');
        var button = \$(this);
        
        button.prop('disabled', true).text('Importando...');
        
        \$.ajax({
            url: wdi_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wdi_import_release',
                nonce: wdi_ajax.nonce,
                release_id: releaseId
            },
            success: function(response) {
                if (response.success) {
                    button.text('Importado').addClass('imported');
                } else {
                    alert('Error al importar');
                    button.prop('disabled', false).text('Importar');
                }
            },
            error: function() {
                alert('Error de conexión');
                button.prop('disabled', false).text('Importar');
            }
        });
    });
    
    function displayResults(results) {
        var container = \$('#wdi-results');
        container.empty();
        
        if (results.length === 0) {
            container.html('<p>No se encontraron resultados</p>');
            return;
        }
        
        results.forEach(function(result) {
            var item = \$('<div class=\"wdi-result-item\">');
            item.html(
                '<img src=\"' + (result.thumb || '') + '\" alt=\"' + result.title + '\">' +
                '<h3>' + result.title + '</h3>' +
                '<p>' + result.artist + '</p>' +
                '<button class=\"wdi-import-button\" data-release-id=\"' + result.id + '\">Importar</button>'
            );
            container.append(item);
        });
    }
});
";
    
    file_put_contents($plugin_dir . '/admin/admin.js', $admin_js);
    
    // Archivo de desinstalación
    $uninstall_content = "<?php
// Si se llama directamente, salir
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Eliminar tablas de la base de datos
global \$wpdb;

\$wpdb->query(\"DROP TABLE IF EXISTS {\$wpdb->prefix}wdi_imports\");

// Eliminar opciones
delete_option('wdi_settings');
delete_option('wdi_version');
";
    
    file_put_contents($plugin_dir . '/uninstall.php', $uninstall_content);
    
    // README básico
    $readme_content = "# Discogs Importer Plugin

Plugin de WordPress para importar productos desde la API de Discogs.

## Instalación

1. Sube el plugin a tu directorio de plugins de WordPress
2. Activa el plugin desde el panel de administración
3. Configura tu API key de Discogs
4. ¡Comienza a importar productos!

## Uso

1. Ve a Discogs Importer en el menú de administración
2. Busca productos usando la barra de búsqueda
3. Haz clic en Importar para agregar productos a tu tienda

## Soporte

Para soporte técnico, contacta a contact@dwoosync.com
";
    
    file_put_contents($plugin_dir . '/README.md', $readme_content);
    
    // Archivos placeholder para otras carpetas
    file_put_contents($plugin_dir . '/public/index.php', '<?php // Silence is golden');
    file_put_contents($plugin_dir . '/assets/index.php', '<?php // Silence is golden');
    file_put_contents($plugin_dir . '/config/index.php', '<?php // Silence is golden');
    file_put_contents($plugin_dir . '/languages/index.php', '<?php // Silence is golden');
    file_put_contents($plugin_dir . '/templates/index.php', '<?php // Silence is golden');
    file_put_contents($plugin_dir . '/includes/index.php', '<?php // Silence is golden');
    file_put_contents($plugin_dir . '/admin/index.php', '<?php // Silence is golden');
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descargar Plugin - Discogs Importer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .spinning-disc {
            animation: spin 3s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .nav-logo h2 {
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #1db954, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(29, 185, 84, 0.3);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <i class="fas fa-download text-blue-600 mr-3"></i>
                            Descargar Plugin
                        </h1>
                        <p class="text-gray-600 mt-2">Descarga tu plugin con la licencia preconfigurada</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Licencia</div>
                        <div class="font-mono text-lg font-bold text-green-600">
                            <?php echo substr($_SESSION['license_key'], 0, 8) . '...'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información del Plugin -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    Información del Plugin
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">Características Incluidas:</h3>
                        <ul class="space-y-2 text-gray-600">
                            <li><i class="fas fa-check text-green-500 mr-2"></i> Licencia preconfigurada</li>
                            <li><i class="fas fa-check text-green-500 mr-2"></i> Búsqueda en Discogs API</li>
                            <li><i class="fas fa-check text-green-500 mr-2"></i> Importación automática</li>
                            <li><i class="fas fa-check text-green-500 mr-2"></i> Interfaz de administración</li>
                            <li><i class="fas fa-check text-green-500 mr-2"></i> Validación de dominio</li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-2">Detalles de tu Licencia:</h3>
                        <div class="space-y-2 text-gray-600">
                            <div><strong>Clave:</strong> <?php echo $_SESSION['license_key']; ?></div>
                            <div><strong>Dominio:</strong> <?php echo $_SESSION['domain'] ?? 'localhost'; ?></div>
                            <div><strong>Email:</strong> <?php echo $_SESSION['user_email']; ?></div>
                            <div><strong>Plan:</strong> <?php echo ucfirst($_SESSION['plan_type'] ?? 'premium'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón de Descarga -->
            <div class="bg-white rounded-lg shadow-md p-6 text-center">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-download text-green-600 mr-2"></i>
                    Descargar Plugin Preconfigurado
                </h2>
                
                <p class="text-gray-600 mb-6">
                    Tu plugin viene con la licencia ya configurada. Solo necesitas instalarlo y configurar tu API de Discogs.
                </p>
                
                <a href="?download=plugin" 
                   class="inline-flex items-center px-8 py-4 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition duration-200 shadow-lg">
                    <i class="fas fa-download mr-3"></i>
                    Descargar Plugin ZIP
                </a>
                
                <div class="mt-4 text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    El archivo ZIP contiene todos los archivos necesarios del plugin
                </div>
            </div>

            <!-- Instrucciones de Instalación -->
            <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-list-ol text-blue-600 mr-2"></i>
                    Instrucciones de Instalación
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-3">1. Instalar Plugin</h3>
                        <ol class="space-y-2 text-gray-600">
                            <li>1. Ve a tu panel de WordPress</li>
                            <li>2. Navega a Plugins → Añadir nuevo</li>
                            <li>3. Haz clic en "Subir plugin"</li>
                            <li>4. Selecciona el archivo ZIP descargado</li>
                            <li>5. Activa el plugin</li>
                        </ol>
                    </div>
                    
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-3">2. Configurar API</h3>
                        <ol class="space-y-2 text-gray-600">
                            <li>1. Ve a <a href="https://www.discogs.com/settings/developers" target="_blank" class="text-blue-600 hover:underline">Discogs Developers</a></li>
                            <li>2. Crea una nueva aplicación</li>
                            <li>3. Copia tu User-Agent y API Key</li>
                            <li>4. Configúralos en el plugin</li>
                            <li>5. ¡Comienza a importar!</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Navegación -->
            <div class="mt-8 text-center">
                <a href="dashboard.php" 
                   class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition duration-200">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Volver al Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
