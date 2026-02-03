<?php
/**
 * Clase para el seguimiento de uso de suscripciones
 * 
 * @package Discogs_Importer
 * @version 1.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class WDI_Usage_Tracker {
    
    private $subscriber_id;
    private $license_key;
    private $api_url;
    private $domain;
    
    /**
     * Constructor
     */
    public function __construct($subscriber_id, $license_key) {
        $this->subscriber_id = $subscriber_id;
        $this->license_key = $license_key;
        $this->init_api_config();
    }
    
    /**
     * Inicializar configuración de la API
     */
    private function init_api_config() {
        $options = get_option('discogs-importer_options');
        $this->api_url = isset($options['api_url']) ? esc_attr($options['api_url']) : 'http://localhost/api_discogs/api/index.php';
        $this->domain = get_site_url();
    }
    
    /**
     * Obtener estado actual del ciclo (simplificado)
     */
    public function get_cycle_status() {
        // El estado del ciclo se maneja ahora en la API centralizada
        return 'active';
    }
    
    /**
     * Incrementar contador de sincronizaciones
     */
    public function increment_sync_count($product_id, $sync_type = 'manual', $fields_updated = null) {
        try {
            // Llamar al endpoint de la API centralizada
            $response = wp_remote_post($this->api_url . 'increment_sync_count', [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'license_key' => $this->license_key,
                    'domain' => $this->domain
                ]),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                error_log('[WDI_Usage_Tracker] Error de conexión: ' . $response->get_error_message());
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data || !$data['success']) {
                $error_message = $data['error'] ?? 'Error desconocido';
                error_log('[WDI_Usage_Tracker] Error incrementando sync_count: ' . $error_message);
                
                // Si es un error de límite excedido, retornar el error completo
                if (isset($data['error']) && $data['error'] === 'USAGE_LIMIT_EXCEEDED') {
                    return $data; // Retornar el error completo para que el plugin lo maneje
                }
                
                return false;
            }
            
            error_log('[WDI_Usage_Tracker] Sync count incrementado exitosamente: ' . $data['data']['new_sync_count']);
            return true;
            
        } catch (Exception $e) {
            error_log('[WDI_Usage_Tracker] Error al incrementar sync_count: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Incrementar contador de llamadas API (simplificado)
     */
    public function increment_api_calls($product_id, $endpoint, $call_type = 'sync') {
        // Las llamadas API se manejan ahora en la API centralizada
        // Este método se mantiene para compatibilidad pero no hace nada
            return true;
    }
    
    /**
     * Obtener uso del ciclo actual (simplificado)
     */
    public function get_current_cycle_usage() {
        try {
            error_log('[WDI_Usage_Tracker] DEBUG: Obteniendo uso actual del ciclo');
            
            // Obtener configuración de la API
            $discogs_options = get_option('discogs-importer_options');
            $api_url = $discogs_options['api_url'] ?? '';
            
            if (empty($api_url)) {
                error_log('[WDI_Usage_Tracker] DEBUG: API URL no configurada');
                return null;
            }
            
            // Llamar al endpoint para obtener el uso actual
            $response = wp_remote_post($api_url . 'get_usage_stats', [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'subscriber_id' => $this->subscriber_id,
                    'license_key' => $this->license_key
                ]),
                'timeout' => 30
            ]);
            
            if (is_wp_error($response)) {
                error_log('[WDI_Usage_Tracker] DEBUG: Error en wp_remote_post: ' . $response->get_error_message());
                return null;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            error_log('[WDI_Usage_Tracker] DEBUG: Respuesta de get_usage_stats: ' . json_encode($data));
            
            if ($data && $data['success']) {
                return [
                    'sync_count' => $data['sync_count'] ?? 0, // Usar sync_count para la validación
                    'api_calls_count' => $data['api_calls_count'] ?? 0,
                    'products_synced' => $data['products_synced'] ?? 0
                ];
            }
            
            error_log('[WDI_Usage_Tracker] DEBUG: Error en respuesta de get_usage_stats');
            return null;
            
        } catch (Exception $e) {
            error_log('[WDI_Usage_Tracker] DEBUG: Excepción en get_current_cycle_usage: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verificar límites de uso (simplificado)
     */
    public function check_usage_limits() {
        // Los límites se verifican ahora en la API centralizada
        return [
            'allowed' => true,
            'remaining' => -1 // Ilimitado
        ];
    }
}