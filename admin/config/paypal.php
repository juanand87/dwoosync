<?php
/**
 * Configuración de PayPal
 */

// Configuración de PayPal Sandbox (para pruebas)
define('PAYPAL_CLIENT_ID', 'ARYSVZGuaEdigjhdE3zwIGLhwu9zjbJGXNWdmBSx_JFGR20EkSBva6H1IhFYVzL3Tcej7ndeA5D5Hkky');
define('PAYPAL_CLIENT_SECRET', 'YOUR_PAYPAL_CLIENT_SECRET'); // Obtener de PayPal Developer Dashboard
define('PAYPAL_ENVIRONMENT', 'sandbox'); // 'sandbox' para pruebas, 'live' para producción

// URLs de PayPal
if (PAYPAL_ENVIRONMENT === 'sandbox') {
    define('PAYPAL_BASE_URL', 'https://api.sandbox.paypal.com');
    define('PAYPAL_WEBHOOK_URL', 'https://api.sandbox.paypal.com/v1/notifications/webhooks');
} else {
    define('PAYPAL_BASE_URL', 'https://api.paypal.com');
    define('PAYPAL_WEBHOOK_URL', 'https://api.paypal.com/v1/notifications/webhooks');
}

// Plan IDs de PayPal
define('PAYPAL_PREMIUM_PLAN_ID', 'P-4BX32647SP6212626NDIASRA');
define('PAYPAL_ENTERPRISE_PLAN_ID', 'P-45V717624S037913FNDENTUY');

// Configuración de webhooks
define('PAYPAL_WEBHOOK_VERIFY_URL', PAYPAL_BASE_URL . '/v1/notifications/verify-webhook-signature');
?>



