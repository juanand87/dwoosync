<?php
/**
 * Envío de correos usando sendmail (más confiable)
 */

function sendWelcomeEmail($email, $firstName, $planName, $licenseKey, $domain, $expiresAt, $isEnglish = false) {
    $subject = $isEnglish ? 'Welcome to DWooSync!' : '¡Bienvenido a DWooSync!';
    
    // Crear mensaje en texto plano (más confiable para sendmail)
    $message = $isEnglish ? "
Welcome to DWooSync!

Hello $firstName,

Your account has been successfully activated!

Account Details:
- Plan: $planName
- License Key: $licenseKey
- Domain: $domain
- Expires: " . date('Y-m-d', strtotime($expiresAt)) . "

You can now start using DWooSync to import your Discogs collection.

Access your dashboard: https://dwoosync.com/subscribe/pages/dashboard.php

Best regards,
The DWooSync Team

---
This email was sent from DWooSync - Discogs Import System
    " : "
¡Bienvenido a DWooSync!

Hola $firstName,

¡Tu cuenta ha sido activada exitosamente!

Detalles de la cuenta:
- Plan: $planName
- Clave de licencia: $licenseKey
- Dominio: $domain
- Expira: " . date('Y-m-d', strtotime($expiresAt)) . "

Ya puedes comenzar a usar DWooSync para importar tu colección de Discogs.

Accede a tu dashboard: https://dwoosync.com/subscribe/pages/dashboard.php

Saludos,
El equipo de DWooSync

---
Este correo fue enviado desde DWooSync - Sistema de Importación de Discogs
    ";
    
    // Intentar múltiples métodos de envío
    $methods = [
        'sendmail_direct',
        'sendmail_pipe',
        'mail_native',
        'smtp_phpmailer'
    ];
    
    foreach ($methods as $method) {
        $result = false;
        
        switch ($method) {
            case 'sendmail_direct':
                $result = sendEmailSendmailDirect($email, $subject, $message);
                break;
            case 'sendmail_pipe':
                $result = sendEmailSendmailPipe($email, $subject, $message);
                break;
            case 'mail_native':
                $result = sendEmailNative($email, $subject, $message);
                break;
            case 'smtp_phpmailer':
                $result = sendEmailPHPMailer($email, $subject, $message);
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

function sendEmailSendmailDirect($to, $subject, $message) {
    // Usar sendmail directamente con parámetros
    $sendmailPath = ini_get('sendmail_path');
    if (!$sendmailPath) {
        $sendmailPath = '/usr/sbin/sendmail';
    }
    
    // Crear archivo temporal
    $tempFile = tempnam(sys_get_temp_dir(), 'email_');
    $emailContent = "To: $to\n";
    $emailContent .= "From: DWooSync <support@dwoosync.com>\n";
    $emailContent .= "Reply-To: support@dwoosync.com\n";
    $emailContent .= "Subject: $subject\n";
    $emailContent .= "Content-Type: text/plain; charset=UTF-8\n";
    $emailContent .= "MIME-Version: 1.0\n";
    $emailContent .= "\n";
    $emailContent .= $message;
    
    file_put_contents($tempFile, $emailContent);
    
    // Ejecutar sendmail
    $command = "$sendmailPath -t < $tempFile";
    $output = [];
    $returnCode = 0;
    
    exec($command, $output, $returnCode);
    unlink($tempFile);
    
    return $returnCode === 0;
}

function sendEmailSendmailPipe($to, $subject, $message) {
    // Usar sendmail con pipe
    $sendmailPath = ini_get('sendmail_path');
    if (!$sendmailPath) {
        $sendmailPath = '/usr/sbin/sendmail -t';
    }
    
    $emailContent = "To: $to\n";
    $emailContent .= "From: DWooSync <support@dwoosync.com>\n";
    $emailContent .= "Reply-To: support@dwoosync.com\n";
    $emailContent .= "Subject: $subject\n";
    $emailContent .= "Content-Type: text/plain; charset=UTF-8\n";
    $emailContent .= "MIME-Version: 1.0\n";
    $emailContent .= "\n";
    $emailContent .= $message;
    
    $handle = popen($sendmailPath, 'w');
    if ($handle) {
        fwrite($handle, $emailContent);
        $result = pclose($handle);
        return $result === 0;
    }
    
    return false;
}

function sendEmailNative($to, $subject, $message) {
    // Configurar headers para sendmail
    $headers = "From: DWooSync <support@dwoosync.com>\r\n";
    $headers .= "Reply-To: support@dwoosync.com\r\n";
    $headers .= "Return-Path: support@dwoosync.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    return mail($to, $subject, $message, $headers);
}

function sendEmailPHPMailer($to, $subject, $message) {
    // Usar PHPMailer si está disponible
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
            $mail->isHTML(false);
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

function sendTestEmail($to) {
    $subject = 'Test Email from DWooSync';
    $message = "This is a test email from DWooSync server.\n\nTimestamp: " . date('Y-m-d H:i:s');
    
    return sendWelcomeEmail($to, 'Test User', 'Test', 'TEST123', 'test.com', date('Y-m-d H:i:s', strtotime('+1 month')), true);
}
?>

