<?php
/**
 * Envío de correos simple y confiable
 */

function sendWelcomeEmail($email, $firstName, $planName, $licenseKey, $domain, $expiresAt, $isEnglish = false) {
    $subject = $isEnglish ? 'Welcome to DWooSync!' : '¡Bienvenido a DWooSync!';
    
    // Crear mensaje en texto plano (más confiable)
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
    
    // Headers optimizados para evitar spam
    $headers = "From: DWooSync <support@dwoosync.com>\r\n";
    $headers .= "Reply-To: support@dwoosync.com\r\n";
    $headers .= "Return-Path: support@dwoosync.com\r\n";
    $headers .= "X-Mailer: DWooSync System\r\n";
    $headers .= "X-Priority: 3\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
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
?>

