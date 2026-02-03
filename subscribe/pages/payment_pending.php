<?php
/**
 * Página de pago pendiente
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión de forma segura
startSecureSession();

// Verificar que el usuario está logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$plan_type = $_SESSION['plan_type'] ?? 'free';

// Obtener información del usuario
$db = getDatabase();
$stmt = $db->prepare("
    SELECT s.*, l.license_key 
    FROM subscribers s 
    LEFT JOIN licenses l ON s.id = l.subscriber_id 
    WHERE s.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Pendiente - DiscogsSync</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .pending-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
            text-align: center;
        }
        .pending-card {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .pending-icon {
            font-size: 4rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }
        .warning-info {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        .license-info {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }
        .license-key {
            background: #1f2937;
            color: #f59e0b;
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 1.1rem;
            margin: 1rem 0;
            word-break: break-all;
        }
        .btn-copy {
            background: #f59e0b;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        .btn-copy:hover {
            background: #d97706;
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
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <?php 
                    $logoUrl = isLoggedIn() ? 'dashboard.php?lang=' . $currentLang : '../index.php';
                    ?>
                    <a href="<?php echo $logoUrl; ?>" style="text-decoration: none; color: inherit;">
                        <h2><i class="fas fa-compact-disc spinning-disc"></i> DWooSync</h2>
                    </a>
                </div>
                <div class="nav-menu">
                    <a href="dashboard.php" class="nav-link btn-login">Ir al Dashboard</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main style="padding-top: 100px; min-height: 100vh; background: #f8fafc;">
        <div class="pending-container">
            <div class="pending-card">
                <div class="pending-icon">
                    <i class="fas fa-clock"></i>
                </div>
                
                <h1 style="color: #1f2937; margin-bottom: 1rem;">
                    Pago Pendiente
                </h1>
                
                <p style="color: #6b7280; font-size: 1.1rem; margin-bottom: 2rem;">
                    Tu suscripción ha sido creada pero está inactiva hasta que se confirme el pago.
                </p>
                
                <div class="warning-info">
                    <h3 style="margin: 0 0 1rem 0; color: #92400e;">
                        <i class="fas fa-exclamation-triangle"></i> Estado de la Suscripción
                    </h3>
                    <ul style="margin: 0; padding-left: 1.5rem; color: #92400e;">
                        <li style="margin-bottom: 0.5rem;">
                            <strong>Cuenta creada:</strong> ✅ Completada
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <strong>Pago:</strong> ⏳ Pendiente de confirmación
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <strong>Suscripción:</strong> ❌ Inactiva (hasta confirmar pago)
                        </li>
                    </ul>
                </div>
                
                <div class="license-info">
                    <h3 style="margin: 0 0 1rem 0; color: #1f2937;">
                        <i class="fas fa-key"></i> Información de tu Licencia
                    </h3>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong>Plan:</strong> <?php echo ucfirst($plan_type); ?><br>
                        <strong>Estado:</strong> <span style="color: #f59e0b; font-weight: bold;">Inactiva</span><br>
                        <strong>Dominio:</strong> <?php echo htmlspecialchars($user['domain']); ?>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">
                            Clave de Licencia:
                        </label>
                        <div class="license-key" id="license-key">
                            <?php echo htmlspecialchars($user['license_key']); ?>
                        </div>
                        <button class="btn-copy" onclick="copyLicenseKey()">
                            <i class="fas fa-copy"></i> Copiar
                        </button>
                    </div>
                </div>
                
                <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 12px; padding: 1.5rem; margin: 2rem 0; text-align: left;">
                    <h4 style="margin: 0 0 1rem 0; color: #0c4a6e;">
                        <i class="fas fa-info-circle"></i> ¿Qué hacer ahora?
                    </h4>
                    <ol style="margin: 0; padding-left: 1.5rem; color: #0c4a6e;">
                        <li style="margin-bottom: 0.5rem;">
                            <strong>Completa el pago</strong> a través de PayPal
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <strong>Espera la confirmación</strong> (puede tomar unos minutos)
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <strong>Recibirás un email</strong> cuando se active tu suscripción
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <strong>Entonces podrás usar</strong> el plugin con tu licencia
                        </li>
                    </ol>
                </div>
                
                <div style="margin-top: 2rem;">
                    <a href="dashboard.php" class="btn btn-primary btn-large">
                        <i class="fas fa-tachometer-alt"></i> Ir al Dashboard
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
    <script>
        function copyLicenseKey() {
            const licenseKey = document.getElementById('license-key').textContent;
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(licenseKey).then(function() {
                    showCopySuccess();
                }).catch(function() {
                    fallbackCopyTextToClipboard(licenseKey);
                });
            } else {
                fallbackCopyTextToClipboard(licenseKey);
            }
        }
        
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            textArea.style.opacity = "0";
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess();
                } else {
                    showCopyError();
                }
            } catch (err) {
                showCopyError();
            }
            
            document.body.removeChild(textArea);
        }
        
        function showCopySuccess() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
            button.style.background = '#10b981';
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.background = '#f59e0b';
            }, 2000);
        }
        
        function showCopyError() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-times"></i> Error';
            button.style.background = '#ef4444';
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.background = '#f59e0b';
            }, 2000);
        }
    </script>
</body>
</html>

