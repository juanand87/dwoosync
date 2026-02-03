<?php
/**
 * Formulario de contacto
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión
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

// No procesar el formulario aquí, se envía a contact_process.php

?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Contacto - DiscogsSync', 'Contact - DiscogsSync'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
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
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #1db954, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(29, 185, 84, 0.3);
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
                    <a href="../index.php" class="nav-link"><?php echo t('Inicio', 'Home'); ?></a>
                    <a href="../index.php#plans" class="nav-link"><?php echo t('Planes', 'Plans'); ?></a>
                    <a href="../index.php#features" class="nav-link"><?php echo t('Características', 'Features'); ?></a>
                    <a href="screenshots.php" class="nav-link"><?php echo t('Screenshots', 'Screenshots'); ?></a>
                    <a href="contact.php" class="nav-link"><?php echo t('Contacto', 'Contact'); ?></a>
                    <a href="login.php" class="nav-link btn-login"><?php echo t('Iniciar Sesión', 'Login'); ?></a>
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
        <div class="container" style="max-width: 800px;">
            <div style="background: white; border-radius: 16px; padding: 3rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                
                <!-- Header -->
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="color: #1f2937; margin-bottom: 0.5rem;"><?php echo t('Contacto', 'Contact'); ?></h1>
                    <p style="color: #6b7280;"><?php echo t('¿Necesitas ayuda? Estamos aquí para asistirte', 'Need help? We are here to assist you'); ?></p>
                </div>
                
                <!-- Mensaje informativo -->
                <div class="alert alert-info" style="background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <i class="fas fa-info-circle"></i> 
                    <?php echo t('Completa el formulario y te redirigiremos a una página de confirmación.', 'Complete the form and we will redirect you to a confirmation page.'); ?>
                </div>

                <!-- Contact Form -->
                <form method="POST" action="contact_process_basic.php" data-validate>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name"><?php echo t('Nombre *', 'Name *'); ?></label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email"><?php echo t('Email *', 'Email *'); ?></label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="subject"><?php echo t('Asunto *', 'Subject *'); ?></label>
                        <input type="text" id="subject" name="subject" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="message"><?php echo t('Mensaje *', 'Message *'); ?></label>
                        <textarea id="message" name="message" class="form-control" rows="6" 
                                  placeholder="<?php echo t('Describe tu consulta o problema...', 'Describe your query or problem...'); ?>" required></textarea>
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-paper-plane"></i> <?php echo t('Enviar Mensaje', 'Send Message'); ?>
                        </button>
                    </div>
                </form>

                <!-- Contact Information -->
                <div style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e5e7eb;">
                    <h3 style="color: #1f2937; margin-bottom: 1.5rem;"><?php echo t('Información de Contacto', 'Contact Information'); ?></h3>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <div style="text-align: center; padding: 1.5rem; background: #f9fafb; border-radius: 12px;">
                            <i class="fas fa-envelope" style="font-size: 2rem; color: #059669; margin-bottom: 1rem;"></i>
                            <h4 style="margin: 0 0 0.5rem 0; color: #1f2937;"><?php echo t('Email', 'Email'); ?></h4>
                            <p style="margin: 0; color: #6b7280;">support@discogssync.com</p>
                        </div>
                        
                        <div style="text-align: center; padding: 1.5rem; background: #f9fafb; border-radius: 12px;">
                            <i class="fas fa-clock" style="font-size: 2rem; color: #059669; margin-bottom: 1rem;"></i>
                            <h4 style="margin: 0 0 0.5rem 0; color: #1f2937;"><?php echo t('Horario', 'Schedule'); ?></h4>
                            <p style="margin: 0; color: #6b7280;"><?php echo t('Lunes - Viernes<br>9:00 - 18:00', 'Monday - Friday<br>9:00 - 18:00'); ?></p>
                        </div>
                        
                        <div style="text-align: center; padding: 1.5rem; background: #f9fafb; border-radius: 12px;">
                            <i class="fas fa-headset" style="font-size: 2rem; color: #059669; margin-bottom: 1rem;"></i>
                            <h4 style="margin: 0 0 0.5rem 0; color: #1f2937;"><?php echo t('Soporte', 'Support'); ?></h4>
                            <p style="margin: 0; color: #6b7280;"><?php echo t('Respuesta en 24h', 'Response in 24h'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Back to Home -->
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="../index.php" style="color: #059669; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> <?php echo t('Volver al Inicio', 'Back to Home'); ?>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
    
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
    </script>
</body>
</html>

