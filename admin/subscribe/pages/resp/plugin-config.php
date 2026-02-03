<?php
/**
 * Página de configuración del plugin DiscogsSync
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

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$subscriber_id = $_SESSION['subscriber_id'];
$license_key = $_SESSION['license_key'] ?? '';

// Verificar si hay factura (cualquier registro en billing_cycles)
$has_active_invoice = false;
try {
$db = getDatabase();
    $stmt = $db->prepare("
        SELECT * FROM billing_cycles 
        WHERE subscriber_id = ? AND status = 'paid'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$subscriber_id]);
    $billing_cycle_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($billing_cycle_data) {
        $has_active_invoice = true;
    }
} catch (Exception $e) {
    // En caso de error, asumir que no hay factura
    $has_active_invoice = false;
}

// Obtener datos del usuario
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'];
$userName = $_SESSION['user_name'];
$userDomain = $_SESSION['user_domain'];
$userPlan = $_SESSION['user_plan'];
$licenseKey = $_SESSION['license_key'];

// Obtener información de la licencia
try {
    $db = Database::getInstance();
    $license = $db->fetch('SELECT * FROM licenses WHERE subscriber_id = ? AND status = "active"', [$userId]);
    
    if (!$license) {
        throw new Exception('No se encontró una licencia activa');
    }
    
    $subscriber = $db->fetch('SELECT * FROM subscribers WHERE id = ?', [$userId]);
    
} catch (Exception $e) {
    $error = 'Error al cargar la información: ' . $e->getMessage();
}

// Obtener planes para mostrar características
$plans = getSubscriptionPlans($currentLang);
$currentPlan = null;
foreach ($plans as $plan) {
    if ($plan['id'] === $userPlan) {
        $currentPlan = $plan;
        break;
    }
}

$success = '';
$error = '';

// Procesar descarga del plugin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_plugin'])) {
    try {
        // Crear archivo ZIP del plugin
        $pluginDir = __DIR__ . '/../plugin/';
        $zipFile = __DIR__ . '/../../temp/discogs-sync-plugin-' . $licenseKey . '.zip';
        
        // Debug logs
        error_log('[Plugin Download Debug] Plugin directory: ' . $pluginDir);
        error_log('[Plugin Download Debug] Directory exists: ' . (is_dir($pluginDir) ? 'YES' : 'NO'));
        error_log('[Plugin Download Debug] Zip file: ' . $zipFile);
        
        // Crear directorio temp si no existe
        if (!is_dir(__DIR__ . '/../../temp/')) {
            mkdir(__DIR__ . '/../../temp/', 0755, true);
        }
        
        // Verificar si ZipArchive está disponible
        if (!class_exists('ZipArchive')) {
            throw new Exception('La extensión ZipArchive no está disponible en este servidor. Contacta al administrador para habilitarla.');
        }
        
        // Crear ZIP usando PHP
        $zip = new ZipArchive();
        $zipResult = $zip->open($zipFile, ZipArchive::CREATE);
        error_log('[Plugin Download Debug] Zip open result: ' . $zipResult);
        
        if ($zipResult === TRUE) {
            error_log('[Plugin Download Debug] Starting to add files to ZIP');
            // Agregar archivos del plugin de forma recursiva
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            $fileCount = 0;
            foreach ($iterator as $file) {
                $filePath = $file->getPathname();
                
                // Normalizar las rutas para evitar problemas con separadores de directorio
                $normalizedPluginDir = rtrim(str_replace('\\', '/', $pluginDir), '/') . '/';
                $normalizedFilePath = str_replace('\\', '/', $filePath);
                
                // Obtener la ruta relativa dentro del plugin
                $relativePath = str_replace($normalizedPluginDir, '', $normalizedFilePath);
                
                // Solo agregar el prefijo 'discogs-sync/' sin incluir la ruta completa
                $zipPath = 'discogs-sync/' . $relativePath;
                
                if ($file->isDir()) {
                    $zip->addEmptyDir($zipPath);
                    error_log('[Plugin Download Debug] Added directory: ' . $zipPath);
                } else {
                    $zip->addFile($filePath, $zipPath);
                    error_log('[Plugin Download Debug] Added file: ' . $zipPath);
                    $fileCount++;
                }
            }
            error_log('[Plugin Download Debug] Total files added: ' . $fileCount);
            
            $zip->close();
            error_log('[Plugin Download Debug] ZIP closed successfully');
            
            // Verificar que el archivo ZIP se creó
            if (!file_exists($zipFile)) {
                throw new Exception('El archivo ZIP no se creó correctamente');
            }
            
            $fileSize = filesize($zipFile);
            error_log('[Plugin Download Debug] ZIP file size: ' . $fileSize . ' bytes');
            
            // Descargar el archivo
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="dwoosync.zip"');
            header('Content-Length: ' . $fileSize);
            readfile($zipFile);
            unlink($zipFile); // Eliminar archivo temporal
            error_log('[Plugin Download Debug] File downloaded and deleted successfully');
            exit;
            
        } else {
            throw new Exception('No se pudo crear el archivo ZIP');
        }
        
    } catch (Exception $e) {
        $error = 'Error al generar el plugin: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Configuración del Plugin', 'Plugin Configuration'); ?> - dwoosync</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo t('Configura y descarga el plugin dwoosync para integrar Discogs con WooCommerce. Licencias y configuración personalizada.', 'Configure and download the dwoosync plugin to integrate Discogs with WooCommerce. Licenses and custom configuration.'); ?>">
    <meta name="keywords" content="<?php echo t('configuración plugin dwoosync, descarga plugin, licencias, integración discogs woocommerce', 'dwoosync plugin configuration, plugin download, licenses, discogs woocommerce integration'); ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo t('Configuración del Plugin - dwoosync', 'Plugin Configuration - dwoosync'); ?>">
    <meta property="og:description" content="<?php echo t('Configura y descarga el plugin dwoosync para Discogs y WooCommerce.', 'Configure and download the dwoosync plugin for Discogs and WooCommerce.'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://dwoosync.com/subscribe/pages/plugin-config.php">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://dwoosync.com/subscribe/pages/plugin-config.php">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
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

        .config-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        .config-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .license-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .config-section {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .code-block {
            background: #1f2937;
            color: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            margin: 1rem 0;
        }
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #059669;
        }
        .step-number {
            background: #059669;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        .btn-download {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(5, 150, 105, 0.3);
        }
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1rem;
            margin: 1rem 0;
        }
        .feature-item {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .feature-item i {
            color: #059669;
            margin-right: 0.75rem;
            font-size: 1.2rem;
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
    <?php if (!$has_active_invoice): ?>
    <!-- Notificación global de suscripción incompleta -->
    <div id="globalSubscriptionNotification" style="
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
        padding: 12px 20px;
        text-align: center;
        z-index: 9999;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    ">
        <div style="display: flex; align-items: center; justify-content: center; gap: 10px; max-width: 1200px; margin: 0 auto;">
            <div style="font-size: 1.2rem;">⚠️</div>
            <div style="flex: 1;">
                <strong>¡Completa tu suscripción!</strong> 
                Selecciona un plan para continuar usando dwoosync.
            </div>
            <a href="checkout.php?plan=free" style="
                background: rgba(255,255,255,0.2);
            color: white;
                padding: 8px 16px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 500;
                transition: all 0.3s ease;
                border: 1px solid rgba(255,255,255,0.3);
            " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                Completar Ahora
            </a>
            <button onclick="hideNotification()" style="
                background: none;
            border: none;
                color: white;
                font-size: 1.5rem;
            cursor: pointer;
                padding: 0;
                margin-left: 10px;
            " title="Cerrar notificación">×</button>
        </div>
    </div>
    
    <!-- Ajustar el body para la notificación -->
    <style>
        body {
            padding-top: 60px !important;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 80px !important;
            }
            
            #globalSubscriptionNotification {
                padding: 15px 10px;
            }
            
            #globalSubscriptionNotification > div {
                flex-direction: column;
                gap: 10px;
            }
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
    </style>
    
    <script>
        
        // Cerrar menú al hacer click fuera
        document.addEventListener('click', function(event) {
            const userDropdown = document.querySelector('.user-dropdown');
            const userMenu = document.getElementById('userMenu');
            const languageDropdown = document.querySelector('.language-dropdown');
            const languageMenu = document.querySelector('.language-menu');
            
            // Cerrar menú de usuario si se hace clic fuera
            if (userDropdown && userMenu && !userDropdown.contains(event.target)) {
                userMenu.classList.remove('show');
            }
            
            // Cerrar menú de idioma si se hace clic fuera
            if (languageDropdown && !languageDropdown.contains(event.target)) {
                languageMenu.style.display = 'none';
            }
        });

        function hideNotification() {
            document.getElementById('globalSubscriptionNotification').style.display = 'none';
            document.body.style.paddingTop = '0px';
        }

        // Funcionalidad del dropdown de idioma
        document.addEventListener('DOMContentLoaded', function() {
            const languageDropdown = document.querySelector('.language-dropdown');
            if (languageDropdown) {
                const languageButton = languageDropdown.querySelector('button');
                const languageMenu = languageDropdown.querySelector('.language-menu');
                
                languageButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    languageMenu.style.display = languageMenu.style.display === 'none' ? 'block' : 'none';
                });
                
                // Prevenir cierre al hacer clic dentro del dropdown
                languageMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        });
    </script>
    <?php endif; ?>
    
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
                <a href="dashboard.php" class="nav-link">🏠 <?php echo t('Inicio', 'Home'); ?></a>
                <a href="billing.php" class="nav-link">💳 <?php echo t('Facturación', 'Billing'); ?></a>
                <a href="tutorials.php" class="nav-link">🎥 <?php echo t('Tutoriales', 'Tutorials'); ?></a>
                <a href="plugin-config.php" class="nav-link btn-primary">⚙️ <?php echo t('Configurar Plugin', 'Configure Plugin'); ?></a>
                
                <!-- Botón de idioma -->
                <div class="language-dropdown" style="position: relative; margin-left: 10px;">
                    <button class="nav-link" style="background: #1db954; color: white; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                        <?php if ($isEnglish): ?>
                            <span style="font-size: 1.2em;">🇬🇧</span>
                            <span>EN</span>
                        <?php else: ?>
                            <span style="font-size: 1.2em;">🇪🇸</span>
                            <span>ES</span>
                        <?php endif; ?>
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
                    <button class="user-button" onclick="toggleUserMenu(event)">
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

    <!-- Main Content -->
    <main style="padding-top: 100px; min-height: 100vh; background: #f8fafc;">
        <div class="config-container">
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error" style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
            <?php endif; ?>

            <!-- Información de la Licencia -->
            <div class="license-info">
                <h2 style="margin-bottom: 1rem;">
                    <i class="fas fa-key"></i> <?php echo t('Tu Licencia de DiscogsSync', 'Your DiscogsSync License'); ?>
                </h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <strong><?php echo t('Clave de Licencia:', 'License Key:'); ?></strong><br>
                        <code style="background: rgba(255,255,255,0.2); padding: 0.5rem; border-radius: 4px; font-size: 1.1rem;">
                            <?php echo htmlspecialchars($licenseKey); ?>
                        </code>
                    </div>
                    <div>
                        <strong><?php echo t('Plan:', 'Plan:'); ?></strong><br>
                        <span style="font-size: 1.2rem; font-weight: bold;">
                            <?php echo htmlspecialchars($currentPlan['name'] ?? 'N/A'); ?>
                        </span>
                    </div>
                    <div>
                        <strong><?php echo t('Dominio:', 'Domain:'); ?></strong><br>
                        <span style="font-size: 1.1rem;">
                            <?php echo htmlspecialchars($userDomain); ?>
                        </span>
                    </div>
                    <div>
                        <strong><?php echo t('Estado:', 'Status:'); ?></strong><br>
                        <span style="color: #10b981; font-weight: bold;">
                            <i class="fas fa-check-circle"></i> <?php echo t('Activa', 'Active'); ?>
                        </span>
            </div>
        </div>
    </div>

            <!-- Descargar Plugin -->
            <div class="config-card">
                <h2 style="margin-bottom: 1.5rem; color: #1f2937;">
                    <i class="fas fa-download"></i> <?php echo t('Descargar Plugin', 'Download Plugin'); ?>
                </h2>
                
                <div class="config-section">
                    <h3 style="margin-bottom: 1rem; color: #374151;">
                        <i class="fas fa-box"></i> <?php echo t('Descargar Plugin', 'Download Plugin'); ?>
                    </h3>
                    <p style="color: #6b7280; margin-bottom: 1.5rem;">
                        <?php echo t('Descarga el plugin de DiscogsSync personalizado para tu sitio. El plugin incluye tu clave de licencia y está configurado para tu dominio.', 'Download the custom DiscogsSync plugin for your site. The plugin includes your license key and is configured for your domain.'); ?>
                    </p>
                    
                    <div style="text-align: center;">
                        <a href="dwoosync.zip" download="dwoosync.zip" class="btn-download" style="display: inline-block; text-decoration: none;">
                            <i class="fas fa-download"></i> <?php echo t('Descargar Plugin', 'Download Plugin'); ?>
                        </a>
                    </div>
                </div>
        </div>

            <!-- Instrucciones de Instalación -->
            <div class="config-card">
                <h2 style="margin-bottom: 1.5rem; color: #1f2937;">
                    <i class="fas fa-cog"></i> <?php echo t('Instrucciones de Instalación', 'Installation Instructions'); ?>
                </h2>
                
                <div class="step">
                <div class="step-number">1</div>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #374151;"><?php echo t('Subir el Plugin', 'Upload the Plugin'); ?></h4>
                        <p style="margin: 0; color: #6b7280;">
                            <?php echo t('Sube el archivo ZIP descargado a tu sitio WordPress a través del panel de administración:', 'Upload the downloaded ZIP file to your WordPress site through the admin panel:'); ?> 
                            <strong><?php echo t('Plugins → Añadir nuevo → Subir plugin', 'Plugins → Add New → Upload Plugin'); ?></strong>
                        </p>
                    </div>
            </div>

                <div class="step">
                <div class="step-number">2</div>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #374151;"><?php echo t('Activar el Plugin', 'Activate the Plugin'); ?></h4>
                        <p style="margin: 0; color: #6b7280;">
                            <?php echo t('Una vez subido, ve a', 'Once uploaded, go to'); ?> <strong><?php echo t('Plugins → Plugins instalados', 'Plugins → Installed Plugins'); ?></strong> <?php echo t('y activa "DiscogsSync".', 'and activate "DiscogsSync".'); ?>
                        </p>
                    </div>
            </div>

                <div class="step">
                <div class="step-number">3</div>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #374151;"><?php echo t('Configurar la Licencia', 'Configure the License'); ?></h4>
                        <p style="margin: 0; color: #6b7280;">
                            <?php echo t('Ve a', 'Go to'); ?> <strong><?php echo t('DiscogsSync → Configuración', 'DiscogsSync → Settings'); ?></strong> <?php echo t('en tu panel de WordPress y pega tu clave de licencia:', 'in your WordPress dashboard and paste your license key:'); ?>
                        </p>
                        <div class="code-block">
                            <?php echo htmlspecialchars($licenseKey); ?>
                    </div>
                </div>
            </div>

                <div class="step">
                <div class="step-number">4</div>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #374151;"><?php echo t('Crear App en Discogs', 'Create App in Discogs'); ?></h4>
                        <p style="margin: 0 0 1rem 0; color: #6b7280;">
                            <?php echo t('Necesitas crear una aplicación en Discogs para obtener las credenciales de la API:', 'You need to create an application in Discogs to get the API credentials:'); ?>
                        </p>
                        <ol style="margin: 0; padding-left: 1.5rem; color: #6b7280;">
                            <li style="margin-bottom: 0.5rem;">
                                <?php echo t('Ve a', 'Go to'); ?> <a href="https://www.discogs.com/developers" target="_blank" style="color: #059669; text-decoration: underline;">Discogs Developers</a>
                            </li>
                            <li style="margin-bottom: 0.5rem;">
                                <?php echo t('Haz clic en "Create a new application"', 'Click on "Create a new application"'); ?>
                            </li>
                            <li style="margin-bottom: 0.5rem;">
                                <?php echo t('Completa el formulario con la información de tu tienda', 'Fill out the form with your store information'); ?>
                            </li>
                            <li style="margin-bottom: 0.5rem;">
                                <?php echo t('Copia la', 'Copy the'); ?> <strong><?php echo t('Clave del cliente', 'Consumer Key'); ?></strong> <?php echo t('y', 'and'); ?> <strong><?php echo t('Información secreta del cliente', 'Consumer Secret'); ?></strong>
                            </li>
                        </ol>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">5</div>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #374151;"><?php echo t('Configurar Credenciales', 'Configure Credentials'); ?></h4>
                        <p style="margin: 0 0 1rem 0; color: #6b7280;">
                            <?php echo t('En la configuración del plugin, pega las credenciales de Discogs:', 'In the plugin settings, paste the Discogs credentials:'); ?>
                        </p>
                        <div class="code-block">
                            <div style="margin-bottom: 0.5rem; color: #9ca3af;"><?php echo t('Clave del cliente:', 'Consumer Key:'); ?></div>
                            <div style="color: #f9fafb;"><?php echo t('[Tu clave del cliente de Discogs]', '[Your Discogs Consumer Key]'); ?></div>
                            <br>
                            <div style="margin-bottom: 0.5rem; color: #9ca3af;"><?php echo t('Información secreta del cliente:', 'Consumer Secret:'); ?></div>
                            <div style="color: #f9fafb;"><?php echo t('[Tu información secreta del cliente de Discogs]', '[Your Discogs Consumer Secret]'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="step">
                    <div class="step-number">6</div>
                    <div>
                        <h4 style="margin: 0 0 0.5rem 0; color: #374151;"><?php echo t('¡Listo para Usar!', 'Ready to Use!'); ?></h4>
                        <p style="margin: 0; color: #6b7280;">
                            <?php echo t('El plugin estará listo para sincronizar productos desde Discogs a tu tienda WooCommerce.', 'The plugin will be ready to sync products from Discogs to your WooCommerce store.'); ?>
                        </p>
                    </div>
            </div>
        </div>

            <!-- Configuración de Discogs API -->
            <div class="config-card">
                <h2 style="margin-bottom: 1.5rem; color: #1f2937;">
                    <i class="fas fa-key"></i> <?php echo t('Configuración de Discogs API', 'Discogs API Configuration'); ?>
                </h2>
                
                <div class="config-section">
                    <h3 style="margin-bottom: 1rem; color: #374151;">
                        <i class="fas fa-info-circle"></i> <?php echo t('¿Por qué necesito credenciales de Discogs?', 'Why do I need Discogs credentials?'); ?>
                    </h3>
                    <p style="color: #6b7280; margin-bottom: 1.5rem;">
                        <?php echo t('Discogs requiere que registres una aplicación para acceder a su API. Esto es necesario para:', 'Discogs requires you to register an application to access their API. This is necessary to:'); ?>
                    </p>
                    <ul style="color: #6b7280; margin-bottom: 1.5rem; padding-left: 1.5rem;">
                        <li style="margin-bottom: 0.5rem;"><?php echo t('Buscar y obtener información de productos', 'Search and retrieve product information'); ?></li>
                        <li style="margin-bottom: 0.5rem;"><?php echo t('Acceder a imágenes y metadatos', 'Access images and metadata'); ?></li>
                        <li style="margin-bottom: 0.5rem;"><?php echo t('Respetar los límites de velocidad de la API', 'Respect API rate limits'); ?></li>
                        <li style="margin-bottom: 0.5rem;"><?php echo t('Identificar tu aplicación en las solicitudes', 'Identify your application in requests'); ?></li>
                    </ul>
                </div>

                <div class="config-section">
                    <h3 style="margin-bottom: 1rem; color: #374151;">
                        <i class="fas fa-external-link-alt"></i> <?php echo t('Enlaces Útiles', 'Useful Links'); ?>
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <a href="https://www.discogs.com/developers" target="_blank" 
                           style="display: block; padding: 1rem; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #374151; transition: all 0.3s ease;"
                           onmouseover="this.style.borderColor='#059669'; this.style.background='#f0fdf4';"
                           onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='#f8fafc';">
                            <i class="fas fa-code" style="color: #059669; margin-right: 0.5rem;"></i>
                            <strong>Discogs Developers</strong><br>
                            <small style="color: #6b7280;"><?php echo t('Crear tu aplicación', 'Create your application'); ?></small>
                        </a>
                        
                        <a href="https://www.discogs.com/developers/#page:authentication" target="_blank" 
                           style="display: block; padding: 1rem; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #374151; transition: all 0.3s ease;"
                           onmouseover="this.style.borderColor='#059669'; this.style.background='#f0fdf4';"
                           onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='#f8fafc';">
                            <i class="fas fa-shield-alt" style="color: #059669; margin-right: 0.5rem;"></i>
                            <strong><?php echo t('Documentación de API', 'API Documentation'); ?></strong><br>
                            <small style="color: #6b7280;"><?php echo t('Guía de autenticación', 'Authentication guide'); ?></small>
                        </a>
                        
                        <a href="https://www.discogs.com/developers/#page:rate-limiting" target="_blank" 
                           style="display: block; padding: 1rem; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; text-decoration: none; color: #374151; transition: all 0.3s ease;"
                           onmouseover="this.style.borderColor='#059669'; this.style.background='#f0fdf4';"
                           onmouseout="this.style.borderColor='#e5e7eb'; this.style.background='#f8fafc';">
                            <i class="fas fa-tachometer-alt" style="color: #059669; margin-right: 0.5rem;"></i>
                            <strong><?php echo t('Límites de Velocidad', 'Rate Limits'); ?></strong><br>
                            <small style="color: #6b7280;"><?php echo t('Información sobre límites', 'Information about limits'); ?></small>
                </a>
            </div>
        </div>

                <div class="config-section">
                    <h3 style="margin-bottom: 1rem; color: #374151;">
                        <i class="fas fa-lightbulb"></i> <?php echo t('Consejos para la Configuración', 'Configuration Tips'); ?>
                    </h3>
                    <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                        <div style="display: flex; align-items: flex-start;">
                            <i class="fas fa-exclamation-triangle" style="color: #f59e0b; margin-right: 0.75rem; margin-top: 0.25rem;"></i>
                            <div>
                                <strong style="color: #92400e;"><?php echo t('Importante:', 'Important:'); ?></strong>
                                <p style="margin: 0.5rem 0 0 0; color: #92400e;">
                                    <?php echo t('Guarda tus credenciales de Discogs en un lugar seguro. No las compartas públicamente.', 'Save your Discogs credentials in a secure place. Do not share them publicly.'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <ul style="color: #6b7280; margin: 0; padding-left: 1.5rem;">
                        <li style="margin-bottom: 0.5rem;"><?php echo t('Usa un nombre descriptivo para tu aplicación (ej: "Mi Tienda - DiscogsSync")', 'Use a descriptive name for your application (e.g., "My Store - DiscogsSync")'); ?></li>
                        <li style="margin-bottom: 0.5rem;"><?php echo t('La URL de la aplicación puede ser la URL de tu tienda', 'The application URL can be your store URL'); ?></li>
                        <li style="margin-bottom: 0.5rem;"><?php echo t('La descripción debe explicar que usas la API para sincronizar productos', 'The description should explain that you use the API to sync products'); ?></li>
                        <li style="margin-bottom: 0.5rem;"><?php echo t('Una vez creada, no podrás ver la información secreta del cliente nuevamente', 'Once created, you will not be able to see the consumer secret again'); ?></li>
                    </ul>
        </div>
    </div>


            <!-- Soporte -->
            <div class="config-card">
                <h2 style="margin-bottom: 1.5rem; color: #1f2937;">
                    <i class="fas fa-life-ring"></i> <?php echo t('¿Necesitas Ayuda?', 'Need Help?'); ?>
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                    <div class="config-section">
                        <h4 style="margin: 0 0 1rem 0; color: #374151;">
                            <i class="fas fa-book"></i> <?php echo t('Documentación', 'Documentation'); ?>
                        </h4>
                        <p style="color: #6b7280; margin-bottom: 1rem;">
                            <?php echo t('Consulta nuestra documentación completa para configurar y usar el plugin.', 'Check our complete documentation to configure and use the plugin.'); ?>
                        </p>
                        <a href="#" class="btn btn-secondary" style="display: inline-block;">
                            <?php echo t('Ver Documentación', 'View Documentation'); ?>
                        </a>
                    </div>
                    
                    <div class="config-section">
                        <h4 style="margin: 0 0 1rem 0; color: #374151;">
                            <i class="fas fa-envelope"></i> <?php echo t('Soporte Técnico', 'Technical Support'); ?>
                        </h4>
                        <p style="color: #6b7280; margin-bottom: 1rem;">
                            <?php echo t('¿Tienes problemas? Nuestro equipo de soporte está aquí para ayudarte.', 'Have problems? Our support team is here to help you.'); ?>
                        </p>
                        <a href="contact.php" class="btn btn-secondary" style="display: inline-block;">
                            <?php echo t('Contactar Soporte', 'Contact Support'); ?>
            </a>
        </div>
    </div>
            </div>

        </div>
    </main>

    <script src="../assets/js/script.js"></script>
    
    <!-- Script del menú móvil -->
    <script>
        function initMobileMenu() {
            console.log('=== INICIANDO MENÚ MÓVIL EN PLUGIN-CONFIG ===');
            
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
        
        // Función para toggle del menú de usuario
        function toggleUserMenu(event) {
            if (event) {
                event.stopPropagation();
            }
            console.log('toggleUserMenu called');
            const userMenu = document.getElementById('userMenu');
            console.log('userMenu element:', userMenu);
            if (userMenu) {
                userMenu.classList.toggle('show');
                console.log('Menu toggled, classes:', userMenu.className);
            } else {
                console.error('userMenu element not found');
            }
        }
        
        // Asegurar que la función esté disponible globalmente
        window.toggleUserMenu = toggleUserMenu;
    </script>
</body>
</html>