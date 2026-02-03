<?php
/**
 * Página de prueba para envío de correos
 */

// Habilitar error reporting para debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Cargar PHPMailer PRIMERO
if (file_exists('../../vendor/autoload.php')) {
    require_once '../../vendor/autoload.php';
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Solo permitir en desarrollo/testing
if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== 'dwoosync.com') {
    die('Esta página solo está disponible en desarrollo');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $testEmail = $_POST['test_email'];
    
    echo "<h2>🔧 Test Email System - Configuración Fija</h2>";
    echo "<p>Enviando a: <strong>$testEmail</strong></p>";
    echo "<p><small>Usando configuración SMTP fija como exito.php</small></p>";
    
    // Verificar PHPMailer
    echo "<h3>📋 Verificación:</h3>";
    echo "<ul>";
    echo "<li><strong>PHPMailer:</strong> " . (class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? '✅ Disponible' : '❌ No encontrado') . "</li>";
    echo "<li><strong>Vendor Autoload:</strong> " . (file_exists('../../vendor/autoload.php') ? '✅ Encontrado' : '❌ No encontrado') . "</li>";
    echo "<li><strong>SMTP Host:</strong> mail.dwoosync.com:465 (fijo)</li>";
    echo "<li><strong>Función sendEmail:</strong> " . (function_exists('sendEmail') ? '✅ Disponible' : '❌ No encontrada') . "</li>";
    echo "</ul>";
    
    $subject = 'Test - dwoosync Welcome Email';
    $message = "
        <h2>Test Email - dwoosync</h2>
        <p>Este es un correo de prueba del sistema de signup.</p>
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
            <h3>Información de Prueba:</h3>
            <p><strong>Email:</strong> $testEmail</p>
            <p><strong>License Key:</strong> <code style='background: #e9ecef; padding: 4px 8px; border-radius: 4px;'>DW123TESTKEY456789</code></p>
            <p><strong>Domain:</strong> example.com</p>
            <p><strong>Plan:</strong> Enterprise</p>
        </div>
        <p>Si recibes este correo, el sistema está funcionando correctamente.</p>
        <p>Saludos,<br>El equipo de dwoosync</p>
    ";
    
    echo "<h3>📨 Intentando enviar correo...</h3>";
    
    // Verificar PHPMailer final
    $phpmailerAvailable = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    echo "<p><strong>Estado final PHPMailer:</strong> " . ($phpmailerAvailable ? '✅ Disponible' : '❌ No disponible') . "</p>";
    
    if ($phpmailerAvailable) {
        echo "<p>🚀 <strong>Enviando correo DIRECTAMENTE (sin función sendEmail)...</strong></p>";
        
        // Flush para mostrar el progreso
        ob_flush();
        flush();
        
        try {
            // USAR PHPMAILER DIRECTAMENTE (como exito.php exitoso)
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuración SMTP directa (igual que test exitoso)
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
            
            // Configurar correo
            $mail->setFrom('support@dwoosync.com', 'DWooSync');
            $mail->addReplyTo('support@dwoosync.com', 'DWooSync');
            $mail->addAddress($testEmail);
            
            // Headers adicionales
            $mail->addCustomHeader('X-Mailer', 'DWooSync System Direct v1.0');
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('Return-Path', 'support@dwoosync.com');
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // ENVIAR
            $result = $mail->send();
            
            echo "<p>📬 Resultado de PHPMailer directo: " . ($result ? 'true' : 'false') . "</p>";
            
        } catch (Exception $e) {
            echo "<p>❌ Excepción en PHPMailer directo: " . $e->getMessage() . "</p>";
            echo "<p>📍 Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "</p>";
            $result = false;
        } catch (Error $e) {
            echo "<p>❌ Error fatal en PHPMailer directo: " . $e->getMessage() . "</p>";
            echo "<p>📍 Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "</p>";
            $result = false;
        }
        
        // Mostrar resultado final
        if ($result) {
            echo "<div style='color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "✅ <strong>Correo enviado exitosamente!</strong>";
            echo "</div>";
        } else {
            echo "<div style='color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "❌ <strong>Error al enviar correo</strong>";
            echo "</div>";
        }
    } else {
        echo "<p>❌ No se puede enviar: PHPMailer no disponible</p>";
        $result = false;
    }
    
    // Mostrar logs
    $logFile = __DIR__ . '/../../logs/email.log';
    if (file_exists($logFile)) {
        echo "<h3>📜 Últimas líneas del log de email:</h3>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; max-height: 300px; overflow-y: auto;'>";
        $lines = file($logFile);
        if ($lines && count($lines) > 0) {
            $lastLines = array_slice($lines, -20); // Últimas 20 líneas
            echo htmlspecialchars(implode('', $lastLines));
        } else {
            echo "📝 Log de email está vacío";
        }
        echo "</pre>";
    } else {
        echo "<p>❌ Archivo de log de email no encontrado en: $logFile</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email System</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="email"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🔧 Test Email System - Configuración Fija</h1>
    <p>Esta página usa el sistema de email <strong>principal</strong> con configuración SMTP fija (como exito.php).</p>
    
    <form method="POST">
        <div class="form-group">
            <label for="test_email">Email de prueba:</label>
            <input type="email" id="test_email" name="test_email" required 
                   placeholder="Ingresa tu email para recibir un correo de prueba">
        </div>
        <button type="submit">📧 Enviar Correo de Prueba</button>
    </form>
    
    <?php if (file_exists(__DIR__ . '/../logs/email.log')): ?>
    <p><small>💡 Los logs de email se almacenan en <code>logs/email.log</code></small></p>
    <?php endif; ?>
</body>
</html>
