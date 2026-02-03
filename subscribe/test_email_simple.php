<?php
/**
 * Simple Email Test
 * Test the email sending functionality step by step
 */

session_start();

// Force set session for testing
$_SESSION['subscriber_id'] = 150; // From the logs

echo "<h3>Simple Email Test</h3>";
echo "<p>Testing email sending with subscriber_id=150, billing_cycle_id=108</p>";

try {
    // Include required files
    require_once '../config/database.php';
    require_once '../vendor/autoload.php';
    
    // Check database connection
    echo "<p>✓ Database connection: OK</p>";
    
    // Get user info
    $userStmt = $db->prepare("SELECT email, first_name FROM subscribers WHERE id = ?");
    $userStmt->execute([150]);
    $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userInfo) {
        echo "<p>✓ User found: {$userInfo['first_name']} ({$userInfo['email']})</p>";
    } else {
        echo "<p>❌ User not found</p>";
        exit;
    }
    
    // Get billing cycle info
    $billingStmt = $db->prepare("SELECT plan_type, status FROM billing_cycles WHERE id = ? AND subscriber_id = ?");
    $billingStmt->execute([108, 150]);
    $billingInfo = $billingStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($billingInfo) {
        echo "<p>✓ Billing cycle found: {$billingInfo['plan_type']} ({$billingInfo['status']})</p>";
    } else {
        echo "<p>❌ Billing cycle not found</p>";
        exit;
    }
    
    // Try to send email
    echo "<h4>Sending Email...</h4>";
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = 'mail.dwoosync.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'support@dwoosync.com';
    $mail->Password = '5802863aA$$';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Timeout = 15;
    $mail->SMTPKeepAlive = false;
    
    echo "<p>✓ SMTP configured</p>";
    
    // Set email content
    $mail->setFrom('support@dwoosync.com', 'DWooSync');
    $mail->addReplyTo('support@dwoosync.com', 'DWooSync');
    $mail->addAddress($userInfo['email'], $userInfo['first_name']);
    
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - Account Activation';
    $mail->Body = "<h3>Hello {$userInfo['first_name']}!</h3><p>This is a test activation email.</p><p>Plan: {$billingInfo['plan_type']}</p>";
    $mail->AltBody = "Hello {$userInfo['first_name']}! This is a test activation email. Plan: {$billingInfo['plan_type']}";
    
    echo "<p>✓ Email content prepared</p>";
    
    // Send email
    $result = $mail->send();
    
    if ($result) {
        echo "<p style='color: green;'>✅ Email sent successfully!</p>";
        echo "<p>Sent to: {$userInfo['email']}</p>";
    } else {
        echo "<p style='color: red;'>❌ Email failed to send</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
}

echo "<h4>Testing Complete</h4>";
?>

// Incluir configuración
require_once 'includes/config.php';
require_once 'includes/email.php';

// Configurar sesión
session_start();

echo "<h2>Prueba de Envío de Correo</h2>";

// Email de prueba
$testEmail = 'juan@example.com'; // Cambia por tu email real
$subject = 'Prueba de DWooSync - ' . date('Y-m-d H:i:s');
$message = '<h3>Prueba de correo</h3><p>Este es un correo de prueba enviado desde DWooSync.</p>';

echo "<p><strong>Enviando correo a:</strong> $testEmail</p>";
echo "<p><strong>Asunto:</strong> $subject</p>";

// Intentar enviar
$result = sendEmail($testEmail, $subject, $message, true);

if ($result) {
    echo "<p style='color: green;'><strong>✓ Correo enviado exitosamente</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Error enviando correo</strong></p>";
}

echo "<h3>Log de Email</h3>";
echo "<pre>";
if (file_exists('logs/email.log')) {
    echo htmlspecialchars(file_get_contents('logs/email.log'));
} else {
    echo "No se encontró el archivo de log.";
}
echo "</pre>";

echo "<h3>Configuración SMTP</h3>";
echo "<ul>";
echo "<li><strong>SMTP_HOST:</strong> " . (defined('SMTP_HOST') ? SMTP_HOST : 'NO DEFINIDO') . "</li>";
echo "<li><strong>SMTP_PORT:</strong> " . (defined('SMTP_PORT') ? SMTP_PORT : 'NO DEFINIDO') . "</li>";
echo "<li><strong>SMTP_USERNAME:</strong> " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NO DEFINIDO') . "</li>";
echo "<li><strong>FROM_EMAIL:</strong> " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'NO DEFINIDO') . "</li>";
echo "<li><strong>FROM_NAME:</strong> " . (defined('FROM_NAME') ? FROM_NAME : 'NO DEFINIDO') . "</li>";
echo "</ul>";

echo "<h3>Verificación de PHPMailer</h3>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color: green;'>✓ PHPMailer está disponible</p>";
} else {
    echo "<p style='color: red;'>✗ PHPMailer NO está disponible</p>";
}

echo "<h3>Verificación de mail() nativo</h3>";
if (function_exists('mail')) {
    echo "<p style='color: green;'>✓ Función mail() está disponible</p>";
} else {
    echo "<p style='color: red;'>✗ Función mail() NO está disponible</p>";
}
?>
