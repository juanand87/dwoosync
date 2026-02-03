<?php
/**
 * Página principal del sitio de suscripciones dwoosync
 * 
 * @package dwoosync
 * @version 1.0.0
 */

// Incluir configuración
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Iniciar sesión
startSecureSession();

// Verificar si el usuario está logueado
$isLoggedIn = isLoggedIn();

// Detectar idioma - Inglés por defecto
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
$currentLang = $_GET['lang'] ?? ($_SESSION['selected_language'] ?? 'en');
$isEnglish = ($currentLang === 'en');

// Guardar idioma seleccionado en sesión
if (isset($_GET['lang'])) {
    $_SESSION['selected_language'] = $_GET['lang'];
}

// Obtener planes de suscripción
$plans = getSubscriptionPlans($currentLang);

// Función para traducir texto
function t($spanish, $english) {
    global $isEnglish;
    return $isEnglish ? $english : $spanish;
}

?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('dwoosync - Suscripciones API', 'dwoosync - API Subscriptions'); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo t('Integra Discogs con WooCommerce. Sincroniza catálogos de música, vinilos y discos automáticamente. Plugin profesional para tiendas de música online.', 'Integrate Discogs with WooCommerce. Automatically sync music catalogs, vinyl records and discs. Professional plugin for online music stores.'); ?>">
    <meta name="keywords" content="<?php echo t('discogs woocommerce, plugin música, sincronización catálogo, tienda vinilos, ecommerce música, api discogs', 'discogs woocommerce, music plugin, catalog sync, vinyl store, music ecommerce, discogs api'); ?>">
    <meta name="author" content="dwoosync">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo t('dwoosync - Suscripciones API', 'dwoosync - API Subscriptions'); ?>">
    <meta property="og:description" content="<?php echo t('Integra Discogs con WooCommerce. Sincroniza catálogos de música automáticamente.', 'Integrate Discogs with WooCommerce. Automatically sync music catalogs.'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://dwoosync.com">
    <meta property="og:site_name" content="dwoosync">
    <meta property="og:locale" content="<?php echo $currentLang === 'en' ? 'en_US' : 'es_ES'; ?>">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo t('dwoosync - Suscripciones API', 'dwoosync - API Subscriptions'); ?>">
    <meta name="twitter:description" content="<?php echo t('Integra Discogs con WooCommerce. Sincroniza catálogos de música automáticamente.', 'Integrate Discogs with WooCommerce. Automatically sync music catalogs.'); ?>">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://dwoosync.com">
    
    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "dwoosync",
        "description": "<?php echo t('Plugin para integrar Discogs con WooCommerce y sincronizar catálogos de música automáticamente', 'Plugin to integrate Discogs with WooCommerce and automatically sync music catalogs'); ?>",
        "url": "https://dwoosync.com",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "WordPress",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        },
        "publisher": {
            "@type": "Organization",
            "name": "dwoosync",
            "url": "https://dwoosync.com"
        }
    }
    </script>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
    .spinning-disc {
        animation: spin 3s linear infinite;
        display: inline-block;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .nav-logo h2 {
        font-family: 'Space Grotesk', sans-serif;
        font-weight: 700;
        letter-spacing: 2px;
        background: linear-gradient(135deg, #1db954, #10b981, #059669, #047857);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        text-shadow: 0 0 30px rgba(29, 185, 84, 0.4);
        font-size: 1.8rem;
    }
    
    .spotify-tooltip-trigger {
        position: relative;
        cursor: pointer;
        color: #1db954 !important;
        font-weight: 500;
        text-decoration: underline;
    }
    
    .spotify-tooltip {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #1db954;
        color: white;
        padding: 15px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.3);
        z-index: 9999;
        margin-bottom: 10px;
        min-width: 300px;
        text-align: center;
        pointer-events: none;
    }
    
    .spotify-tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 8px solid transparent;
        border-top-color: #1db954;
    }
    
    
    .spotify-tooltip img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        margin-top: 10px;
    }
    
    .spotify-tooltip-text {
        font-size: 14px;
        margin-bottom: 10px;
        font-weight: 500;
    }
    </style>
    
    <?php renderWhatsAppStyles(); ?>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <h2><i class="fas fa-compact-disc spinning-disc"></i> DWooSync</h2>
                </div>
                
                <!-- Botón hamburguesa para móvil -->
                <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation">
                    <span class="hamburger"></span>
                    <span class="hamburger"></span>
                    <span class="hamburger"></span>
                </button>
                
                <div class="nav-menu" id="nav-menu">
                    <a href="#inicio" class="nav-link"><?php echo t('Inicio', 'Home'); ?></a>
                    <a href="#plans" class="nav-link"><?php echo t('Planes', 'Plans'); ?></a>
                    <a href="#features" class="nav-link"><?php echo t('Características', 'Features'); ?></a>
                    <a href="pages/screenshots.php" class="nav-link"><?php echo t('Screenshots', 'Screenshots'); ?></a>
                    <a href="pages/contact.php" class="nav-link"><?php echo t('Contacto', 'Contact'); ?></a>
                    <?php if ($isLoggedIn): ?>
                        <a href="pages/dashboard.php" class="nav-link btn-login"><?php echo t('Ver Cuenta', 'View Account'); ?></a>
                    <?php else: ?>
                        <a href="pages/login.php" class="nav-link btn-login"><?php echo t('Iniciar Sesión', 'Login'); ?></a>
                    <?php endif; ?>
                    <div class="language-dropdown" style="position: relative; margin-left: 10px;">
                        <button class="nav-link" style="background: #1db954; color: white; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <?php if ($isEnglish): ?>
                                <span style="font-size: 1.2em;">🇬🇧</span>
                            <?php else: ?>
                                <span style="font-size: 1.2em;">🇪🇸</span>
                            <?php endif; ?>
                            <span><?php echo $isEnglish ? 'EN' : 'ES'; ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.8em;"></i>
                        </button>
                        <div class="language-menu" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1000; min-width: 140px;">
                            <a href="?lang=es" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; border-bottom: 1px solid #f3f4f6; <?php echo !$isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                                <span style="font-size: 1.1em; margin-right: 8px;">🇪🇸</span> Español
                            </a>
                            <a href="?lang=en" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; <?php echo $isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                                <span style="font-size: 1.1em; margin-right: 8px;">🇬🇧</span> English
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section id="inicio" class="hero">
        <div class="hero-container">
            <div class="hero-content">
                <h1 class="hero-title">
                    <?php echo t('Plugin API Discogs para <span class="highlight">WooCommerce</span>', 'Discogs API Plugin for <span class="highlight">WooCommerce</span>'); ?>
                </h1>
                <p class="hero-subtitle">
                    <?php echo t('El mejor plugin para tu tienda de música. Importa datos de productos desde Discogs directamente a tu tienda WooCommerce - WordPress. Fácil, rápido y confiable.', 'The best plugin for your music store. Import product data from Discogs directly to your WooCommerce - WordPress store. Easy, fast and reliable.'); ?>
                </p>
                <div class="hero-buttons">
                    <a href="#plans" class="btn btn-primary btn-large">
                        <i class="fas fa-rocket"></i> <?php echo t('Comenzar Ahora', 'Get Started'); ?>
                    </a>
                    <a href="#" class="btn btn-secondary btn-large" onclick="openVideoModal(); return false;">
                        <i class="fas fa-play"></i> <?php echo t('Ver Demo', 'View Demo'); ?>
                    </a>
                </div>
            </div>
            <div class="hero-image">
                <div class="hero-illustration">
                    <img src="assets/images/sync.png" alt="DwooSync - Sincronización entre Discogs y WooCommerce" style="max-width: 100%; height: auto; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
        <div class="container">
            <div class="section-header">
                <h2><?php echo t('¿Por qué elegir dwoosync?', 'Why choose dwoosync?'); ?></h2>
                <p><?php echo t('El mejor plugin para integrar Discogs con WooCommerce - WordPress', 'The best plugin to integrate Discogs with WooCommerce - WordPress'); ?></p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3><?php echo t('Rápido y Eficiente', 'Fast and Efficient'); ?></h3>
                    <p><?php echo t('Importa miles de productos en segundos con nuestra API optimizada', 'Import thousands of products in seconds with our optimized API'); ?></p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3><?php echo t('Seguro y Confiable', 'Secure and Reliable'); ?></h3>
                    <p><?php echo t('Sistema de licencias robusto con validación en tiempo real', 'Robust license system with real-time validation'); ?></p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3><?php echo t('Fácil de Usar', 'Easy to Use'); ?></h3>
                    <p><?php echo t('Interfaz intuitiva que no requiere conocimientos técnicos', 'Intuitive interface that requires no technical knowledge'); ?></p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3><?php echo t('Soporte 24/7', '24/7 Support'); ?></h3>
                    <p><?php echo t('Equipo de soporte disponible para ayudarte en todo momento', 'Support team available to help you at all times'); ?></p>
                </div>
            </div>
        </div>
    </section>


    <!-- Pricing Section -->
    <section id="plans" class="pricing">
        <div class="container">
            <div class="section-header">
                <h2><?php echo t('Elige tu Plan', 'Choose Your Plan'); ?></h2>
                <p><?php echo t('Planes flexibles para cada necesidad', 'Flexible plans for every need'); ?></p>
            </div>
            <div class="pricing-grid">
                <?php foreach ($plans as $plan): ?>
                <div class="pricing-card <?php echo $plan['featured'] ? 'featured' : ''; ?>">
                    <?php if ($plan['featured']): ?>
                    <div class="pricing-badge"><?php echo t('Más Popular', 'Most Popular'); ?></div>
                    <?php endif; ?>
                    
                    <div class="pricing-header">
                        <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                        <div class="pricing-price">
                            <span class="currency">$</span>
                            <span class="amount"><?php echo $plan['price']; ?></span>
                            <span class="period"><?php echo t('/mes', '/month'); ?></span>
                        </div>
                    </div>
                    
                    <div class="pricing-features">
                        <ul>
                            <?php foreach ($plan['features'] as $feature): ?>
                            <li>
                                <?php 
                                // Determinar si mostrar X basado en el contenido de la característica, no en texto hardcodeado
                                $showX = false;
                                if ($plan['id'] === 'free') {
                                    $showX = (strpos($feature, 'No support') !== false || strpos($feature, 'Sin soporte') !== false || 
                                             strpos($feature, 'Detailed statistics') !== false || strpos($feature, 'Estadística detallada') !== false || 
                                             strpos($feature, 'Spotify Widget') !== false || strpos($feature, 'Widget Spotify') !== false);
                                } elseif ($plan['id'] === 'premium') {
                                    $showX = (strpos($feature, 'Spotify Widget') !== false || strpos($feature, 'Widget Spotify') !== false);
                                }
                                ?>
                                <?php if ($showX): ?>
                                    <i class="fas fa-times" style="color: #dc2626;"></i>
                                <?php else: ?>
                                    <i class="fas fa-check"></i>
                                <?php endif; ?>
                                <?php if ((strpos($feature, 'Spotify Widget') !== false || strpos($feature, 'Widget Spotify') !== false) && $plan['id'] === 'enterprise'): ?>
                                    <span class="spotify-tooltip-trigger" onmouseover="showSpotifyTooltip(this)" onmouseout="hideSpotifyTooltip(this)"><?php echo htmlspecialchars($feature); ?></span>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($feature); ?>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="pricing-footer">
                        <?php if ($isLoggedIn): ?>
                            <!-- Usuario logueado: ir directo a checkout con el plan seleccionado -->
                            <a href="pages/checkout.php?plan=<?php echo $plan['id']; ?>&lang=<?php echo $currentLang; ?>" 
                               class="btn <?php echo $plan['featured'] ? 'btn-primary' : 'btn-outline'; ?> btn-block">
                                <?php echo $plan['price'] == 0 ? t('Comenzar Gratis', 'Start Free') : t('Suscribirse', 'Subscribe'); ?>
                            </a>
                        <?php else: ?>
                            <!-- Usuario no logueado: ir a signup con el plan seleccionado -->
                            <a href="pages/signup.php?plan=<?php echo $plan['id']; ?>&lang=<?php echo $currentLang; ?>" 
                               class="btn <?php echo $plan['featured'] ? 'btn-primary' : 'btn-outline'; ?> btn-block">
                                <?php echo $plan['price'] == 0 ? t('Comenzar Gratis', 'Start Free') : t('Suscribirse', 'Subscribe'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>dwoosync</h3>
                    <p><?php echo t('El mejor plugin para integrar Discogs con WooCommerce - WordPress', 'The best plugin to integrate Discogs with WooCommerce - WordPress'); ?></p>
                </div>
                <div class="footer-section">
                    <h4><?php echo t('Enlaces', 'Links'); ?></h4>
                    <ul>
                        <li><a href="#plans"><?php echo t('Planes', 'Plans'); ?></a></li>
                        <li><a href="#features"><?php echo t('Características', 'Features'); ?></a></li>
                        <li><a href="pages/screenshots.php"><?php echo t('Screenshots', 'Screenshots'); ?></a></li>
                        <?php if ($isLoggedIn): ?>
                            <li><a href="pages/dashboard.php"><?php echo t('Ver Cuenta', 'View Account'); ?></a></li>
                        <?php else: ?>
                            <li><a href="pages/login.php"><?php echo t('Iniciar Sesión', 'Login'); ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4><?php echo t('Soporte', 'Support'); ?></h4>
                    <ul>
                        <li><a href="mailto:support@discogsync.com">support@discogsync.com</a></li>
                        <li><a href="#"><?php echo t('Documentación', 'Documentation'); ?></a></li>
                        <li><a href="#"><?php echo t('FAQ', 'FAQ'); ?></a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> dwoosync. <?php echo t('Todos los derechos reservados.', 'All rights reserved.'); ?></p>
            </div>
        </div>
    </footer>

    <script src="assets/js/script.js"></script>
    
    <script>
    // Funcionalidad del dropdown de idioma
    document.addEventListener('DOMContentLoaded', function() {
        const languageDropdown = document.querySelector('.language-dropdown');
        const languageButton = languageDropdown.querySelector('button');
        const languageMenu = languageDropdown.querySelector('.language-menu');
        
        languageButton.addEventListener('click', function(e) {
            e.stopPropagation();
            languageMenu.style.display = languageMenu.style.display === 'none' ? 'block' : 'none';
        });
        
        // Cerrar dropdown al hacer clic fuera
        document.addEventListener('click', function() {
            languageMenu.style.display = 'none';
        });
        
        // Prevenir cierre al hacer clic dentro del dropdown
        languageMenu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
    
    function showSpotifyTooltip(element) {
        console.log('showSpotifyTooltip llamado');
        
        // Remover tooltip existente si hay uno
        const existingTooltip = document.querySelector('.spotify-tooltip');
        if (existingTooltip) {
            existingTooltip.remove();
        }
        
        // Crear nuevo tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'spotify-tooltip';
        tooltip.style.cssText = `
            position: fixed !important;
            top: 40% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            background: #1db954 !important;
            color: white !important;
            padding: 20px !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3) !important;
            z-index: 99999 !important;
            min-width: 470px !important;
            text-align: center !important;
            font-family: Arial, sans-serif !important;
        `;
        // Obtener el idioma actual desde PHP
        const currentLang = '<?php echo $currentLang; ?>';
        const isEnglish = currentLang === 'en';
        
        tooltip.innerHTML = `
            <div style="position: relative;">
                <div style="
                    background: #1ed760;
                    color: white;
                    font-size: 18px;
                    font-weight: bold;
                    padding: 10px 15px;
                    margin: -20px -20px 0px -20px;
                    border-radius: 12px 12px 0px 0px;
                ">🎵 ${isEnglish ? 'Spotify Widget' : 'Widget Spotify'}</div>
                <img src="assets/images/spotify-widget.png" alt="${isEnglish ? 'Spotify Widget' : 'Widget Spotify'}" style="
                    width: 450px;
                    height: 270px;
                    border-radius: 0px 0px 8px 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                    margin-top: 0px;
                    display: block;
                " onerror="this.style.display='none'">
            </div>
        `;
        
        // Agregar tooltip al DOM
        document.body.appendChild(tooltip);
        console.log('Tooltip agregado al DOM');
        
        // Auto-ocultar después de 3 segundos
        setTimeout(function() {
            if (tooltip.parentNode) {
                tooltip.remove();
            }
        }, 3000);
    }
    
    function hideSpotifyTooltip(element) {
        const tooltip = document.querySelector('.spotify-tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }
    
    // Menú móvil
    document.addEventListener('DOMContentLoaded', function() {
        const navToggle = document.getElementById('nav-toggle');
        const navMenu = document.getElementById('nav-menu');
        
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', function() {
                navMenu.classList.toggle('active');
                navToggle.classList.toggle('active');
            });
            
            // Cerrar menú al hacer clic en un enlace
            const navLinks = navMenu.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    navMenu.classList.remove('active');
                    navToggle.classList.remove('active');
                });
            });
            
            // Cerrar menú al hacer clic fuera
            document.addEventListener('click', function(event) {
                if (!navToggle.contains(event.target) && !navMenu.contains(event.target)) {
                    navMenu.classList.remove('active');
                    navToggle.classList.remove('active');
                }
            });
        }
    });
    
    // Funciones para el modal de video
    function openVideoModal() {
        const modal = document.getElementById('videoModal');
        const video = document.getElementById('demoVideo');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; // Prevenir scroll del body
        
        // Configurar y reproducir el video cuando se abre el modal
        if (video) {
            video.currentTime = 0; // Reiniciar desde el principio
            video.volume = 0.7; // Volumen al 70% (0.0 a 1.0)
            
            // Intentar reproducir con sonido
            video.play().catch(function(error) {
                // Si falla el autoplay con sonido, intentar sin sonido
                console.log('Autoplay con sonido bloqueado, intentando sin sonido:', error);
                video.muted = true;
                video.play().catch(function(muteError) {
                    console.log('Autoplay completamente bloqueado:', muteError);
                });
            });
        }
    }
    
    function closeVideoModal() {
        const modal = document.getElementById('videoModal');
        const video = document.getElementById('demoVideo');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restaurar scroll del body
        
        // Pausar el video cuando se cierra el modal
        if (video) {
            video.pause();
        }
    }
    
    // Cerrar modal al hacer clic fuera del video
    window.onclick = function(event) {
        const modal = document.getElementById('videoModal');
        if (event.target === modal) {
            closeVideoModal();
        }
    }
    
    // Cerrar modal con la tecla Escape
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeVideoModal();
        }
    });
    </script>

    <!-- Modal de Video Demo -->
    <div id="videoModal" class="video-modal">
        <div class="video-modal-content">
            <div class="video-container">
                <button class="video-close-btn" onclick="closeVideoModal()" title="<?php echo t('Cerrar', 'Close'); ?>">
                    <i class="fas fa-times"></i>
                </button>
                <video id="demoVideo" controls>
                    <source src="media/dwosync.mp4" type="video/mp4">
                    <?php echo t('Tu navegador no soporta videos HTML5.', 'Your browser does not support HTML5 video.'); ?>
                </video>
            </div>
        </div>
    </div>

    <style>
    /* Estilos para el modal de video */
    .video-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        z-index: 9999;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.3s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    .video-modal-content {
        position: relative;
        max-width: 90vw;
        max-height: 90vh;
        width: 1000px;
        height: auto;
    }
    
    .video-container {
        position: relative;
        width: 100%;
        height: 100%;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
    }
    
    .video-close-btn {
        position: absolute;
        top: -40px;
        right: 0;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        cursor: pointer;
        font-size: 18px;
        color: #333;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        z-index: 10001;
    }
    
    .video-close-btn:hover {
        background: white;
        transform: scale(1.1);
    }
    
    #demoVideo {
        width: 100%;
        height: auto;
        border-radius: 12px;
        outline: none;
    }
    
    /* Responsive para el modal */
    @media (max-width: 768px) {
        .video-modal-content {
            max-width: 95vw;
            max-height: 80vh;
        }
        
        .video-close-btn {
            top: -35px;
            width: 30px;
            height: 30px;
            font-size: 16px;
        }
    }
    </style>
    
    <?php renderWhatsAppButton($currentLang); ?>
    <?php renderWhatsAppScript($currentLang); ?>
</body>
</html>