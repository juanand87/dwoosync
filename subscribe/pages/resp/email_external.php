<?php
/**
 * Envío de correos usando servicios externos (más confiable)
 */

function sendWelcomeEmail($email, $firstName, $planName, $licenseKey, $domain, $expiresAt, $isEnglish = false) {
    $subject = $isEnglish ? 'Welcome to DWooSync!' : '¡Bienvenido a DWooSync!';
    
    // Crear mensaje HTML profesional
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
    
    // Intentar múltiples métodos de envío
    $methods = [
        'sendgrid_api',
        'mailgun_api',
        'smtp_phpmailer',
        'sendmail_direct',
        'mail_native'
    ];
    
    foreach ($methods as $method) {
        $result = false;
        
        switch ($method) {
            case 'sendgrid_api':
                $result = sendEmailSendGrid($email, $subject, $message);
                break;
            case 'mailgun_api':
                $result = sendEmailMailgun($email, $subject, $message);
                break;
            case 'smtp_phpmailer':
                $result = sendEmailPHPMailer($email, $subject, $message);
                break;
            case 'sendmail_direct':
                $result = sendEmailSendmail($email, $subject, $message);
                break;
            case 'mail_native':
                $result = sendEmailNative($email, $subject, $message);
                break;
        }
        
        if ($result) {
            error_log("✓ Email enviado usando método: $method a: $email");
            return true;
        } else {
            error_log("✗ Falló método: $method para: $email");
        }
    }
    
    error_log("✗ Todos los métodos de envío fallaron para: $email");
    return false;
}

function sendEmailSendGrid($to, $subject, $message) {
    // SendGrid API (requiere API key)
    $apiKey = 'SG.your-api-key-here'; // Cambiar por tu API key de SendGrid
    
    if ($apiKey === 'SG.your-api-key-here') {
        error_log("SendGrid API key no configurada");
        return false;
    }
    
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $to]]
            ]
        ],
        'from' => [
            'email' => 'support@dwoosync.com',
            'name' => 'DWooSync'
        ],
        'subject' => $subject,
        'content' => [
            [
                'type' => 'text/html',
                'value' => $message
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 202;
}

function sendEmailMailgun($to, $subject, $message) {
    // Mailgun API (requiere API key y dominio)
    $apiKey = 'your-mailgun-api-key'; // Cambiar por tu API key de Mailgun
    $domain = 'mg.dwoosync.com'; // Cambiar por tu dominio de Mailgun
    
    if ($apiKey === 'your-mailgun-api-key') {
        error_log("Mailgun API key no configurada");
        return false;
    }
    
    $data = [
        'from' => 'DWooSync <support@dwoosync.com>',
        'to' => $to,
        'subject' => $subject,
        'html' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/$domain/messages");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_USERPWD, "api:$apiKey");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

function sendEmailPHPMailer($to, $subject, $message) {
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'mail.dwoosync.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'support@dwoosync.com';
            $mail->Password = '5802863aA$$';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom('support@dwoosync.com', 'DWooSync');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("PHPMailer error: " . $e->getMessage());
            return false;
        }
    }
    return false;
}

function sendEmailSendmail($to, $subject, $message) {
    $sendmailPath = ini_get('sendmail_path');
    if (!$sendmailPath) {
        $sendmailPath = '/usr/sbin/sendmail';
    }
    
    $emailContent = "To: $to\n";
    $emailContent .= "From: DWooSync <support@dwoosync.com>\n";
    $emailContent .= "Reply-To: support@dwoosync.com\n";
    $emailContent .= "Subject: $subject\n";
    $emailContent .= "Content-Type: text/html; charset=UTF-8\n";
    $emailContent .= "MIME-Version: 1.0\n";
    $emailContent .= "\n";
    $emailContent .= $message;
    
    $tempFile = tempnam(sys_get_temp_dir(), 'email_');
    file_put_contents($tempFile, $emailContent);
    
    $command = "$sendmailPath -t < $tempFile";
    $output = [];
    $returnCode = 0;
    
    exec($command, $output, $returnCode);
    unlink($tempFile);
    
    return $returnCode === 0;
}

function sendEmailNative($to, $subject, $message) {
    $headers = "From: DWooSync <support@dwoosync.com>\r\n";
    $headers .= "Reply-To: support@dwoosync.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    return mail($to, $subject, $message, $headers);
}
?>

