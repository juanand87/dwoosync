<?php
/**
 * Página de bienvenida después del registro exitoso
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión de forma segura
startSecureSession();

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Obtener datos del suscriptor
$db = getDatabase();
$stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$subscriber_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener datos de la licencia
$stmt = $db->prepare("SELECT * FROM licenses WHERE subscriber_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$license_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener información del plan
$plans = getSubscriptionPlans();
$current_plan = $plans[array_search($subscriber_data['plan_type'], array_column($plans, 'id'))] ?? $plans[0];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Bienvenido a DiscogsSync! - DiscogsSync</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .welcome-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .welcome-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
        }
        
        .welcome-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .welcome-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .steps-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .step-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #10b981;
        }
        
        .step-number {
            background: #10b981;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .step-title {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        
        .step-content {
            color: #6b7280;
            line-height: 1.6;
        }
        
        .code-block {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 1rem 0;
            overflow-x: auto;
        }
        
        .license-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .license-key {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            font-weight: bold;
            color: #1f2937;
            margin: 1rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .copy-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }
        
        .copy-btn:hover {
            background: #2563eb;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: #10b981;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }
        
        .test-payment-btn {
            background: #f59e0b;
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .test-payment-btn:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        
        .plan-badge {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .success-message {
            background: #d1fae5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
    <div class="welcome-container">
        <!-- Header de Bienvenida -->
        <div class="welcome-header">
            <h1 class="welcome-title">¡Bienvenido a DiscogsSync! 🎉</h1>
            <p class="welcome-subtitle">Tu cuenta ha sido creada exitosamente. Sigue estos pasos para comenzar a usar el plugin.</p>
        </div>

        <!-- Mensaje de Éxito -->
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>¡Cuenta Creada Exitosamente!</strong><br>
                Plan: <span class="plan-badge"><?php echo htmlspecialchars($current_plan['name']); ?></span>
                <?php if ($subscriber_data['plan_type'] !== 'free'): ?>
                    - Estado: <strong>Pendiente de Activación</strong>
                <?php else: ?>
                    - Estado: <strong>Activo</strong>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información de la Licencia -->
        <div class="license-info">
            <h3><i class="fas fa-key"></i> Tu Clave de Licencia</h3>
            <p>Usa esta clave para configurar el plugin en tu sitio WordPress:</p>
            <div class="license-key">
                <span id="licenseKey"><?php echo htmlspecialchars($license_data['license_key']); ?></span>
                <button class="copy-btn" onclick="copyLicenseKey()">
                    <i class="fas fa-copy"></i> Copiar
                </button>
            </div>
        </div>

        <!-- Pasos de Implementación -->
        <div class="steps-container">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3 class="step-title">Descargar el Plugin</h3>
                <div class="step-content">
                    <p>Descarga el plugin DiscogsSync desde tu panel de control:</p>
                    <a href="download-plugin.php" class="btn-primary">
                        <i class="fas fa-download"></i> Descargar Plugin
                    </a>
                </div>
            </div>

            <div class="step-card">
                <div class="step-number">2</div>
                <h3 class="step-title">Instalar en WordPress</h3>
                <div class="step-content">
                    <p>Sube el archivo ZIP a tu WordPress:</p>
                    <ul>
                        <li>Ve a <strong>Plugins → Añadir nuevo</strong></li>
                        <li>Haz clic en <strong>Subir plugin</strong></li>
                        <li>Selecciona el archivo ZIP descargado</li>
                        <li>Activa el plugin</li>
                    </ul>
                </div>
            </div>

            <div class="step-card">
                <div class="step-number">3</div>
                <h3 class="step-title">Configurar la Licencia</h3>
                <div class="step-content">
                    <p>Ve a la configuración del plugin y pega tu clave de licencia:</p>
                    <div class="code-block">
                        Clave de Licencia: <?php echo htmlspecialchars($license_data['license_key']); ?><br>
                        Dominio: <?php echo htmlspecialchars($subscriber_data['domain']); ?>
                    </div>
                </div>
            </div>

            <div class="step-card">
                <div class="step-number">4</div>
                <h3 class="step-title">¡Comenzar a Usar!</h3>
                <div class="step-content">
                    <p>Ya puedes empezar a importar productos desde Discogs:</p>
                    <ul>
                        <li>Buscar artistas y álbumes</li>
                        <li>Importar datos automáticamente</li>
                        <li>Sincronizar con tu tienda WooCommerce</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="action-buttons">
            <a href="dashboard.php" class="btn-primary">
                <i class="fas fa-tachometer-alt"></i> Ir al Dashboard
            </a>
            <a href="download-plugin.php" class="btn-secondary">
                <i class="fas fa-download"></i> Descargar Plugin
            </a>
            <?php if ($subscriber_data['plan_type'] !== 'free'): ?>
            <button class="test-payment-btn" onclick="simulatePayment()">
                <i class="fas fa-credit-card"></i> Simular Pago (Prueba)
            </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function copyLicenseKey() {
            const licenseKey = document.getElementById('licenseKey').textContent;
            navigator.clipboard.writeText(licenseKey).then(function() {
                const btn = document.querySelector('.copy-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Copiado!';
                btn.style.background = '#10b981';
                
                setTimeout(function() {
                    btn.innerHTML = originalText;
                    btn.style.background = '#3b82f6';
                }, 2000);
            });
        }
        
        function simulatePayment() {
            if (confirm('¿Simular pago y activar la cuenta? Esto activará tu suscripción inmediatamente.')) {
                // Simular pago exitoso
                fetch('simulate-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=simulate_payment'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('¡Pago simulado exitosamente! Tu cuenta ha sido activada.');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error al simular el pago: ' + error);
                });
            }
        }
    </script>
</body>
</html>
