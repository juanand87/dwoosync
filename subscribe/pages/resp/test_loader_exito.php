<?php
/**
 * Simulador de exito.php para probar el precargador
 * URL: https://dwoosync.com/subscribe/pages/test_loader_exito.php?billing_cycle_id=198&lang=en
 */

// Simular que el correo está enviándose
$emailStatus = 'sending';
$currentLang = $_GET['lang'] ?? 'es';
$isEnglish = ($currentLang === 'en');

function t($spanish, $english) {
    global $isEnglish;
    return $isEnglish ? $english : $spanish;
}

// Simular datos de usuario
$userInfo = [
    'first_name' => 'Juan',
    'email' => 'juanand87@gmail.com'
];

$paymentMessage = t('Tu pago ha sido procesado exitosamente. Tu suscripción está siendo activada.', 'Your payment has been processed successfully. Your subscription is being activated.');
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

        .loading-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            animation: loadingPulse 2s ease-in-out infinite;
        }

        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes loadingPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .success-icon i, .loading-icon i {
            font-size: 48px;
            color: white;
        }

        .loading-icon i {
            animation: spin 1.5s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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

        .email-status {
            margin: 20px 0;
            padding: 20px;
            border-radius: 12px;
            position: relative;
            z-index: 1;
            transition: all 0.5s ease;
        }

        .email-sending {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #0284c7;
            color: #075985;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .email-success {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1px solid #22c55e;
            color: #15803d;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .email-failed {
            background: linear-gradient(135deg, #fefce8, #fef3c7);
            border: 1px solid #f59e0b;
            color: #92400e;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .email-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #0284c7;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .status-text {
            font-weight: 500;
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
        <!-- Icono que cambia según el estado del correo -->
        <div class="loading-icon" id="mainIcon">
            <i class="fas fa-envelope"></i>
        </div>
        
        <h1 class="success-title" id="mainTitle">
            <?php echo t('¡Pago Exitoso!', 'Payment Successful!'); ?>
        </h1>
        
        <p class="success-message" id="mainMessage">
            <?php echo $paymentMessage; ?>
        </p>

        <div class="user-info">
            <div class="user-greeting">
                <?php echo t('¡Hola', 'Hello') . ' ' . htmlspecialchars($userInfo['first_name']); ?>!
            </div>
            <div class="user-email">
                <?php echo htmlspecialchars($userInfo['email']); ?>
            </div>
        </div>
        
        <!-- Estado del correo -->
        <div id="emailStatus" class="email-status email-sending">
            <div class="email-spinner"></div>
            <span class="status-text">
                <?php echo t('Enviando correo de bienvenida...', 'Sending welcome email...'); ?>
            </span>
        </div>

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
        // Variables de estado
        const isEnglish = <?php echo $isEnglish ? 'true' : 'false'; ?>;
        
        // Textos en ambos idiomas
        const texts = {
            es: {
                sending: 'Enviando correo de bienvenida...',
                success: '¡Correo de bienvenida enviado exitosamente!',
                failed: 'Cuenta activada pero el correo no se pudo enviar.',
                redirect: '¿Deseas ir al panel de administración?'
            },
            en: {
                sending: 'Sending welcome email...',
                success: 'Welcome email sent successfully!',
                failed: 'Account activated but email failed to send.',
                redirect: 'Do you want to go to the admin panel?'
            }
        };
        
        const t = isEnglish ? texts.en : texts.es;
        
        // Simular envío del correo
        setTimeout(function() {
            // Cambiar icono a éxito
            const mainIcon = document.getElementById('mainIcon');
            mainIcon.className = 'success-icon';
            mainIcon.innerHTML = '<i class="fas fa-check"></i>';
            
            // Actualizar estado del correo
            const emailStatusDiv = document.getElementById('emailStatus');
            emailStatusDiv.className = 'email-status email-success';
            emailStatusDiv.innerHTML = '<i class="fas fa-envelope-check"></i><span class="status-text">' + t.success + '</span>';
            
            // Efecto de éxito
            emailStatusDiv.style.transform = 'scale(1.05)';
            setTimeout(function() {
                emailStatusDiv.style.transform = 'scale(1)';
            }, 300);
            
        }, 3000); // 3 segundos de "envío"
        
        // Efecto de entrada suave
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.success-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(function() {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>