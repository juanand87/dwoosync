<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Detectar idioma del navegador
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'es', 0, 2);
$currentLang = $_GET['lang'] ?? ($_SESSION['selected_language'] ?? $browserLang);
$isEnglish = ($currentLang === 'en');

// Guardar idioma seleccionado en sesión
if (isset($_GET['lang'])) {
    $_SESSION['selected_language'] = $_GET['lang'];
}

// Función para traducir texto
function t($spanish, $english) {
    global $isEnglish;
    return $isEnglish ? $english : $spanish;
}

// Obtener billing_cycle_id desde la URL
$billingCycleId = $_GET['billing_cycle_id'] ?? null;
$paymentSuccess = true; // Siempre mostrar como exitoso
$paymentMessage = '';

// Obtener información del usuario si está logueado
$userInfo = null;
if (isLoggedIn() && isset($_SESSION['subscriber_id'])) {
    try {
        $db = getDatabase();
        $stmt = $db->prepare("SELECT first_name, last_name, email FROM subscribers WHERE id = ?");
        $stmt->execute([$_SESSION['subscriber_id']]);
        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error obteniendo información del usuario: ' . $e->getMessage());
    }
}

// Procesar solo el cambio de is_active si viene billing_cycle_id
if ($billingCycleId && isLoggedIn() && isset($_SESSION['subscriber_id'])) {
    try {
        $db = getDatabase();
        
        // Obtener el plan_type del billing_cycle para calcular las fechas
        $planStmt = $db->prepare("SELECT plan_type FROM billing_cycles WHERE id = ?");
        $planStmt->execute([$billingCycleId]);
        $billingData = $planStmt->fetch(PDO::FETCH_ASSOC);
        $planType = $billingData['plan_type'] ?? 'free';
        
        // Calcular fechas del ciclo (30 días para todos los planes)
        $cycle_start_date = date('Y-m-d');
        $cycle_end_date = date('Y-m-d', strtotime('+30 days'));
        $due_date = $cycle_end_date; // due_date = cycle_end_date
        $paid_date = date('Y-m-d');
        
        // Actualizar billing_cycles: establecer todas las fechas y activar
        $updateBillingStmt = $db->prepare("
            UPDATE billing_cycles 
            SET is_active = 1, 
                status = 'paid',
                cycle_start_date = ?,
                cycle_end_date = ?,
                due_date = ?,
                paid_date = ?,
                updated_at = NOW() 
            WHERE id = ? AND subscriber_id = ?
        ");
        $billingResult = $updateBillingStmt->execute([
            $cycle_start_date,
            $cycle_end_date,
            $due_date,
            $paid_date,
            $billingCycleId, 
            $_SESSION['subscriber_id']
        ]);
        
        // Establecer usage_limit según el plan
        $usage_limit = 10; // Por defecto free
        if ($planType === 'premium') {
            $usage_limit = 5000; // requests_per_day del plan premium
        } elseif ($planType === 'enterprise') {
            $usage_limit = 20000; // requests_per_day del plan enterprise
        }
        
        // Actualizar licenses: status a 'active', expires_at a +1 mes y usage_limit según el plan
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
        $updateLicenseStmt = $db->prepare("
            UPDATE licenses 
            SET status = 'active',
                expires_at = ?,
                usage_limit = ?,
                updated_at = NOW()
            WHERE subscriber_id = ?
        ");
        $licenseResult = $updateLicenseStmt->execute([$expiresAt, $usage_limit, $_SESSION['subscriber_id']]);
        
        // Actualizar subscribers: status a 'active' y plan_type al plan pagado
        $updateSubscriberStmt = $db->prepare("
            UPDATE subscribers 
            SET status = 'active',
                plan_type = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $subscriberResult = $updateSubscriberStmt->execute([$planType, $_SESSION['subscriber_id']]);
        
        if ($billingResult && $licenseResult && $subscriberResult) {
            error_log("✓ billing_cycles.is_active=1, status='paid', paid_date=NOW() y licenses.status='active', expires_at='$expiresAt' (+1 mes) y subscribers.status='active', plan_type='$planType' actualizado para ID: $billingCycleId");
            $paymentMessage = t('Tu pago ha sido procesado exitosamente. Tu suscripción está siendo activada.', 'Your payment has been processed successfully. Your subscription is being activated.');
            
            // Enviar correo de activación de cuenta
            if ($userInfo) {
                try {
                    // Obtener nombre del plan
                    $planNames = [
                        'free' => $isEnglish ? 'Free' : 'Gratuito',
                        'premium' => 'Premium',
                        'enterprise' => '+Spotify'
                    ];
                    $planName = $planNames[$planType] ?? ucfirst($planType);
                    
                    // Obtener license_key
                    $licenseStmt = $db->prepare("SELECT license_key FROM licenses WHERE subscriber_id = ?");
                    $licenseStmt->execute([$_SESSION['subscriber_id']]);
                    $licenseData = $licenseStmt->fetch(PDO::FETCH_ASSOC);
                    $license_key = $licenseData['license_key'] ?? 'N/A';
                    
                    // Obtener dominio
                    $subscriberStmt = $db->prepare("SELECT domain FROM subscribers WHERE id = ?");
                    $subscriberStmt->execute([$_SESSION['subscriber_id']]);
                    $subscriberData = $subscriberStmt->fetch(PDO::FETCH_ASSOC);
                    $domain = $subscriberData['domain'] ?? 'N/A';
                    
                    // Incluir el sistema de correo mejorado con PHPMailer
                    require_once __DIR__ . '/email_phpmailer_smtp.php';
                    
                    $to = $userInfo['email'];
                    $subject = $isEnglish ? 'Welcome to DWooSync! - Your account has been activated' : '¡Bienvenido a DWooSync! - Tu cuenta ha sido activada';
                    
                    $message = $isEnglish ? 
                        "<h3>Hello {$userInfo['first_name']}!</h3><p>Welcome to DWooSync! Your account has been successfully activated.</p><p><strong>Plan:</strong> $planName<br><strong>License Key:</strong> $license_key<br><strong>Domain:</strong> $domain<br><strong>Expires:</strong> $expiresAt</p><p>Thank you for choosing DWooSync!</p>" :
                        "<h3>¡Hola {$userInfo['first_name']}!</h3><p>¡Bienvenido a DWooSync! Tu cuenta ha sido activada exitosamente.</p><p><strong>Plan:</strong> $planName<br><strong>Clave de licencia:</strong> $license_key<br><strong>Dominio:</strong> $domain<br><strong>Expira:</strong> $expiresAt</p><p>¡Gracias por elegir DWooSync!</p>";
                    
                    // Usar PHPMailer
                    $emailResult = sendEmail($to, $subject, $message, true, 'DWooSync');
                    
                    if ($emailResult) {
                        error_log("✓ Correo de activación enviado correctamente a: " . $userInfo['email']);
                    } else {
                        error_log("✗ Error enviando correo de activación a: " . $userInfo['email']);
                    }
                } catch (Exception $emailException) {
                    error_log("✗ Error enviando correo de activación: " . $emailException->getMessage());
                }
            }
        } else {
            error_log("✗ Error actualizando billing_cycles, licenses o subscribers para ID: $billingCycleId");
            error_log("  billing_cycles: " . ($billingResult ? 'OK' : 'ERROR'));
            error_log("  licenses: " . ($licenseResult ? 'OK' : 'ERROR'));
            error_log("  subscribers: " . ($subscriberResult ? 'OK' : 'ERROR'));
            $paymentMessage = t('Tu pago ha sido procesado exitosamente. Tu suscripción está siendo activada.', 'Your payment has been processed successfully. Your subscription is being activation.');
        }
        
    } catch (Exception $e) {
        error_log('Error actualizando billing_cycles, licenses y subscribers: ' . $e->getMessage());
        $paymentMessage = t('Tu pago ha sido procesado exitosamente. Tu suscripción está siendo activada.', 'Your payment has been processed successfully. Your subscription is being activation.');
    }
} else {
    // Mensaje de éxito simple si no hay billing_cycle_id
    $paymentMessage = t('Tu pago ha sido procesado exitosamente. Tu suscripción está siendo activada.', 'Your payment has been processed successfully. Your subscription is being activation.');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Pago Exitoso - DWooSync', 'Payment Successful - DWooSync'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .success-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(102, 126, 234, 0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
            100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        }

        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            animation: successPulse 2s ease-in-out infinite;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .success-icon i {
            font-size: 48px;
            color: white;
        }

        .success-title {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        .success-message {
            font-size: 18px;
            color: #6b7280;
            margin-bottom: 40px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        .user-info {
            background: #f8fafc;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }

        .user-greeting {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .user-email {
            font-size: 16px;
            color: #6b7280;
        }

        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
            position: relative;
            z-index: 1;
        }

        .btn {
            padding: 16px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .footer-text {
            margin-top: 30px;
            font-size: 14px;
            color: #9ca3af;
            position: relative;
            z-index: 1;
        }

        @media (max-width: 640px) {
            .success-container {
                padding: 40px 20px;
            }

            .success-title {
                font-size: 24px;
            }

            .success-message {
                font-size: 16px;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                padding: 14px 24px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1 class="success-title">
            <?php echo t('¡Pago Exitoso!', 'Payment Successful!'); ?>
        </h1>
        
        <p class="success-message">
            <?php echo $paymentMessage; ?>
        </p>

        <?php if ($userInfo): ?>
        <div class="user-info">
            <div class="user-greeting">
                <?php echo t('¡Hola', 'Hello') . ' ' . htmlspecialchars($userInfo['first_name']); ?>!
            </div>
            <div class="user-email">
                <?php echo htmlspecialchars($userInfo['email']); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="dashboard.php?lang=<?php echo $currentLang; ?>" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i>
                <?php echo t('Ir al Panel', 'Go to Dashboard'); ?>
            </a>
            <a href="../pages/payment.php?lang=<?php echo $currentLang; ?>" class="btn btn-secondary">
                <i class="fas fa-credit-card"></i>
                <?php echo t('Ver Planes', 'View Plans'); ?>
            </a>
        </div>

        <p class="footer-text">
            <?php echo t('Si tienes alguna pregunta, contáctanos en', 'If you have any questions, contact us at'); ?> 
            <strong>support@dwoosync.com</strong>
        </p>
    </div>

    <script>
        // Redirección automática opcional después de 10 segundos
        setTimeout(function() {
            const userConfirmed = confirm('<?php echo t("¿Deseas ir al panel de administración?", "Do you want to go to the admin panel?"); ?>');
            if (userConfirmed) {
                window.location.href = 'dashboard.php?lang=<?php echo $currentLang; ?>';
            }
        }, 10000);
    </script>
</body>
</html>