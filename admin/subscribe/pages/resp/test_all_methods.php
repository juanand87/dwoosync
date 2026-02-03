<?php
/**
 * Prueba todos los métodos de envío de correos
 */

require_once 'email_curl.php';

echo "<h2>Prueba de Todos los Métodos de Envío</h2>";

$testEmail = 'test@example.com'; // Cambia por tu email real
$subject = 'Test Email from DWooSync';
$message = '<h3>Test Email</h3><p>This is a test email from DWooSync.</p>';

echo "<p><strong>Enviando correo a:</strong> $testEmail</p>";

// Probar correo de bienvenida (que usa todos los métodos)
echo "<h3>Probando correo de bienvenida:</h3>";
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

// Verificar disponibilidad de métodos
echo "<h3>Verificación de métodos disponibles:</h3>";

// PHPMailer
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "<p style='color: green;'>✓ PHPMailer disponible</p>";
} else {
    echo "<p style='color: red;'>✗ PHPMailer NO disponible</p>";
}

// mail() nativo
if (function_exists('mail')) {
    echo "<p style='color: green;'>✓ Función mail() disponible</p>";
} else {
    echo "<p style='color: red;'>✗ Función mail() NO disponible</p>";
}

// cURL
if (function_exists('curl_init')) {
    echo "<p style='color: green;'>✓ cURL disponible</p>";
} else {
    echo "<p style='color: red;'>✗ cURL NO disponible</p>";
}

// Configuración del servidor
echo "<h3>Configuración del servidor:</h3>";
echo "<p><strong>SMTP:</strong> " . ini_get('SMTP') . "</p>";
echo "<p><strong>SMTP Port:</strong> " . ini_get('smtp_port') . "</p>";
echo "<p><strong>Sendmail From:</strong> " . ini_get('sendmail_from') . "</p>";
echo "<p><strong>Sendmail Path:</strong> " . ini_get('sendmail_path') . "</p>";

// Información del servidor
echo "<h3>Información del servidor:</h3>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>OS:</strong> " . php_uname() . "</p>";

// Probar cada método individualmente
echo "<h3>Prueba individual de métodos:</h3>";

// Método 1: PHPMailer
echo "<h4>1. PHPMailer SMTP:</h4>";
$result1 = sendEmailSMTPAuth($testEmail, $subject, $message);
echo "<p>" . ($result1 ? "✓ Exitoso" : "✗ Falló") . "</p>";

// Método 2: mail() nativo
echo "<h4>2. mail() nativo:</h4>";
$result2 = sendEmailNative($testEmail, $subject, $message);
echo "<p>" . ($result2 ? "✓ Exitoso" : "✗ Falló") . "</p>";

// Método 3: cURL
echo "<h4>3. cURL SMTP:</h4>";
$result3 = sendEmailCurl($testEmail, $subject, $message);
echo "<p>" . ($result3 ? "✓ Exitoso" : "✗ Falló") . "</p>";

echo "<h3>Resumen:</h3>";
$totalMethods = 3;
$workingMethods = ($result1 ? 1 : 0) + ($result2 ? 1 : 0) + ($result3 ? 1 : 0);
echo "<p><strong>Métodos funcionando:</strong> $workingMethods de $totalMethods</p>";

if ($workingMethods > 0) {
    echo "<p style='color: green;'><strong>✓ Al menos un método está funcionando</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Ningún método está funcionando</strong></p>";
}
?>

