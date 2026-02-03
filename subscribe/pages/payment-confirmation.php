<?php
/**
 * Página de confirmación de pago
 * Solo se muestra si hay facturas pendientes
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$subscriber_id = $_SESSION['subscriber_id'];

// Verificar si hay facturas pendientes
$has_pending_billing = false;
try {
    $pdo = getDatabase();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM billing_cycles 
        WHERE subscriber_id = ? AND status = 'pending'
    ");
    $stmt->execute([$subscriber_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_pending_billing = $result['count'] > 0;
} catch (Exception $e) {
    error_log('Error verificando facturas pendientes: ' . $e->getMessage());
}

// Si no hay facturas pendientes, redirigir al dashboard
if (!$has_pending_billing) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validación de Pago - DwooSync</title>
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
            padding: 60px 40px;
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
            margin-bottom: 30px;
            animation: pulse 2s ease-in-out infinite;
            color: #f59e0b;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 20px;
            font-size: 2.2rem;
            font-weight: 700;
        }
        
        .message {
            color: #4b5563;
            margin-bottom: 40px;
            font-size: 1.2rem;
            line-height: 1.6;
        }
        
        .highlight {
            color: #1f2937;
            font-weight: 600;
        }
        
        .contact-info {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
        }
        
        .contact-info h3 {
            color: #374151;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .contact-info p {
            color: #6b7280;
            margin-bottom: 8px;
        }
        
        .contact-info a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }
        
        .contact-info a:hover {
            text-decoration: underline;
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
        
        .note {
            margin-top: 30px;
            font-size: 0.9rem;
            color: #9ca3af;
            font-style: italic;
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
        <div class="icon">
            <i class="fas fa-clock"></i>
        </div>
        
        <h1>Validando tu Pago</h1>
        <p class="message">
            En los próximos minutos se va a <span class="highlight">validar el pago</span> de la suscripción y se va a <span class="highlight">activar la cuenta</span> en cuanto eso suceda.
        </p>
        
        <div class="contact-info">
            <h3><i class="fas fa-headset"></i> ¿Necesitas Ayuda?</h3>
            <p>Para cualquier consulta escribir a:</p>
            <p><i class="fas fa-envelope"></i> <a href="mailto:support@dwoosync.com">support@dwoosync.com</a></p>
        </div>
        
        <a href="dashboard.php" class="btn">
            <i class="fas fa-arrow-right"></i> Ir al Dashboard
        </a>
        
        <div class="note">
            <p>Esta página se puede cerrar de forma segura. Tu pago está siendo procesado.</p>
        </div>
    </div>
</body>
</html>
