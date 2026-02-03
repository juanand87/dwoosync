<?php
/**
 * Screenshots page
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Detectar idioma - Inglés por defecto
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
$currentLang = $_GET['lang'] ?? ($_SESSION['selected_language'] ?? 'en');
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

?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Screenshots - DWooSync', 'Screenshots - DWooSync'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <?php renderWhatsAppStyles(); ?>
    
    <style>
        .screenshots-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .screenshot-item {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .screenshot-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .screenshot-image {
            position: relative;
            overflow: hidden;
        }
        
        .screenshot-image img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .screenshot-item:hover .screenshot-image img {
            transform: scale(1.05);
        }
        
        .screenshot-content {
            padding: 1.5rem;
        }
        
        .screenshot-content h3 {
            color: #1f2937;
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .screenshot-content p {
            color: #6b7280;
            line-height: 1.6;
            margin: 0;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            position: relative;
            margin: 5% auto;
            width: 70%;
            max-width: 800px;
            animation: zoomIn 0.3s ease;
        }
        
        /* Indicador de carga */
        .modal-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 2rem;
            z-index: 1002;
        }
        
        .modal-loading i {
            animation: spin 1s linear infinite;
        }
        
        .modal-image {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            max-height: 80vh;
            object-fit: contain;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
            transition: color 0.3s ease;
        }
        
        .modal-close:hover {
            color: #10b981;
        }
        
        .modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: #fff;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
            background: rgba(0, 0, 0, 0.5);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .modal-nav:hover {
            background: rgba(16, 185, 129, 0.8);
            transform: translateY(-50%) scale(1.1);
        }
        
        .modal-nav.prev {
            left: 20px;
        }
        
        .modal-nav.next {
            right: 20px;
        }
        
        .modal-nav.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .modal-nav.disabled:hover {
            background: rgba(0, 0, 0, 0.5);
            transform: translateY(-50%) scale(1);
        }
        
        /* Botón de pantalla completa */
        .fullscreen-btn {
            position: absolute;
            top: 15px;
            left: 35px;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            z-index: 1001;
            background: rgba(0, 0, 0, 0.5);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .fullscreen-btn:hover {
            background: rgba(16, 185, 129, 0.8);
            transform: scale(1.1);
        }
        
        /* Estilos para pantalla completa */
        .modal.fullscreen {
            background-color: rgba(0, 0, 0, 0.95);
        }
        
        .modal.fullscreen .modal-content {
            width: 90vw;
            height: 90vh;
            max-width: none;
            margin: 5vh auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal.fullscreen .modal-image {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        
        .modal.fullscreen .modal-nav {
            top: 50%;
            transform: translateY(-50%);
            font-size: 40px;
            width: 60px;
            height: 60px;
        }
        
        .modal.fullscreen .modal-close {
            font-size: 50px;
            top: 20px;
            right: 40px;
        }
        
            .modal.fullscreen .fullscreen-btn {
                font-size: 28px;
                width: 50px;
                height: 50px;
                top: 20px;
                left: 40px;
            }
        

        
        /* Estilos para navegación responsive */        .modal-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 1.5rem;
            text-align: center;
            backdrop-filter: blur(10px);
            display: none; /* Oculto por defecto para eliminar el degradado */
        }
        
        .modal-caption h3 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
        }
        
        .modal-caption p {
            margin: 0;
            opacity: 0.9;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes zoomIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
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
        
        @media (max-width: 1024px) {
            .screenshots-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .screenshots-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .screenshot-image img {
                height: 200px;
            }
            
            .modal-content {
                width: 90%;
                margin: 10% auto;
            }
            
            .modal-close {
                top: 10px;
                right: 20px;
                font-size: 30px;
            }
            
            .fullscreen-btn {
                top: 10px;
                left: 20px;
                font-size: 20px;
                width: 35px;
                height: 35px;
            }
            
            .modal-nav {
                font-size: 25px;
                width: 45px;
                height: 45px;
            }
            
            .modal-nav.prev {
                left: 10px;
            }
            
            .modal-nav.next {
                right: 10px;
            }
            
            .modal.fullscreen .modal-content {
                width: 95vw;
                height: 95vh;
                margin: 2.5vh auto;
            }
            
            .modal.fullscreen .modal-close {
                font-size: 35px;
                top: 15px;
                right: 25px;
            }
            
            .modal.fullscreen .fullscreen-btn {
                font-size: 24px;
                width: 45px;
                height: 45px;
                top: 15px;
                left: 25px;
            }
        }
        
        /* Estilos para navegación responsive */
        .nav-toggle {
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 10001;
        }
        
        .hamburger {
            width: 30px;
            height: 3px;
            background: #374151;
            border-radius: 10px;
            transition: all 0.3s linear;
            position: relative;
            transform-origin: 1px;
        }
        
        .nav-toggle.active .hamburger:nth-child(1) {
            transform: rotate(45deg);
            background: #374151;
        }
        
        .nav-toggle.active .hamburger:nth-child(2) {
            opacity: 0;
        }
        
        .nav-toggle.active .hamburger:nth-child(3) {
            transform: rotate(-45deg);
            background: #374151;
        }
        
        @media (max-width: 768px) {
            .nav-toggle {
                display: flex;
            }
            
            .nav-menu {
                position: fixed;
                top: 0;
                right: -100%;
                width: 100%;
                height: 100vh;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(10px);
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 2rem;
                transition: right 0.3s ease;
                z-index: 9999;
                padding-top: 80px;
                overflow-y: auto;
            }
            
            .nav-menu.active {
                right: 0;
            }
            
            .nav-link {
                font-size: 1.2rem;
                padding: 1rem 0;
                text-align: center;
                width: 100%;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .nav-link:last-child {
                border-bottom: none;
            }
            
            .language-dropdown {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <?php 
                    $logoUrl = isLoggedIn() ? 'dashboard.php?lang=' . $currentLang : '../index.php';
                    ?>
                    <a href="<?php echo $logoUrl; ?>" style="text-decoration: none; color: inherit;">
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
                    <a href="../index.php" class="nav-link"><?php echo t('Inicio', 'Home'); ?></a>
                    <a href="../index.php#plans" class="nav-link"><?php echo t('Planes', 'Plans'); ?></a>
                    <a href="../index.php#features" class="nav-link"><?php echo t('Características', 'Features'); ?></a>
                    <a href="screenshots.php" class="nav-link active"><?php echo t('Screenshots', 'Screenshots'); ?></a>
                    <a href="contact.php" class="nav-link"><?php echo t('Contacto', 'Contact'); ?></a>
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="dashboard.php?lang=<?php echo $currentLang; ?>" class="nav-link btn-login"><?php echo t('Ver Cuenta', 'View Account'); ?></a>
                    <?php else: ?>
                        <a href="login.php" class="nav-link btn-login"><?php echo t('Iniciar Sesión', 'Login'); ?></a>
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

    <!-- Main Content -->
    <main style="padding-top: 100px; min-height: 100vh; background: #f8fafc;">
        <div class="container">
            
            <!-- Header Section -->
            <div style="text-align: center; margin-bottom: 4rem;">
                <h1 style="color: #1f2937; margin-bottom: 1rem; font-size: 3rem;"><?php echo t('Capturas de Pantalla', 'Screenshots'); ?></h1>
                <p style="color: #6b7280; font-size: 1.25rem; max-width: 600px; margin: 0 auto;">
                    <?php echo t('Descubre cómo se ve DWooSync en acción. Explora la interfaz del plugin y todas sus funcionalidades.', 'Discover how DWooSync looks in action. Explore the plugin interface and all its features.'); ?>
                </p>
            </div>

            <!-- Screenshots Grid -->
            <div class="screenshots-grid">
                <div class="screenshot-item">
                    <div class="screenshot-image">
                        <img src="../assets/images/Screenshot_1.jpg" alt="<?php echo t('Panel de Control Principal', 'Main Control Panel'); ?>" style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    </div>
                    <div class="screenshot-content">
                        <h3><?php echo t('Panel de Control Principal', 'Main Control Panel'); ?></h3>
                    </div>
                </div>

                <div class="screenshot-item">
                    <div class="screenshot-image">
                        <img src="../assets/images/Screenshot_2.jpg" alt="<?php echo t('Configuración de API', 'API Configuration'); ?>" style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    </div>
                    <div class="screenshot-content">
                        <h3><?php echo t('Configuración de API', 'API Configuration'); ?></h3>
                    </div>
                </div>

                <div class="screenshot-item">
                    <div class="screenshot-image">
                        <img src="../assets/images/Screenshot_3.jpg" alt="<?php echo t('Productos Sincronizados', 'Synchronized Products'); ?>" style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    </div>
                    <div class="screenshot-content">
                        <h3><?php echo t('Productos Sincronizados', 'Synchronized Products'); ?></h3>
                    </div>
                </div>

                <div class="screenshot-item">
                    <div class="screenshot-image">
                        <img src="../assets/images/Screenshot_4.jpg" alt="<?php echo t('Widget Spotify', 'Spotify Widget'); ?>" style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    </div>
                    <div class="screenshot-content">
                        <h3><?php echo t('Widget Spotify', 'Spotify Widget'); ?></h3>
                    </div>
                </div>

                <div class="screenshot-item">
                    <div class="screenshot-image">
                        <img src="../assets/images/Screenshot_5.jpg" alt="<?php echo t('Estadísticas Detalladas', 'Detailed Statistics'); ?>" style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    </div>
                    <div class="screenshot-content">
                        <h3><?php echo t('Estadísticas Detalladas', 'Detailed Statistics'); ?></h3>
                    </div>
                </div>

                <div class="screenshot-item">
                    <div class="screenshot-image">
                        <img src="../assets/images/Screenshot_6.jpg" alt="<?php echo t('Búsqueda de discos', 'Disc Search'); ?>" style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    </div>
                    <div class="screenshot-content">
                        <h3><?php echo t('Búsqueda de discos', 'Disc Search'); ?></h3>
                    </div>
                </div>

                <div class="screenshot-item">
                    <div class="screenshot-image">
                        <img src="../assets/images/Screenshot_7.jpg" alt="<?php echo t('Funcionalidades Avanzadas', 'Advanced Features'); ?>" style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    </div>
                    <div class="screenshot-content">
                        <h3><?php echo t('Funcionalidades Avanzadas', 'Advanced Features'); ?></h3>
                    </div>
                </div>
            </div>

            <!-- Call to Action -->
            <div style="text-align: center; margin-top: 4rem; padding: 3rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 16px; color: white;">
                <h2 style="margin-bottom: 1rem; color: white;"><?php echo t('¿Listo para probar DWooSync?', 'Ready to try DWooSync?'); ?></h2>
                <p style="margin-bottom: 2rem; opacity: 0.9; font-size: 1.125rem;">
                    <?php echo t('Comienza a sincronizar tus productos de Discogs con WooCommerce hoy mismo.', 'Start synchronizing your Discogs products with WooCommerce today.'); ?>
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="../index.php#plans" class="btn btn-white btn-large">
                        <i class="fas fa-rocket"></i> <?php echo t('Ver Planes', 'View Plans'); ?>
                    </a>
                    <a href="login.php" class="btn btn-outline-white btn-large">
                        <i class="fas fa-sign-in-alt"></i> <?php echo t('Iniciar Sesión', 'Login'); ?>
                    </a>
                </div>
            </div>

            <!-- Back to Home -->
            <div style="text-align: center; margin-top: 2rem;">
                <a href="../index.php" style="color: #059669; text-decoration: none; font-weight: 500;">
                    <i class="fas fa-arrow-left"></i> <?php echo t('Volver al Inicio', 'Back to Home'); ?>
                </a>
            </div>
        </div>
    </main>

    <!-- Modal para mostrar imágenes en grande -->
    <div id="screenshotModal" class="modal">
        <span class="modal-close">&times;</span>
        <div class="fullscreen-btn" id="fullscreenBtn" title="<?php echo t('Pantalla completa', 'Fullscreen'); ?>">
            <i class="fas fa-expand"></i>
        </div>
        <div class="modal-nav prev" id="prevBtn">‹</div>
        <div class="modal-nav next" id="nextBtn">›</div>
        <div class="modal-content">
            <div class="modal-loading" id="modalLoading" style="display: none;">
                <i class="fas fa-spinner"></i>
            </div>
            <img id="modalImage" class="modal-image" src="" alt="">
            <div class="modal-caption">
                <h3 id="modalTitle"></h3>
                <p id="modalDescription"></p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>DWooSync</h3>
                    <p><?php echo t('El mejor plugin para integrar Discogs con WooCommerce - WordPress', 'The best plugin to integrate Discogs with WooCommerce - WordPress'); ?></p>
                </div>
                <div class="footer-section">
                    <h4><?php echo t('Enlaces', 'Links'); ?></h4>
                    <ul>
                        <li><a href="../index.php#plans"><?php echo t('Planes', 'Plans'); ?></a></li>
                        <li><a href="../index.php#features"><?php echo t('Características', 'Features'); ?></a></li>
                        <li><a href="screenshots.php"><?php echo t('Screenshots', 'Screenshots'); ?></a></li>
                        <li><a href="login.php"><?php echo t('Iniciar Sesión', 'Login'); ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4><?php echo t('Soporte', 'Support'); ?></h4>
                    <ul>
                        <li><a href="mailto:support@dwoosync.com">support@dwoosync.com</a></li>
                        <li><a href="#"><?php echo t('Documentación', 'Documentation'); ?></a></li>
                        <li><a href="contact.php"><?php echo t('Contacto', 'Contact'); ?></a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> DWooSync. <?php echo t('Todos los derechos reservados.', 'All rights reserved.'); ?></p>
            </div>
        </div>
    </footer>

    <?php renderWhatsAppButton($currentLang); ?>

    <script src="../assets/js/script.js"></script>
    <script>
        // Funcionalidad del selector de idioma
        document.addEventListener('DOMContentLoaded', function() {
            const languageButton = document.querySelector('.language-dropdown button');
            const languageMenu = document.querySelector('.language-menu');
            
            if (languageButton && languageMenu) {
                languageButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    languageMenu.style.display = languageMenu.style.display === 'none' ? 'block' : 'none';
                });
                
                // Cerrar menú al hacer clic fuera
                document.addEventListener('click', function() {
                    languageMenu.style.display = 'none';
                });
            }
        });
        
        // Funcionalidad del menú hamburguesa
        document.addEventListener('DOMContentLoaded', function() {
            const navToggle = document.getElementById('nav-toggle');
            const navMenu = document.getElementById('nav-menu');
            
            if (navToggle && navMenu) {
                navToggle.addEventListener('click', function() {
                    navToggle.classList.toggle('active');
                    navMenu.classList.toggle('active');
                });
                
                // Cerrar menú al hacer clic en un enlace
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.addEventListener('click', () => {
                        navToggle.classList.remove('active');
                        navMenu.classList.remove('active');
                    });
                });
                
                // Cerrar menú al hacer clic fuera
                document.addEventListener('click', function(event) {
                    if (!navToggle.contains(event.target) && !navMenu.contains(event.target)) {
                        navToggle.classList.remove('active');
                        navMenu.classList.remove('active');
                    }
                });
            }
        });
        
        // Funcionalidad del modal de screenshots
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('screenshotModal');
            const modalImage = document.getElementById('modalImage');
            const modalTitle = document.getElementById('modalTitle');
            const modalDescription = document.getElementById('modalDescription');
            const closeBtn = document.querySelector('.modal-close');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const fullscreenBtn = document.getElementById('fullscreenBtn');
            const screenshotItems = document.querySelectorAll('.screenshot-item');
            
            let currentIndex = 0;
            let screenshots = [];
            let isFullscreen = false;

            // Preparar array de screenshots
            screenshotItems.forEach((item, index) => {
                const img = item.querySelector('img');
                const title = item.querySelector('h3').textContent;
                
                screenshots.push({
                    src: img.src,
                    alt: img.alt,
                    title: title,
                    description: ''
                });
            });

            // Mostrar imagen en el modal
            function showImage(index) {
                const screenshot = screenshots[index];
                const modalLoading = document.getElementById('modalLoading');
                
                // Mostrar indicador de carga
                modalLoading.style.display = 'block';
                modalImage.style.opacity = '0';
                
                // Cargar nueva imagen
                const newImage = new Image();
                newImage.onload = function() {
                    modalImage.src = screenshot.src;
                    modalImage.alt = screenshot.alt;
                    modalTitle.textContent = screenshot.title;
                    modalDescription.textContent = screenshot.description;
                    
                    // Actualizar estado de botones de navegación
                    prevBtn.classList.toggle('disabled', index === 0);
                    nextBtn.classList.toggle('disabled', index === screenshots.length - 1);
                    
                    // Actualizar contador en el título
                    const counter = ` (${index + 1}/${screenshots.length})`;
                    modalTitle.textContent = screenshot.title + counter;
                    
                    // Ocultar indicador de carga y mostrar imagen
                    modalLoading.style.display = 'none';
                    modalImage.style.opacity = '1';
                };
                
                newImage.src = screenshot.src;
            }

            // Función para alternar pantalla completa
            function toggleFullscreen() {
                isFullscreen = !isFullscreen;
                modal.classList.toggle('fullscreen', isFullscreen);
                
                const icon = fullscreenBtn.querySelector('i');
                if (isFullscreen) {
                    icon.className = 'fas fa-compress';
                    fullscreenBtn.title = '<?php echo t("Salir de pantalla completa", "Exit fullscreen"); ?>';
                } else {
                    icon.className = 'fas fa-expand';
                    fullscreenBtn.title = '<?php echo t("Pantalla completa", "Fullscreen"); ?>';
                }
            }

            // Abrir modal al hacer clic en una imagen
            screenshotItems.forEach((item, index) => {
                item.addEventListener('click', function() {
                    currentIndex = index;
                    showImage(currentIndex);
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                });
            });

            // Navegación anterior
            prevBtn.addEventListener('click', function() {
                if (currentIndex > 0) {
                    currentIndex--;
                    showImage(currentIndex);
                }
            });

            // Navegación siguiente
            nextBtn.addEventListener('click', function() {
                if (currentIndex < screenshots.length - 1) {
                    currentIndex++;
                    showImage(currentIndex);
                }
            });

            // Botón de pantalla completa
            fullscreenBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleFullscreen();
            });

            // Cerrar modal
            function closeModal() {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                // Salir de pantalla completa al cerrar
                if (isFullscreen) {
                    isFullscreen = false;
                    modal.classList.remove('fullscreen');
                    const icon = fullscreenBtn.querySelector('i');
                    icon.className = 'fas fa-expand';
                    fullscreenBtn.title = '<?php echo t("Pantalla completa", "Fullscreen"); ?>';
                }
            }

            closeBtn.addEventListener('click', closeModal);

            // Cerrar modal al hacer clic fuera de la imagen
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeModal();
                }
            });

            // Navegación con teclado
            document.addEventListener('keydown', function(e) {
                if (modal.style.display === 'block') {
                    e.preventDefault(); // Prevenir scroll de página
                    
                    if (e.key === 'Escape') {
                        closeModal();
                    } else if (e.key === 'ArrowLeft' && currentIndex > 0) {
                        currentIndex--;
                        showImage(currentIndex);
                    } else if (e.key === 'ArrowRight' && currentIndex < screenshots.length - 1) {
                        currentIndex++;
                        showImage(currentIndex);
                    } else if (e.key === 'f' || e.key === 'F') {
                        toggleFullscreen();
                    } else if (e.key === ' ') { // Barra espaciadora para siguiente imagen
                        if (currentIndex < screenshots.length - 1) {
                            currentIndex++;
                            showImage(currentIndex);
                        }
                    }
                }
            });

            // Navegación con gestos touch para móviles
            let touchStartX = 0;
            let touchEndX = 0;
            
            modal.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            });
            
            modal.addEventListener('touchend', function(e) {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            });
            
            function handleSwipe() {
                const swipeThreshold = 50;
                const diff = touchStartX - touchEndX;
                
                if (Math.abs(diff) > swipeThreshold) {
                    if (diff > 0 && currentIndex < screenshots.length - 1) {
                        // Swipe izquierda - siguiente imagen
                        currentIndex++;
                        showImage(currentIndex);
                    } else if (diff < 0 && currentIndex > 0) {
                        // Swipe derecha - imagen anterior
                        currentIndex--;
                        showImage(currentIndex);
                    }
                }
            }
        });
    </script>
    
    <?php renderWhatsAppScript($currentLang); ?>
</body>
</html>

