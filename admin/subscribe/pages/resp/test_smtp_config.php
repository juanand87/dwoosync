<?php
/**
 * Prueba de configuración SMTP
 */

require_once 'email_smtp_config.php';

echo "<h2>Prueba de Configuración SMTP</h2>";

// Mostrar configuración actual
echo "<h3>Configuración actual:</h3>";
echo "<p><strong>SMTP:</strong> " . ini_get('SMTP') . "</p>";
echo "<p><strong>SMTP Port:</strong> " . ini_get('smtp_port') . "</p>";
echo "<p><strong>Sendmail From:</strong> " . ini_get('sendmail_from') . "</p>";
echo "<p><strong>Sendmail Path:</strong> " . ini_get('sendmail_path') . "</p>";

// Probar envío
$testEmail = 'test@example.com'; // Cambia por tu email real
echo "<h3>Prueba de envío:</h3>";
echo "<p><strong>Enviando correo a:</strong> $testEmail</p>";

$result = sendTestEmail($testEmail);

if ($result) {
    echo "<p style='color: green;'><strong>✓ Correo de prueba enviado exitosamente</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Error enviando correo de prueba</strong></p>";
}

// Probar correo de bienvenida
echo "<h3>Prueba de correo de bienvenida:</h3>";
$welcomeResult = sendWelcomeEmail(
    $testEmail,
    'Test User',
    'Premium',
    'DISC-TEST123456',
    'test.com',
    date('Y-m-d H:i:s', strtotime('+1 month')),
    false
);

if ($welcomeResult) {
    echo "<p style='color: green;'><strong>✓ Correo de bienvenida enviado exitosamente</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Error enviando correo de bienvenida</strong></p>";
}

echo "<h3>Información del servidor:</h3>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>OS:</strong> " . php_uname() . "</p>";

// Verificar si mail() está disponible
echo "<h3>Verificaciones:</h3>";
echo "<p><strong>Función mail():</strong> " . (function_exists('mail') ? '✓ Disponible' : '✗ NO disponible') . "</p>";

// Verificar configuración de sendmail
$sendmailPath = ini_get('sendmail_path');
if ($sendmailPath) {
    echo "<p><strong>Sendmail configurado:</strong> ✓ $sendmailPath</p>";
} else {
    echo "<p><strong>Sendmail configurado:</strong> ✗ No configurado</p>";
}
?>

