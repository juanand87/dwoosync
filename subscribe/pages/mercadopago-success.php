<?php
/**
 * Página de confirmación de pago MercadoPago
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Obtener parámetros de MercadoPago
$preapproval_id = $_GET['preapproval_id'] ?? '';
$status = $_GET['status'] ?? '';
$external_reference = $_GET['external_reference'] ?? '';

if (empty($preapproval_id)) {
    header('Location: dashboard.php?error=no_payment_data');
    exit;
}

// Procesar pago exitoso
$subscriber_id = $_SESSION['subscriber_id'];
$plan_type = 'premium';

try {
    $pdo = getDatabase();
    $pdo->beginTransaction();
    
    // Obtener información del plan
    $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
    $plan_stmt->execute([$plan_type]);
    $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plan_data) {
        throw new Exception('Plan no encontrado');
    }
    
    // Actualizar plan del suscriptor
    $update_subscriber = $pdo->prepare("UPDATE subscribers SET plan_type = ?, status = 'active' WHERE id = ?");
    $update_subscriber->execute([$plan_type, $subscriber_id]);
    
    // Actualizar plan en billing_cycles
    $update_billing = $pdo->prepare("UPDATE billing_cycles SET plan_type = ? WHERE subscriber_id = ? AND status = 'paid' ORDER BY created_at DESC LIMIT 1");
    $update_billing->execute([$plan_type, $subscriber_id]);
    
    // Actualizar licencia
    $update_license = $pdo->prepare("
        UPDATE licenses 
        SET usage_limit = 9999999, status = 'active' 
        WHERE subscriber_id = ?
    ");
    $update_license->execute([$subscriber_id]);
    
    // Crear ciclo de facturación
    $cycle_start = date('Y-m-d');
    $cycle_end = date('Y-m-d', strtotime('+30 days'));
    $due_date = date('Y-m-d', strtotime('+33 days'));
    $invoice_number = 'INV-' . date('Y') . '-' . str_pad($subscriber_id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(time(), 4, '0', STR_PAD_LEFT);
    
    $create_cycle = $pdo->prepare("
        INSERT INTO billing_cycles (
            subscriber_id, 
            plan_type, 
            license_key, 
            cycle_start_date, 
            cycle_end_date, 
            due_date,
            is_active, 
            status, 
            sync_count, 
            api_calls_count, 
            products_synced, 
            amount,
            currency,
            payment_method,
            payment_id,
            invoice_number,
            paid_date,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $license_key = $_SESSION['license_key'] ?? 'N/A';
    
    $create_cycle->execute([
        $subscriber_id,
        $plan_type,
        $license_key,
        $cycle_start,
        $cycle_end,
        $due_date,
        1, // is_active
        'paid',
        0, // sync_count
        0, // api_calls_count
        0, // products_synced
        $plan_data['price'],
        'CLP', // MercadoPago usa pesos chilenos
        'mercadopago',
        $preapproval_id,
        $invoice_number,
        date('Y-m-d'),
        date('Y-m-d H:i:s')
    ]);
    
    $billing_cycle_id = $pdo->lastInsertId();
    
    // Actualizar sesión
    $_SESSION['plan_type'] = $plan_type;
    $_SESSION['billing_cycle_id'] = $billing_cycle_id;
    
    $pdo->commit();
    
    error_log('[MERCADOPAGO] Pago procesado exitosamente - Subscriber: ' . $subscriber_id . ', Preapproval ID: ' . $preapproval_id);
    
    $success = true;
    $message = '¡Pago procesado exitosamente! Tu plan premium ha sido activado.';
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log('[MERCADOPAGO] Error al procesar pago: ' . $e->getMessage());
    $success = false;
    $message = 'Error al procesar el pago: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pago - MercadoPago</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: bounce 1s ease-in-out;
        }
        
        .success .icon {
            color: #10b981;
        }
        
        .error .icon {
            color: #ef4444;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        
        .message {
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .details {
            background: #f9fafb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #374151;
        }
        
        .detail-value {
            color: #6b7280;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            text-decoration: none;
            color: white;
        }
        
        .loading {
            display: none;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
    <div class="container <?php echo $success ? 'success' : 'error'; ?>">
        <?php if ($success): ?>
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>¡Pago Exitoso!</h1>
            <p class="message">Tu suscripción premium ha sido activada correctamente.</p>
            
            <div class="details">
                <div class="detail-row">
                    <span class="detail-label">Plan:</span>
                    <span class="detail-value">Premium</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Método de pago:</span>
                    <span class="detail-value">MercadoPago</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">ID de suscripción:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($preapproval_id); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Fecha de activación:</span>
                    <span class="detail-value"><?php echo date('d/m/Y H:i'); ?></span>
                </div>
            </div>
            
            <a href="dashboard.php?payment_success=true&method=mercadopago" class="btn" id="continueBtn">
                <i class="fas fa-arrow-right" style="margin-right: 8px;"></i>
                Ir al Dashboard
            </a>
            
        <?php else: ?>
            <div class="icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1>Error en el Pago</h1>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
            
            <a href="checkout.php?plan=premium" class="btn">
                <i class="fas fa-redo" style="margin-right: 8px;"></i>
                Intentar Nuevamente
            </a>
        <?php endif; ?>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Procesando pago...</p>
        </div>
    </div>
    
    <script>
        // Mostrar loading al hacer clic en continuar
        document.getElementById('continueBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('.container').style.display = 'none';
            document.getElementById('loading').style.display = 'block';
            
            // Redirigir después de un breve delay
            setTimeout(() => {
                window.location.href = 'dashboard.php?payment_success=true&method=mercadopago';
            }, 1500);
        });
        
        // Auto-redirigir después de 10 segundos si no hay interacción
        setTimeout(() => {
            if (document.getElementById('continueBtn')) {
                document.getElementById('continueBtn').click();
            }
        }, 10000);
    </script>
</body>
</html>
