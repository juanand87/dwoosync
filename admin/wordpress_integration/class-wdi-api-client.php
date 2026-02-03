<?php
/**
 * Cliente para la API de Discogs
 * 
 * @package Discogs_Importer
 * @version 1.0.0
 */

class WDI_API_Client {
    private $api_url;
    private $license_key;
    private $domain;
    private $timeout;

    public function __construct() {
        $this->api_url = 'http://localhost/api_discogs/api/';
        $this->timeout = 30;
        $this->license_key = get_option('discogs_importer_license_key', '');
        $this->domain = $this->get_domain();
    }

    /**
     * Buscar masters en Discogs
     */
    public function search_masters($query, $filters = []) {
        $params = array_merge([
            'q' => $query,
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'discogs_api_key' => get_option('discogs_importer_api_key', ''),
            'discogs_api_secret' => get_option('discogs_importer_api_secret', '')
        ], $filters);

        return $this->make_request('search', $params);
    }

    /**
     * Obtener versiones de un master
     */
    public function get_master_versions($master_id, $filters = []) {
        $params = array_merge([
            'master_id' => $master_id,
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'discogs_api_key' => get_option('discogs_importer_api_key', ''),
            'discogs_api_secret' => get_option('discogs_importer_api_secret', '')
        ], $filters);

        return $this->make_request('versions', $params);
    }

    /**
     * Obtener detalles de una versión
     */
    public function get_version_details($version_id) {
        $params = [
            'release_id' => $version_id,
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'discogs_api_key' => get_option('discogs_importer_api_key', ''),
            'discogs_api_secret' => get_option('discogs_importer_api_secret', '')
        ];

        return $this->make_request('release', $params);
    }

    /**
     * Obtener detalles de un artista
     */
    public function get_artist_details($artist_id) {
        $params = [
            'artist_id' => $artist_id,
            'license_key' => $this->license_key,
            'domain' => $this->domain,
            'discogs_api_key' => get_option('discogs_importer_api_key', ''),
            'discogs_api_secret' => get_option('discogs_importer_api_secret', '')
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
            'discogs_api_key' => get_option('discogs_importer_api_key', ''),
            'discogs_api_secret' => get_option('discogs_importer_api_secret', '')
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

        return $this->make_request('license', $params);
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
    private function make_request($endpoint, $params = [], $method = 'GET') {
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
            $url .= '?' . http_build_query($params);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Error decodificando respuesta JSON'
            ];
        }

        return $data;
    }

    /**
     * Obtener dominio actual
     */
    private function get_domain() {
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        $domain = preg_replace('/^www\./', '', $domain);
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
                'error' => $validation['error'] ?? 'Licencia inválida'
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
