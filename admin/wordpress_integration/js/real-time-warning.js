/**
 * Script para mostrar warning del período de gracia en tiempo real
 */

jQuery(document).ready(function($) {
    console.log('Script de warning en tiempo real cargado');
    
    // Función para validar licencia
    function validateLicense() {
        var licenseKey = getCookie('wdi_license_key') || getCookie('discogs_license_key') || '';
        var domain = window.location.hostname;
        
        if (!licenseKey) {
            return;
        }
        
        $.ajax({
            url: ajaxurl || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'wdi_validate_license',
                nonce: '<?php echo wp_create_nonce("wdi_validate_license"); ?>',
                license_key: licenseKey,
                domain: domain
            },
            success: function(response) {
                if (response.success && response.data && response.data.warning) {
                    showWarningInSearchBox(response.data.warning);
                }
            },
            error: function() {
                console.log('Error validando licencia');
            }
        });
    }
    
    // Función para mostrar warning en el cuadro de búsqueda
    function showWarningInSearchBox(warningMessage) {
        // Buscar el texto "ID de Lanzamiento, Catálogo o Matriz"
        var labelText = $('*').filter(function() {
            return $(this).text().trim() === 'ID de Lanzamiento, Catálogo o Matriz:';
        });
        
        if (labelText.length > 0) {
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
