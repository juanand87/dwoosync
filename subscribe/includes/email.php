<?php
/**
 * Configuración de email con SMTP usando PHPMailer
 */

// Incluir PHPMailer (asumiendo que está en vendor/autoload.php)
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    // Si no hay Composer, incluir PHPMailer manualmente
    require_once __DIR__ . '/../../libraries/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../../libraries/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../../libraries/PHPMailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Enviar email usando SMTP
 */
function sendEmailSMTP($to, $subject, $message, $isHTML = true) {
    // Crear directorio de logs si no existe
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/email.log';
    $timestamp = date('Y-m-d H:i:s');
    
    // Log inicial
    file_put_contents($logFile, "[$timestamp] ===== INTENTO ENVÍO EMAIL SMTP =====\n", FILE_APPEND | LOCK_EX);
    file_put_contents($logFile, "[$timestamp] TO: $to\n", FILE_APPEND | LOCK_EX);
    file_put_contents($logFile, "[$timestamp] SUBJECT: $subject\n", FILE_APPEND | LOCK_EX);
    file_put_contents($logFile, "[$timestamp] IS_HTML: " . ($isHTML ? 'YES' : 'NO') . "\n", FILE_APPEND | LOCK_EX);
    
    try {
        // Verificar configuración SMTP
        file_put_contents($logFile, "[$timestamp] SMTP_HOST: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NO DEFINIDO') . "\n", FILE_APPEND | LOCK_EX);
        file_put_contents($logFile, "[$timestamp] SMTP_PORT: " . (defined('SMTP_PORT') ? SMTP_PORT : 'NO DEFINIDO') . "\n", FILE_APPEND | LOCK_EX);
        file_put_contents($logFile, "[$timestamp] SMTP_USERNAME: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NO DEFINIDO') . "\n", FILE_APPEND | LOCK_EX);
        file_put_contents($logFile, "[$timestamp] FROM_EMAIL: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'NO DEFINIDO') . "\n", FILE_APPEND | LOCK_EX);
        
        // Verificar si PHPMailer existe
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            file_put_contents($logFile, "[$timestamp] ERROR: PHPMailer no está disponible\n", FILE_APPEND | LOCK_EX);
            throw new Exception("PHPMailer no está disponible");
        }
        
        $mail = new PHPMailer(true);
        file_put_contents($logFile, "[$timestamp] PHPMailer instanciado correctamente\n", FILE_APPEND | LOCK_EX);
        
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        
        file_put_contents($logFile, "[$timestamp] Configuración SMTP establecida\n", FILE_APPEND | LOCK_EX);
        
        // Configuración de remitente y destinatario
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(FROM_EMAIL, FROM_NAME);
        
        file_put_contents($logFile, "[$timestamp] Remitente y destinatario configurados\n", FILE_APPEND | LOCK_EX);
        
        // Contenido del email
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        file_put_contents($logFile, "[$timestamp] Contenido del email configurado\n", FILE_APPEND | LOCK_EX);
        
        // Enviar email
        $result = $mail->send();
        
        file_put_contents($logFile, "[$timestamp] RESULTADO ENVÍO: " . ($result ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND | LOCK_EX);
        
        // Log del envío
        error_log('[EMAIL_SMTP] Email enviado exitosamente a: ' . $to);
        
        return $result;
        
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        file_put_contents($logFile, "[$timestamp] ERROR: $errorMsg\n", FILE_APPEND | LOCK_EX);
        file_put_contents($logFile, "[$timestamp] STACK TRACE: " . $e->getTraceAsString() . "\n", FILE_APPEND | LOCK_EX);
        
        // Log del error
        error_log('[EMAIL_SMTP] Error enviando email: ' . $errorMsg);
        
        // Fallback a mail() nativo si SMTP falla
        file_put_contents($logFile, "[$timestamp] Intentando fallback con mail() nativo\n", FILE_APPEND | LOCK_EX);
        return sendEmailFallback($to, $subject, $message, $isHTML);
    }
}

/**
 * Función de fallback usando mail() nativo
 */
function sendEmailFallback($to, $subject, $message, $isHTML = true) {
    // Crear directorio de logs si no existe
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/email.log';
    $timestamp = date('Y-m-d H:i:s');
    
    file_put_contents($logFile, "[$timestamp] ===== FALLBACK MAIL() NATIVO =====\n", FILE_APPEND | LOCK_EX);
    file_put_contents($logFile, "[$timestamp] TO: $to\n", FILE_APPEND | LOCK_EX);
    file_put_contents($logFile, "[$timestamp] SUBJECT: $subject\n", FILE_APPEND | LOCK_EX);
    
    $headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    if ($isHTML) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    }
    
    file_put_contents($logFile, "[$timestamp] Headers: " . implode(' | ', $headers) . "\n", FILE_APPEND | LOCK_EX);
    
    error_log('[EMAIL_FALLBACK] Usando mail() nativo para: ' . $to);
    
    $result = mail($to, $subject, $message, implode("\r\n", $headers));
    
    file_put_contents($logFile, "[$timestamp] RESULTADO FALLBACK: " . ($result ? 'SUCCESS' : 'FAILED') . "\n", FILE_APPEND | LOCK_EX);
    
    return $result;
}

/**
 * Función principal de envío de email (con fallback automático)
 */
function sendEmail($to, $subject, $message, $isHTML = true) {
    // Intentar SMTP primero
    if (defined('SMTP_HOST') && !empty(SMTP_HOST) && 
        defined('SMTP_USERNAME') && !empty(SMTP_USERNAME)) {
        return sendEmailSMTP($to, $subject, $message, $isHTML);
    }
    
    // Si no hay configuración SMTP, usar fallback
    return sendEmailFallback($to, $subject, $message, $isHTML);
}

/**
 * Enviar email de contacto
 */
function sendContactEmail($name, $email, $subject, $message) {
    $to = 'support@discogsync.com';
    $emailSubject = 'Contacto desde DWooSync: ' . $subject;
    
    $emailMessage = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #1db954; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h2 style='margin: 0; font-size: 24px;'>
                    <i class='fas fa-compact-disc' style='margin-right: 10px;'></i>
                    Nuevo mensaje de contacto
                </h2>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #e9ecef;'>
                <div style='background: white; padding: 20px; border-radius: 6px; margin-bottom: 20px;'>
                    <h3 style='color: #1f2937; margin-top: 0;'>Información del contacto</h3>
                    <p><strong>Nombre:</strong> " . htmlspecialchars($name) . "</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                    <p><strong>Asunto:</strong> " . htmlspecialchars($subject) . "</p>
                </div>
                
                <div style='background: white; padding: 20px; border-radius: 6px;'>
                    <h3 style='color: #1f2937; margin-top: 0;'>Mensaje</h3>
                    <div style='line-height: 1.6; color: #374151;'>" . nl2br(htmlspecialchars($message)) . "</div>
                </div>
                
                <div style='margin-top: 20px; padding: 15px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;'>
                    <p style='margin: 0; color: #1565c0; font-size: 14px;'>
                        <i class='fas fa-info-circle' style='margin-right: 5px;'></i>
                        <strong>Respuesta automática:</strong> Este mensaje fue enviado desde el formulario de contacto de DWooSync.
                    </p>
                </div>
            </div>
            
            <div style='text-align: center; margin-top: 20px; color: #6b7280; font-size: 12px;'>
                <p>Enviado desde DWooSync - Sistema de importación de Discogs</p>
            </div>
        </div>
    ";
    
    return sendEmail($to, $emailSubject, $emailMessage, true);
}

/**
 * Enviar email de confirmación al usuario
 */
function sendContactConfirmation($name, $email) {
    $subject = 'Confirmación de contacto - DWooSync';
    
    $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #1db954; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h2 style='margin: 0; font-size: 24px;'>
                    <i class='fas fa-check-circle' style='margin-right: 10px;'></i>
                    ¡Mensaje recibido!
                </h2>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #e9ecef;'>
                <div style='background: white; padding: 20px; border-radius: 6px; text-align: center;'>
                    <h3 style='color: #1f2937; margin-top: 0;'>Hola " . htmlspecialchars($name) . ",</h3>
                    <p style='color: #374151; line-height: 1.6; font-size: 16px;'>
                        Hemos recibido tu mensaje y te responderemos en un plazo máximo de 24 horas.
                    </p>
                    
                    <div style='background: #f0fdf4; border: 1px solid #bbf7d0; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                        <p style='margin: 0; color: #166534; font-size: 14px;'>
                            <i class='fas fa-clock' style='margin-right: 5px;'></i>
                            <strong>Tiempo de respuesta:</strong> 24 horas máximo
                        </p>
                    </div>
                    
                    <p style='color: #6b7280; font-size: 14px;'>
                        Si tienes alguna pregunta urgente, puedes contactarnos directamente en 
                        <a href='mailto:support@discogsync.com' style='color: #1db954; text-decoration: none;'>
                            support@discogsync.com
                        </a>
                    </p>
                </div>
                
                <div style='text-align: center; margin-top: 20px;'>
                    <a href='http://localhost/api_discogs/subscribe/index.php' 
                       style='background: #1db954; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;'>
                        Volver a DWooSync
                    </a>
                </div>
            </div>
            
            <div style='text-align: center; margin-top: 20px; color: #6b7280; font-size: 12px;'>
                <p>DWooSync - Sistema de importación de Discogs</p>
            </div>
        </div>
    ";
    
    return sendEmail($email, $subject, $message, true);
}
?>
