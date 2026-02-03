<?php
/**
 * Cliente para la API de Discogs
 * 
 * @package Discogs_Importer
 * @version 1.0.0
 */

// Cargar configuración de API
require_once plugin_dir_path(__FILE__) . '../config/api-config.php';

// Cargar clase de seguimiento de uso
require_once plugin_dir_path(__FILE__) . '../includes/class-wdi-usage-tracker.php';

class WDI_API_Client {
    private $api_url;
    private $license_key;
    private $domain;
    private $timeout;
    private $usage_tracker;

    public function __construct($api_url = null, $license_key = null, $domain = null, $subscriber_id = null) {
        // Usar configuración centralizada
        $this->api_url = $api_url ?: wdi_get_api_url();
        $this->license_key = $license_key ?: get_option('discogs-importer_api_options')['license_key'] ?? '';
        $this->domain = $domain ?: $this->get_domain();
        $this->timeout = wdi_get_api_timeout();
        
        // Inicializar tracker de uso si tenemos subscriber_id
        if ($subscriber_id && $this->license_key) {
            try {
                $this->usage_tracker = new WDI_Usage_Tracker($subscriber_id, $this->license_key);
            } catch (Exception $e) {
                error_log('[WDI_API_Client] Error al inicializar usage tracker: ' . $e->getMessage());
                $this->usage_tracker = null;
            }
        }
    }

    /**
     * Buscar masters en Discogs
     */
    public function search_masters($query, $filters = [], $product_id = null) {
        $params = array_merge([
            'q' => $query,
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'discogs_api_key' => get_option('discogs-importer_options')['api_key'] ?? '',
            'discogs_api_secret' => get_option('discogs-importer_options')['api_secret'] ?? ''
        ], $filters);

        error_log('[API Client Debug] Parámetros de búsqueda: ' . print_r($params, true));
        error_log('[API Client Debug] URL de API: ' . $this->api_url);

        return $this->make_request('search', $params, 'GET', $product_id);
    }

    /**
     * Obtener versiones de un master
     */
    public function get_master_versions($master_id, $filters = [], $product_id = null) {
        $params = array_merge([
            'master_id' => $master_id,
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'discogs_api_key' => get_option('discogs-importer_options')['api_key'] ?? '',
            'discogs_api_secret' => get_option('discogs-importer_options')['api_secret'] ?? ''
        ], $filters);

        return $this->make_request('versions', $params, 'GET', $product_id);
    }

    /**
     * Obtener detalles de una versión
     */
    public function get_version_details($version_id, $product_id = null) {
        $params = [
            'release_id' => $version_id,
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'discogs_api_key' => get_option('discogs-importer_options')['api_key'] ?? '',
            'discogs_api_secret' => get_option('discogs-importer_options')['api_secret'] ?? ''
        ];

        return $this->make_request('release', $params, 'GET', $product_id);
    }

    /**
     * Obtener detalles de un artista
     */
    public function get_artist_details($artist_id) {
        $params = [
            'artist_id' => $artist_id,
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'discogs_api_key' => get_option('discogs-importer_options')['api_key'] ?? '',
            'discogs_api_secret' => get_option('discogs-importer_options')['api_secret'] ?? ''
        ];

        return $this->make_request('artist', $params);
    }

    /**
     * Obtener imagen de un release
     */
    public function get_release_image($release_id, $size = 'large') {
        $params = [
            'release_id' => $release_id,
            'size' => $size,
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'discogs_api_key' => get_option('discogs-importer_options')['api_key'] ?? '',
            'discogs_api_secret' => get_option('discogs-importer_options')['api_secret'] ?? ''
        ];

        return $this->make_request('image', $params);
    }

    /**
     * Validar licencia
     */
    public function validate_license($license_key = null, $domain = null) {
        $params = [
            'license_key' => $license_key ?: $this->license_key,
            'domain' => $domain ?: $this->domain
        ];

        return $this->make_request('validate_license', $params, 'POST');
    }

    /**
     * Crear nueva suscripción
     */
    public function create_subscription($data) {
        $data['license_key'] = $this->license_key;
        $data['domain'] = $this->domain;

        return $this->make_request('license', $data, 'POST');
    }

    /**
     * Obtener información de suscripción
     */
    public function get_subscription_info($subscription_code) {
        $params = [
            'subscription_code' => $subscription_code
        ];

        return $this->make_request('subscription', $params);
    }

    /**
     * Obtener estadísticas
     */
    public function get_stats() {
        $params = [
            'license_key' => $this->license_key,
            'domain' => $this->domain
        ];

        return $this->make_request('stats', $params);
    }

    /**
     * Realizar petición HTTP
     */
    private function make_request($endpoint, $params = [], $method = 'GET', $product_id = null) {
        $url = $this->api_url . $endpoint;
        
        $args = [
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'DiscogsImporter/1.0.0'
            ]
        ];

        if ($method === 'POST') {
            $args['method'] = 'POST';
            $args['body'] = json_encode($params);
        } else {
            // Si la URL ya tiene parámetros (endpoint=), usar & en lugar de ?
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . http_build_query($params);
        }

        error_log('[API Client Debug] Realizando petición a: ' . $url);
        error_log('[API Client Debug] Argumentos: ' . print_r($args, true));

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('[API Client Debug] Error en petición: ' . $response->get_error_message());
            
            // Contabilizar llamada API fallida
            if ($this->usage_tracker && $product_id) {
                $this->usage_tracker->increment_api_calls($product_id, $endpoint, 'other');
            }
            
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $response->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('[API Client Debug] Código de respuesta: ' . $response_code);
        error_log('[API Client Debug] Cuerpo de respuesta: ' . $body);

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[API Client Debug] Error JSON: ' . json_last_error_msg());
            error_log('[API Client Debug] Respuesta completa: ' . $body);
            
            // Contabilizar llamada API fallida
            if ($this->usage_tracker && $product_id) {
                $this->usage_tracker->increment_api_calls($product_id, $endpoint, 'other');
            }
            
            return [
                'success' => false,
                'error' => 'Error decodificando respuesta JSON: ' . json_last_error_msg()
            ];
        }

        // Contabilizar llamada API exitosa
        if ($this->usage_tracker && $product_id) {
            $call_type = $this->get_call_type_from_endpoint($endpoint);
            $this->usage_tracker->increment_api_calls($product_id, $endpoint, $call_type);
        }

        // Verificar si hay error de licencia en la respuesta
        if (isset($data['error']) && (strpos($data['error'], 'Licencia inválida') !== false || strpos($data['error'], 'expirada') !== false)) {
            $data['error'] = 'Licencia inválida o expirada. Debe adquirir una licencia válida en: <a href="https://www.discogsync.com" target="_blank" style="color: #0073aa; text-decoration: underline;">www.discogsync.com</a>';
        }

        return $data;
    }
    
    /**
     * Determinar tipo de llamada basado en el endpoint
     */
    private function get_call_type_from_endpoint($endpoint) {
        switch ($endpoint) {
            case 'search':
                return 'search';
            case 'versions':
            case 'release':
            case 'master':
                return 'details';
            case 'image':
                return 'other';
            default:
                return 'sync';
        }
    }

    /**
     * Obtener dominio actual
     */
    private function get_domain() {
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        $domain = preg_replace('/^www\./', '', $domain);
        // Remover puerto si existe
        $domain = preg_replace('/:\d+$/', '', $domain);
        return $domain;
    }

    /**
     * Verificar si la licencia es válida
     */
    public function is_license_valid() {
        if (empty($this->license_key)) {
            return false;
        }

        $validation = $this->validate_license();
        return $validation['success'] && $validation['valid'];
    }

    /**
     * Obtener información de la licencia
     */
    public function get_license_info() {
        if (empty($this->license_key)) {
            return null;
        }

        $validation = $this->validate_license();
        return $validation['success'] ? $validation['license'] : null;
    }

    /**
     * Verificar límites de uso
     */
    public function check_usage_limits() {
        $validation = $this->validate_license();
        
        if (!$validation['success'] || !$validation['valid']) {
            return [
                'allowed' => false,
                'error' => 'Licencia inválida o expirada. Debe adquirir una licencia válida en: <a href="https://www.discogsync.com" target="_blank" style="color: #0073aa; text-decoration: underline;">www.discogsync.com</a>'
            ];
        }

        return [
            'allowed' => true,
            'license' => $validation['license']
        ];
    }

    /**
     * Obtener URL de la API
     */
    public function get_api_url() {
        return $this->api_url;
    }

    /**
     * Establecer URL de la API
     */
    public function set_api_url($url) {
        $this->api_url = rtrim($url, '/') . '/';
    }

    /**
     * Establecer clave de licencia
     */
    public function set_license_key($key) {
        $this->license_key = $key;
    }

    /**
     * Establecer dominio
     */
    public function set_domain($domain) {
        $this->domain = $domain;
    }
}
