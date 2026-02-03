<?php
/**
 * Procesar activación de plan gratuito
 * Este archivo activa el plan gratuito y crea el ciclo de facturación
 */

// Mostrar errores solo en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cargar archivos de configuración
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Detectar idioma
$currentLang = $_SESSION['selected_language'] ?? 'es';
$isEnglish = ($currentLang === 'en');

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php?lang=' . $currentLang);
    exit;
}

// Obtener datos del usuario
$subscriber_id = $_SESSION['subscriber_id'] ?? null;
$license_key = $_SESSION['license_key'] ?? null;

if (!$subscriber_id) {
    header('Location: login.php?lang=' . $currentLang . '&error=session_expired');
    exit;
}

try {
    $db = getDatabase();
    $db->beginTransaction();
    
    // 1. Actualizar el estado del suscriptor a 'active'
    $stmt = $db->prepare("UPDATE subscribers SET status = 'active', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$subscriber_id]);
    
    // 2. Actualizar el estado de la licencia a 'active' y establecer usage_limit en 10
    if ($license_key) {
        $stmt = $db->prepare("UPDATE licenses SET status = 'active', usage_limit = 10, updated_at = NOW() WHERE license_key = ?");
        $stmt->execute([$license_key]);
    }
    
    // 3. Crear ciclo de facturación para el plan gratuito
    // El plan gratuito dura 1 mes y se renueva automáticamente
    $cycle_start_date = date('Y-m-d H:i:s');
    $cycle_end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // Desactivar cualquier ciclo anterior
    $stmt = $db->prepare("UPDATE billing_cycles SET is_active = 0 WHERE subscriber_id = ?");
    $stmt->execute([$subscriber_id]);
    
    // Generar license_key única
    $license_key = 'FREE-' . strtoupper(substr(md5(uniqid($subscriber_id, true)), 0, 12));
    
    // Crear nuevo ciclo de facturación activo
    $stmt = $db->prepare("
        INSERT INTO billing_cycles 
        (subscriber_id, plan_type, amount, license_key, cycle_start_date, cycle_end_date, due_date, status, is_active, created_at) 
        VALUES (?, 'free', 0.00, ?, ?, ?, ?, 'paid', 1, NOW())
    ");
    $stmt->execute([$subscriber_id, $license_key, $cycle_start_date, $cycle_end_date, $cycle_end_date]);
    
    // 4. Actualizar la licencia del suscriptor con la nueva license_key
    $stmt = $db->prepare("
        UPDATE licenses 
        SET license_key = ?, status = 'active', expires_at = ?, usage_limit = 10, updated_at = NOW()
        WHERE subscriber_id = ?
    ");
    $stmt->execute([$license_key, $cycle_end_date, $subscriber_id]);
    
    // Si no existe una licencia, crearla
    $stmt = $db->prepare("SELECT id FROM licenses WHERE subscriber_id = ?");
    $stmt->execute([$subscriber_id]);
    if (!$stmt->fetch()) {
        $stmt = $db->prepare("
            INSERT INTO licenses 
            (subscriber_id, license_key, status, expires_at, usage_limit, created_at) 
            VALUES (?, ?, 'active', ?, 10, NOW())
        ");
        $stmt->execute([$subscriber_id, $license_key, $cycle_end_date]);
    }
    
    // Confirmar transacción
    $db->commit();
    
    // 5. Enviar correo de bienvenida
    try {
        // Obtener información del usuario
        $stmt = $db->prepare("SELECT first_name, email, domain FROM subscribers WHERE id = ?");
        $stmt->execute([$subscriber_id]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            // Incluir el sistema de correo mejorado con PHPMailer
            try {
                require_once __DIR__ . '/email_phpmailer_smtp.php';
                
                $planName = $isEnglish ? 'Free' : 'Gratuito';
                
                // Usar PHPMailer
                $subject = $isEnglish ? 'Welcome to DWooSync! - Your account has been activated' : '¡Bienvenido a DWooSync! - Tu cuenta ha sido activada';
                
                $message = $isEnglish ? 
                    "<h3>Hello {$userData['first_name']}!</h3><p>Welcome to DWooSync! Your account has been successfully activated.</p><p><strong>Plan:</strong> $planName<br><strong>License Key:</strong> $license_key<br><strong>Domain:</strong> {$userData['domain']}<br><strong>Expires:</strong> $cycle_end_date</p><p>Thank you for choosing DWooSync!</p>" :
                    "<h3>¡Hola {$userData['first_name']}!</h3><p>¡Bienvenido a DWooSync! Tu cuenta ha sido activada exitosamente.</p><p><strong>Plan:</strong> $planName<br><strong>Clave de licencia:</strong> $license_key<br><strong>Dominio:</strong> {$userData['domain']}<br><strong>Expira:</strong> $cycle_end_date</p><p>¡Gracias por elegir DWooSync!</p>";
                
                // Usar la función sendEmail
                $emailResult = sendEmail($userData['email'], $subject, $message, true, 'DWooSync');
                
                if ($emailResult) {
                    error_log("✓ Correo de activación de plan gratuito enviado correctamente a: " . $userData['email']);
                } else {
                    error_log("✗ Error enviando correo de activación a: " . $userData['email']);
                }
            } catch (Exception $emailException) {
                // Si falla PHPMailer, usar mail() nativo como respaldo
                error_log("✗ Error con PHPMailer, usando mail() nativo: " . $emailException->getMessage());
                
                $planName = $isEnglish ? 'Free' : 'Gratuito';
                $subject = $isEnglish ? 'Welcome to DWooSync! - Your account has been activated' : '¡Bienvenido a DWooSync! - Tu cuenta ha sido activada';
                
                $message = $isEnglish ? 
                    "Hello {$userData['first_name']}!\n\nWelcome to DWooSync! Your account has been successfully activated.\n\nPlan: $planName\nLicense Key: $license_key\nDomain: {$userData['domain']}\nExpires: $cycle_end_date\n\nThank you for choosing DWooSync!" :
                    "¡Hola {$userData['first_name']}!\n\n¡Bienvenido a DWooSync! Tu cuenta ha sido activada exitosamente.\n\nPlan: $planName\nClave de licencia: $license_key\nDominio: {$userData['domain']}\nExpira: $cycle_end_date\n\n¡Gracias por elegir DWooSync!";
                
                $headers = "From: DWooSync <support@dwoosync.com>\r\n";
                $headers .= "Reply-To: support@dwoosync.com\r\n";
                $headers .= "X-Mailer: DWooSync System\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                $emailResult = mail($userData['email'], $subject, $message, $headers);
                
                if ($emailResult) {
                    error_log("✓ Correo de activación enviado con mail() nativo a: " . $userData['email']);
                } else {
                    error_log("✗ Error enviando correo con mail() nativo a: " . $userData['email']);
                }
            }
        }
    } catch (Exception $e) {
        error_log("✗ Error enviando correo de activación: " . $e->getMessage());
        // No fallar el proceso si el correo falla
    }
    
    // Redirigir al dashboard con mensaje de éxito
    header('Location: dashboard.php?lang=' . $currentLang . '&success=account_activated');
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($db) {
        $db->rollBack();
    }
    
    // Log del error
    error_log("Error al procesar plan gratuito: " . $e->getMessage());
    
    // Redirigir con error
    header('Location: checkout.php?plan=free&lang=' . $currentLang . '&error=activation_failed');
    exit;
}
?>