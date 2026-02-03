<?php
/**
 * Prueba de servicios de email externos
 */

require_once 'email_external.php';

echo "<h2>Prueba de Servicios de Email Externos</h2>";

$testEmail = 'test@example.com'; // Cambia por tu email real

echo "<p><strong>Enviando correo a:</strong> $testEmail</p>";

// Probar correo de bienvenida
echo "<h3>Prueba de correo de bienvenida:</h3>";
$result = sendWelcomeEmail(
    $testEmail,
    'Test User',
    'Premium',
    'DISC-TEST123456',
    'test.com',
    date('Y-m-d H:i:s', strtotime('+1 month')),
    false
);

if ($result) {
    echo "<p style='color: green;'><strong>✓ Correo de bienvenida enviado exitosamente</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Error enviando correo de bienvenida</strong></p>";
}

// Probar cada método individualmente
echo "<h3>Prueba individual de métodos:</h3>";

// Método 1: SendGrid
echo "<h4>1. SendGrid API:</h4>";
$result1 = sendEmailSendGrid($testEmail, 'Test SendGrid', '<h3>Test SendGrid</h3><p>Test message from SendGrid API</p>');
echo "<p>" . ($result1 ? "✓ Exitoso" : "✗ Falló (API key no configurada)") . "</p>";

// Método 2: Mailgun
echo "<h4>2. Mailgun API:</h4>";
$result2 = sendEmailMailgun($testEmail, 'Test Mailgun', '<h3>Test Mailgun</h3><p>Test message from Mailgun API</p>');
echo "<p>" . ($result2 ? "✓ Exitoso" : "✗ Falló (API key no configurada)") . "</p>";

// Método 3: PHPMailer
echo "<h4>3. PHPMailer SMTP:</h4>";
$result3 = sendEmailPHPMailer($testEmail, 'Test PHPMailer', '<h3>Test PHPMailer</h3><p>Test message from PHPMailer</p>');
echo "<p>" . ($result3 ? "✓ Exitoso" : "✗ Falló") . "</p>";

// Método 4: Sendmail
echo "<h4>4. Sendmail Directo:</h4>";
$result4 = sendEmailSendmail($testEmail, 'Test Sendmail', '<h3>Test Sendmail</h3><p>Test message from Sendmail</p>');
echo "<p>" . ($result4 ? "✓ Exitoso" : "✗ Falló") . "</p>";

// Método 5: mail() nativo
echo "<h4>5. mail() Nativo:</h4>";
$result5 = sendEmailNative($testEmail, 'Test Mail Native', '<h3>Test Mail Native</h3><p>Test message from mail native</p>');
echo "<p>" . ($result5 ? "✓ Exitoso" : "✗ Falló") . "</p>";

// Verificar servicios disponibles
echo "<h3>Servicios disponibles:</h3>";
echo "<p><strong>cURL:</strong> " . (function_exists('curl_init') ? "✓ Disponible" : "✗ No disponible") . "</p>";
echo "<p><strong>JSON:</strong> " . (function_exists('json_encode') ? "✓ Disponible" : "✗ No disponible") . "</p>";
echo "<p><strong>PHPMailer:</strong> " . (class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? "✓ Disponible" : "✗ No disponible") . "</p>";

// Información del servidor
echo "<h3>Información del servidor:</h3>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>OS:</strong> " . php_uname() . "</p>";

// Resumen
echo "<h3>Resumen:</h3>";
$totalMethods = 5;
$workingMethods = ($result1 ? 1 : 0) + ($result2 ? 1 : 0) + ($result3 ? 1 : 0) + ($result4 ? 1 : 0) + ($result5 ? 1 : 0);
echo "<p><strong>Métodos funcionando:</strong> $workingMethods de $totalMethods</p>";

if ($workingMethods > 0) {
    echo "<p style='color: green;'><strong>✓ Al menos un método está funcionando</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Ningún método está funcionando</strong></p>";
}

// Recomendaciones
echo "<h3>Recomendaciones:</h3>";
if (!$result1 && !$result2) {
    echo "<p style='color: orange;'><strong>⚠️ Considera configurar SendGrid o Mailgun para mejor deliverability:</strong></p>";
    echo "<ul>";
    echo "<li><strong>SendGrid:</strong> Gratis hasta 100 emails/día. Registro en sendgrid.com</li>";
    echo "<li><strong>Mailgun:</strong> Gratis hasta 5,000 emails/mes. Registro en mailgun.com</li>";
    echo "</ul>";
}

if ($result3 || $result4 || $result5) {
    echo "<p style='color: green;'><strong>✓ Los métodos locales están funcionando</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Los métodos locales no están funcionando. Considera usar un servicio externo.</strong></p>";
}
?>

