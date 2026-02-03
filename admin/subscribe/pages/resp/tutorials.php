<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

startSecureSession();

// Detectar idioma del navegador
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'es', 0, 2);
$currentLang = $_GET['lang'] ?? ($_SESSION['selected_language'] ?? $browserLang);
$isEnglish = ($currentLang === 'en');

// Guardar idioma seleccionado en sesión
if (isset($_GET['lang'])) {
    $_SESSION['selected_language'] = $_GET['lang'];
}

// Función para traducir texto
function t($spanish, $english) {
    global $isEnglish;
    return $isEnglish ? $english : $spanish;
}

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$subscriber_id = $_SESSION['subscriber_id'];
$license_key = $_SESSION['license_key'] ?? '';

// Obtener información del suscriptor
try {
    $db = getDatabase();
    $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
    $stmt->execute([$subscriber_id]);
    $subscriber_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error obteniendo datos del suscriptor: " . $e->getMessage());
    $subscriber_data = [];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Tutoriales', 'Tutorials'); ?> - dwoosync</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo t('Tutoriales y guías para usar dwoosync. Aprende a integrar Discogs con WooCommerce paso a paso.', 'Tutorials and guides for using dwoosync. Learn to integrate Discogs with WooCommerce step by step.'); ?>">
    <meta name="keywords" content="<?php echo t('tutoriales dwoosync, guías plugin, integración discogs woocommerce, video tutoriales', 'dwoosync tutorials, plugin guides, discogs woocommerce integration, video tutorials'); ?>">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo t('Tutoriales - dwoosync', 'Tutorials - dwoosync'); ?>">
    <meta property="og:description" content="<?php echo t('Tutoriales y guías para usar dwoosync con Discogs y WooCommerce.', 'Tutorials and guides for using dwoosync with Discogs and WooCommerce.'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://dwoosync.com/subscribe/pages/tutorials.php">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://dwoosync.com/subscribe/pages/tutorials.php">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .videos-section {
            margin: 40px 0;
            padding: 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .videos-section h2 {
            color: #1f2937;
            font-size: 1.8rem;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .video-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .video-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }
        
        .video-thumbnail {
            width: 100%;
            height: 180px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            position: relative;
            overflow: hidden;
        }
        
        .video-thumbnail::before {
            content: '▶';
            font-size: 3rem;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .video-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }
        
        .video-description {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .video-duration {
            background: #3b82f6;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .video-placeholder {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
            font-style: italic;
        }
        
        .video-placeholder .icon {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* Estilos para menú desplegable de usuario */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .user-button {
            background: none;
            border: none;
            color: #6b7280;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .user-button:hover {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
        
        .user-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        
        .user-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .user-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.2s ease;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .user-menu-item:last-child {
            border-bottom: none;
        }
        
        .user-menu-item:hover {
            background: #f8fafc;
            color: #1f2937;
        }
        
        .user-menu-item.logout:hover {
            background: #fef2f2;
            color: #dc2626;
        }
    
        .spinning-disc {
            animation: spin 3s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .nav-logo h2 {
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #1db954, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(29, 185, 84, 0.3);
        }
        
        /* MENÚ MÓVIL - ESTILOS */
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #059669;
        }

        .nav-toggle {
            display: none;
            flex-direction: column;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            z-index: 10000;
        }

        .hamburger {
            width: 25px;
            height: 3px;
            background: #059669;
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 2px;
        }

        .nav-toggle.active .hamburger:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }

        .nav-toggle.active .hamburger:nth-child(2) {
            opacity: 0;
        }

        .nav-toggle.active .hamburger:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }

        @media (max-width: 768px) {
            .nav-toggle {
                display: flex !important;
                background: #f0f0f0 !important;
                border: 2px solid #059669 !important;
            }
            
            .nav-menu {
                position: fixed !important;
                top: 0 !important;
                right: -100% !important;
                width: 100% !important;
                height: 100vh !important;
                background: rgba(255, 255, 255, 0.98) !important;
                backdrop-filter: blur(10px) !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 2rem !important;
                transition: right 0.3s ease !important;
                z-index: 9999 !important;
                padding-top: 80px !important;
                overflow-y: auto !important;
            }
            
            .nav-menu.active {
                right: 0 !important;
            }
            
            .nav-link {
                font-size: 1.2rem !important;
                padding: 1rem 0 !important;
                text-align: center !important;
                width: 100% !important;
                border-bottom: 1px solid #e5e7eb !important;
            }
            
            .nav-link:last-child {
                border-bottom: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Header con menú de navegación -->
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="../index.php" style="text-decoration: none; color: inherit;">
                        <h2><i class="fas fa-compact-disc spinning-disc"></i> DWooSync</h2>
                    </a>
                </div>
                
                <!-- Botón hamburguesa para móvil -->
                <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation">
                    <span class="hamburger"></span>
                    <span class="hamburger"></span>
                    <span class="hamburger"></span>
                </button>
                
                <div class="nav-menu" id="nav-menu">
                    <a href="dashboard.php?lang=<?php echo $currentLang; ?>" class="nav-link">🏠 <?php echo t('Inicio', 'Home'); ?></a>
                    <a href="billing.php?lang=<?php echo $currentLang; ?>" class="nav-link">💳 <?php echo t('Facturación', 'Billing'); ?></a>
                    <a href="tutorials.php?lang=<?php echo $currentLang; ?>" class="nav-link btn-primary">🎥 <?php echo t('Tutoriales', 'Tutorials'); ?></a>
                    <a href="plugin-config.php?lang=<?php echo $currentLang; ?>" class="nav-link">⚙️ <?php echo t('Configurar Plugin', 'Configure Plugin'); ?></a>
                    
                    <!-- Botón de idioma -->
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
                    
                    <!-- Menú desplegable de usuario -->
                    <div class="user-dropdown">
                        <button class="user-button" onclick="toggleUserMenu()">
                            <i class="fas fa-user-circle"></i>
                        </button>
                        <div class="user-menu" id="userMenu">
                            <a href="profile.php?lang=<?php echo $currentLang; ?>" class="user-menu-item">
                                <i class="fas fa-user"></i> <?php echo t('Mi Perfil', 'My Profile'); ?>
                            </a>
                            <a href="logout.php?lang=<?php echo $currentLang; ?>" class="user-menu-item logout">
                                <i class="fas fa-sign-out-alt"></i> <?php echo t('Cerrar Sesión', 'Logout'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main style="padding-top: 100px; min-height: 100vh; background: #f8fafc;">
        <div class="container">
            <!-- Sección de Videos -->
            <div class="videos-section">
                <h2>🎥 <?php echo t('Videos de Funcionamiento y Configuración', 'Operation and Configuration Videos'); ?></h2>
                
                <div class="videos-grid">
                    <!-- Video 1: Instalación del Plugin -->
                    <div class="video-card" onclick="openVideo('instalacion')">
                        <div class="video-thumbnail"></div>
                        <div class="video-title"><?php echo t('Instalación del Plugin', 'Plugin Installation'); ?></div>
                        <div class="video-description">
                            <?php echo t('Aprende cómo instalar y configurar el plugin de dwoosync en tu WordPress paso a paso.', 'Learn how to install and configure the dwoosync plugin in your WordPress step by step.'); ?>
                        </div>
                    </div>
                    
                    <!-- Video 2: Configuración de Licencia -->
                    <div class="video-card" onclick="openVideo('licencia')">
                        <div class="video-thumbnail"></div>
                        <div class="video-title"><?php echo t('Configuración de Licencia', 'License Configuration'); ?></div>
                        <div class="video-description">
                            <?php echo t('Descubre cómo configurar tu clave de licencia y activar todas las funcionalidades.', 'Discover how to configure your license key and activate all features.'); ?>
                        </div>
                    </div>
                    
                    <!-- Video 3: Importación de Productos -->
                    <div class="video-card" onclick="openVideo('importacion')">
                        <div class="video-thumbnail"></div>
                        <div class="video-title"><?php echo t('Importación de Productos', 'Product Import'); ?></div>
                        <div class="video-description">
                            <?php echo t('Guía completa para importar productos desde Discogs a tu tienda WooCommerce.', 'Complete guide to import products from Discogs to your WooCommerce store.'); ?>
                        </div>
                    </div>
                    
                    <!-- Video 4: Configuración de API Discogs -->
                    <div class="video-card" onclick="openVideo('api-discogs')">
                        <div class="video-thumbnail"></div>
                        <div class="video-title"><?php echo t('Configuración de API Discogs', 'Discogs API Configuration'); ?></div>
                        <div class="video-description">
                            <?php echo t('Aprende a obtener y configurar tu API key de Discogs para el funcionamiento del plugin.', 'Learn how to get and configure your Discogs API key for the plugin to work.'); ?>
                        </div>
                    </div>
                    
                    <!-- Video 5: Gestión de Planes -->
                    <div class="video-card" onclick="openVideo('planes')">
                        <div class="video-thumbnail"></div>
                        <div class="video-title"><?php echo t('Gestión de Planes y Suscripciones', 'Plans and Subscriptions Management'); ?></div>
                        <div class="video-description">
                            <?php echo t('Conoce los diferentes planes disponibles y cómo gestionar tu suscripción.', 'Learn about the different available plans and how to manage your subscription.'); ?>
                        </div>
                    </div>
                    
                    <!-- Video 6: Solución de Problemas -->
                    <div class="video-card" onclick="openVideo('soporte')">
                        <div class="video-thumbnail"></div>
                        <div class="video-title"><?php echo t('Solución de Problemas Comunes', 'Common Troubleshooting'); ?></div>
                        <div class="video-description">
                            <?php echo t('Resuelve los problemas más frecuentes que pueden surgir durante el uso del plugin.', 'Solve the most common problems that may arise during plugin usage.'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Placeholder para videos futuros -->
                <div class="video-placeholder">
                    <div class="icon">🎬</div>
                    <p><?php echo t('Más videos tutoriales próximamente...', 'More tutorial videos coming soon...'); ?></p>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Función para toggle del menú de usuario
        function toggleUserMenu() {
            const userMenu = document.getElementById('userMenu');
            userMenu.classList.toggle('show');
        }
        
        // Cerrar menú al hacer click fuera
        document.addEventListener('click', function(event) {
            const userDropdown = document.querySelector('.user-dropdown');
            const userMenu = document.getElementById('userMenu');
            
            if (!userDropdown.contains(event.target)) {
                userMenu.classList.remove('show');
            }
        });

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

        // Función para abrir videos
        function openVideo(videoType) {
            const videoUrls = {
                'instalacion': 'https://www.youtube.com/watch?v=instalacion-plugin',
                'licencia': 'https://www.youtube.com/watch?v=configuracion-licencia',
                'importacion': 'https://www.youtube.com/watch?v=importacion-productos',
                'api-discogs': 'https://www.youtube.com/watch?v=api-discogs',
                'planes': 'https://www.youtube.com/watch?v=gestion-planes',
                'soporte': 'https://www.youtube.com/watch?v=solucion-problemas'
            };
            
            const url = videoUrls[videoType];
            if (url) {
                // Abrir en nueva pestaña
                window.open(url, '_blank');
            } else {
                alert('Video no disponible aún. Próximamente...');
            }
        }
    </script>
    
    <!-- Script del menú móvil -->
    <script>
        function initMobileMenu() {
            console.log('=== INICIANDO MENÚ MÓVIL EN TUTORIALS ===');
            
            const navToggle = document.getElementById('nav-toggle');
            const navMenu = document.getElementById('nav-menu');
            
            console.log('navToggle encontrado:', !!navToggle);
            console.log('navMenu encontrado:', !!navMenu);
            
            if (navToggle && navMenu) {
                console.log('Agregando event listener al botón hamburguesa');
                
                navToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Botón hamburguesa clickeado!');
                    navMenu.classList.toggle('active');
                    navToggle.classList.toggle('active');
                    console.log('Menú activo:', navMenu.classList.contains('active'));
                });
                
                // Cerrar menú al hacer clic en enlaces (excluyendo selector de idioma)
                const navLinks = navMenu.querySelectorAll('.nav-link:not(.language-dropdown button)');
                console.log('Enlaces encontrados:', navLinks.length);
                navLinks.forEach(function(link, index) {
                    console.log('Agregando listener al enlace', index, 'href:', link.href);
                    link.onclick = function(e) {
                        console.log('Enlace clickeado:', link.href);
                        console.log('Cerrando menú...');
                        navMenu.classList.remove('active');
                        navToggle.classList.remove('active');
                        console.log('Menú cerrado, permitiendo navegación');
                        return true; // Permitir navegación
                    };
                });
                
                console.log('Menú móvil inicializado correctamente');
            } else {
                console.error('No se encontraron los elementos del menú móvil');
            }
        }
        
        // Inicializar menú móvil
        setTimeout(function() {
            try {
                console.log('=== INICIALIZANDO MENÚ MÓVIL CON TIMEOUT ===');
                initMobileMenu();
            } catch (error) {
                console.error('Error al inicializar menú móvil:', error);
            }
        }, 1000);
    </script>
</body>
</html>
