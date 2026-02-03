<?php
/**
 * Integración del warning en tiempo real en el cuadro de búsqueda
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encolar script para validación en tiempo real
 */
function discogs_realtime_warning_script() {
    // Solo en páginas de admin
    if (!is_admin()) {
        return;
    }
    
    wp_enqueue_script(
        'discogs-realtime-warning',
        plugin_dir_url(__FILE__) . '../js/real-time-warning.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    // Localizar script con ajaxurl
    wp_localize_script('discogs-realtime-warning', 'discogs_warning_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wdi_validate_license')
    ));
}
add_action('admin_enqueue_scripts', 'discogs_realtime_warning_script');

/**
 * Script inline como respaldo
 */
function discogs_realtime_warning_inline() {
    if (!is_admin()) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        console.log('Script de warning en tiempo real cargado');
        
        // Función para validar licencia
        function validateLicense() {
            console.log('=== INICIANDO VALIDACIÓN DE LICENCIA ===');
            
            // Buscar license key en diferentes lugares
            var licenseKey = getCookie('wdi_license_key') || 
                           getCookie('discogs_license_key') || 
                           getCookie('wdi_license') ||
                           localStorage.getItem('wdi_license_key') ||
                           '';
            
            var domain = window.location.hostname;
            
            console.log('License key encontrado:', licenseKey);
            console.log('Dominio:', domain);
            
            if (!licenseKey) {
                console.log('No se encontró license key, mostrando warning de prueba');
                showWarningInSearchBox('PRUEBA: No se encontró license key. Su licencia está vencida, en 2 días se bloqueará su licencia, debe renovarla acá: http://www.dwoosync.com');
                return;
            }
            
            console.log('Validando licencia:', licenseKey);
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'wdi_validate_license',
                    nonce: '<?php echo wp_create_nonce("wdi_validate_license"); ?>',
                    license_key: licenseKey,
                    domain: domain
                },
                success: function(response) {
                    console.log('Respuesta de validación:', response);
                    if (response.success && response.data && response.data.warning) {
                        showWarningInSearchBox(response.data.warning);
                    } else {
                        console.log('No hay warning en la respuesta');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error validando licencia:', error);
                    console.log('XHR:', xhr);
                    // Mostrar warning de prueba en caso de error
                    showWarningInSearchBox('PRUEBA (Error AJAX): Su licencia está vencida, en 2 días se bloqueará su licencia, debe renovarla acá: http://www.dwoosync.com');
                }
            });
        }
        
        // Función para mostrar warning en el cuadro de búsqueda
        function showWarningInSearchBox(warningMessage) {
            console.log('Mostrando warning:', warningMessage);
            
            // Buscar el texto "ID de Lanzamiento, Catálogo o Matriz"
            var labelText = $('*').filter(function() {
                return $(this).text().trim() === 'ID de Lanzamiento, Catálogo o Matriz:';
            });
            
            if (labelText.length > 0) {
                console.log('Label encontrado, agregando warning arriba');
                
                // Remover warning anterior
                $('.discogs-warning').remove();
                
                // Crear warning
                var warningHtml = '<div class="discogs-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 13px;">';
                warningHtml += '<strong>⚠️ Advertencia:</strong> ' + warningMessage;
                warningHtml += '</div>';
                
                // Insertar antes del label
                labelText.before(warningHtml);
                console.log('Warning agregado arriba del label');
            } else {
                console.log('No se encontró el label "ID de Lanzamiento, Catálogo o Matriz:"');
            }
        }
        
        // Función para obtener cookie
        function getCookie(name) {
            var value = "; " + document.cookie;
            var parts = value.split("; " + name + "=");
            if (parts.length == 2) return parts.pop().split(";").shift();
        }
        
        // Validar licencia al cargar la página
        validateLicense();
        
        // PRUEBA: Mostrar warning de prueba al hacer doble clic en el campo
        $(document).on('dblclick', '#discogs_release_id', function() {
            showWarningInSearchBox('PRUEBA: Su licencia está vencida, en 2 días se bloqueará su licencia, debe renovarla acá: http://www.dwoosync.com');
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'discogs_realtime_warning_inline');
?>
