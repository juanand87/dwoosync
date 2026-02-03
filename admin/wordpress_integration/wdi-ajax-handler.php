<?php
/**
 * Manejador AJAX para el plugin de WordPress
 * Este archivo debe ser incluido en el plugin principal
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manejar búsqueda de datos de Discogs
 */
function wdi_fetch_discogs_data() {
    // Verificar nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wdi_fetch_discogs_data')) {
        wp_die('Nonce inválido');
    }
    
    try {
        // Obtener parámetros
        $release_id = sanitize_text_field($_POST['release_id'] ?? '');
        $format = sanitize_text_field($_POST['format'] ?? '');
        $country = sanitize_text_field($_POST['country'] ?? '');
        
        if (empty($release_id)) {
            wp_send_json_error(['message' => 'Release ID requerido']);
            return;
        }
        
        // Obtener configuración del plugin
        $api_url = get_option('wdi_api_url', 'http://localhost/api_discogs/api/index.php');
        $license_key = get_option('wdi_license_key', '');
        $domain = get_option('wdi_domain', get_site_url());
        $discogs_api_key = get_option('wdi_discogs_api_key', '');
        $discogs_api_secret = get_option('wdi_discogs_api_secret', '');
        
        if (empty($license_key)) {
            wp_send_json_error(['message' => 'License key no configurada']);
            return;
        }
        
        if (empty($discogs_api_key)) {
            wp_send_json_error(['message' => 'Discogs API key no configurada']);
            return;
        }
        
        // Preparar parámetros para la búsqueda
        $search_params = [
            'endpoint' => 'search',
            'q' => $release_id,
            'license_key' => $license_key,
            'domain' => $domain,
            'discogs_api_key' => $discogs_api_key,
            'discogs_api_secret' => $discogs_api_secret
        ];
        
        // Agregar filtros si están presentes
        if (!empty($format)) {
            $search_params['format'] = $format;
        }
        if (!empty($country)) {
            $search_params['country'] = $country;
        }
        
        // Hacer la petición a la API
        $api_url = rtrim($api_url, '/') . '?' . http_build_query($search_params);
        
        $response = wp_remote_get($api_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            ]
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Error de conexión: ' . $response->get_error_message()]);
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            wp_send_json_error(['message' => 'Respuesta inválida de la API']);
            return;
        }
        
        if (!$data['success']) {
            wp_send_json_error(['message' => 'Error en la búsqueda: ' . ($data['error'] ?? 'Error desconocido')]);
            return;
        }
        
        // Devolver los resultados
        $response_data = $data['data'];
        
        // Incluir warning si está en período de gracia
        if (isset($data['warning'])) {
            $response_data['warning'] = $data['warning'];
        }
        
        wp_send_json_success($response_data);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error interno: ' . $e->getMessage()]);
    }
}


/**
 * Probar conexión con la API
 */
function wdi_test_api_connection() {
    if (!wp_verify_nonce($_POST['nonce'], 'wdi_test_api_connection')) {
        wp_die('Nonce inválido');
    }
    
    try {
        $api_url = get_option('wdi_api_url', 'http://localhost/api_discogs/api/index.php');
        $response = wp_remote_get($api_url . '?endpoint=health', ['timeout' => 10]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Error de conexión: ' . $response->get_error_message()]);
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            wp_send_json_success(['message' => 'Conexión exitosa con la API']);
        } else {
            wp_send_json_error(['message' => 'Error en la respuesta de la API']);
        }
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error interno: ' . $e->getMessage()]);
    }
}

/**
 * Validar licencia
 */
function wdi_validate_license() {
    if (!wp_verify_nonce($_POST['nonce'], 'wdi_validate_license')) {
        wp_die('Nonce inválido');
    }
    
    try {
        $api_url = get_option('wdi_api_url', 'http://localhost/api_discogs/api/index.php');
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');
        $domain = sanitize_text_field($_POST['domain'] ?? get_site_url());
        
        if (empty($license_key)) {
            wp_send_json_error(['message' => 'License key requerida']);
            return;
        }
        
        $response = wp_remote_post($api_url . '?endpoint=validate_license', [
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
            wp_send_json_error(['message' => 'Error de conexión: ' . $response->get_error_message()]);
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success'] && $data['valid']) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error(['message' => $data['message'] ?? 'Licencia inválida']);
        }
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error interno: ' . $e->getMessage()]);
    }
}

/**
 * Crear suscripción
 */
function wdi_create_subscription() {
    if (!wp_verify_nonce($_POST['nonce'], 'wdi_create_subscription')) {
        wp_die('Nonce inválido');
    }
    
    try {
        $api_url = get_option('wdi_api_url', 'http://localhost/api_discogs/api/index.php');
        
        $subscription_data = [
            'email' => sanitize_email($_POST['email'] ?? ''),
            'domain' => sanitize_text_field($_POST['domain'] ?? get_site_url()),
            'first_name' => sanitize_text_field($_POST['first_name'] ?? ''),
            'last_name' => sanitize_text_field($_POST['last_name'] ?? ''),
            'plan_type' => sanitize_text_field($_POST['plan_type'] ?? 'free')
        ];
        
        $response = wp_remote_post($api_url . '?endpoint=license', [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($subscription_data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Error de conexión: ' . $response->get_error_message()]);
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            // Guardar la license key
            update_option('wdi_license_key', $data['data']['license_key']);
            wp_send_json_success($data['data']);
        } else {
            wp_send_json_error(['message' => $data['error'] ?? 'Error creando suscripción']);
        }
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error interno: ' . $e->getMessage()]);
    }
}

// Registrar las acciones AJAX
add_action('wp_ajax_wdi_fetch_discogs_data', 'wdi_fetch_discogs_data');
add_action('wp_ajax_wdi_test_api_connection', 'wdi_test_api_connection');
add_action('wp_ajax_wdi_validate_license', 'wdi_validate_license');
add_action('wp_ajax_wdi_create_subscription', 'wdi_create_subscription');
?>
