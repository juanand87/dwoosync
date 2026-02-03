<?php
/**
 * Envío de correos usando SMTP con sockets (sin dependencias externas)
 */

function sendEmailSMTP($to, $subject, $message, $isHTML = false) {
    // Configuración SMTP
    $smtp_host = 'mail.dwoosync.com';
    $smtp_port = 465;
    $smtp_username = 'support@dwoosync.com';
    $smtp_password = '5802863aA$$';
    $from_email = 'support@dwoosync.com';
    $from_name = 'DWooSync';
    
    try {
        // Crear contexto SSL
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        // Conectar al servidor SMTP
        $socket = stream_socket_client(
            "ssl://$smtp_host:$smtp_port",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            error_log("Error conectando a SMTP: $errstr ($errno)");
            return false;
        }
        
        // Leer respuesta inicial
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) != '220') {
            error_log("Error SMTP inicial: $response");
            fclose($socket);
            return false;
        }
        
        // EHLO
        fputs($socket, "EHLO dwoosync.com\r\n");
        $response = fgets($socket, 512);
        
        // STARTTLS (si es necesario)
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket, 512);
        
        // Autenticación
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 512);
        
        // Usuario
        fputs($socket, base64_encode($smtp_username) . "\r\n");
        $response = fgets($socket, 512);
        
        // Contraseña
        fputs($socket, base64_encode($smtp_password) . "\r\n");
        $response = fgets($socket, 512);
        
        if (substr($response, 0, 3) != '235') {
            error_log("Error autenticación SMTP: $response");
            fclose($socket);
            return false;
        }
        
        // MAIL FROM
        fputs($socket, "MAIL FROM:<$from_email>\r\n");
        $response = fgets($socket, 512);
        
        // RCPT TO
        fputs($socket, "RCPT TO:<$to>\r\n");
        $response = fgets($socket, 512);
        
        // DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 512);
        
        // Headers del correo
        $headers = "From: $from_name <$from_email>\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Reply-To: $from_email\r\n";
        $headers .= "Return-Path: $from_email\r\n";
        $headers .= "X-Mailer: DWooSync System\r\n";
        $headers .= "X-Priority: 3\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if ($isHTML) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        $headers .= "\r\n";
        
        // Enviar headers y mensaje
        fputs($socket, $headers);
        fputs($socket, $message);
        fputs($socket, "\r\n.\r\n");
        
        $response = fgets($socket, 512);
        
        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        if (substr($response, 0, 3) == '250') {
            error_log("✓ Email enviado exitosamente a: $to");
            return true;
        } else {
            error_log("✗ Error enviando email: $response");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("✗ Error SMTP: " . $e->getMessage());
        return false;
    }
}

function sendWelcomeEmail($email, $firstName, $planName, $licenseKey, $domain, $expiresAt, $isEnglish = false) {
    $subject = $isEnglish ? 'Welcome to DWooSync!' : '¡Bienvenido a DWooSync!';
    
    $message = $isEnglish ? "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Welcome to DWooSync</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
    <div style='background: #1db954; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
        <h1 style='margin: 0; font-size: 24px;'>Welcome to DWooSync!</h1>
    </div>
    
    <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #e9ecef;'>
        <p>Hello <strong>$firstName</strong>,</p>
        
        <p>Your account has been successfully activated!</p>
        
        <div style='background: white; padding: 20px; border-radius: 6px; margin: 20px 0;'>
            <h3 style='margin-top: 0; color: #1f2937;'>Account Details</h3>
            <p><strong>Plan:</strong> $planName</p>
            <p><strong>License Key:</strong> <code style='background: #f3f4f6; padding: 2px 6px; border-radius: 4px;'>$licenseKey</code></p>
            <p><strong>Domain:</strong> $domain</p>
            <p><strong>Expires:</strong> " . date('Y-m-d', strtotime($expiresAt)) . "</p>
        </div>
        
        <p>You can now start using DWooSync to import your Discogs collection.</p>
        
        <div style='text-align: center; margin: 30px 0;'>
            <a href='https://dwoosync.com/subscribe/pages/dashboard.php' 
               style='background: #1db954; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                Access Dashboard
            </a>
        </div>
        
        <p style='color: #6b7280; font-size: 14px;'>
            Best regards,<br>
            The DWooSync Team
        </p>
    </div>
</body>
</html>
    " : "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>¡Bienvenido a DWooSync!</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
    <div style='background: #1db954; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
        <h1 style='margin: 0; font-size: 24px;'>¡Bienvenido a DWooSync!</h1>
    </div>
    
    <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #e9ecef;'>
        <p>Hola <strong>$firstName</strong>,</p>
        
        <p>¡Tu cuenta ha sido activada exitosamente!</p>
        
        <div style='background: white; padding: 20px; border-radius: 6px; margin: 20px 0;'>
            <h3 style='margin-top: 0; color: #1f2937;'>Detalles de la cuenta</h3>
            <p><strong>Plan:</strong> $planName</p>
            <p><strong>Clave de licencia:</strong> <code style='background: #f3f4f6; padding: 2px 6px; border-radius: 4px;'>$licenseKey</code></p>
            <p><strong>Dominio:</strong> $domain</p>
            <p><strong>Expira:</strong> " . date('Y-m-d', strtotime($expiresAt)) . "</p>
        </div>
        
        <p>Ya puedes comenzar a usar DWooSync para importar tu colección de Discogs.</p>
        
        <div style='text-align: center; margin: 30px 0;'>
            <a href='https://dwoosync.com/subscribe/pages/dashboard.php' 
               style='background: #1db954; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                Acceder al Dashboard
            </a>
        </div>
        
        <p style='color: #6b7280; font-size: 14px;'>
            Saludos,<br>
            El equipo de DWooSync
        </p>
    </div>
</body>
</html>
    ";
    
    return sendEmailSMTP($email, $subject, $message, true);
}
?>

