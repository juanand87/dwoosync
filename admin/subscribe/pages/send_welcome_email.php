<?php
/**
 * Endpoint para enviar correo de bienvenida asíncronamente
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar logs detallados
error_log("📧 === INICIO DE SEND_WELCOME_EMAIL (SESIÓN) ===", 3, '../logs/email.log');

// Iniciar sesión
startSecureSession();

error_log("📊 Sesión iniciada. ID: " . session_id(), 3, '../logs/email.log');
error_log("📥 Datos de sesión disponibles: " . print_r($_SESSION, true), 3, '../logs/email.log');

// Verificar si hay un correo pendiente
if (isset($_SESSION['send_welcome_email'])) {
    $emailData = $_SESSION['send_welcome_email'];
    
    error_log("✅ Datos de email encontrados en sesión: " . print_r($emailData, true), 3, '../logs/email.log');
    
    // Limpiar la sesión inmediatamente
    unset($_SESSION['send_welcome_email']);
    error_log("🧹 Datos de email removidos de la sesión", 3, '../logs/email.log');
    
    try {
        $isEnglish = ($emailData['language'] === 'en');
        $subject = $isEnglish ? 'Welcome to dwoosync - Access Key Included' : 'Bienvenido a dwoosync - Clave de Acceso Incluida';
        
        error_log("📧 Preparando correo en idioma: " . ($isEnglish ? 'English' : 'Spanish'), 3, '../logs/email.log');
        error_log("📧 Subject: " . $subject, 3, '../logs/email.log');
        error_log("📧 Destinatario: " . $emailData['email'], 3, '../logs/email.log');
        
        $welcomeMessage = $isEnglish ? 
            "<h2>Welcome to dwoosync!</h2>
             <p>Hello {$emailData['first_name']},</p>
             <p>Thank you for creating your account. Here are your access credentials:</p>
             <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                 <h3>Access Information:</h3>
                 <p><strong>Email:</strong> {$emailData['email']}</p>
                 <p><strong>License Key:</strong> <code style='background: #e9ecef; padding: 4px 8px; border-radius: 4px;'>{$emailData['license_key']}</code></p>
                 <p><strong>Domain:</strong> {$emailData['domain']}</p>
                 <p><strong>Plan:</strong> {$emailData['plan_name']}</p>
             </div>
             <p>You can access your dashboard at: <a href='https://dwoosync.com/subscribe/pages/dashboard.php'>https://dwoosync.com/subscribe/pages/dashboard.php</a></p>
             <p>If you have any questions, don't hesitate to contact our support team.</p>
             <p>Best regards,<br>The dwoosync Team</p>" 
            : 
            "<h2>¡Bienvenido a dwoosync!</h2>
             <p>Hola {$emailData['first_name']},</p>
             <p>Gracias por crear tu cuenta. Aquí tienes tus credenciales de acceso:</p>
             <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                 <h3>Información de Acceso:</h3>
                 <p><strong>Email:</strong> {$emailData['email']}</p>
                 <p><strong>Clave de Licencia:</strong> <code style='background: #e9ecef; padding: 4px 8px; border-radius: 4px;'>{$emailData['license_key']}</code></p>
                 <p><strong>Dominio:</strong> {$emailData['domain']}</p>
                 <p><strong>Plan:</strong> {$emailData['plan_name']}</p>
             </div>
             <p>Puedes acceder a tu panel en: <a href='https://dwoosync.com/subscribe/pages/dashboard.php'>https://dwoosync.com/subscribe/pages/dashboard.php</a></p>
             <p>Si tienes alguna pregunta, no dudes en contactar a nuestro equipo de soporte.</p>
             <p>Saludos cordiales,<br>El equipo de dwoosync</p>";
        
        error_log("📨 Llamando a sendEmail...", 3, '../logs/email.log');
        
        $emailSent = sendEmail($emailData['email'], $subject, $welcomeMessage, true);
        
        error_log("📬 Resultado de sendEmail: " . ($emailSent ? 'true' : 'false'), 3, '../logs/email.log');
        
        if ($emailSent) {
            error_log("✅ [WELCOME_EMAIL] Correo enviado exitosamente a: " . $emailData['email'], 3, '../logs/email.log');
            echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
        } else {
            error_log("❌ [WELCOME_EMAIL] Error enviando correo a: " . $emailData['email'], 3, '../logs/email.log');
            echo json_encode(['success' => false, 'message' => 'Failed to send email']);
        }
    } catch (Exception $e) {
        error_log("❌ [WELCOME_EMAIL] Excepción: " . $e->getMessage(), 3, '../logs/email.log');
        error_log("📍 Stack trace: " . $e->getTraceAsString(), 3, '../logs/email.log');
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
} else {
    error_log("❌ No se encontraron datos de email en la sesión", 3, '../logs/email.log');
    echo json_encode(['success' => false, 'message' => 'No email data found']);
}

error_log("📧 === FIN DE SEND_WELCOME_EMAIL (SESIÓN) ===", 3, '../logs/email.log');
?>