/**
 * Reproductor de Spotify real usando Web Playback SDK
 */
jQuery(document).ready(function($) {
    
    // Configuración de Spotify
    const SPOTIFY_CLIENT_ID = '1b60339de498414b8bc26de183077da4';
    const SPOTIFY_REDIRECT_URI = window.location.origin + '/spotify-callback';
    
    let accessToken = null;
    let deviceId = null;
    let player = null;
    let currentTrack = null;
    let isPlaying = false;
    
    // Función para obtener token de acceso
    function getAccessToken() {
        return new Promise((resolve, reject) => {
            // Verificar si ya tenemos un token válido
            const storedToken = localStorage.getItem('spotify_access_token');
            const tokenExpiry = localStorage.getItem('spotify_token_expiry');
            
            if (storedToken && tokenExpiry && Date.now() < parseInt(tokenExpiry)) {
                accessToken = storedToken;
                resolve(storedToken);
                return;
            }
            
            // Solicitar autorización
            const scopes = [
                'streaming',
                'user-read-email',
                'user-read-private',
                'user-read-playback-state',
                'user-modify-playback-state',
                'user-read-currently-playing'
            ].join(' ');
            
            const authUrl = 'https://accounts.spotify.com/authorize?' + 
                'response_type=token&' +
                'client_id=' + SPOTIFY_CLIENT_ID + '&' +
                'scope=' + encodeURIComponent(scopes) + '&' +
                'redirect_uri=' + encodeURIComponent(SPOTIFY_REDIRECT_URI);
            
            // Abrir ventana de autorización
            const authWindow = window.open(authUrl, 'Spotify Auth', 'width=500,height=600');
            
            // Escuchar el mensaje de respuesta
            const messageListener = function(event) {
                if (event.origin !== window.location.origin) return;
                
                if (event.data.type === 'SPOTIFY_AUTH_SUCCESS') {
                    accessToken = event.data.access_token;
                    const expiresIn = event.data.expires_in || 3600;
                    const expiryTime = Date.now() + (expiresIn * 1000);
                    
                    localStorage.setItem('spotify_access_token', accessToken);
                    localStorage.setItem('spotify_token_expiry', expiryTime.toString());
                    
                    window.removeEventListener('message', messageListener);
                    authWindow.close();
                    resolve(accessToken);
                } else if (event.data.type === 'SPOTIFY_AUTH_ERROR') {
                    window.removeEventListener('message', messageListener);
                    authWindow.close();
                    reject(new Error(event.data.error));
                }
            };
            
            window.addEventListener('message', messageListener);
            
            // Timeout después de 5 minutos
            setTimeout(() => {
                window.removeEventListener('message', messageListener);
                authWindow.close();
                reject(new Error('Timeout'));
            }, 300000);
        });
    }
    
    // Función para inicializar el Web Playback SDK
    function initializeSpotifyPlayer() {
        return new Promise((resolve, reject) => {
            if (!window.Spotify) {
                // Cargar el SDK de Spotify
                const script = document.createElement('script');
                script.src = 'https://sdk.scdn.co/spotify-player.js';
                script.onload = () => {
                    window.onSpotifyWebPlaybackSDKReady = () => {
                        player = new window.Spotify.Player({
                            name: 'Discogs Importer Player',
                            getOAuthToken: cb => { cb(accessToken); },
                            volume: 0.5
                        });
                        
                        // Event listeners
                        player.addListener('ready', ({ device_id }) => {
                            console.log('Ready with Device ID', device_id);
                            deviceId = device_id;
                            resolve(device_id);
                        });
                        
                        player.addListener('not_ready', ({ device_id }) => {
                            console.log('Device ID has gone offline', device_id);
                        });
                        
                        player.addListener('initialization_error', ({ message }) => {
                            console.error('Failed to initialize', message);
                            reject(new Error(message));
                        });
                        
                        player.addListener('authentication_error', ({ message }) => {
                            console.error('Failed to authenticate', message);
                            reject(new Error(message));
                        });
                        
                        player.addListener('account_error', ({ message }) => {
                            console.error('Failed to validate Spotify account', message);
                            reject(new Error(message));
                        });
                        
                        player.addListener('playback_error', ({ message }) => {
                            console.error('Failed to perform playback', message);
                        });
                        
                        // Estado de reproducción
                        player.addListener('player_state_changed', state => {
                            if (!state) return;
                            
                            currentTrack = state.track_window.current_track;
                            isPlaying = !state.paused;
                            
                            updatePlayerUI(state);
                        });
                        
                        // Conectar al reproductor
                        player.connect();
                    };
                };
                script.onerror = () => reject(new Error('Failed to load Spotify SDK'));
                document.head.appendChild(script);
            } else {
                // SDK ya cargado
                window.onSpotifyWebPlaybackSDKReady();
            }
        });
    }
    
    // Función para actualizar la UI del reproductor
    function updatePlayerUI(state) {
        if (!state || !state.track_window.current_track) return;
        
        const track = state.track_window.current_track;
        const position = state.position;
        const duration = state.duration;
        
        // Actualizar información de la canción
        $('.wdi-song-title').text(track.name);
        $('.wdi-song-artist').text(track.artists[0].name);
        
        // Actualizar imagen del álbum
        if (track.album.images[0]) {
            $('.wdi-album-art img').attr('src', track.album.images[0].url);
        }
        
        // Actualizar progreso
        const progressPercent = (position / duration) * 100;
        $('.wdi-progress').css('width', progressPercent + '%');
        
        // Actualizar tiempo
        $('.wdi-current-time').text(formatTime(position / 1000));
        $('.wdi-duration').text(formatTime(duration / 1000));
        
        // Actualizar botón de play/pause
        if (isPlaying) {
            $('.wdi-play-btn').text('⏸️');
        } else {
            $('.wdi-play-btn').text('▶️');
        }
        
        // Actualizar lista de reproducción
        $('.wdi-playlist-item').removeClass('wdi-active');
        $('.wdi-playlist-item').each(function() {
            const trackName = $(this).find('.wdi-playlist-title').text();
            if (trackName === track.name) {
                $(this).addClass('wdi-active');
            }
        });
    }
    
    // Función para reproducir una canción
    function playTrack(trackUri) {
        if (!deviceId || !accessToken) {
            console.error('Device ID or access token not available');
            return;
        }
        
        const playRequest = {
            uris: [trackUri],
            device_id: deviceId
        };
        
        fetch('https://api.spotify.com/v1/me/player/play', {
            method: 'PUT',
            body: JSON.stringify(playRequest),
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + accessToken
            }
        }).then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
        }).catch(error => {
            console.error('Error playing track:', error);
        });
    }
    
    // Función para pausar/reanudar
    function togglePlayback() {
        if (!deviceId || !accessToken) return;
        
        const action = isPlaying ? 'pause' : 'play';
        
        fetch(`https://api.spotify.com/v1/me/player/${action}`, {
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + accessToken
            }
        }).catch(error => {
            console.error('Error toggling playback:', error);
        });
    }
    
    // Función para siguiente canción
    function nextTrack() {
        if (!deviceId || !accessToken) return;
        
        fetch('https://api.spotify.com/v1/me/player/next', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + accessToken
            }
        }).catch(error => {
            console.error('Error skipping to next track:', error);
        });
    }
    
    // Función para canción anterior
    function previousTrack() {
        if (!deviceId || !accessToken) return;
        
        fetch('https://api.spotify.com/v1/me/player/previous', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + accessToken
            }
        }).catch(error => {
            console.error('Error skipping to previous track:', error);
        });
    }
    
    // Función para formatear tiempo
    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ':' + secs.toString().padStart(2, '0');
    }
    
    // Función para crear el HTML del reproductor
    window.createSpotifyRealPlayerHTML = function(albumData, tracksData) {
        const albumName = albumData.name || 'Álbum desconocido';
        const artistName = albumData.artists[0].name || 'Artista desconocido';
        const albumImage = albumData.images[0].url || '';
        const spotifyUrl = albumData.external_urls.spotify || '#';
        
        let html = '<div class="wdi-spotify-real-player" style="background: #191414; color: white; border-radius: 12px; max-width: 400px; font-family: Arial, sans-serif; box-shadow: 0 8px 32px rgba(0,0,0,0.3); overflow: hidden; margin: 20px 0;">';
        
        // Header del álbum
        html += '<div class="wdi-player-header" style="background: linear-gradient(135deg, #1db954 0%, #1ed760 100%); padding: 20px; text-align: center;">';
        html += '<h2 style="margin: 0; font-size: 24px; font-weight: bold;">🎵 ' + albumName + '</h2>';
        html += '<p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">' + artistName + '</p>';
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
        
        html += '<div class="wdi-song-title" style="font-size: 20px; font-weight: bold; margin-bottom: 5px;">Conectando con Spotify...</div>';
        html += '<div class="wdi-song-artist" style="font-size: 16px; color: #b3b3b3; margin-bottom: 20px;">Iniciando reproductor</div>';
        
        // Barra de progreso
        html += '<div class="wdi-progress-container" style="margin: 20px 0;">';
        html += '<div class="wdi-progress-bar" style="width: 100%; height: 6px; background: #404040; border-radius: 3px; overflow: hidden; cursor: pointer;">';
        html += '<div class="wdi-progress" style="height: 100%; background: #1db954; width: 0%; transition: width 0.1s ease;"></div>';
        html += '</div>';
        html += '<div class="wdi-time-info" style="display: flex; justify-content: space-between; font-size: 12px; color: #b3b3b3; margin-top: 5px;">';
        html += '<span class="wdi-current-time">0:00</span>';
        html += '<span class="wdi-duration">0:00</span>';
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
        html += '<div class="wdi-volume-progress" style="height: 100%; background: #1db954; width: 50%; border-radius: 2px;"></div>';
        html += '</div>';
        html += '</div>';
        
        // Lista de reproducción
        html += '<div class="wdi-playlist" style="background: #191414; max-height: 300px; overflow-y: auto;">';
        tracksData.forEach(function(track, index) {
            const trackName = track.name || 'Canción desconocida';
            const trackDuration = track.duration_ms ? 
                Math.floor(track.duration_ms / 60000) + ':' + 
                String(Math.floor((track.duration_ms % 60000) / 1000)).padStart(2, '0') : '0:00';
            const trackUri = track.uri || '';
            
            html += '<div class="wdi-playlist-item" data-uri="' + trackUri + '" style="display: flex; align-items: center; padding: 12px 20px; border-bottom: 1px solid #333; cursor: pointer; transition: background 0.2s ease;">';
            html += '<span class="wdi-playlist-number" style="color: #b3b3b3; font-size: 14px; width: 30px;">' + (index + 1) + '</span>';
            html += '<div class="wdi-playlist-info" style="flex: 1; margin-left: 12px;">';
            html += '<div class="wdi-playlist-title" style="color: white; font-size: 14px; font-weight: 500;">' + trackName + '</div>';
            html += '<div class="wdi-playlist-artist" style="color: #b3b3b3; font-size: 12px; margin-top: 2px;">' + artistName + '</div>';
            html += '</div>';
            html += '<span class="wdi-playlist-duration" style="color: #b3b3b3; font-size: 12px;">' + trackDuration + '</span>';
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
            initializeSpotifyPlayer().then(() => {
                // Event listeners
                $('.wdi-play-btn').click(togglePlayback);
                $('.wdi-prev-btn').click(previousTrack);
                $('.wdi-next-btn').click(nextTrack);
                
                $('.wdi-playlist-item').click(function() {
                    const trackUri = $(this).data('uri');
                    if (trackUri) {
                        playTrack(trackUri);
                    }
                });
                
                $('.wdi-progress-bar').click(function(e) {
                    if (!deviceId || !accessToken || !currentTrack) return;
                    
                    const rect = this.getBoundingClientRect();
                    const clickX = e.clientX - rect.left;
                    const width = rect.width;
                    const clickPercent = clickX / width;
                    
                    // Obtener duración actual y calcular nueva posición
                    fetch('https://api.spotify.com/v1/me/player', {
                        headers: {
                            'Authorization': 'Bearer ' + accessToken
                        }
                    }).then(response => response.json())
                    .then(data => {
                        if (data.item) {
                            const newPosition = Math.floor(data.item.duration_ms * clickPercent);
                            
                            fetch('https://api.spotify.com/v1/me/player/seek?position_ms=' + newPosition, {
                                method: 'PUT',
                                headers: {
                                    'Authorization': 'Bearer ' + accessToken
                                }
                            });
                        }
                    });
                });
                
                $('.wdi-volume-bar').click(function(e) {
                    if (!deviceId || !accessToken) return;
                    
                    const rect = this.getBoundingClientRect();
                    const clickX = e.clientX - rect.left;
                    const width = rect.width;
                    const volume = Math.max(0, Math.min(1, clickX / width));
                    
                    fetch('https://api.spotify.com/v1/me/player/volume?volume_percent=' + Math.floor(volume * 100), {
                        method: 'PUT',
                        headers: {
                            'Authorization': 'Bearer ' + accessToken
                        }
                    });
                    
                    $('.wdi-volume-progress').css('width', (volume * 100) + '%');
                });
                
                console.log('Reproductor de Spotify inicializado correctamente');
            }).catch(error => {
                console.error('Error inicializando reproductor de Spotify:', error);
                $('.wdi-song-title').text('Error: ' + error.message);
                $('.wdi-song-artist').text('No se pudo conectar con Spotify');
            });
        }, 100);
        
        return html;
    };
});





