<?php
/**
 * Integración de tracking de importaciones para WordPress
 * 
 * Este archivo debe ser incluido en el plugin de WordPress para
 * registrar automáticamente las importaciones cuando se presiona el botón importar.
 */

if (!defined('ABSPATH')) {
    exit; // Salir si se accede directamente
}

class DiscogsImportTracker {
    
    private $api_url;
    private $license_key;
    private $subscriber_id;
    
    public function __construct() {
        $this->api_url = get_option('discogs_api_url', 'http://localhost/api_discogs/api/');
        $this->license_key = get_option('discogs_license_key', '');
        $this->subscriber_id = get_option('discogs_subscriber_id', 0);
        
        // Hook para registrar importaciones
        add_action('discogs_import_completed', [$this, 'track_import'], 10, 3);
        add_action('discogs_bulk_import_completed', [$this, 'track_bulk_import'], 10, 2);
    }
    
    /**
     * Registrar una importación individual
     */
    public function track_import($master_id, $release_id, $import_data) {
        if (!$this->can_import()) {
            return false;
        }
        
        $data = [
            'subscriber_id' => $this->subscriber_id,
            'license_key' => $this->license_key,
            'import_type' => 'manual',
            'products_imported' => 1,
            'discogs_master_id' => $master_id,
            'discogs_release_id' => $release_id,
            'import_status' => 'success',
            'import_data' => $import_data
        ];
        
        return $this->send_tracking_request($data);
    }
    
    /**
     * Registrar una importación masiva
     */
    public function track_bulk_import($total_products, $import_data) {
        if (!$this->can_import()) {
            return false;
        }
        
        $data = [
            'subscriber_id' => $this->subscriber_id,
            'license_key' => $this->license_key,
            'import_type' => 'bulk',
            'products_imported' => $total_products,
            'import_status' => 'success',
            'import_data' => $import_data
        ];
        
        return $this->send_tracking_request($data);
    }
    
    /**
     * Verificar si se puede importar (límites)
     */
    public function can_import() {
        if (empty($this->license_key) || empty($this->subscriber_id)) {
            return false;
        }
        
        $response = wp_remote_get($this->api_url . 'index.php?endpoint=import_limits&subscriber_id=' . $this->subscriber_id . '&license_key=' . $this->license_key);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data['can_import'] ?? false;
    }
    
    /**
     * Obtener estadísticas de importaciones
     */
    public function get_import_stats() {
        if (empty($this->subscriber_id)) {
            return false;
        }
        
        $response = wp_remote_get($this->api_url . 'index.php?endpoint=import_stats&subscriber_id=' . $this->subscriber_id);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Enviar solicitud de tracking a la API
     */
    private function send_tracking_request($data) {
        $response = wp_remote_post($this->api_url . 'index.php?endpoint=import_track', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('Error al registrar importación: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Mostrar límites en el admin de WordPress
     */
    public function display_import_limits() {
        $stats = $this->get_import_stats();
        
        if (!$stats || !$stats['success']) {
            echo '<div class="notice notice-warning"><p>No se pudieron cargar las estadísticas de importación.</p></div>';
            return;
        }
        
        $stats = $stats['stats'];
        $percentage = ($stats['used_imports'] / $stats['max_imports']) * 100;
        
        ?>
        <div class="discogs-import-limits">
            <h3>Límites de Importación</h3>
            <div class="import-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                </div>
                <p>
                    <strong><?php echo $stats['used_imports']; ?></strong> de <strong><?php echo $stats['max_imports']; ?></strong> importaciones usadas
                    (<?php echo round($percentage, 1); ?>%)
                </p>
                <p>
                    <strong><?php echo $stats['remaining_imports']; ?></strong> importaciones restantes este mes
                </p>
            </div>
            
            <?php if ($stats['remaining_imports'] <= 5): ?>
                <div class="notice notice-warning">
                    <p><strong>Advertencia:</strong> Te quedan pocas importaciones este mes. Considera actualizar tu plan.</p>
                </div>
            <?php endif; ?>
            
            <?php if ($stats['remaining_imports'] <= 0): ?>
                <div class="notice notice-error">
                    <p><strong>Límite alcanzado:</strong> Has alcanzado el límite de importaciones para este mes.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .discogs-import-limits {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            transition: width 0.3s ease;
        }
        </style>
        <?php
    }
}

// Inicializar el tracker
$discogs_import_tracker = new DiscogsImportTracker();

/**
 * Función helper para usar en el plugin
 */
function discogs_track_import($master_id, $release_id = null, $import_data = []) {
    global $discogs_import_tracker;
    return $discogs_import_tracker->track_import($master_id, $release_id, $import_data);
}

function discogs_track_bulk_import($total_products, $import_data = []) {
    global $discogs_import_tracker;
    return $discogs_import_tracker->track_bulk_import($total_products, $import_data);
}

function discogs_can_import() {
    global $discogs_import_tracker;
    return $discogs_import_tracker->can_import();
}

function discogs_get_import_stats() {
    global $discogs_import_tracker;
    return $discogs_import_tracker->get_import_stats();
}





