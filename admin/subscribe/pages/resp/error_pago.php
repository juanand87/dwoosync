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
            padding: 60px 40px;
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
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: shake 0.8s ease-out;
        }

        .error-icon i {
            font-size: 50px;
            color: white;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .error-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        .error-subtitle {
            font-size: 1.2rem;
            color: #6b7280;
            margin-bottom: 40px;
            line-height: 1.6;
            animation: fadeInUp 0.8s ease-out 0.4s both;
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 40px;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .error-message h3 {
            color: #dc2626;
            font-size: 1.3rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .error-message p {
            color: #374151;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .error-message p:last-child {
            margin-bottom: 0;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.8s both;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
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
            padding: 20px;
            margin-bottom: 30px;
            animation: fadeInUp 0.8s ease-out 0.6s both;
        }

        .user-info h4 {
            color: #374151;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .user-info p {
            color: #6b7280;
            margin: 5px 0;
        }

        .language-switcher {
            position: absolute;
            top: 20px;
            right: 20px;
        }

        .language-switcher a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .language-switcher a:hover {
            background: #e5e7eb;
        }

        .language-switcher a.active {
            background: #ef4444;
            color: white;
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
        <!-- Language Switcher -->
        <div class="language-switcher">
            <a href="?lang=es&error=<?php echo $errorType; ?>" class="<?php echo !$isEnglish ? 'active' : ''; ?>">
                <span>🇪🇸</span> Español
            </a>
            <a href="?lang=en&error=<?php echo $errorType; ?>" class="<?php echo $isEnglish ? 'active' : ''; ?>">
                <span>🇬🇧</span> English
            </a>
        </div>

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
                <?php echo t('No te preocupes, tu información está segura y no se ha procesado ningún cargo.', 'Don\'t worry, your information is safe and no charges have been processed.'); ?>
            </p>
            <p>
                <?php echo t('Puedes intentar el pago nuevamente o contactar a soporte si el problema persiste.', 'You can try the payment again or contact support if the problem persists.'); ?>
            </p>
        </div>

        <!-- Help Section -->
        <div class="help-section">
            <h4>
                <i class="fas fa-question-circle"></i>
                <?php echo t('¿Necesitas ayuda?', 'Need help?'); ?>
            </h4>
            <p><?php echo t('Si el problema persiste, puedes:', 'If the problem persists, you can:'); ?></p>
            <ul>
                <li><?php echo t('Verificar que tu método de pago sea válido', 'Verify that your payment method is valid'); ?></li>
                <li><?php echo t('Intentar con otro método de pago', 'Try with another payment method'); ?></li>
                <li><?php echo t('Contactar a soporte técnico', 'Contact technical support'); ?></li>
                <li><?php echo t('Revisar tu conexión a internet', 'Check your internet connection'); ?></li>
            </ul>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="payment.php?plan=premium&lang=<?php echo $currentLang; ?>" class="btn btn-primary">
                <i class="fas fa-redo"></i>
                <?php echo t('Intentar Nuevamente', 'Try Again'); ?>
            </a>
            <a href="contact.php?lang=<?php echo $currentLang; ?>" class="btn btn-danger">
                <i class="fas fa-headset"></i>
                <?php echo t('Contactar Soporte', 'Contact Support'); ?>
            </a>
            <a href="../index.php?lang=<?php echo $currentLang; ?>" class="btn btn-secondary">
                <i class="fas fa-home"></i>
                <?php echo t('Volver al Inicio', 'Back to Home'); ?>
            </a>
        </div>
    </div>

    <script>
        // Auto-redirect after 60 seconds to try again
        setTimeout(function() {
            if (confirm('<?php echo t("¿Te gustaría intentar el pago nuevamente?", "Would you like to try the payment again?"); ?>')) {
                window.location.href = 'payment.php?plan=premium&lang=<?php echo $currentLang; ?>';
            }
        }, 60000);
    </script>
</body>
</html>


