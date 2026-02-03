<?php
/**
 * Configuración SMTP para PHP mail()
 */

// Configurar PHP para usar SMTP
ini_set('SMTP', 'mail.dwoosync.com');
ini_set('smtp_port', '587');
ini_set('sendmail_from', 'support@dwoosync.com');

function sendWelcomeEmail($email, $firstName, $planName, $licenseKey, $domain, $expiresAt, $isEnglish = false) {
    $subject = $isEnglish ? 'Welcome to DWooSync!' : '¡Bienvenido a DWooSync!';
    
    // Mensaje en texto plano
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
    
    // Headers mínimos pero efectivos
    $headers = "From: support@dwoosync.com\r\n";
    $headers .= "Reply-To: support@dwoosync.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Intentar envío
    $result = mail($email, $subject, $message, $headers);
    
    if ($result) {
        error_log("✓ Email de bienvenida enviado a: $email");
        return true;
    } else {
        error_log("✗ Error enviando email a: $email");
        return false;
    }
}

function sendTestEmail($to) {
    $subject = 'Test Email from DWooSync';
    $message = "This is a test email from DWooSync server.\n\nTimestamp: " . date('Y-m-d H:i:s');
    $headers = "From: support@dwoosync.com\r\n";
    $headers .= "Reply-To: support@dwoosync.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}
?>

