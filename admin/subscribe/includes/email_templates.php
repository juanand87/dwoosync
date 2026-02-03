<?php
/**
 * Plantillas de correo electrónico para DWooSync
 */

/**
 * Plantilla de correo de bienvenida al registrarse
 */
function getWelcomeEmailTemplate($firstName, $email, $planName, $licenseKey, $domain, $lang = 'en') {
    $isEnglish = ($lang === 'en');
    
    $subject = $isEnglish ? 
        'Welcome to DWooSync!' : 
        '¡Bienvenido a DWooSync!';
    
    $greeting = $isEnglish ? "Hello" : "Hola";
    $title = $isEnglish ? "Welcome to DWooSync!" : "¡Bienvenido a DWooSync!";
    $intro = $isEnglish ? 
        "Thank you for registering with DWooSync. Your account has been created successfully." : 
        "Gracias por registrarte en DWooSync. Tu cuenta ha sido creada exitosamente.";
    
    $accountDetailsTitle = $isEnglish ? "Account Details" : "Detalles de la Cuenta";
    $planLabel = $isEnglish ? "Plan" : "Plan";
    $licenseKeyLabel = $isEnglish ? "License Key" : "Clave de Licencia";
    $domainLabel = $isEnglish ? "Domain" : "Dominio";
    $emailLabel = $isEnglish ? "Email" : "Email";
    
    $nextStepsTitle = $isEnglish ? "Next Steps" : "Próximos Pasos";
    $step1 = $isEnglish ? 
        "Complete your payment to activate your subscription" : 
        "Completa tu pago para activar tu suscripción";
    $step2 = $isEnglish ? 
        "Download the DWooSync plugin from your dashboard" : 
        "Descarga el plugin DWooSync desde tu panel de control";
    $step3 = $isEnglish ? 
        "Install the plugin on your WordPress site" : 
        "Instala el plugin en tu sitio WordPress";
    $step4 = $isEnglish ? 
        "Configure your Discogs API credentials" : 
        "Configura tus credenciales de API de Discogs";
    
    $dashboardBtn = $isEnglish ? "Go to Dashboard" : "Ir al Panel de Control";
    $supportText = $isEnglish ? 
        "If you have any questions, our support team is here to help." : 
        "Si tienes alguna pregunta, nuestro equipo de soporte está aquí para ayudarte.";
    
    $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;'>
            <!-- Header -->
            <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h1 style='margin: 0; font-size: 28px;'>
                    <span style='font-size: 32px;'>🎵</span> DWooSync
                </h1>
                <p style='margin: 10px 0 0 0; font-size: 18px; opacity: 0.9;'>{$title}</p>
            </div>
            
            <!-- Body -->
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #e9ecef; border-top: none;'>
                <!-- Saludo -->
                <div style='background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                    <h2 style='color: #1f2937; margin-top: 0; font-size: 22px;'>{$greeting} {$firstName}! 👋</h2>
                    <p style='color: #374151; line-height: 1.6; font-size: 16px; margin-bottom: 0;'>
                        {$intro}
                    </p>
                </div>
                
                <!-- Detalles de la cuenta -->
                <div style='background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                    <h3 style='color: #1f2937; margin-top: 0; font-size: 18px; border-bottom: 2px solid #10b981; padding-bottom: 10px;'>
                        📋 {$accountDetailsTitle}
                    </h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280; font-weight: bold;'>{$planLabel}:</td>
                            <td style='padding: 10px 0; color: #1f2937;'>{$planName}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280; font-weight: bold;'>{$emailLabel}:</td>
                            <td style='padding: 10px 0; color: #1f2937;'>{$email}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280; font-weight: bold;'>{$domainLabel}:</td>
                            <td style='padding: 10px 0; color: #1f2937;'>{$domain}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280; font-weight: bold;'>{$licenseKeyLabel}:</td>
                            <td style='padding: 10px 0;'>
                                <code style='background: #f3f4f6; padding: 8px 12px; border-radius: 4px; color: #059669; font-weight: bold; font-size: 14px;'>
                                    {$licenseKey}
                                </code>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Próximos pasos -->
                <div style='background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                    <h3 style='color: #1f2937; margin-top: 0; font-size: 18px; border-bottom: 2px solid #10b981; padding-bottom: 10px;'>
                        🚀 {$nextStepsTitle}
                    </h3>
                    <ol style='color: #374151; line-height: 2; padding-left: 20px; margin: 15px 0;'>
                        <li>{$step1}</li>
                        <li>{$step2}</li>
                        <li>{$step3}</li>
                        <li>{$step4}</li>
                    </ol>
                </div>
                
                <!-- Botón CTA -->
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://dwoosync.com/subscribe/pages/dashboard.php' 
                       style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);'>
                        {$dashboardBtn} →
                    </a>
                </div>
                
                <!-- Soporte -->
                <div style='background: #e0f2fe; border: 1px solid #7dd3fc; padding: 15px; border-radius: 6px; text-align: center;'>
                    <p style='margin: 0; color: #0c4a6e; font-size: 14px;'>
                        <i class='fas fa-life-ring' style='margin-right: 5px;'></i>
                        {$supportText}<br>
                        <a href='mailto:support@dwoosync.com' style='color: #0369a1; text-decoration: none; font-weight: bold;'>
                            support@dwoosync.com
                        </a>
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style='text-align: center; margin-top: 20px; padding: 20px; color: #6b7280; font-size: 12px;'>
                <p style='margin: 0 0 5px 0;'>© " . date('Y') . " DWooSync - Discogs Import for WooCommerce</p>
                <p style='margin: 0;'>
                    <a href='https://dwoosync.com' style='color: #059669; text-decoration: none;'>www.dwoosync.com</a>
                </p>
            </div>
        </div>
    ";
    
    return ['subject' => $subject, 'message' => $message];
}

/**
 * Plantilla de correo de activación de cuenta (después del pago)
 */
function getAccountActivatedEmailTemplate($firstName, $email, $planName, $licenseKey, $domain, $expiresAt, $lang = 'en') {
    $isEnglish = ($lang === 'en');
    
    $subject = $isEnglish ? 
        'Your DWooSync Account is Active!' : 
        '¡Tu cuenta de DWooSync está activa!';
    
    $greeting = $isEnglish ? "Hello" : "Hola";
    $title = $isEnglish ? "Account Activated!" : "¡Cuenta Activada!";
    $intro = $isEnglish ? 
        "Your payment has been processed successfully and your subscription is now active." : 
        "Tu pago ha sido procesado exitosamente y tu suscripción está ahora activa.";
    
    $subscriptionDetailsTitle = $isEnglish ? "Subscription Details" : "Detalles de la Suscripción";
    $planLabel = $isEnglish ? "Plan" : "Plan";
    $statusLabel = $isEnglish ? "Status" : "Estado";
    $activeLabel = $isEnglish ? "Active" : "Activa";
    $expiresLabel = $isEnglish ? "Expires" : "Expira";
    $licenseKeyLabel = $isEnglish ? "License Key" : "Clave de Licencia";
    $domainLabel = $isEnglish ? "Domain" : "Dominio";
    
    $downloadTitle = $isEnglish ? "Download & Install" : "Descargar e Instalar";
    $downloadText = $isEnglish ? 
        "You can now download the DWooSync plugin and install it on your WordPress site." : 
        "Ahora puedes descargar el plugin DWooSync e instalarlo en tu sitio WordPress.";
    
    $downloadBtn = $isEnglish ? "Download Plugin" : "Descargar Plugin";
    $dashboardBtn = $isEnglish ? "Go to Dashboard" : "Ir al Panel de Control";
    
    $supportText = $isEnglish ? 
        "Need help? Contact our support team." : 
        "¿Necesitas ayuda? Contacta a nuestro equipo de soporte.";
    
    $formattedExpires = date('F d, Y', strtotime($expiresAt));
    
    $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;'>
            <!-- Header -->
            <div style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                <div style='font-size: 48px; margin-bottom: 15px;'>✅</div>
                <h1 style='margin: 0; font-size: 28px;'>DWooSync</h1>
                <p style='margin: 10px 0 0 0; font-size: 18px; opacity: 0.9;'>{$title}</p>
            </div>
            
            <!-- Body -->
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #e9ecef; border-top: none;'>
                <!-- Saludo -->
                <div style='background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                    <h2 style='color: #1f2937; margin-top: 0; font-size: 22px;'>{$greeting} {$firstName}! 🎉</h2>
                    <p style='color: #374151; line-height: 1.6; font-size: 16px; margin-bottom: 0;'>
                        {$intro}
                    </p>
                </div>
                
                <!-- Detalles de la suscripción -->
                <div style='background: white; padding: 25px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>
                    <h3 style='color: #1f2937; margin-top: 0; font-size: 18px; border-bottom: 2px solid #10b981; padding-bottom: 10px;'>
                        📋 {$subscriptionDetailsTitle}
                    </h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280; font-weight: bold;'>{$planLabel}:</td>
                            <td style='padding: 10px 0; color: #1f2937; font-weight: bold;'>{$planName}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280; font-weight: bold;'>{$statusLabel}:</td>
                            <td style='padding: 10px 0; color: #10b981; font-weight: bold;'>✓ {$activeLabel}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280; font-weight: bold;'>{$expiresLabel}:</td>
                            <td style='padding: 10px 0; color: #1f2937;'>{$formattedExpires}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280; font-weight: bold;'>{$domainLabel}:</td>
                            <td style='padding: 10px 0; color: #1f2937;'>{$domain}</td>
                        </tr>
                        <tr>
                            <td style='padding: 10px 0; color: #6b7280; font-weight: bold;'>{$licenseKeyLabel}:</td>
                            <td style='padding: 10px 0;'>
                                <code style='background: #f3f4f6; padding: 8px 12px; border-radius: 4px; color: #059669; font-weight: bold; font-size: 14px;'>
                                    {$licenseKey}
                                </code>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Download -->
                <div style='background: #f0fdf4; border: 2px solid #10b981; padding: 25px; border-radius: 8px; margin-bottom: 20px; text-align: center;'>
                    <h3 style='color: #1f2937; margin-top: 0; font-size: 18px;'>📦 {$downloadTitle}</h3>
                    <p style='color: #374151; line-height: 1.6; margin-bottom: 20px;'>
                        {$downloadText}
                    </p>
                    <a href='https://dwoosync.com/subscribe/pages/plugin-config.php' 
                       style='background: #10b981; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold; margin-right: 10px;'>
                        {$downloadBtn}
                    </a>
                    <a href='https://dwoosync.com/subscribe/pages/dashboard.php' 
                       style='background: #3b82f6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                        {$dashboardBtn}
                    </a>
                </div>
                
                <!-- Soporte -->
                <div style='background: #e0f2fe; border: 1px solid #7dd3fc; padding: 15px; border-radius: 6px; text-align: center;'>
                    <p style='margin: 0; color: #0c4a6e; font-size: 14px;'>
                        <i class='fas fa-life-ring' style='margin-right: 5px;'></i>
                        {$supportText}<br>
                        <a href='mailto:support@dwoosync.com' style='color: #0369a1; text-decoration: none; font-weight: bold;'>
                            support@dwoosync.com
                        </a>
                    </p>
                </div>
            </div>
            
            <!-- Footer -->
            <div style='text-align: center; margin-top: 20px; padding: 20px; color: #6b7280; font-size: 12px;'>
                <p style='margin: 0 0 5px 0;'>© " . date('Y') . " DWooSync - Discogs Import for WooCommerce</p>
                <p style='margin: 0;'>
                    <a href='https://dwoosync.com' style='color: #059669; text-decoration: none;'>www.dwoosync.com</a>
                </p>
            </div>
        </div>
    ";
    
    return ['subject' => $subject, 'message' => $message];
}
?>



