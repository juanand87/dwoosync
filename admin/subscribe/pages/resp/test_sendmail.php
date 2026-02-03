<?php
/**
 * Prueba específica de sendmail
 */

require_once 'email_sendmail.php';

echo "<h2>Prueba de Sendmail</h2>";

$testEmail = 'test@example.com'; // Cambia por tu email real

echo "<p><strong>Enviando correo a:</strong> $testEmail</p>";

// Verificar configuración de sendmail
echo "<h3>Configuración de Sendmail:</h3>";
$sendmailPath = ini_get('sendmail_path');
echo "<p><strong>Sendmail Path:</strong> " . ($sendmailPath ? $sendmailPath : 'No configurado') . "</p>";

// Verificar si sendmail existe
if ($sendmailPath) {
    $sendmailExists = file_exists(trim(explode(' ', $sendmailPath)[0]));
    echo "<p><strong>Sendmail existe:</strong> " . ($sendmailExists ? "✓ Sí" : "✗ No") . "</p>";
} else {
    echo "<p><strong>Sendmail existe:</strong> ✗ No configurado</p>";
}

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

// Método 1: Sendmail directo
echo "<h4>1. Sendmail Directo:</h4>";
$result1 = sendEmailSendmailDirect($testEmail, 'Test Sendmail Direct', 'Test message from sendmail direct');
echo "<p>" . ($result1 ? "✓ Exitoso" : "✗ Falló") . "</p>";

// Método 2: Sendmail pipe
echo "<h4>2. Sendmail Pipe:</h4>";
$result2 = sendEmailSendmailPipe($testEmail, 'Test Sendmail Pipe', 'Test message from sendmail pipe');
echo "<p>" . ($result2 ? "✓ Exitoso" : "✗ Falló") . "</p>";

// Método 3: mail() nativo
echo "<h4>3. mail() Nativo:</h4>";
$result3 = sendEmailNative($testEmail, 'Test Mail Native', 'Test message from mail native');
echo "<p>" . ($result3 ? "✓ Exitoso" : "✗ Falló") . "</p>";

// Método 4: PHPMailer
echo "<h4>4. PHPMailer SMTP:</h4>";
$result4 = sendEmailPHPMailer($testEmail, 'Test PHPMailer', 'Test message from PHPMailer');
echo "<p>" . ($result4 ? "✓ Exitoso" : "✗ Falló") . "</p>";

// Información del servidor
echo "<h3>Información del servidor:</h3>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>OS:</strong> " . php_uname() . "</p>";

// Verificar funciones disponibles
echo "<h3>Funciones disponibles:</h3>";
echo "<p><strong>exec():</strong> " . (function_exists('exec') ? "✓ Disponible" : "✗ No disponible") . "</p>";
echo "<p><strong>popen():</strong> " . (function_exists('popen') ? "✓ Disponible" : "✗ No disponible") . "</p>";
echo "<p><strong>mail():</strong> " . (function_exists('mail') ? "✓ Disponible" : "✗ No disponible") . "</p>";
echo "<p><strong>PHPMailer:</strong> " . (class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? "✓ Disponible" : "✗ No disponible") . "</p>";

// Resumen
echo "<h3>Resumen:</h3>";
$totalMethods = 4;
$workingMethods = ($result1 ? 1 : 0) + ($result2 ? 1 : 0) + ($result3 ? 1 : 0) + ($result4 ? 1 : 0);
echo "<p><strong>Métodos funcionando:</strong> $workingMethods de $totalMethods</p>";

if ($workingMethods > 0) {
    echo "<p style='color: green;'><strong>✓ Al menos un método está funcionando</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Ningún método está funcionando</strong></p>";
}

// Mostrar configuración completa
echo "<h3>Configuración completa de PHP:</h3>";
echo "<pre>";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "SMTP Port: " . ini_get('smtp_port') . "\n";
echo "Sendmail From: " . ini_get('sendmail_from') . "\n";
echo "Sendmail Path: " . ini_get('sendmail_path') . "\n";
echo "Mail Log: " . ini_get('mail.log') . "\n";
echo "Sendmail Args: " . ini_get('sendmail_args') . "\n";
echo "</pre>";
?>

