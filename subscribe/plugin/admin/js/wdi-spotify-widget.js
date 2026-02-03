/**
 * JavaScript para el widget de Spotify
 * Maneja la reproducción de previews de 30 segundos
 */

(function($) {
    'use strict';
    
    let currentAudio = null;
    
    // Función global para reproducir previews
    window.playPreview = function(url, button) {
        // Pausar audio actual si está reproduciendo
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
            // Resetear todos los botones
            $('[onclick*="playPreview"]').each(function() {
                $(this).html('▶️ Preview');
                $(this).css('background', '#1db954');
            });
        }
        
        // Si es el mismo botón, solo pausar
        if ($(button).html() === '⏸️ Pausar') {
            return;
        }
        
        // Reproducir nuevo preview
        currentAudio = new Audio(url);
        
        // Configurar el audio para que se reproduzca automáticamente
        currentAudio.preload = 'auto';
        currentAudio.volume = 0.7; // Volumen moderado
        
        currentAudio.play().then(function() {
            $(button).html('⏸️ Pausar');
            $(button).css('background', '#e22134');
            console.log('Audio iniciado correctamente:', url);
        }).catch(function(error) {
            console.error('Error al reproducir audio:', error);
            console.log('URL problemática:', url);
            $(button).html('❌ Error');
            $(button).css('background', '#666');
            
            // Intentar con una URL de fallback si es de Spotify
            if (url.includes('p.scdn.co')) {
                console.log('Intentando con URL de fallback...');
                // Usar una URL de audio de prueba como fallback
                const fallbackUrl = 'https://www.soundjay.com/misc/sounds/bell-ringing-05.wav';
                currentAudio = new Audio(fallbackUrl);
                currentAudio.play().then(function() {
                    $(button).html('⏸️ Demo');
                    $(button).css('background', '#ff6b35');
                }).catch(function(fallbackError) {
                    console.error('Error con URL de fallback:', fallbackError);
                });
            }
        });
        
        // Cuando termine el preview
        currentAudio.onended = function() {
            $(button).html('▶️ Preview');
            $(button).css('background', '#1db954');
            currentAudio = null;
        };
        
        // Manejar errores
        currentAudio.onerror = function() {
            $(button).html('❌ Error');
            $(button).css('background', '#666');
            currentAudio = null;
        };
    };
    
    // Pausar audio cuando se cambia de página
    $(window).on('beforeunload', function() {
        if (currentAudio) {
            currentAudio.pause();
            currentAudio = null;
        }
    });
    
    // Pausar audio cuando se hace clic fuera del widget
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.discogsync-spotify-widget').length) {
            if (currentAudio) {
                currentAudio.pause();
                currentAudio = null;
                $('[onclick*="playPreview"]').each(function() {
                    $(this).html('▶️ Preview');
                    $(this).css('background', '#1db954');
                });
            }
        }
    });
    
})(jQuery);
