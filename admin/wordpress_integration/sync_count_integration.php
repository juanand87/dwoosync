<?php
/**
 * Integración del incremento de sync_count con el plugin de WordPress
 * Usa el sistema de API centralizado
 */

class DwooSyncCountManager {
    
    private $api_url;
    private $license_key;
    private $domain;
    
    public function __construct($license_key, $domain) {
        $this->api_url = 'http://localhost/api_discogs/api/index.php?endpoint=increment_sync_count';
        $this->license_key = $license_key;
        $this->domain = $domain;
    }
    
    /**
     * Incrementar el contador de sincronizaciones
     */
    public function incrementSyncCount() {
        $data = [
            'license_key' => $this->license_key,
            'domain' => $this->domain
        ];
        
        $response = wp_remote_post($this->api_url, [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            error_log('Error incrementando sync_count: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($result['success']) {
            error_log('Sync count incrementado: ' . $result['data']['new_sync_count']);
            return $result['data'];
        } else {
            error_log('Error incrementando sync_count: ' . $result['error']);
            return false;
        }
    }
}

// Función que se ejecuta cuando se presiona "importar"
function handle_import_button_click() {
    // Obtener configuración del plugin
    $license_key = get_option('dwoosync_license_key');
    $domain = get_site_url();
    
    if (empty($license_key)) {
        wp_die('Licencia no configurada');
    }
    
    // Crear instancia del manager
    $sync_manager = new DwooSyncCountManager($license_key, $domain);
    
    // Incrementar contador
    $result = $sync_manager->incrementSyncCount();
    
    if ($result) {
        // Mostrar mensaje de éxito
        add_action('admin_notices', function() use ($result) {
            echo '<div class="notice notice-success"><p>';
            echo 'Sincronización exitosa. Total de sincronizaciones: ' . $result['new_sync_count'];
            echo '</p></div>';
        });
    } else {
        // Mostrar mensaje de error
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo 'Error al sincronizar. Verifique su licencia.';
            echo '</p></div>';
        });
    }
}

// Hook para el botón de importar
add_action('wp_ajax_dwoosync_import', 'handle_import_button_click');
add_action('wp_ajax_nopriv_dwoosync_import', 'handle_import_button_click');

// JavaScript para el botón de importar
function add_import_button_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#dwoosync-import-btn').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var originalText = button.text();
            
            // Deshabilitar botón y mostrar loading
            button.prop('disabled', true).text('Importando...');
            
            // Llamar a la función de importar
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dwoosync_import'
                },
                success: function(response) {
                    if (response.success) {
                        // Mostrar mensaje de éxito
                        alert('Importación exitosa!');
                        // Recargar la página para mostrar el mensaje
                        location.reload();
                    } else {
                        alert('Error en la importación: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error de conexión');
                },
                complete: function() {
                    // Restaurar botón
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'add_import_button_script');
?>





