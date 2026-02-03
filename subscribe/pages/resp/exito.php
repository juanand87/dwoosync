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

// Validar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: ../index.php?error=unauthorized&lang=' . $currentLang);
    exit;
}

// Obtener billing_cycle_id de la URL
$billingCycleId = $_GET['billing_cycle_id'] ?? null;

if (!$billingCycleId || !is_numeric($billingCycleId)) {
    header('Location: ../index.php?error=invalid_id&lang=' . $currentLang);
    exit;
}

// Usar la función getDatabase() que ya está disponible en functions.php
$db = getDatabase();

// Inicializar variables
$emailStatus = 'pending';
$shouldSendEmail = false;
$userInfo = null;
$paymentMessage = t('Tu pago ha sido procesado exitosamente.', 'Your payment has been processed successfully.');

// Obtener información del usuario
$userStmt = $db->prepare("SELECT email, first_name FROM subscribers WHERE id = ?");
$userStmt->execute([$_SESSION['subscriber_id']]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

// Verificar y actualizar el billing cycle, licenses y subscribers
$billingStmt = $db->prepare("SELECT plan_type, status FROM billing_cycles WHERE id = ? AND subscriber_id = ?");
$billingStmt->execute([$billingCycleId, $_SESSION['subscriber_id']]);
$billingCycleData = $billingStmt->fetch(PDO::FETCH_ASSOC);

if ($billingCycleData) {
    $planType = $billingCycleData['plan_type'];
    
    // Solo procesar si el status no es 'paid' ya
    if ($billingCycleData['status'] !== 'paid') {
        // Calcular fecha de expiración (1 mes desde ahora)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
        
        // Actualizar billing_cycles
        $updateBillingStmt = $db->prepare("UPDATE billing_cycles SET is_active = 1, status = 'paid', paid_date = NOW() WHERE id = ?");
        $billingResult = $updateBillingStmt->execute([$billingCycleId]);
        
        // Actualizar licenses
        $updateLicenseStmt = $db->prepare("UPDATE licenses SET status = 'active', expires_at = ? WHERE subscriber_id = ?");
        $licenseResult = $updateLicenseStmt->execute([$expiresAt, $_SESSION['subscriber_id']]);
        
        // Actualizar subscribers
        $updateSubscriberStmt = $db->prepare("UPDATE subscribers SET status = 'active', plan_type = ? WHERE id = ?");
        $subscriberResult = $updateSubscriberStmt->execute([$planType, $_SESSION['subscriber_id']]);
        
        if ($billingResult && $licenseResult && $subscriberResult) {
            error_log("✓ billing_cycles.is_active=1, status='paid', paid_date=NOW() y licenses.status='active', expires_at='$expiresAt' (+1 mes) y subscribers.status='active', plan_type='$planType' actualizado para ID: $billingCycleId");
            $paymentMessage = t('Tu pago ha sido procesado exitosamente. Tu suscripción está siendo activada.', 'Your payment has been processed successfully. Your subscription is being activated.');
            
            // Solo marcar que se debe enviar el correo, no enviarlo aquí
            if ($userInfo) {
                $shouldSendEmail = true;
                $emailStatus = 'pending';
                error_log("[EXITO_DEFERRED] Marcando correo para envío diferido a: " . $userInfo['email']);
            }
        } else {
            error_log("✗ Error actualizando datos para billing_cycle_id: $billingCycleId");
            $paymentMessage = t('Hubo un error procesando tu pago. Contacta al soporte.', 'There was an error processing your payment. Contact support.');
        }
    } else {
        // Ya está pagado, solo confirmar
        $paymentMessage = t('Tu pago ha sido procesado exitosamente. Tu suscripción está activada.', 'Your payment has been processed successfully. Your subscription is activated.');
        if ($userInfo) {
            $shouldSendEmail = true;
            $emailStatus = 'pending';
        }
    }
} else {
    $paymentMessage = t('No se encontró información del pago.', 'Payment information not found.');
}
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Pago Exitoso - DWooSync', 'Payment Successful - DWooSync'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .success-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #28a745, #20c997, #17a2b8);
        }

        .success-icon, .loading-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 36px;
            transition: all 0.3s ease;
        }

        .loading-icon {
            background: linear-gradient(135deg, #007bff, #6610f2);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .success-title {
            font-size: 28px;
            color: #2c5aa0;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .success-message {
            font-size: 16px;
            color: #6c757d;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .user-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .user-greeting {
            font-size: 18px;
            color: #2c5aa0;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-email {
            font-size: 14px;
            color: #6c757d;
        }

        .email-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .email-status.email-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .email-status.email-sending {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .email-status.email-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .email-status.email-failed {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .email-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #0c5460;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 20px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .footer-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 15px;
        }

        /* Debug styles */
        .debug-panel {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            text-align: left;
        }

        .debug-panel h5 {
            margin: 0 0 10px 0;
            color: #6c757d;
            text-align: center;
        }

        .debug-button {
            margin: 5px;
            padding: 8px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }

        .debug-button.test {
            background: #007bff;
            color: white;
        }

        .debug-button.email {
            background: #28a745;
            color: white;
        }

        .debug-output {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-family: monospace;
            font-size: 11px;
            min-height: 60px;
            white-space: pre-wrap;
            overflow-y: auto;
            max-height: 200px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .success-container {
                padding: 30px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <!-- Icono que cambia según el estado del correo -->
        <?php if ($emailStatus === 'sending'): ?>
        <div class="loading-icon" id="mainIcon">
            <i class="fas fa-envelope"></i>
        </div>
        <?php else: ?>
        <div class="success-icon" id="mainIcon">
            <i class="fas fa-check"></i>
        </div>
        <?php endif; ?>
        
        <h1 class="success-title" id="mainTitle">
            <?php echo t('¡Pago Exitoso!', 'Payment Successful!'); ?>
        </h1>
        
        <p class="success-message" id="mainMessage">
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
        
        <!-- Estado del correo -->
        <div id="emailStatus" class="email-status <?php echo 'email-' . $emailStatus; ?>">
            <?php if ($emailStatus === 'sending'): ?>
                <div class="email-spinner"></div>
                <span class="status-text">
                    <?php echo t('Enviando correo de bienvenida...', 'Sending welcome email...'); ?>
                </span>
            <?php elseif ($emailStatus === 'success'): ?>
                <i class="fas fa-envelope-check"></i>
                <span class="status-text">
                    <?php echo t('¡Correo de bienvenida enviado exitosamente!', 'Welcome email sent successfully!'); ?>
                </span>
            <?php elseif ($emailStatus === 'failed'): ?>
                <i class="fas fa-exclamation-triangle"></i>
                <span class="status-text">
                    <?php echo t('Cuenta activada pero el correo no se pudo enviar.', 'Account activated but email failed to send.'); ?>
                </span>
            <?php else: ?>
                <i class="fas fa-clock"></i>
                <span class="status-text">
                    <?php echo t('Preparando envío de correo...', 'Preparing to send email...'); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Debug AJAX Controls -->
        <div class="debug-panel">
            <h5>Debug AJAX</h5>
            <button onclick="testAjax()" class="debug-button test">Test AJAX Simple</button>
            <button onclick="sendActivationEmail()" class="debug-button email">Enviar Correo Manual</button>
            <div id="debugOutput" class="debug-output"></div>
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
        const emailStatus = '<?php echo $emailStatus; ?>';
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
        
        // Función para hacer debug output
        function debugLog(message) {
            console.log(message);
            const debugDiv = document.getElementById('debugOutput');
            if (debugDiv) {
                debugDiv.textContent += new Date().toLocaleTimeString() + ': ' + message + '\n';
                debugDiv.scrollTop = debugDiv.scrollHeight;
            }
        }
        
        // Función de prueba AJAX simple
        function testAjax() {
            debugLog('Iniciando test AJAX simple...');
            
            const formData = new FormData();
            formData.append('test', 'hello world');
            
            fetch('./ajax_test.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                debugLog('Response status: ' + response.status);
                return response.text().then(text => {
                    debugLog('Raw response: ' + text);
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        debugLog('JSON parse error: ' + e.message);
                        throw new Error('Invalid JSON: ' + text);
                    }
                });
            })
            .then(data => {
                debugLog('Success: ' + JSON.stringify(data));
            })
            .catch(error => {
                debugLog('Error: ' + error.message);
            });
        }
        
        // Función para enviar correo por AJAX
        function sendActivationEmail() {
            debugLog('Iniciando envío de correo...');
            debugLog('billing_cycle_id: <?php echo $billingCycleId; ?>');
            debugLog('shouldSendEmail: <?php echo isset($shouldSendEmail) && $shouldSendEmail ? 'true' : 'false'; ?>');
            
            // Mostrar estado de envío
            updateEmailStatus('sending');
            
            // Crear FormData para el request AJAX
            const formData = new FormData();
            formData.append('billing_cycle_id', '<?php echo $billingCycleId; ?>');
            
            debugLog('Enviando request a: ./ajax_send_email.php');
            
            // Enviar request AJAX
            fetch('./ajax_send_email.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                debugLog('Response status: ' + response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text().then(text => {
                    debugLog('Raw response: ' + text);
                    try {
                        return JSON.parse(text);
                    } catch(e) {
                        debugLog('JSON parse error: ' + e.message);
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                debugLog('Respuesta recibida: ' + JSON.stringify(data));
                if (data.success) {
                    debugLog('✓ Correo enviado exitosamente');
                    debugLog('Email: ' + data.email);
                    debugLog('Idioma: ' + (data.language || 'No especificado'));
                    debugLog('Subject: ' + (data.subject || 'No especificado'));
                    updateEmailStatus('success');
                } else {
                    debugLog('✗ Error enviando correo: ' + data.error);
                    updateEmailStatus('failed');
                }
            })
            .catch(error => {
                debugLog('✗ Error en request AJAX: ' + error.message);
                updateEmailStatus('failed');
            });
        }
        
        // Función para actualizar el estado del correo
        function updateEmailStatus(status) {
            const emailStatusDiv = document.getElementById('emailStatus');
            if (!emailStatusDiv) return;
            
            switch(status) {
                case 'sending':
                    emailStatusDiv.className = 'email-status email-sending';
                    emailStatusDiv.innerHTML = '<div class="email-spinner"></div><span class="status-text">' + t.sending + '</span>';
                    break;
                    
                case 'success':
                    emailStatusDiv.className = 'email-status email-success';
                    emailStatusDiv.innerHTML = '<i class="fas fa-envelope-check"></i><span class="status-text">' + t.success + '</span>';
                    
                    // Efecto de éxito
                    emailStatusDiv.style.transform = 'scale(1.05)';
                    setTimeout(function() {
                        emailStatusDiv.style.transform = 'scale(1)';
                    }, 300);
                    break;
                    
                case 'failed':
                    emailStatusDiv.className = 'email-status email-failed';
                    emailStatusDiv.innerHTML = '<i class="fas fa-envelope-open-text"></i><span class="status-text">' + t.failed + '</span>';
                    break;
            }
        }
        
        // Inicialización cuando la página se carga
        document.addEventListener('DOMContentLoaded', function() {
            debugLog('Page loaded');
            debugLog('Idioma página: <?php echo $isEnglish ? 'English' : 'Español'; ?> (<?php echo $currentLang; ?>)');
            debugLog('shouldSendEmail: <?php echo isset($shouldSendEmail) && $shouldSendEmail ? 'true' : 'false'; ?>');
            debugLog('userInfo: <?php echo $userInfo ? 'found' : 'null'; ?>');
            debugLog('billingCycleId: <?php echo $billingCycleId ?? 'null'; ?>');
            debugLog('emailStatus: <?php echo $emailStatus; ?>');
            debugLog('Session selected_language: <?php echo $_SESSION['selected_language'] ?? 'null'; ?>');
            
            // Efecto de entrada suave
            const container = document.querySelector('.success-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(function() {
                container.style.transition = 'all 0.6s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
            
            // Si hay que enviar correo, hacerlo por AJAX después de que la página se muestre
            <?php if (isset($shouldSendEmail) && $shouldSendEmail): ?>
            debugLog('Programando envío de correo en 800ms');
            setTimeout(function() {
                debugLog('Ejecutando sendActivationEmail()');
                sendActivationEmail();
            }, 800); // Esperar a que termine la animación de entrada
            <?php else: ?>
            debugLog('No se enviará correo - shouldSendEmail es false');
            <?php endif; ?>
        });
        
        // Redirección automática después de 15 segundos
        setTimeout(function() {
            const userConfirmed = confirm(t.redirect);
            if (userConfirmed) {
                window.location.href = 'dashboard.php?lang=<?php echo $currentLang; ?>';
            }
        }, 15000);
    </script>
</body>
</html>