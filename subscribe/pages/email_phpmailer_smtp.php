<?php
/**
 * Sistema de correo con PHPMailer SMTP autenticado
 * Versión final que resuelve el problema de "no autenticado"
 */

// Cargar PHPMailer desde Composer
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Verificar configuración DNS para email
 */
function checkEmailDNSConfiguration($domain = 'dwoosync.com') {
    $results = [];
    
    // Verificar SPF
    $spfRecord = dns_get_record($domain, DNS_TXT);
    $spfFound = false;
    foreach ($spfRecord as $record) {
        if (strpos($record['txt'], 'v=spf1') === 0) {
            $spfFound = true;
            $results['spf'] = $record['txt'];
            break;
        }
    }
    if (!$spfFound) {
        $results['spf'] = 'NO CONFIGURADO';
    }
    
    // Verificar DMARC
    $dmarcRecord = dns_get_record('_dmarc.' . $domain, DNS_TXT);
    $dmarcFound = false;
    foreach ($dmarcRecord as $record) {
        if (strpos($record['txt'], 'v=DMARC1') === 0) {
            $dmarcFound = true;
            $results['dmarc'] = $record['txt'];
            break;
        }
    }
    if (!$dmarcFound) {
        $results['dmarc'] = 'NO CONFIGURADO';
    }
    
    // Verificar MX
    $mxRecords = dns_get_record($domain, DNS_MX);
    $results['mx'] = !empty($mxRecords) ? 'CONFIGURADO' : 'NO CONFIGURADO';
    
    // Intentar verificar DKIM con selectores comunes
    $dkimSelectors = ['default', 'mail', 'dkim', 'selector1', 'selector2', 'google', 'k1'];
    $results['dkim'] = 'NO ENCONTRADO';
    $results['dkim_selector'] = '';
    
    foreach ($dkimSelectors as $selector) {
        $dkimRecord = dns_get_record($selector . '._domainkey.' . $domain, DNS_TXT);
        if (!empty($dkimRecord)) {
            foreach ($dkimRecord as $record) {
                if (strpos($record['txt'], 'v=DKIM1') !== false || strpos($record['txt'], 'k=rsa') !== false) {
                    $results['dkim'] = 'CONFIGURADO';
                    $results['dkim_selector'] = $selector;
                    $results['dkim_record'] = $record['txt'];
                    break 2;
                }
            }
        }
    }
    
    return $results;
}

/**
 * Enviar correo usando PHPMailer con SMTP autenticado
 */
function sendEmail($to, $subject, $message, $isHTML = true, $fromName = 'DWooSync') {
    // Validar email antes del envío
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("✗ Email inválido: $to");
        return false;
    }
    
    // Validar que no sea una dirección temporal/desechable
    $disposableEmailDomains = ['10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com'];
    $emailDomain = strtolower(substr(strrchr($to, '@'), 1));
    
    if (in_array($emailDomain, $disposableEmailDomains)) {
        error_log("✗ Email desechable detectado: $to");
        return false;
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.dwoosync.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'support@dwoosync.com';
        $mail->Password = '5802863aA$$';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port = 465;
        
        // Configuración de caracteres y timeout
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Timeout = 15; // Reducido de 30 a 15 segundos
        $mail->SMTPKeepAlive = false; // Deshabilitar keep-alive para evitar cuelgues
        
        // Configuraciones adicionales para evitar timeouts
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'stream_timeout' => 15 // 15 segundos timeout para stream
            )
        );
        
        // Configurar remitente
        $mail->setFrom('support@dwoosync.com', $fromName);
        $mail->addReplyTo('support@dwoosync.com', $fromName);
        
        // Configurar destinatario
        $mail->addAddress($to);
        
        // Configurar contenido
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // Agregar versión texto plano para mejor deliverability
        if ($isHTML) {
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
        }
        
        // Headers adicionales para evitar spam
        $mail->addCustomHeader('X-Mailer', 'DWooSync System v1.0');
        $mail->addCustomHeader('X-Priority', '3');
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
        $mail->addCustomHeader('List-Unsubscribe', '<mailto:unsubscribe@dwoosync.com>');
        $mail->addCustomHeader('Return-Path', 'support@dwoosync.com');
        $mail->addCustomHeader('Precedence', 'bulk');
        
        // Headers para mejorar deliverability
        $mail->addCustomHeader('X-Original-To', $to);
        $mail->addCustomHeader('X-Sender', 'support@dwoosync.com');
        $mail->addCustomHeader('Organization', 'DWooSync - Discogs WooCommerce Sync');
        $mail->addCustomHeader('X-Originating-IP', $_SERVER['SERVER_ADDR'] ?? '');
        $mail->addCustomHeader('X-Spam-Status', 'No');
        $mail->addCustomHeader('X-Source', 'DWooSync Application');
        $mail->addCustomHeader('X-Source-Args', 'dwoosync.com');
        $mail->addCustomHeader('X-Transport', 'SMTP/TLS');
        
        // Enviar correo
        $result = $mail->send();
        
        if ($result) {
            error_log("✓ Correo enviado exitosamente a: $to");
            return true;
        } else {
            error_log("✗ Error enviando correo a: $to - Sin detalles específicos");
            return false;
        }
        
    } catch (Exception $e) {
        error_log("✗ Error PHPMailer SMTP: " . $e->getMessage());
        return false;
    }
}

/**
 * Enviar correo de bienvenida/activación de cuenta
 */
function sendWelcomeEmail($email, $firstName, $planName, $licenseKey, $domain, $expiresAt, $isEnglish = false) {
    // Configurar idioma
    $lang = $isEnglish ? 'en' : 'es';
    
    // Traducir textos
    $texts = [
        'es' => [
            'subject' => '¡Bienvenido a DWooSync! - Tu cuenta ha sido activada',
            'greeting' => "¡Hola $firstName!",
            'welcome' => '¡Bienvenido a DWooSync! Tu cuenta ha sido activada exitosamente.',
            'plan' => 'Plan activado',
            'license' => 'Clave de licencia',
            'domain' => 'Dominio',
            'expires' => 'Expira el',
            'features' => 'Características de tu plan',
            'download' => 'Descargar Plugin',
            'support' => 'Soporte Técnico',
            'footer' => 'Gracias por elegir DWooSync. Si tienes alguna pregunta, no dudes en contactarnos.',
            'unlimited' => 'Ilimitado',
            'free_features' => [
                'Sincronización básica con Discogs',
                'Hasta 10 productos por mes',
                'Soporte por email',
                'Actualizaciones automáticas'
            ],
            'premium_features' => [
                'Sincronización avanzada con Discogs',
                'Hasta 5,000 productos por mes',
                'Soporte prioritario',
                'Actualizaciones automáticas',
                'Integración con WooCommerce'
            ],
            'enterprise_features' => [
                'Sincronización completa con Discogs',
                'Hasta 20,000 productos por mes',
                'Soporte 24/7',
                'Actualizaciones automáticas',
                'Integración con WooCommerce',
                'Integración con Spotify'
            ]
        ],
        'en' => [
            'subject' => 'Welcome to DWooSync! - Your account has been activated',
            'greeting' => "Hello $firstName!",
            'welcome' => 'Welcome to DWooSync! Your account has been successfully activated.',
            'plan' => 'Activated plan',
            'license' => 'License key',
            'domain' => 'Domain',
            'expires' => 'Expires on',
            'features' => 'Your plan features',
            'download' => 'Download Plugin',
            'support' => 'Technical Support',
            'footer' => 'Thank you for choosing DWooSync. If you have any questions, please don\'t hesitate to contact us.',
            'unlimited' => 'Unlimited',
            'free_features' => [
                'Basic Discogs synchronization',
                'Up to 10 products per month',
                'Email support',
                'Automatic updates'
            ],
            'premium_features' => [
                'Advanced Discogs synchronization',
                'Up to 5,000 products per month',
                'Priority support',
                'Automatic updates',
                'WooCommerce integration'
            ],
            'enterprise_features' => [
                'Complete Discogs synchronization',
                'Up to 20,000 products per month',
                '24/7 support',
                'Automatic updates',
                'WooCommerce integration',
                'Spotify integration'
            ]
        ]
    ];
    
    $t = $texts[$lang];
    
    // Obtener características según el plan
    $features = [];
    if (strtolower($planName) === 'free' || strtolower($planName) === 'gratuito') {
        $features = $t['free_features'];
    } elseif (strtolower($planName) === 'premium') {
        $features = $t['premium_features'];
    } elseif (strtolower($planName) === 'enterprise' || strtolower($planName) === '+spotify') {
        $features = $t['enterprise_features'];
    }
    
    // Crear lista de características
    $featuresList = '';
    foreach ($features as $feature) {
        $featuresList .= "<li style='margin: 8px 0; color: #374151;'><i class='fas fa-check' style='color: #10b981; margin-right: 10px;'></i>$feature</li>";
    }
    
    // Crear HTML del correo
    $htmlMessage = "
    <!DOCTYPE html>
    <html lang='$lang'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$t['subject']}</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f7f6; }
            .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); overflow: hidden; }
            .header { background: linear-gradient(135deg, #1db954, #168a3e); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; font-weight: 300; }
            .content { padding: 40px 30px; }
            .welcome-text { font-size: 18px; margin-bottom: 30px; color: #2d3748; }
            .info-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0; }
            .info-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #e2e8f0; }
            .info-row:last-child { border-bottom: none; }
            .info-label { font-weight: 600; color: #4a5568; }
            .info-value { color: #2d3748; font-family: monospace; }
            .features-section { margin: 30px 0; }
            .features-title { font-size: 20px; font-weight: 600; color: #2d3748; margin-bottom: 15px; }
            .features-list { list-style: none; padding: 0; margin: 0; }
            .cta-section { text-align: center; margin: 30px 0; }
            .cta-button { display: inline-block; background: linear-gradient(135deg, #1db954, #168a3e); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 16px; transition: all 0.3s ease; margin: 10px; }
            .cta-button:hover { background: linear-gradient(135deg, #168a3e, #1db954); transform: translateY(-2px); }
            .footer { background-color: #f8fafc; padding: 20px 30px; text-align: center; color: #6b7280; font-size: 14px; }
            .footer a { color: #1db954; text-decoration: none; }
            .footer a:hover { text-decoration: underline; }
            .logo { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>🎵 DWooSync</div>
                <h1>{$t['subject']}</h1>
            </div>
            
            <div class='content'>
                <div class='welcome-text'>
                    <p>{$t['greeting']}</p>
                    <p>{$t['welcome']}</p>
                </div>
                
                <div class='info-box'>
                    <div class='info-row'>
                        <span class='info-label'>{$t['plan']}:</span>
                        <span class='info-value'>$planName</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>{$t['license']}:</span>
                        <span class='info-value'>$licenseKey</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>{$t['domain']}:</span>
                        <span class='info-value'>$domain</span>
                    </div>
                    <div class='info-row'>
                        <span class='info-label'>{$t['expires']}:</span>
                        <span class='info-value'>$expiresAt</span>
                    </div>
                </div>
                
                <div class='features-section'>
                    <h3 class='features-title'>{$t['features']}</h3>
                    <ul class='features-list'>
                        $featuresList
                    </ul>
                </div>
                
                <div class='cta-section'>
                    <a href='https://dwoosync.com/subscribe/pages/plugin-config.php' class='cta-button'>
                        📥 {$t['download']}
                    </a>
                    <a href='https://dwoosync.com/subscribe/pages/contact.php' class='cta-button'>
                        💬 {$t['support']}
                    </a>
                </div>
            </div>
            
            <div class='footer'>
                <p>{$t['footer']}</p>
                <p>
                    <a href='https://dwoosync.com'>dwoosync.com</a> | 
                    <a href='https://dwoosync.com/subscribe/pages/contact.php'>{$t['support']}</a>
                </p>
            </div>
        </div>
    </body>
    </html>";
    
    // Enviar correo
    return sendEmail($email, $t['subject'], $htmlMessage, true, 'DWooSync');
}

/**
 * Enviar correo de contacto
 */
function sendContactEmail($name, $email, $subject, $message) {
    $htmlMessage = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Nuevo mensaje de contacto</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1db954; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
            .field { margin: 15px 0; }
            .label { font-weight: bold; color: #555; }
            .value { background-color: white; padding: 10px; border-radius: 4px; border-left: 4px solid #1db954; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Nuevo mensaje de contacto desde DWooSync</h2>
            </div>
            <div class='content'>
                <div class='field'>
                    <div class='label'>Nombre:</div>
                    <div class='value'>$name</div>
                </div>
                <div class='field'>
                    <div class='label'>Email:</div>
                    <div class='value'>$email</div>
                </div>
                <div class='field'>
                    <div class='label'>Asunto:</div>
                    <div class='value'>$subject</div>
                </div>
                <div class='field'>
                    <div class='label'>Mensaje:</div>
                    <div class='value'>" . nl2br(htmlspecialchars($message)) . "</div>
                </div>
            </div>
        </div>
    </body>
    </html>";
    
    return sendEmail('support@dwoosync.com', "Contacto desde DWooSync: $subject", $htmlMessage, true, 'DWooSync Contact Form');
}
?>


