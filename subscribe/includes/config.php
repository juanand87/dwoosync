<?php
/**
 * Configuración del sitio de suscripciones - Versión sin problemas de sesión
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'dwoosync_bd');
define('DB_USER', 'dwoosync_user');
define('DB_PASS', '5802863aA$$');
define('DB_CHARSET', 'utf8mb4');

// Configuración del sitio
define('SITE_URL', 'https://dwoosync/subscribe');
define('API_URL', 'https://dwoosync/api');
define('ADMIN_URL', 'https://dwoosync/admin');

// Configuración de pagos (Stripe)
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...'); // Cambiar por tu clave pública
define('STRIPE_SECRET_KEY', 'sk_test_...'); // Cambiar por tu clave secreta
define('STRIPE_WEBHOOK_SECRET', 'whsec_...'); // Cambiar por tu webhook secret

// Configuración de email
define('SMTP_HOST', 'mail.dwoosync.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'support@dwoosync.com');
define('SMTP_PASSWORD', '5802863aA$$');
define('FROM_EMAIL', 'support@dwoosync.com');
define('FROM_NAME', 'DWooSync');

// Configuración de seguridad
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hora

// Configuración de errores
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Función para iniciar sesión de forma segura
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar sesión solo si no está iniciada
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
        session_start();
    }
}

// Iniciar sesión automáticamente
startSecureSession();

// La notificación se incluirá manualmente en cada página después de startSecureSession()
?>
