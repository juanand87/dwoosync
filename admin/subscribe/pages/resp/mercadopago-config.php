<?php
/**
 * Página de configuración de MercadoPago
 * Muestra las URLs que debes configurar en tu cuenta de MercadoPago
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/api_discogs';

$success_url = $base_url . '/api/mercadopago-confirm.php';
$failure_url = $base_url . '/subscribe/pages/checkout.php?plan=premium&error=payment_failed';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración MercadoPago</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .config-box {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        .url-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 14px;
            word-break: break-all;
            position: relative;
        }
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 12px;
        }
        .copy-btn:hover {
            background: #0056b3;
        }
        .step {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 15px 0;
        }
        .step h4 {
            margin: 0 0 10px 0;
            color: #007bff;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        .test-button {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            display: inline-block;
            margin: 5px;
            font-weight: 500;
        }
        .test-button:hover {
            background: #218838;
            text-decoration: none;
            color: white;
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
        <h1><i class="fas fa-cog"></i> Configuración de MercadoPago</h1>
        
        <div class="warning">
            <strong><i class="fas fa-exclamation-triangle"></i> Importante:</strong>
            Debes configurar estas URLs en tu cuenta de MercadoPago para que los pagos funcionen correctamente.
        </div>

        <div class="config-box">
            <h3><i class="fas fa-link"></i> URLs para configurar en MercadoPago</h3>
            
            <div class="step">
                <h4>1. Pago aprobado (Success URL)</h4>
                <p>Esta URL se ejecutará cuando el pago sea exitoso:</p>
                <div class="url-display">
                    <?php echo htmlspecialchars($success_url); ?>
                    <button class="copy-btn" onclick="copyToClipboard('<?php echo $success_url; ?>')">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                </div>
            </div>

            <div class="step">
                <h4>2. Pago rechazado (Failure URL)</h4>
                <p>Esta URL se ejecutará cuando el pago sea rechazado:</p>
                <div class="url-display">
                    <?php echo htmlspecialchars($failure_url); ?>
                    <button class="copy-btn" onclick="copyToClipboard('<?php echo $failure_url; ?>')">
                        <i class="fas fa-copy"></i> Copiar
                    </button>
                </div>
            </div>
        </div>

        <div class="step">
            <h4>3. Cómo configurar en MercadoPago</h4>
            <ol>
                <li>Inicia sesión en tu <a href="https://www.mercadopago.cl/developers" target="_blank">cuenta de desarrollador de MercadoPago</a></li>
                <li>Ve a <strong>Configuración</strong> → <strong>Webhooks</strong></li>
                <li>Busca tu plan de suscripción (ID: 90334be51787402bad7d41110e0904fb)</li>
                <li>Configura las URLs como se muestran arriba</li>
                <li>Guarda los cambios</li>
            </ol>
        </div>

        <div class="success">
            <h4><i class="fas fa-check-circle"></i> ¿Qué hace cada URL?</h4>
            <ul>
                <li><strong>Success URL:</strong> Procesa el pago, crea la factura y activa el plan premium</li>
                <li><strong>Failure URL:</strong> Regresa al checkout con mensaje de error</li>
            </ul>
        </div>

        <div class="step">
            <h4>4. Probar la configuración</h4>
            <p>Una vez configurado, puedes probar el flujo completo:</p>
            <a href="checkout.php?plan=premium" class="test-button">
                <i class="fas fa-credit-card"></i> Probar Pago
            </a>
            <a href="test-mercadopago.php" class="test-button">
                <i class="fas fa-bug"></i> Página de Pruebas
            </a>
        </div>

        <div class="warning">
            <h4><i class="fas fa-info-circle"></i> Información técnica</h4>
            <ul>
                <li><strong>Método:</strong> GET</li>
                <li><strong>Parámetros:</strong> preapproval_id, external_reference, status</li>
                <li><strong>Respuesta:</strong> Redirección automática al dashboard o checkout</li>
                <li><strong>Logs:</strong> Revisa los logs del servidor para debugging</li>
            </ul>
        </div>

        <div style="margin-top: 30px; text-align: center;">
            <a href="dashboard.php" class="test-button" style="background: #6c757d;">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('URL copiada al portapapeles');
            }, function(err) {
                console.error('Error al copiar: ', err);
                // Fallback para navegadores antiguos
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('URL copiada al portapapeles');
            });
        }
    </script>
</body>
</html>


