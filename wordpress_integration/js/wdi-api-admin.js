/**
 * JavaScript para la configuración de la API
 * 
 * @package Discogs_Importer
 * @version 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Probar conexión con la API
        $('#test-api-connection').on('click', function() {
            const button = $(this);
            const status = $('#api-connection-status');
            
            button.prop('disabled', true).text('Probando...');
            status.html('<span class="spinner is-active"></span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wdi_test_api_connection',
                    nonce: wdi_api_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    status.html('<span style="color: red;">✗ Error de conexión</span>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Probar Conexión');
                }
            });
        });
        
        // Validar licencia
        $('#validate-license').on('click', function() {
            const button = $(this);
            const status = $('#license-validation-status');
            const licenseKey = $('#license_key').val();
            const domain = $('#domain').val();
            
            if (!licenseKey) {
                status.html('<span style="color: red;">✗ License key requerida</span>');
                return;
            }
            
            button.prop('disabled', true).text('Validando...');
            status.html('<span class="spinner is-active"></span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wdi_validate_license',
                    nonce: wdi_api_admin.nonce,
                    license_key: licenseKey,
                    domain: domain
                },
                success: function(response) {
                    if (response.success) {
                        const license = response.data;
                        status.html('<span style="color: green;">✓ Licencia válida - Plan: ' + license.plan_type + '</span>');
                    } else {
                        status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    status.html('<span style="color: red;">✗ Error de conexión</span>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Validar Licencia');
                }
            });
        });
        
        // Crear suscripción
        $('#create-subscription').on('click', function() {
            const button = $(this);
            const status = $('#subscription-status');
            const email = $('#sub_email').val();
            const firstName = $('#sub_first_name').val();
            const lastName = $('#sub_last_name').val();
            const planType = $('#sub_plan_type').val();
            const domain = $('#domain').val();
            
            if (!email || !firstName || !lastName) {
                status.html('<span style="color: red;">✗ Todos los campos son requeridos</span>');
                return;
            }
            
            button.prop('disabled', true).text('Creando...');
            status.html('<span class="spinner is-active"></span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wdi_create_subscription',
                    nonce: wdi_api_admin.nonce,
                    email: email,
                    domain: domain,
                    first_name: firstName,
                    last_name: lastName,
                    plan_type: planType
                },
                success: function(response) {
                    if (response.success) {
                        status.html('<span style="color: green;">✓ Suscripción creada exitosamente</span>');
                        $('#license_key').val(response.data.license_key);
                        $('#sub_email, #sub_first_name, #sub_last_name').val('');
                        alert('Suscripción creada exitosamente. Tu License Key ha sido guardada automáticamente.');
                    } else {
                        status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    status.html('<span style="color: red;">✗ Error de conexión</span>');
                },
                complete: function() {
                    button.prop('disabled', false).text('Crear Suscripción');
                }
            });
        });
        
        // Auto-llenar dominio
        if (!$('#domain').val()) {
            $('#domain').val(window.location.hostname);
        }
        
        // Validar URL de API
        $('#api_url').on('blur', function() {
            const url = $(this).val();
            if (url && !url.endsWith('/')) {
                $(this).val(url + '/');
            }
        });
        
        // Mostrar/ocultar campos de suscripción según el plan
        $('#sub_plan_type').on('change', function() {
            const planType = $(this).val();
            if (planType === 'free') {
                $('.subscription-premium-fields').hide();
            } else {
                $('.subscription-premium-fields').show();
            }
        });
        
        // Verificar estado de la licencia al cargar la página
        const licenseKey = $('#license_key').val();
        if (licenseKey) {
            $('#validate-license').click();
        }
        
    });

})(jQuery);

