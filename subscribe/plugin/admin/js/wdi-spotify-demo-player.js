/**
 * Reproductor de Spotify con previews de 30 segundos (sin login)
 */
jQuery(document).ready(function($) {
    
    // Función para crear el HTML del reproductor
    window.createSpotifyDemoPlayerHTML = function(albumData, tracksData) {
        const albumName = albumData.name || 'Álbum desconocido';
        const artistName = albumData.artists[0].name || 'Artista desconocido';
        const albumImage = albumData.images[0].url || '';
        const spotifyUrl = albumData.external_urls.spotify || '#';
        
        // Generar datos de la playlist
        const playlistData = [];
        tracksData.slice(0, 10).forEach(function(track, index) {
            const trackName = track.name || 'Canción desconocida';
            const trackDuration = track.duration_ms ? 
                Math.floor(track.duration_ms / 60000) + ':' + 
                String(Math.floor((track.duration_ms % 60000) / 1000)).padStart(2, '0') : '0:00';
            const previewUrl = track.preview_url || '';
            
            // Si no hay preview_url, usar un audio de ejemplo
            let audioUrl = previewUrl;
            if (!audioUrl) {
                // Usar diferentes audios de ejemplo para variedad
                const demoAudios = [
                    'https://www.soundjay.com/misc/sounds/bell-ringing-05.wav',
                    'https://www.soundjay.com/misc/sounds/bell-ringing-06.wav',
                    'https://www.soundjay.com/misc/sounds/bell-ringing-07.wav',
                    'https://www.soundjay.com/misc/sounds/bell-ringing-08.wav',
                    'https://www.soundjay.com/misc/sounds/bell-ringing-09.wav'
                ];
                audioUrl = demoAudios[index % demoAudios.length];
            }
            
            playlistData.push({
                title: trackName,
                artist: artistName,
                duration: trackDuration,
                audioUrl: audioUrl,
                hasPreview: !!previewUrl,
                spotifyUrl: track.external_urls ? track.external_urls.spotify : ''
            });
        });
        
        const playerId = 'wdi-demo-player-' + Date.now();
        
        let html = '<div class="wdi-spotify-demo-player" id="' + playerId + '" style="background: #191414; color: white; border-radius: 12px; max-width: 400px; font-family: Arial, sans-serif; box-shadow: 0 8px 32px rgba(0,0,0,0.3); overflow: hidden; margin: 20px 0;">';
        
        // Header del álbum
        html += '<div class="wdi-player-header" style="background: linear-gradient(135deg, #1db954 0%, #1ed760 100%); padding: 20px; text-align: center;">';
        html += '<h2 style="margin: 0; font-size: 24px; font-weight: bold;">🎵 ' + albumName + '</h2>';
        html += '<p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">' + artistName + '</p>';
        html += '<p style="margin: 5px 0 0 0; opacity: 0.8; font-size: 12px;">Preview de 30 segundos</p>';
        html += '</div>';
        
        // Área de reproducción actual
        html += '<div class="wdi-now-playing" style="padding: 20px; background: #282828; text-align: center;">';
        html += '<div class="wdi-album-art" style="width: 200px; height: 200px; border-radius: 8px; margin: 0 auto 20px; background: #333; display: flex; align-items: center; justify-content: center; font-size: 48px;">';
        if (albumImage) {
            html += '<img src="' + albumImage + '" alt="' + albumName + '" style="width: 100%; height: 100%; border-radius: 8px; object-fit: cover;">';
        } else {
            html += '🎵';
        }
        html += '</div>';
        
        html += '<div class="wdi-song-title" style="font-size: 20px; font-weight: bold; margin-bottom: 5px;">Selecciona una canción</div>';
        html += '<div class="wdi-song-artist" style="font-size: 16px; color: #b3b3b3; margin-bottom: 20px;">de la lista de abajo</div>';
        
        // Barra de progreso
        html += '<div class="wdi-progress-container" style="margin: 20px 0;">';
        html += '<div class="wdi-progress-bar" style="width: 100%; height: 6px; background: #404040; border-radius: 3px; overflow: hidden; cursor: pointer;">';
        html += '<div class="wdi-progress" style="height: 100%; background: #1db954; width: 0%; transition: width 0.1s ease;"></div>';
        html += '</div>';
        html += '<div class="wdi-time-info" style="display: flex; justify-content: space-between; font-size: 12px; color: #b3b3b3; margin-top: 5px;">';
        html += '<span class="wdi-current-time">0:00</span>';
        html += '<span class="wdi-duration">0:30</span>';
        html += '</div>';
        html += '</div>';
        
        // Controles
        html += '<div class="wdi-controls" style="display: flex; justify-content: center; align-items: center; gap: 20px; margin: 20px 0;">';
        html += '<button class="wdi-control-btn wdi-prev-btn" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 10px; border-radius: 50%; transition: all 0.2s ease;">⏮️</button>';
        html += '<button class="wdi-control-btn wdi-play-btn" style="background: #1db954; color: white; font-size: 32px; cursor: pointer; padding: 10px; border-radius: 50%; width: 60px; height: 60px; border: none; transition: all 0.2s ease;">▶️</button>';
        html += '<button class="wdi-control-btn wdi-next-btn" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 10px; border-radius: 50%; transition: all 0.2s ease;">⏭️</button>';
        html += '</div>';
        html += '</div>';
        
        // Control de volumen
        html += '<div class="wdi-volume-container" style="padding: 0 20px 20px; display: flex; align-items: center; gap: 10px;">';
        html += '<span style="color: #b3b3b3; font-size: 16px;">🔊</span>';
        html += '<div class="wdi-volume-bar" style="flex: 1; height: 4px; background: #404040; border-radius: 2px; cursor: pointer;">';
        html += '<div class="wdi-volume-progress" style="height: 100%; background: #1db954; width: 70%; border-radius: 2px;"></div>';
        html += '</div>';
        html += '</div>';
        
        // Lista de reproducción
        html += '<div class="wdi-playlist" style="background: #191414; max-height: 300px; overflow-y: auto;">';
        playlistData.forEach(function(track, index) {
            const activeClass = index === 0 ? ' wdi-active' : '';
            const previewIcon = track.hasPreview ? '🎵' : '🔊';
            
            html += '<div class="wdi-playlist-item' + activeClass + '" data-index="' + index + '" style="display: flex; align-items: center; padding: 12px 20px; border-bottom: 1px solid #333; cursor: pointer; transition: background 0.2s ease;">';
            html += '<span class="wdi-playlist-number" style="color: #b3b3b3; font-size: 14px; width: 30px;">' + (index + 1) + '</span>';
            html += '<div class="wdi-playlist-info" style="flex: 1; margin-left: 12px;">';
            html += '<div class="wdi-playlist-title" style="color: white; font-size: 14px; font-weight: 500;">' + track.title + '</div>';
            html += '<div class="wdi-playlist-artist" style="color: #b3b3b3; font-size: 12px; margin-top: 2px;">' + track.artist + ' ' + previewIcon + '</div>';
            html += '</div>';
            html += '<span class="wdi-playlist-duration" style="color: #b3b3b3; font-size: 12px;">0:30</span>';
            html += '</div>';
        });
        html += '</div>';
        
        // Enlace a Spotify
        html += '<div style="text-align: center; padding: 20px; background: #191414;">';
        html += '<a href="' + spotifyUrl + '" target="_blank" style="background: #1db954; color: white; text-decoration: none; padding: 10px 20px; border-radius: 20px; font-size: 14px; font-weight: bold;">Abrir en Spotify</a>';
        html += '</div>';
        
        html += '</div>';
        
        // Inicializar el reproductor
        setTimeout(function() {
            initDemoPlayer(playlistData, playerId);
        }, 100);
        
        return html;
    };
    
    // Función para inicializar el reproductor demo
    function initDemoPlayer(playlistData, playerId) {
        let currentTrack = 0;
        let isPlaying = false;
        let audio = null;
        let progressInterval = null;
        let currentVolume = 0.7;
        
        function loadTrack(index) {
            currentTrack = index;
            const track = playlistData[currentTrack];
            
            // Actualizar UI
            $('.wdi-song-title').text(track.title);
            $('.wdi-song-artist').text(track.artist + (track.hasPreview ? ' 🎵' : ' 🔊'));
            $('.wdi-duration').text('0:30');
            
            // Actualizar lista
            $('.wdi-playlist-item').removeClass('wdi-active');
            $('.wdi-playlist-item[data-index="' + index + '"]').addClass('wdi-active');
            
            // Cargar audio
            if (audio) {
                audio.pause();
                audio = null;
            }
            
            audio = new Audio(track.audioUrl);
            audio.volume = currentVolume;
            
            // Configurar eventos del audio
            audio.addEventListener('loadedmetadata', function() {
                $('.wdi-duration').text('0:30');
            });
            
            audio.addEventListener('ended', function() {
                // Auto-siguiente después de 30 segundos
                nextTrack();
            });
            
            audio.addEventListener('error', function(e) {
                console.error('Error cargando audio:', e);
                $('.wdi-song-artist').text(track.artist + ' ❌ Error');
            });
            
            // Si estaba reproduciéndose, continuar
            if (isPlaying) {
                play();
            }
        }
        
        function play() {
            if (audio) {
                audio.play().then(function() {
                    isPlaying = true;
                    $('.wdi-play-btn').text('⏸️');
                    startProgressUpdate();
                }).catch(function(error) {
                    console.error('Error al reproducir:', error);
                    $('.wdi-song-artist').text(playlistData[currentTrack].artist + ' ❌ Error');
                });
            }
        }
        
        function pause() {
            if (audio) {
                audio.pause();
                isPlaying = false;
                $('.wdi-play-btn').text('▶️');
                stopProgressUpdate();
            }
        }
        
        function nextTrack() {
            currentTrack = (currentTrack + 1) % playlistData.length;
            loadTrack(currentTrack);
        }
        
        function prevTrack() {
            currentTrack = (currentTrack - 1 + playlistData.length) % playlistData.length;
            loadTrack(currentTrack);
        }
        
        function startProgressUpdate() {
            progressInterval = setInterval(updateProgress, 100);
        }
        
        function stopProgressUpdate() {
            if (progressInterval) {
                clearInterval(progressInterval);
                progressInterval = null;
            }
        }
        
        function updateProgress() {
            if (audio && audio.duration) {
                const progressPercent = (audio.currentTime / audio.duration) * 100;
                $('.wdi-progress').css('width', progressPercent + '%');
                $('.wdi-current-time').text(formatTime(audio.currentTime));
            }
        }
        
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return mins + ':' + secs.toString().padStart(2, '0');
        }
        
        // Event listeners
        $('.wdi-play-btn').click(function() {
            if (isPlaying) pause(); else play();
        });
        
        $('.wdi-prev-btn').click(prevTrack);
        $('.wdi-next-btn').click(nextTrack);
        
        $('.wdi-playlist-item').click(function() {
            loadTrack(parseInt($(this).data('index')));
        });
        
        $('.wdi-progress-bar').click(function(e) {
            if (audio && audio.duration) {
                const rect = this.getBoundingClientRect();
                const clickX = e.clientX - rect.left;
                const width = rect.width;
                const clickPercent = clickX / width;
                audio.currentTime = clickPercent * audio.duration;
            }
        });
        
        $('.wdi-volume-bar').click(function(e) {
            const rect = this.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const width = rect.width;
            currentVolume = Math.max(0, Math.min(1, clickX / width));
            if (audio) audio.volume = currentVolume;
            $('.wdi-volume-progress').css('width', (currentVolume * 100) + '%');
        });
        
        // Inicializar con la primera canción
        loadTrack(0);
        
        console.log('Reproductor demo de Spotify inicializado correctamente');
    }
});





