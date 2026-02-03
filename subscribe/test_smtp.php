<?php
/**
 * Test de configuración SMTP
 * Este archivo prueba el envío de correos
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/email_templates.php';

echo "=== TEST DE CONFIGURACIÓN SMTP ===\n\n";

echo "Configuración SMTP:\n";
echo "Host: " . SMTP_HOST . "\n";
echo "Port: " . SMTP_PORT . "\n";
echo "Username: " . SMTP_USERNAME . "\n";
echo "From Email: " . FROM_EMAIL . "\n";
echo "From Name: " . FROM_NAME . "\n\n";

// Email de prueba
$testEmail = 'test@example.com'; // Cambiar por tu email real para probar

echo "--- Probando correo de bienvenida ---\n";

$emailTemplate = getWelcomeEmailTemplate(
    'Juan',                    // Nombre
    $testEmail,               // Email
    'Premium',                // Plan
    'DISC12345678',          // License Key
    'www.mitienda.com',      // Domain
    'en'                     // Idioma
);

echo "Subject: " . $emailTemplate['subject'] . "\n";
echo "Enviando correo a: " . $testEmail . "\n\n";

try {
    $result = sendEmail($testEmail, $emailTemplate['subject'], $emailTemplate['message'], true);
    
    if ($result) {
        echo "✓ Correo enviado exitosamente!\n";
    } else {
        echo "✗ Error al enviar correo\n";
    }
} catch (Exception $e) {
    echo "✗ Excepción: " . $e->getMessage() . "\n";
}

echo "\n--- Probando correo de activación ---\n";

$activationTemplate = getAccountActivatedEmailTemplate(
    'Juan',                           // Nombre
    $testEmail,                       // Email
    'Premium',                        // Plan
    'DISC12345678',                   // License Key
    'www.mitienda.com',              // Domain
    date('Y-m-d H:i:s', strtotime('+30 days')), // Expires At
    'en'                              // Idioma
);

echo "Subject: " . $activationTemplate['subject'] . "\n";
echo "Enviando correo a: " . $testEmail . "\n\n";

try {
    $result = sendEmail($testEmail, $activationTemplate['subject'], $activationTemplate['message'], true);
    
    if ($result) {
        echo "✓ Correo enviado exitosamente!\n";
    } else {
        echo "✗ Error al enviar correo\n";
    }
} catch (Exception $e) {
    echo "✗ Excepción: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL TEST ===\n";
?>




