<?php
/**
 * Test de email usando la metodología de exito.php
 */

// Cargar PHPMailer
require_once '../vendor/autoload.php';
require_once 'includes/config.php';

// Solo permitir en desarrollo
if ($_SERVER['HTTP_HOST'] !== 'localhost') {
    die('Solo disponible en desarrollo');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $testEmail = $_POST['test_email'];
    
    echo "<h2>🧪 Test Email usando metodología de exito.php</h2>";
    echo "<p>Enviando a: <strong>$testEmail</strong></p>";
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        echo "<p>✅ PHPMailer instanciado</p>";
        
        // Configuración SMTP exacta de ajax_send_email.php
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
        
        echo "<p>✅ Configuración SMTP establecida</p>";
        
        // Configurar correo
        $mail->setFrom('support@dwoosync.com', 'DWooSync');
        $mail->addReplyTo('support@dwoosync.com', 'DWooSync');
        $mail->addAddress($testEmail);
        
        echo "<p>✅ Remitente y destinatario configurados</p>";
        
        // Headers adicionales (igual que exito.php)
        $mail->addCustomHeader('X-Mailer', 'DWooSync System TEST v1.0');
        $mail->addCustomHeader('X-Priority', '3');
        $mail->addCustomHeader('Return-Path', 'support@dwoosync.com');
        
        // Contenido del email
        $subject = 'Test - DWooSync Email System';
        $message = "
        <html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #2c5aa0; text-align: center;'>Test Email - DWooSync</h2>
            <p>Este es un correo de prueba del sistema DWooSync.</p>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4 style='margin-top: 0; color: #2c5aa0;'>Información de Prueba:</h4>
                <p><strong>Email:</strong> $testEmail</p>
                <p><strong>License Key:</strong> DW123TESTKEY456789</p>
                <p><strong>Plan:</strong> Enterprise</p>
                <p><strong>Fecha:</strong> " . date('Y-m-d H:i:s') . "</p>
            </div>
            
            <p>Si recibes este correo, significa que el sistema de email está funcionando correctamente.</p>
            <p>Saludos,<br>El equipo de DWooSync</p>
        </div>
        </body></html>";
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        echo "<p>✅ Contenido del email configurado</p>";
        echo "<p>🚀 <strong>Enviando email...</strong></p>";
        
        // Flush para mostrar progreso
        ob_flush();
        flush();
        
        // ENVIAR
        $result = $mail->send();
        
        if ($result) {
            echo "<div style='color: green; background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
            echo "✅ <strong>¡Correo enviado exitosamente!</strong><br>";
            echo "📧 Enviado a: $testEmail<br>";
            echo "📊 Método: SMTP Directo (igual que exito.php)<br>";
            echo "⚙️ Configuración: mail.dwoosync.com:465 SSL";
            echo "</div>";
        } else {
            echo "<div style='color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
            echo "❌ <strong>Error al enviar correo</strong>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='color: red; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
        echo "❌ <strong>Excepción:</strong> " . $e->getMessage() . "<br>";
        echo "📍 <strong>Archivo:</strong> " . $e->getFile() . " línea " . $e->getLine();
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Email - Metodología Exito.php</title>
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
    <h1>🧪 Test Email - Metodología Exito.php</h1>
    <p>Esta página usa la <strong>misma configuración SMTP</strong> que utiliza exito.php exitosamente.</p>
    
    <form method="POST">
        <div class="form-group">
            <label for="test_email">Email de prueba:</label>
            <input type="email" id="test_email" name="test_email" required 
                   placeholder="Ingresa tu email para recibir un correo de prueba">
        </div>
        <button type="submit">📧 Enviar Correo (Método Exito.php)</button>
    </form>
    
    <div style="background: #e3f2fd; padding: 15px; border-radius: 5px; margin-top: 20px;">
        <h3>💡 Diferencias con el test anterior:</h3>
        <ul>
            <li>✅ <strong>Configuración SMTP directa</strong> (sin constantes)</li>
            <li>✅ <strong>Puerto 465 + SMTPS</strong> (configuración original)</li>
            <li>✅ <strong>Headers adicionales</strong> (X-Mailer, X-Priority, Return-Path)</li>
            <li>✅ <strong>Timeouts y encoding</strong> específicos</li>
        </ul>
    </div>
</body>
</html>