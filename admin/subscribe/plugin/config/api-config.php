<?php
/**
 * Configuración de API por entorno
 * 
 * @package Discogs_Importer
 * @version 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Configuración de entorno
// Cambiar a 'production' cuando se despliegue en producción
define('WDI_API_ENVIRONMENT', 'local');

// Configuración de API por entorno
$wdi_api_config = [
    'local' => [
        'base_url' => 'http://localhost/api_discogs/api/index.php?endpoint=',
        'timeout' => 30,
        'description' => 'Entorno de desarrollo local'
    ],
    'production' => [
        'base_url' => 'https://dwoosync.com/api/index.php?endpoint=',
        'timeout' => 30,
        'description' => 'Entorno de producción'
    ]
];

// Función para obtener la configuración actual
function wdi_get_api_config() {
    $environment = defined('WDI_API_ENVIRONMENT') ? WDI_API_ENVIRONMENT : 'local';
    
    $wdi_api_config = [
        'local' => [
            'base_url' => 'http://localhost/api_discogs/api/index.php?endpoint=',
            'timeout' => 30,
            'description' => 'Entorno de desarrollo local'
        ],
    'production' => [
        'base_url' => 'https://dwoosync.com/api/index.php?endpoint=',
        'timeout' => 30,
        'description' => 'Entorno de producción'
    ]
    ];
    
    return $wdi_api_config[$environment] ?? $wdi_api_config['local'];
}

// Función para obtener la URL base de la API
function wdi_get_api_url() {
    // Detectar si estamos en localhost
    $is_localhost = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
                    strpos($_SERVER['HTTP_HOST'], '::1') !== false);
    
    if ($is_localhost) {
        // En localhost: usar configuración editable de WordPress
        $options = get_option('woocommerce_discogs_importer_options');
        if (!empty($options['api_url'])) {
            return $options['api_url'];
        }
        
        // Si no hay configuración guardada, usar la configuración del entorno
        $config = wdi_get_api_config();
        return isset($config['base_url']) ? $config['base_url'] : 'http://localhost/api_discogs/api/index.php?endpoint=';
    } else {
        // En producción: siempre usar la URL fija
        return 'https://dwoosync.com/api/index.php?endpoint=';
    }
}

// Función para obtener el timeout
function wdi_get_api_timeout() {
    $config = wdi_get_api_config();
    return isset($config['timeout']) ? $config['timeout'] : 30;
}

// Función para obtener la descripción del entorno
function wdi_get_environment_description() {
    $config = wdi_get_api_config();
    return isset($config['description']) ? $config['description'] : 'Entorno de desarrollo local';
}
