<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Detectar idioma - Inglés por defecto
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
$currentLang = $_GET['lang'] ?? ($_SESSION['selected_language'] ?? 'en');
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

// Obtener información del error si viene en la URL
$errorType = $_GET['error'] ?? 'general';
$billingCycleId = $_GET['billing_cycle_id'] ?? null;

// Obtener información del plan desde billing_cycle_id o URL
$planType = $_GET['plan'] ?? null;  // Primero intentar desde URL
if ($billingCycleId && !$planType) {
    try {
        $db = getDatabase();
        $stmt = $db->prepare("SELECT plan_type FROM billing_cycles WHERE id = ?");
        $stmt->execute([$billingCycleId]);
        $billing_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $planType = $billing_data['plan_type'] ?? null;
    } catch (Exception $e) {
        error_log('Error obteniendo plan desde billing_cycle_id: ' . $e->getMessage());
        $planType = null;
    }
}

// Si aún no tenemos plan, usar premium como último recurso
if (!$planType) {
    $planType = 'premium';
}

$errorMessages = [
    'general' => [
        'es' => 'Ha ocurrido un error durante el procesamiento del pago',
        'en' => 'An error occurred during payment processing'
    ],
    'cancelled' => [
        'es' => 'El pago fue cancelado',
        'en' => 'Payment was cancelled'
    ],
    'failed' => [
        'es' => 'El pago no pudo ser procesado',
        'en' => 'Payment could not be processed'
    ],
    'timeout' => [
        'es' => 'El pago expiró por tiempo de espera',
        'en' => 'Payment expired due to timeout'
    ]
];

$currentErrorMessage = $errorMessages[$errorType][$isEnglish ? 'en' : 'es'] ?? $errorMessages['general'][$isEnglish ? 'en' : 'es'];
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Error de Pago - DWooSync', 'Payment Error - DWooSync'); ?></title>
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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 30px 25px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #ef4444, #dc2626, #ef4444);
            background-size: 200% 100%;
            animation: shimmer 2s ease-in-out infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .error-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: shake 0.8s ease-out;
        }

        .error-icon i {
            font-size: 40px;
            color: white;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .error-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 12px;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .error-subtitle {
            font-size: 1.1rem;
            color: #6b7280;
            margin-bottom: 25px;
            line-height: 1.6;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .error-message h3 {
            color: #dc2626;
            font-size: 1.2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .error-message p {
            color: #374151;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .error-message p:last-child {
            margin-bottom: 0;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.8s both;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            min-width: 200px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
        }

        .user-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .user-info h4 {
            color: #374151;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .user-info p {
            color: #6b7280;
            margin: 3px 0;
        }

        .help-section {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .help-section h4 {
            color: #0369a1;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .help-section p {
            color: #374151;
            margin-bottom: 10px;
        }

        .help-section ul {
            text-align: left;
            color: #374151;
            margin-left: 20px;
        }

        .help-section li {
            margin-bottom: 5px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .error-container {
                padding: 40px 20px;
            }

            .error-title {
                font-size: 2rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <!-- Error Icon -->
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>

        <!-- Error Title -->
        <h1 class="error-title">
            <?php echo t('Error de Pago', 'Payment Error'); ?>
        </h1>

        <!-- Error Subtitle -->
        <p class="error-subtitle">
            <?php echo $currentErrorMessage; ?>
        </p>

        <!-- User Info (if logged in) -->
        <?php if ($userInfo): ?>
        <div class="user-info">
            <h4><i class="fas fa-user"></i> <?php echo t('Información de la Cuenta', 'Account Information'); ?></h4>
            <p><strong><?php echo t('Nombre:', 'Name:'); ?></strong> <?php echo htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']); ?></p>
            <p><strong><?php echo t('Email:', 'Email:'); ?></strong> <?php echo htmlspecialchars($userInfo['email']); ?></p>
        </div>
        <?php endif; ?>

        <!-- Error Message -->
        <div class="error-message">
            <h3>
                <i class="fas fa-info-circle"></i>
                <?php echo t('¿Qué puedes hacer?', 'What can you do?'); ?>
            </h3>
            <p>
                <?php echo t('Tu información está segura. Puedes intentar nuevamente o contactar soporte.', 'Your information is safe. You can try again or contact support.'); ?>
            </p>
            <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                <li><?php echo t('Verificar tu método de pago', 'Verify your payment method'); ?></li>
                <li><?php echo t('Revisar tu conexión a internet', 'Check your internet connection'); ?></li>
                <li><?php echo t('Contactar soporte si persiste', 'Contact support if it persists'); ?></li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if ($billingCycleId && $planType): ?>
                <?php if (isLoggedIn()): ?>
                    <!-- Usuario logueado: ir directamente a payment -->
                    <a href="payment.php?plan=<?php echo urlencode($planType); ?>&billing_cycle_id=<?php echo urlencode($billingCycleId); ?>&lang=<?php echo $currentLang; ?>" class="btn btn-primary">
                <?php else: ?>
                    <!-- Usuario no logueado: ir a login con redirección a payment -->
                    <?php 
                    $redirectUrl = "payment.php?plan=" . urlencode($planType) . "&billing_cycle_id=" . urlencode($billingCycleId) . "&lang=" . $currentLang;
                    ?>
                    <a href="login.php?redirect=<?php echo urlencode($redirectUrl); ?>" class="btn btn-primary">
                <?php endif; ?>
            <?php else: ?>
                <a href="checkout.php?lang=<?php echo $currentLang; ?>" class="btn btn-primary">
            <?php endif; ?>
                <i class="fas fa-redo"></i>
                <?php echo t('Intentar Nuevamente', 'Try Again'); ?>
            </a>
            <a href="contact.php?lang=<?php echo $currentLang; ?>" class="btn btn-danger">
                <i class="fas fa-headset"></i>
                <?php echo t('Contactar Soporte', 'Contact Support'); ?>
            </a>
        </div>
    </div>

    <script>
        // Auto-redirect after 60 seconds to try again
        setTimeout(function() {
            if (confirm('<?php echo t("¿Te gustaría intentar el pago nuevamente?", "Would you like to try the payment again?"); ?>')) {
                <?php if ($billingCycleId && $planType): ?>
                    <?php if (isLoggedIn()): ?>
                        window.location.href = 'payment.php?plan=<?php echo urlencode($planType); ?>&billing_cycle_id=<?php echo urlencode($billingCycleId); ?>&lang=<?php echo $currentLang; ?>';
                    <?php else: ?>
                        <?php 
                        $redirectUrl = "payment.php?plan=" . urlencode($planType) . "&billing_cycle_id=" . urlencode($billingCycleId) . "&lang=" . $currentLang;
                        ?>
                        window.location.href = 'login.php?redirect=<?php echo urlencode($redirectUrl); ?>';
                    <?php endif; ?>
                <?php else: ?>
                    window.location.href = 'checkout.php?lang=<?php echo $currentLang; ?>';
                <?php endif; ?>
            }
        }, 60000);
    </script>
</body>
</html>


