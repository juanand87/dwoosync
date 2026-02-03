<?php
/**
 * AJAX Endpoint: Send Activation Email
 * Sends welcome email after successful payment without blocking page render
 */

// Inicio de sesión segura
function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_strict_mode', 1);
        session_start();
    }
}

function isLoggedIn() {
    return isset($_SESSION['subscriber_id']) && !empty($_SESSION['subscriber_id']);
}

startSecureSession();

// Solo permitir requests POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validar que el usuario esté logueado
if (!isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'User not authenticated']);
    exit;
}

// Validar parámetros requeridos
if (!isset($_POST['billing_cycle_id']) || !is_numeric($_POST['billing_cycle_id'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid billing_cycle_id']);
    exit;
}

$billingCycleId = (int)$_POST['billing_cycle_id'];

error_log("[AJAX_EMAIL] Iniciando envío de correo para billing_cycle_id: $billingCycleId");

try {
    // Incluir conexión a la base de datos
    require_once __DIR__ . '/../../config/database.php';
    
    // Detectar idioma
    $isEnglish = (isset($_SESSION['language']) && $_SESSION['language'] === 'en') || 
                 (isset($_GET['lang']) && $_GET['lang'] === 'en') ||
                 (isset($_COOKIE['language']) && $_COOKIE['language'] === 'en');
    
    // Función de traducción
    function t($spanish, $english) {
        global $isEnglish;
        return $isEnglish ? $english : $spanish;
    }
    
    // Obtener información del usuario
    $userStmt = $db->prepare("SELECT email, first_name FROM subscribers WHERE id = ?");
    $userStmt->execute([$_SESSION['subscriber_id']]);
    $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userInfo) {
        error_log("[AJAX_EMAIL] ✗ Usuario no encontrado para subscriber_id: " . $_SESSION['subscriber_id']);
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    // Obtener información del billing cycle
    $billingStmt = $db->prepare("SELECT plan_type, status FROM billing_cycles WHERE id = ? AND subscriber_id = ?");
    $billingStmt->execute([$billingCycleId, $_SESSION['subscriber_id']]);
    $billingInfo = $billingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$billingInfo) {
        error_log("[AJAX_EMAIL] ✗ Billing cycle no encontrado para ID: $billingCycleId");
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Billing cycle not found']);
        exit;
    }
    
    $planType = $billingInfo['plan_type'];
    
    // Obtener license_key
    $licenseStmt = $db->prepare("SELECT license_key, expires_at FROM licenses WHERE subscriber_id = ?");
    $licenseStmt->execute([$_SESSION['subscriber_id']]);
    $licenseData = $licenseStmt->fetch(PDO::FETCH_ASSOC);
    $license_key = $licenseData['license_key'] ?? 'N/A';
    $expiresAt = $licenseData['expires_at'] ?? 'N/A';
    
    // Obtener dominio
    $subscriberStmt = $db->prepare("SELECT domain FROM subscribers WHERE id = ?");
    $subscriberStmt->execute([$_SESSION['subscriber_id']]);
    $subscriberData = $subscriberStmt->fetch(PDO::FETCH_ASSOC);
    $domain = $subscriberData['domain'] ?? 'N/A';
    
    // ENVÍO DE CORREO usando PHPMailer
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    // Configuración SMTP
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
    
    // Configurar correo
    $mail->setFrom('support@dwoosync.com', 'DWooSync');
    $mail->addReplyTo('support@dwoosync.com', 'DWooSync');
    $mail->addAddress($userInfo['email'], $userInfo['first_name']);
    
    // Headers adicionales para evitar spam
    $mail->addCustomHeader('X-Mailer', 'DWooSync System AJAX v1.0');
    $mail->addCustomHeader('X-Priority', '3');
    $mail->addCustomHeader('Return-Path', 'support@dwoosync.com');
    $mail->addCustomHeader('List-Unsubscribe', '<mailto:support@dwoosync.com?subject=unsubscribe>');
    
    // Obtener nombre del plan
    $planNames = [
        'free' => $isEnglish ? 'Free' : 'Gratuito',
        'premium' => 'Premium',
        'enterprise' => '+Spotify'
    ];
    $planName = $planNames[$planType] ?? ucfirst($planType);
    
    $subject = $isEnglish ? 
        'Welcome to DWooSync! - Your account has been activated' : 
        '¡Bienvenido a DWooSync! - Tu cuenta ha sido activada';
    
    $message = $isEnglish ? 
        "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #2c5aa0;'>Welcome to DWooSync!</h2>
            <h3>Hello {$userInfo['first_name']}!</h3>
            <p>Your account has been successfully activated and is ready to use.</p>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4 style='margin-top: 0;'>Account Details:</h4>
                <p><strong>Plan:</strong> $planName</p>
                <p><strong>License Key:</strong> $license_key</p>
                <p><strong>Domain:</strong> $domain</p>
                <p><strong>Expires:</strong> $expiresAt</p>
            </div>
            
            <p>You can now start using all the features included in your plan.</p>
            <p>If you have any questions, please don't hesitate to contact our support team.</p>
            
            <p style='margin-top: 30px;'>Thank you for choosing DWooSync!</p>
            <hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'>
            <p style='font-size: 12px; color: #666;'>DWooSync Team</p>
        </div>
        </body></html>" :
        "<html><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #2c5aa0;'>¡Bienvenido a DWooSync!</h2>
            <h3>¡Hola {$userInfo['first_name']}!</h3>
            <p>Tu cuenta ha sido activada exitosamente y está lista para usar.</p>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <h4 style='margin-top: 0;'>Detalles de tu cuenta:</h4>
                <p><strong>Plan:</strong> $planName</p>
                <p><strong>Clave de licencia:</strong> $license_key</p>
                <p><strong>Dominio:</strong> $domain</p>
                <p><strong>Expira:</strong> $expiresAt</p>
            </div>
            
            <p>Ahora puedes comenzar a usar todas las funciones incluidas en tu plan.</p>
            <p>Si tienes alguna pregunta, no dudes en contactar a nuestro equipo de soporte.</p>
            
            <p style='margin-top: 30px;'>¡Gracias por elegir DWooSync!</p>
            <hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'>
            <p style='font-size: 12px; color: #666;'>Equipo DWooSync</p>
        </div>
        </body></html>";
    
    // Configurar contenido
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $message;
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
    
    // Enviar
    error_log("[AJAX_EMAIL] Enviando correo a: " . $userInfo['email']);
    $emailResult = $mail->send();
    
    if ($emailResult) {
        error_log("[AJAX_EMAIL] ✓ Correo enviado exitosamente a: " . $userInfo['email']);
        
        // Registrar en email.log
        $logPath = __DIR__ . '/../../logs/email.log';
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logLine = "[" . date('Y-m-d H:i:s') . "] AJAX EMAIL SUCCESS TO: " . $userInfo['email'] . " PLAN: $planType\n";
        @file_put_contents($logPath, $logLine, FILE_APPEND);
        
        // Respuesta exitosa
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => t('Correo de activación enviado exitosamente', 'Activation email sent successfully'),
            'email' => $userInfo['email'],
            'plan' => $planName
        ]);
        
    } else {
        error_log("[AJAX_EMAIL] ✗ Error enviando correo a: " . $userInfo['email']);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => t('Error enviando correo de activación', 'Error sending activation email')
        ]);
    }
    
} catch (Exception $e) {
    error_log("[AJAX_EMAIL] ✗ Excepción: " . $e->getMessage());
    
    // Registrar error en email.log
    $logPath = __DIR__ . '/../../logs/email.log';
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logLine = "[" . date('Y-m-d H:i:s') . "] AJAX EMAIL FAILED ERROR: " . $e->getMessage() . "\n";
    @file_put_contents($logPath, $logLine, FILE_APPEND);
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'error' => t('Error interno del servidor', 'Internal server error')
    ]);
}

?>