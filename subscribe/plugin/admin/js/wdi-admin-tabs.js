(function( $ ) {
    'use strict';

    $(document).ready(function() {
        
        // --- INICIO: Funcionalidad de Pestañas de Configuración ---
        $('.wdi-tab-link').on('click', function(e) {
            e.preventDefault();
            
            const targetTab = $(this).data('tab');
            const targetPanel = $('#tab-' + targetTab);
            
            // Remover clase active de todos los enlaces y paneles
            $('.wdi-tab-link').removeClass('active');
            $('.wdi-tab-panel').removeClass('active');
            
            // Añadir clase active al enlace y panel seleccionados
            $(this).addClass('active');
            targetPanel.addClass('active');
            
            // Actualizar la URL sin recargar la página
            if (history.pushState) {
                const newUrl = window.location.pathname + '?page=' + getUrlParameter('page') + '&tab=' + targetTab;
                history.pushState(null, null, newUrl);
            }
        });
        
        // Función auxiliar para obtener parámetros de URL
        function getUrlParameter(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }
        
        // Cargar pestaña desde URL al cargar la página
        const urlTab = getUrlParameter('tab');
        if (urlTab && ['api', 'templates', 'shortcode', 'options'].includes(urlTab)) {
            $('.wdi-tab-link[data-tab="' + urlTab + '"]').click();
        }
        
        // Asegurar que todos los campos estén visibles cuando se envía el formulario
        $('form.wdi-tabs-content').on('submit', function(e) {
            console.log('Formulario enviado - mostrando todos los campos');
            $('.wdi-tab-panel').each(function() {
                $(this).css({
                    'position': 'static',
                    'left': 'auto',
                    'top': 'auto',
                    'visibility': 'visible',
                    'opacity': '1',
                    'display': 'block'
                });
            });
        });
        // --- FIN: Funcionalidad de Pestañas de Configuración ---
        
        // --- INICIO: Funcionalidad de Campos de Spotify ---
        // Función para mostrar/ocultar campos de ancho y alto
        function toggleSpotifyFields() {
            var isCompact = $('#discogs-importer_options\\[spotify_design\\]').filter(':checked').val() === 'compact';
            if (isCompact) {
                $('#spotify-width-row').show();
                $('#spotify-height-row').show();
            } else {
                $('#spotify-width-row').hide();
                $('#spotify-height-row').hide();
            }
        }
        
        // Ejecutar al cargar la página
        toggleSpotifyFields();
        
        // Ejecutar cuando cambie el radio button
        $('input[name="discogs-importer_options[spotify_design]"]').change(toggleSpotifyFields);
        // --- FIN: Funcionalidad de Campos de Spotify ---
        
    });

})( jQuery );






