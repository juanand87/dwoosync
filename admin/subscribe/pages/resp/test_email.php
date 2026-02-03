<?php
/**
 * Script de prueba para verificar el envío de correos
 */

require_once 'simple_email.php';

echo "<h2>Prueba de Envío de Correo</h2>";

// Datos de prueba
$testEmail = 'test@example.com'; // Cambia por tu email real
$firstName = 'Juan';
$planName = 'Premium';
$licenseKey = 'DISC-TEST123456';
$domain = 'test.com';
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
$isEnglish = false;

echo "<p><strong>Enviando correo a:</strong> $testEmail</p>";
echo "<p><strong>Nombre:</strong> $firstName</p>";
echo "<p><strong>Plan:</strong> $planName</p>";

// Intentar enviar
$result = sendWelcomeEmail($testEmail, $firstName, $planName, $licenseKey, $domain, $expiresAt, $isEnglish);

if ($result) {
    echo "<p style='color: green;'><strong>✓ Correo enviado exitosamente</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Error enviando correo</strong></p>";
}

echo "<h3>Verificación del servidor</h3>";
echo "<p><strong>Función mail():</strong> " . (function_exists('mail') ? 'Disponible' : 'NO disponible') . "</p>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// Verificar configuración de mail
$mailConfig = ini_get('sendmail_path');
echo "<p><strong>Sendmail path:</strong> " . ($mailConfig ? $mailConfig : 'No configurado') . "</p>";

$smtpConfig = ini_get('SMTP');
echo "<p><strong>SMTP:</strong> " . ($smtpConfig ? $smtpConfig : 'No configurado') . "</p>";

$smtpPort = ini_get('smtp_port');
echo "<p><strong>SMTP Port:</strong> " . ($smtpPort ? $smtpPort : 'No configurado') . "</p>";
?>

