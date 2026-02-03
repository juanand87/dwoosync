<?php
/**
 * Página de prueba para MercadoPago
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$subscriber_id = $_SESSION['subscriber_id'] ?? 'test';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba MercadoPago</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .test-button {
            background: #3483FA;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            margin: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .test-button:hover {
            background: #2a68c8;
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }
        .test-button.success {
            background: #28a745;
        }
        .test-button.warning {
            background: #ffc107;
            color: #212529;
        }
        .url-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
    
        .spinning-disc {
            animation: spin 3s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .nav-logo h2 {
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #1db954, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(29, 185, 84, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-credit-card"></i> Prueba de MercadoPago</h1>
        
        <div class="info-box">
            <strong>Subscriber ID:</strong> <?php echo htmlspecialchars($subscriber_id); ?><br>
            <strong>Plan:</strong> Premium<br>
            <strong>Precio:</strong> $1 USD
        </div>

        <h3>Opciones de prueba:</h3>

        <div>
            <h4>1. Botón básico (sin parámetros)</h4>
            <a href="https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=90334be51787402bad7d41110e0904fb" 
               class="test-button" 
               onclick="openMercadoPago(this.href); return false;">
                <i class="fas fa-credit-card"></i> MercadoPago Básico
            </a>
            <div class="url-display">
                https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=90334be51787402bad7d41110e0904fb
            </div>
        </div>

        <div>
            <h4>2. Con external_reference</h4>
            <a href="https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=90334be51787402bad7d41110e0904fb&external_reference=<?php echo $subscriber_id; ?>" 
               class="test-button success" 
               onclick="openMercadoPago(this.href); return false;">
                <i class="fas fa-tag"></i> Con Referencia
            </a>
            <div class="url-display">
                https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=90334be51787402bad7d41110e0904fb&external_reference=<?php echo $subscriber_id; ?>
            </div>
        </div>

        <div>
            <h4>3. Con URLs de callback (localhost)</h4>
            <?php 
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $success_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/api_discogs/subscribe/pages/mercadopago-success.php';
            $failure_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/api_discogs/subscribe/pages/checkout.php?plan=premium&error=payment_failed';
            $mp_url_with_callbacks = "https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=90334be51787402bad7d41110e0904fb&success_url=" . urlencode($success_url) . "&failure_url=" . urlencode($failure_url) . "&external_reference=" . $subscriber_id;
            ?>
            <a href="<?php echo $mp_url_with_callbacks; ?>" 
               class="test-button warning" 
               onclick="openMercadoPago(this.href); return false;">
                <i class="fas fa-link"></i> Con Callbacks
            </a>
            <div class="url-display">
                <?php echo htmlspecialchars($mp_url_with_callbacks); ?>
            </div>
        </div>

        <div>
            <h4>4. Con URLs de callback (ngrok/túnel)</h4>
            <?php 
            // Usar ngrok o túnel público para testing
            $ngrok_url = 'https://your-ngrok-url.ngrok.io'; // Reemplazar con tu URL de ngrok
            $success_url_ngrok = $ngrok_url . '/api_discogs/subscribe/pages/mercadopago-success.php';
            $failure_url_ngrok = $ngrok_url . '/api_discogs/subscribe/pages/checkout.php?plan=premium&error=payment_failed';
            $mp_url_ngrok = "https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=90334be51787402bad7d41110e0904fb&success_url=" . urlencode($success_url_ngrok) . "&failure_url=" . urlencode($failure_url_ngrok) . "&external_reference=" . $subscriber_id;
            ?>
            <a href="<?php echo $mp_url_ngrok; ?>" 
               class="test-button" 
               onclick="openMercadoPago(this.href); return false;"
               style="background: #6f42c1;">
                <i class="fas fa-globe"></i> Con Ngrok
            </a>
            <div class="url-display">
                <?php echo htmlspecialchars($mp_url_ngrok); ?>
            </div>
            <small style="color: #6c757d;">Nota: Necesitas configurar ngrok para que funcione</small>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
            <h4><i class="fas fa-info-circle"></i> Información de debugging:</h4>
            <ul>
                <li><strong>Error 403:</strong> MercadoPago rechaza la solicitud</li>
                <li><strong>Posibles causas:</strong></li>
                <ul>
                    <li>URLs de callback no son accesibles desde internet</li>
                    <li>Plan de suscripción no está activo en MercadoPago</li>
                    <li>Parámetros inválidos en la URL</li>
                    <li>Restricciones de CORS</li>
                </ul>
                <li><strong>Solución:</strong> Usar ngrok o túnel público para las URLs de callback</li>
            </ul>
        </div>

        <div style="margin-top: 20px;">
            <a href="checkout.php?plan=premium" class="test-button" style="background: #6c757d;">
                <i class="fas fa-arrow-left"></i> Volver al Checkout
            </a>
        </div>
    </div>
    
    <script>
        // Función para abrir MercadoPago en ventana flotante
        function openMercadoPago(url) {
            console.log('Abriendo MercadoPago:', url);
            
            // Dimensiones optimizadas para MercadoPago
            const width = 900;
            const height = 700;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            
            // Abrir ventana flotante
            const mpWindow = window.open(
                url,
                'mercadopago',
                `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes,status=yes,location=yes,toolbar=no,menubar=no`
            );
            
            // Verificar si se abrió correctamente
            if (!mpWindow || mpWindow.closed || typeof mpWindow.closed === 'undefined') {
                alert('No se pudo abrir la ventana de pago. Por favor, permite ventanas emergentes para este sitio.');
                // Fallback: abrir en nueva pestaña
                window.open(url, '_blank');
            } else {
                // Enfocar la ventana
                mpWindow.focus();
                
                // Monitorear si la ventana se cierra
                const checkClosed = setInterval(() => {
                    if (mpWindow.closed) {
                        clearInterval(checkClosed);
                        console.log('Ventana de MercadoPago cerrada');
                        
                        // Verificar si el pago fue exitoso
                        setTimeout(() => {
                            // Recargar la página para verificar cambios
                            location.reload();
                        }, 1000);
                    }
                }, 1000);
            }
        }
    </script>
</body>
</html>
