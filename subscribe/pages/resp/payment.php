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

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php?lang=' . $currentLang);
    exit;
}

// Debug: Mostrar información del usuario y facturas
if (isset($_SESSION['subscriber_id'])) {
$subscriber_id = $_SESSION['subscriber_id'];
    echo "<!-- DEBUG: subscriber_id = $subscriber_id -->";
    
    try {
        $db = getDatabase();
        $stmt = $db->prepare("SELECT id, plan_type, status, amount, created_at FROM billing_cycles WHERE subscriber_id = ? AND status = 'paid' ORDER BY created_at DESC");
        $stmt->execute([$subscriber_id]);
        $paidInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($paidInvoices) {
            echo "<!-- DEBUG: Usuario $subscriber_id tiene " . count($paidInvoices) . " facturas pagadas -->";
            foreach ($paidInvoices as $invoice) {
                echo "<!-- DEBUG: Factura ID: {$invoice['id']}, Plan: {$invoice['plan_type']}, Status: {$invoice['status']}, Amount: {$invoice['amount']} -->";
            }
        } else {
            echo "<!-- DEBUG: Usuario $subscriber_id NO tiene facturas pagadas -->";
        }
    } catch (Exception $e) {
        echo "<!-- DEBUG ERROR: " . $e->getMessage() . " -->";
    }
} else {
    echo "<!-- DEBUG: No hay subscriber_id en sesión -->";
}

// Obtener el plan desde la URL
$planType = $_GET['plan'] ?? 'premium';
$billingCycleId = $_GET['billing_cycle_id'] ?? null;

// Validar que el plan sea válido
$validPlans = ['free', 'premium', 'enterprise'];
if (!in_array($planType, $validPlans)) {
    $planType = 'premium';
}

// Obtener información del plan desde la base de datos
try {
    $db = getDatabase();
    $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
    $stmt->execute([$planType]);
    $planData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$planData) {
        throw new Exception('Plan no encontrado: ' . $planType);
    }
    
    
    $selectedPlan = [
        'id' => $planData['plan_type'],
        'name' => $planData['plan_name'],
        'price' => $planData['price']
    ];
    
} catch (Exception $e) {
    error_log('Error obteniendo plan: ' . $e->getMessage());
    // Fallback a plan por defecto
    $planNames = [
        'free' => $isEnglish ? 'Free' : 'Gratuito',
        'premium' => 'Premium',
        'enterprise' => $isEnglish ? '+Spotify' : 'Plan +Spotify'
    ];
    
    $selectedPlan = [
        'id' => $planType,
        'name' => $planNames[$planType] ?? ucfirst($planType),
        'price' => $planType === 'free' ? 0 : ($planType === 'premium' ? 22 : 45)
    ];
}

// Obtener información del suscriptor
$signupData = $_SESSION['signup_data'] ?? [];
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Procesar Pago', 'Process Payment'); ?> - <?php echo htmlspecialchars($selectedPlan['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Estilos para menú desplegable de usuario */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .user-button {
            background: none;
            border: none;
            color: #6b7280;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .user-button:hover {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
        
        .user-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 180px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        
        .user-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .user-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.2s ease;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .user-menu-item:last-child {
            border-bottom: none;
        }
        
        .user-menu-item:hover {
            background: #f8fafc;
            color: #1f2937;
        }
        
        .user-menu-item.logout:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #ffffff;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }

        .navbar {
            padding: 1rem 0;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo h2 {
            color: #059669;
            font-size: 1.5rem;
        }

        .nav-logo i {
            margin-right: 0.5rem;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: #059669;
        }

        .btn-primary {
            background: #059669;
            color: white !important;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background 0.3s ease;
        }

        .btn-primary:hover {
            background: #047857;
        }

        .btn-logout {
            background: #dc2626;
            color: white !important;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background 0.3s ease;
        }

        .btn-logout:hover {
            background: #b91c1c;
        }
        
        .simulate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .simulate-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .page-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 30px;
        }
        .plan-summary {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .payment-methods {
            margin-bottom: 30px;
        }
        .payment-method {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .payment-method:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.1);
        }
        .payment-method.selected {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            color: #6b7280;
            text-decoration: none;
            margin-bottom: 20px;
            transition: color 0.3s ease;
        }
        .back-button:hover {
            color: #374151;
        }
        .blue-button {
            background-color: #3483FA;
            color: white;
            padding: 10px 24px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            font-size: 16px;
            transition: background-color 0.3s;
            font-family: Arial, sans-serif;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        .blue-button:hover {
            background-color: #2a68c8;
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

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease-out;
        }

        .loading-overlay.show {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .loading-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e5e7eb;
            border-top: 4px solid #10b981;
            border-radius: 50%;
            animation: spinLoader 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spinLoader {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 1.1rem;
            color: #374151;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .loading-subtext {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .free-plan-btn {
            transition: all 0.3s ease;
        }

        .free-plan-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text"><?php echo t('Activando tu cuenta...', 'Activating your account...'); ?></div>
            <div class="loading-subtext"><?php echo t('Esto puede tomar unos segundos', 'This may take a few seconds'); ?></div>
        </div>
    </div>

    <!-- Header con menú de navegación -->
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
                <a href="dashboard.php?lang=<?php echo $currentLang; ?>" class="nav-link">🏠 <?php echo t('Inicio', 'Home'); ?></a>
                <a href="billing.php?lang=<?php echo $currentLang; ?>" class="nav-link">💳 <?php echo t('Facturación', 'Billing'); ?></a>
                <a href="tutorials.php?lang=<?php echo $currentLang; ?>" class="nav-link">🎥 <?php echo t('Tutoriales', 'Tutorials'); ?></a>
                <a href="plugin-config.php?lang=<?php echo $currentLang; ?>" class="nav-link btn-primary">⚙️ <?php echo t('Configurar Plugin', 'Configure Plugin'); ?></a>
                
                <!-- Botón de idioma -->
                <div class="language-dropdown" style="position: relative; margin-left: 10px;">
                    <button class="nav-link" style="background: #1db954; color: white; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                        <?php if ($isEnglish): ?>
                            <span style="font-size: 1.2em;">🇬🇧</span>
                        <?php else: ?>
                            <span style="font-size: 1.2em;">🇪🇸</span>
                        <?php endif; ?>
                        <span><?php echo $isEnglish ? 'EN' : 'ES'; ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.8em;"></i>
                    </button>
                    <div class="language-menu" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1000; min-width: 140px;">
                        <a href="?plan=<?php echo $planType; ?>&lang=es" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; border-bottom: 1px solid #f3f4f6; <?php echo !$isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                            <span style="font-size: 1.1em; margin-right: 8px;">🇪🇸</span> Español
                        </a>
                        <a href="?plan=<?php echo $planType; ?>&lang=en" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; <?php echo $isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                            <span style="font-size: 1.1em; margin-right: 8px;">🇬🇧</span> English
                        </a>
                    </div>
                </div>
                
                <!-- Menú desplegable de usuario -->
                <div class="user-dropdown">
                    <button class="user-button" onclick="toggleUserMenu()">
                        <i class="fas fa-user-circle"></i>
                    </button>
                    <div class="user-menu" id="userMenu">
                        <a href="profile.php?lang=<?php echo $currentLang; ?>" class="user-menu-item">
                            <i class="fas fa-user"></i> <?php echo t('Mi Perfil', 'My Profile'); ?>
                        </a>
                        <a href="logout.php?lang=<?php echo $currentLang; ?>" class="user-menu-item logout">
                            <i class="fas fa-sign-out-alt"></i> <?php echo t('Cerrar Sesión', 'Logout'); ?>
                        </a>
                    </div>
            </div>
        </div>
    </div>
        </nav>
    </header>

    <main style="padding-top: 100px; min-height: 100vh; background: #ffffff;">
    <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-<?php echo $planType === 'free' ? 'check-circle' : 'credit-card'; ?>"></i> <?php echo $planType === 'free' ? t('Cuenta Creada', 'Account Created') : t('Procesar Pago', 'Process Payment'); ?></h1>
                <p><?php echo $planType === 'free' ? t('Tu cuenta gratuita ha sido creada exitosamente', 'Your free account has been created successfully') : t('Selecciona tu método de pago preferido', 'Select your preferred payment method'); ?></p>
            </div>

        <div class="content">
            <a href="checkout.php?lang=<?php echo $currentLang; ?><?php echo isset($_GET['billing_cycle_id']) ? '&billing_cycle_id=' . $_GET['billing_cycle_id'] : ''; ?>" class="back-button">
                <i class="fas fa-arrow-left" style="margin-right: 8px;"></i>
                <?php echo t('Volver al Resumen', 'Back to Summary'); ?>
            </a>

            <!-- Resumen del Plan -->
            <div class="plan-summary">
                <h3 style="margin: 0 0 15px 0; color: #1f2937;">
                    <i class="fas fa-box"></i> <?php echo htmlspecialchars($selectedPlan['name']); ?>
                </h3>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <div>
                        <div style="font-size: 24px; font-weight: bold; color: #10b981;">
                            $<?php echo $selectedPlan['price']; ?>/<?php echo t('mes', 'month'); ?>
                        </div>
                        <div style="color: #6b7280; font-size: 14px;"><?php echo t('Suscripción mensual', 'Monthly subscription'); ?></div>
                    </div>
                </div>
                
            </div>

            <!-- Plan Gratuito - Activación directa -->
            <?php if ($planType === 'free'): ?>
            <div style="background: #f0fdf4; border: 2px solid #10b981; border-radius: 12px; padding: 30px; text-align: center; margin-bottom: 30px;">
                <div style="font-size: 48px; color: #10b981; margin-bottom: 15px;">
                    <i class="fas fa-gift"></i>
                </div>
                <h3 style="margin: 0 0 10px 0; color: #1f2937;">
                    <?php echo t('¡Plan Gratuito Seleccionado!', 'Free Plan Selected!'); ?>
                </h3>
                <p style="color: #6b7280; margin-bottom: 20px;">
                    <?php echo t('Puedes activar tu cuenta inmediatamente sin necesidad de pago', 'You can activate your account immediately without payment'); ?>
                </p>
                <div style="background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                    <div style="font-size: 14px; color: #6b7280; margin-bottom: 10px;">
                        <?php echo t('Tu plan incluye:', 'Your plan includes:'); ?>
                    </div>
                    <ul style="list-style: none; padding: 0; margin: 0; text-align: left;">
                        <li style="padding: 8px 0; color: #374151;">
                            <i class="fas fa-check" style="color: #10b981; margin-right: 10px;"></i>
                            <?php echo t('10 importaciones por mes', '10 imports per month'); ?>
                        </li>
                        <li style="padding: 8px 0; color: #374151;">
                            <i class="fas fa-check" style="color: #10b981; margin-right: 10px;"></i>
                            <?php echo t('1 Dominio/Sitio Web', '1 Domain/Website'); ?>
                        </li>
                        <li style="padding: 8px 0; color: #374151;">
                            <i class="fas fa-check" style="color: #10b981; margin-right: 10px;"></i>
                            <?php echo t('Actualizaciones', 'Updates'); ?>
                        </li>
                        <li style="padding: 8px 0; color: #374151;">
                            <i class="fas fa-check" style="color: #10b981; margin-right: 10px;"></i>
                            <?php echo t('Renovación automática mensual', 'Automatic monthly renewal'); ?>
                        </li>
                    </ul>
                </div>
                <a href="process_free_plan.php?plan=free&billing_cycle_id=<?php echo $_GET['billing_cycle_id'] ?? ''; ?>&lang=<?php echo $currentLang; ?>" 
                   class="free-plan-btn"
                   id="freePlanBtn"
                   onclick="showLoading()"
                   style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 15px 40px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 18px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(16, 185, 129, 0.4)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(16, 185, 129, 0.3)';">
                    <i class="fas fa-rocket" style="margin-right: 10px;"></i>
                    <?php echo t('Activar Cuenta Gratuita', 'Activate Free Account'); ?>
                </a>
                <div style="margin-top: 15px; font-size: 12px; color: #6b7280;">
                    <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                    <?php echo t('No se requiere tarjeta de crédito', 'No credit card required'); ?>
                </div>
            </div>
            
            <!-- Métodos de Pago -->
            <?php elseif ($planType !== 'free'): ?>
            <div class="payment-methods">
                <h3 style="margin: 0 0 20px 0; color: #1f2937;">
                    <i class="fas fa-credit-card"></i> <?php echo t('Selecciona tu método de pago', 'Select your payment method'); ?>
                </h3>
                
                <?php if ($planType === 'premium'): ?>
                    <!-- PayPal para Premium -->
                    <div class="payment-method">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center;">
                                <div style="width: 40px; height: 40px; background: #0070ba; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="fab fa-paypal" style="color: white; font-size: 20px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight: bold; margin-bottom: 5px;">PayPal</div>
                                    <div style="font-size: 14px; color: #6b7280;"><?php echo t('Pago seguro con PayPal', 'Secure payment with PayPal'); ?></div>
                </div>
                            </div>
                            <div style="color: #10b981; font-weight: bold;">$<?php echo $selectedPlan['price']; ?>/<?php echo t('mes', 'month'); ?></div>
                        </div>
                        <div id="paypal-button-container-P-6BB75141LH256054MNDENVZQ" style="margin-top: 15px;"></div>
            </div>


                <?php elseif ($planType === 'enterprise'): ?>
                    <!-- Solo PayPal para Enterprise -->
                    <div class="payment-method" onclick="selectPaymentMethod('paypal')">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div style="display: flex; align-items: center;">
                                <div style="width: 40px; height: 40px; background: #0070ba; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 15px;">
                                    <i class="fab fa-paypal" style="color: white; font-size: 20px;"></i>
                                </div>
                                <div>
                                    <div style="font-weight: bold; margin-bottom: 5px;">PayPal</div>
                                    <div style="font-size: 14px; color: #6b7280;"><?php echo t('Pago seguro con PayPal', 'Secure payment with PayPal'); ?></div>
            </div>
                            </div>
                            <div style="color: #10b981; font-weight: bold;">$<?php echo $selectedPlan['price']; ?>/<?php echo t('mes', 'month'); ?></div>
                        </div>
                        <div id="paypal-button-container-enterprise" style="margin-top: 15px;"></div>
                    </div>
                <?php endif; ?>
        </div>

            <!-- Información adicional -->
            <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 15px; text-align: center;">
                <i class="fas fa-info-circle" style="color: #f59e0b; margin-right: 8px;"></i>
                <strong><?php echo t('Proceso Manual:', 'Manual Process:'); ?></strong> <?php echo t('Después del pago, un administrador revisará y activará tu suscripción manualmente.', 'After payment, an administrator will review and manually activate your subscription.'); ?>
            </div>

            <!-- Botones de Simulación para Testing -->
            <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 2px dashed #e5e7eb;">
                <h4 style="margin: 0 0 15px 0; color: #374151; font-size: 16px;">
                    <i class="fas fa-vial"></i> <?php echo t('Simulador de Pagos (Solo para Testing)', 'Payment Simulator (Testing Only)'); ?>
                </h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 15px;">
                    <button onclick="simulatePayment('success')" class="simulate-btn" style="background: #10b981; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.3s ease;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                        <?php echo t('Simular Éxito', 'Simulate Success'); ?>
                    </button>
                    <button onclick="simulatePayment('timeout')" class="simulate-btn" style="background: #f59e0b; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.3s ease;">
                        <i class="fas fa-clock" style="margin-right: 8px;"></i>
                        <?php echo t('Simular Timeout', 'Simulate Timeout'); ?>
                    </button>
                    <button onclick="simulatePayment('failed')" class="simulate-btn" style="background: #ef4444; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.3s ease;">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                        <?php echo t('Simular Error', 'Simulate Error'); ?>
                    </button>
                    <button onclick="simulatePayment('cancelled')" class="simulate-btn" style="background: #6b7280; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.3s ease;">
                        <i class="fas fa-times-circle" style="margin-right: 8px;"></i>
                        <?php echo t('Simular Cancelación', 'Simulate Cancellation'); ?>
                    </button>
                </div>
                <p style="margin: 0; font-size: 12px; color: #6b7280; text-align: center;">
                    <i class="fas fa-info-circle" style="margin-right: 5px;"></i>
                    <?php echo t('Estos botones simulan diferentes estados de pago para testing', 'These buttons simulate different payment states for testing'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://www.paypal.com/sdk/js?client-id=ARYSVZGuaEdigjhdE3zwIGLhwu9zjbJGXNWdmBSx_JFGR20EkSBva6H1IhFYVzL3Tcej7ndeA5D5Hkky&vault=true&intent=subscription" data-sdk-integration-source="button-factory"></script>
    <script>
        // Función para toggle del menú de usuario
        function toggleUserMenu() {
            const userMenu = document.getElementById('userMenu');
            userMenu.classList.toggle('show');
        }
        
        // Cerrar menú al hacer click fuera
        document.addEventListener('click', function(event) {
            const userDropdown = document.querySelector('.user-dropdown');
            const userMenu = document.getElementById('userMenu');
            
            if (!userDropdown.contains(event.target)) {
                userMenu.classList.remove('show');
            }
        });

        // Funcionalidad del dropdown de idioma
        document.addEventListener('DOMContentLoaded', function() {
            const languageDropdown = document.querySelector('.language-dropdown');
            const languageButton = languageDropdown.querySelector('button');
            const languageMenu = languageDropdown.querySelector('.language-menu');
            
            languageButton.addEventListener('click', function(e) {
                e.stopPropagation();
                languageMenu.style.display = languageMenu.style.display === 'none' ? 'block' : 'none';
            });
            
            // Cerrar dropdown al hacer clic fuera
            document.addEventListener('click', function() {
                languageMenu.style.display = 'none';
            });
            
            // Prevenir cierre al hacer clic dentro del dropdown
            languageMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });

        let selectedMethod = null;
        
        function selectPaymentMethod(method) {
            // Remover selección anterior
            document.querySelectorAll('.payment-method').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Seleccionar nuevo método
            event.currentTarget.classList.add('selected');
            selectedMethod = method;
            
            // Mostrar botones de pago solo si existen
            if (method === 'paypal') {
                const paypalContainer = document.getElementById('paypal-button-container');
                const mercadopagoContainer = document.getElementById('mercadopago-button-container');
                
                if (paypalContainer) {
                    paypalContainer.style.display = 'block';
                }
                if (mercadopagoContainer) {
                    mercadopagoContainer.style.display = 'none';
                }
            } else if (method === 'mercadopago') {
                const paypalContainer = document.getElementById('paypal-button-container');
                const mercadopagoContainer = document.getElementById('mercadopago-button-container');
                
                if (mercadopagoContainer) {
                    mercadopagoContainer.style.display = 'block';
                }
                if (paypalContainer) {
                    paypalContainer.style.display = 'none';
                }
            }
        }
        
        // === CONFIGURACIÓN ===
        const baseURL = 'https://www.dwoosync.com/subscribe/pages/'; // Cambia por tu dominio real
        const planIdPremium = 'P-6BB75141LH256054MNDENVZQ'; // ID del plan Premium

        // === Función para obtener parámetros de la URL ===
        function getQueryParam(param) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(param);
        }

        // Obtener billing_cycle_id o usar valor por defecto
        const billingCycleId = getQueryParam('billing_cycle_id') || 'premium_default';

        // === Renderizar el botón de PayPal ===
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('paypal-button-container-P-6BB75141LH256054MNDENVZQ');
            if (container) {
                paypal.Buttons({
                    style: {
                        shape: 'rect',
                        color: 'gold',
                        layout: 'vertical',
                        label: 'subscribe'
                    },

                    // Crear la suscripción
                    createSubscription: function(data, actions) {
                        return actions.subscription.create({
                            plan_id: planIdPremium
                        });
                    },

                    // Pago aprobado
                    onApprove: function(data, actions) {
                        alert('Thank you for subscribing to the Premium Plan! We will activate your subscription shortly. You will receive an email with your license details.');

                        const redirectUrl = `${baseURL}exito.php?billing_cycle_id=${encodeURIComponent(billingCycleId)}&sub=${encodeURIComponent(data.subscriptionID)}`;

                        setTimeout(() => {
                            window.location.href = redirectUrl;
                        }, 3000);
                    },

                    // Pago cancelado por el usuario
                    onCancel: function(data) {
                        const cancelUrl = `${baseURL}error_pago.php?error=cancelled&billing_cycle_id=${encodeURIComponent(billingCycleId)}`;
                        window.location.href = cancelUrl;
                    },

                    // Error técnico o inesperado
                    onError: function(err) {
                        console.error('PayPal error:', err);

                        let errorType = 'failed';
                        if (err?.message?.includes('timeout')) {
                            errorType = 'timeout';
                        }

                        const errorUrl = `${baseURL}error_pago.php?error=${errorType}&billing_cycle_id=${encodeURIComponent(billingCycleId)}`;
                        window.location.href = errorUrl;
                    }
                }).render('#paypal-button-container-P-6BB75141LH256054MNDENVZQ');
            }
            
            // === CONFIGURACIÓN ===
            const baseURL = 'https://www.dwoosync.com/subscribe/pages/'; // Reemplaza por tu dominio real
            const planIdSpotify = 'P-4BX32647SP6212626NDIASRA'; // ID del plan +Spotify

            // === Función para obtener parámetros de la URL ===
            function getQueryParam(param) {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get(param);
            }

            // Obtener billing_cycle_id o usar valor por defecto
            const billingCycleId = getQueryParam('billing_cycle_id') || 'spotify_default';

            // === Renderizar el botón de PayPal ===
            const enterpriseContainer = document.getElementById('paypal-button-container-enterprise');
            if (enterpriseContainer) {
                paypal.Buttons({
                    style: {
                        shape: 'rect',
                        color: 'gold',
                        layout: 'vertical',
                        label: 'subscribe'
                    },

                    // Crear la suscripción
                    createSubscription: function(data, actions) {
                        return actions.subscription.create({
                            plan_id: planIdSpotify
                        });
                    },

                    // Pago aprobado
                    onApprove: function(data, actions) {
                        alert('Thank you for subscribing to the +Spotify Plan! We will activate your subscription shortly. You will receive an email with your license details.');

                        const redirectUrl = `${baseURL}exito.php?billing_cycle_id=${encodeURIComponent(billingCycleId)}&sub=${encodeURIComponent(data.subscriptionID)}`;

                        setTimeout(() => {
                            window.location.href = redirectUrl;
                        }, 3000);
                    },

                    // Pago cancelado por el usuario
                    onCancel: function(data) {
                        const cancelUrl = `${baseURL}error_pago.php?error=cancelled&billing_cycle_id=${encodeURIComponent(billingCycleId)}`;
                        window.location.href = cancelUrl;
                    },

                    // Error técnico o inesperado
                    onError: function(err) {
                        console.error('PayPal error:', err);

                        let errorType = 'failed';
                        if (err?.message?.includes('timeout')) {
                            errorType = 'timeout';
                        }

                        const errorUrl = `${baseURL}error_pago.php?error=${errorType}&billing_cycle_id=${encodeURIComponent(billingCycleId)}`;
                        window.location.href = errorUrl;
                    }
                }).render('#paypal-button-container-enterprise');
            }
        });
        
        function handleMercadoPagoClick(event) {
            event.preventDefault();
            
            // Mostrar loading
            const button = event.target;
            const originalText = button.innerHTML;
            const currentLang = '<?php echo $currentLang; ?>';
            const isEnglish = currentLang === 'en';
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (isEnglish ? 'Starting payment with MercadoPago...' : 'Iniciando pago con MercadoPago...');
            button.disabled = true;
            
            // Crear ciclo de facturación pendiente y luego abrir MercadoPago
            createPendingBillingCycle('mercadopago', 'mercadopago_' + Date.now());
        }
        
        function createPendingBillingCycle(paymentMethod, paymentReference) {
            const planType = '<?php echo $planType; ?>';
            
            fetch('create-pending-billing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `plan_type=${encodeURIComponent(planType)}&payment_method=${encodeURIComponent(paymentMethod)}&payment_reference=${encodeURIComponent(paymentReference)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Ciclo de facturación procesado:', data);
                    
                    // Mostrar mensaje informativo según el caso
                    if (data.no_action_needed) {
                        console.log('Factura ya existe para este plan:', data.message);
                    } else if (data.replaced_existing) {
                        console.log('Factura actualizada:', data.message);
                    } else {
                        console.log('Nueva factura creada:', data.message);
                    }
                    
                    if (paymentMethod === 'mercadopago') {
                        // Abrir MercadoPago en ventana flotante (sin URLs de callback para evitar error 403)
                        const mercadopagoUrl = `https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=90334be51787402bad7d41110e0904fb&external_reference=${data.billing_cycle_id}`;
                        window.open(mercadopagoUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
                    } else {
                        // Redirigir a confirmación de pago
                        window.location.href = `payment-confirmation.php?billing_cycle_id=${data.billing_cycle_id}&payment_method=${paymentMethod}`;
                    }
                } else {
                    console.error('Error al crear ciclo de facturación:', data.message);
                    const currentLang = '<?php echo $currentLang; ?>';
                    const isEnglish = currentLang === 'en';
                    alert(isEnglish ? 'Error processing payment. Please try again.' : 'Error al procesar el pago. Por favor, intenta nuevamente.');
                }
            })
            .catch(error => {
                console.error('Error en la llamada AJAX:', error);
                const currentLang = '<?php echo $currentLang; ?>';
                const isEnglish = currentLang === 'en';
                alert(isEnglish ? 'Connection error. Please try again.' : 'Error de conexión. Por favor, intenta nuevamente.');
            });
        }
        
        // Función para simular diferentes estados de pago
        function simulatePayment(type) {
            const billingCycleId = '<?php echo $billingCycleId; ?>';
            const currentLang = '<?php echo $currentLang; ?>';
            
            // Mostrar loading en el botón presionado
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i><?php echo t('Simulando...', 'Simulating...'); ?>';
            button.disabled = true;
            
            // Simular delay de procesamiento
            setTimeout(() => {
                switch(type) {
                    case 'success':
                        // Simular pago exitoso - usar billing_cycle real
                        alert('<?php echo t('Simulando pago exitoso...', 'Simulating successful payment...'); ?>');
                        window.location.href = `exito.php?billing_cycle_id=${encodeURIComponent(billingCycleId)}&sub=SUB_${Date.now()}&lang=${currentLang}`;
                        break;
                        
                    case 'timeout':
                        // Simular timeout
                        alert('<?php echo t('Simulando timeout de pago...', 'Simulating payment timeout...'); ?>');
                        window.location.href = `error_pago.php?error=timeout&lang=${currentLang}`;
                        break;
                        
                    case 'failed':
                        // Simular error técnico
                        alert('<?php echo t('Simulando error de pago...', 'Simulating payment error...'); ?>');
                        window.location.href = `error_pago.php?error=failed&lang=${currentLang}`;
                        break;
                        
                    case 'cancelled':
                        // Simular cancelación
                        alert('<?php echo t('Simulando cancelación de pago...', 'Simulating payment cancellation...'); ?>');
                        window.location.href = `error_pago.php?error=cancelled&lang=${currentLang}`;
                        break;
                }
            }, 1500);
        }
        
    </script>
            <?php else: ?>
            <!-- Procesar plan gratuito -->
            <?php
            try {
                $db = getDatabase();
                $subscriberId = $_SESSION['subscriber_id'];
                
                // 1. Verificar si hay ciclo pendiente
                $stmt = $db->prepare("SELECT id, status, plan_type FROM billing_cycles WHERE subscriber_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$subscriberId]);
                $pendingCycle = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($pendingCycle) {
                    // 2. Actualizar ciclo existente
                    $updateStmt = $db->prepare("UPDATE billing_cycles SET plan_type = 'free', amount = 0, status = 'paid', updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$pendingCycle['id']]);
                    
                    // 3. Activar suscripción
                    $subscriberStmt = $db->prepare("UPDATE subscribers SET status = 'active', plan_type = 'free', updated_at = NOW() WHERE id = ?");
                    $subscriberStmt->execute([$subscriberId]);
                    
                    // 4. Obtener fecha de expiración del ciclo y establecer en licenses
                    $cycleStmt = $db->prepare("SELECT expires_at FROM billing_cycles WHERE id = ?");
                    $cycleStmt->execute([$pendingCycle['id']]);
                    $cycleData = $cycleStmt->fetch(PDO::FETCH_ASSOC);
                    $expiresAt = $cycleData['expires_at'];
                    
                    $licenseStmt = $db->prepare("UPDATE licenses SET expires_at = ?, updated_at = NOW() WHERE subscriber_id = ?");
                    $licenseStmt->execute([$expiresAt, $subscriberId]);
                    
                    error_log("Plan gratuito activado - Ciclo actualizado: " . $pendingCycle['id']);
                } else {
                    // 1. Crear nuevo ciclo
                    $insertStmt = $db->prepare("INSERT INTO billing_cycles (subscriber_id, plan_type, amount, status, created_at, updated_at) VALUES (?, 'free', 0, 'paid', NOW(), NOW())");
                    $insertStmt->execute([$subscriberId]);
                    $billingCycleId = $db->lastInsertId();
                    
                    // 2. Activar suscripción
                    $subscriberStmt = $db->prepare("UPDATE subscribers SET status = 'active', plan_type = 'free', updated_at = NOW() WHERE id = ?");
                    $subscriberStmt->execute([$subscriberId]);
                    
                    // 3. Obtener fecha de expiración del ciclo y establecer en licenses
                    $cycleStmt = $db->prepare("SELECT expires_at FROM billing_cycles WHERE id = ?");
                    $cycleStmt->execute([$billingCycleId]);
                    $cycleData = $cycleStmt->fetch(PDO::FETCH_ASSOC);
                    $expiresAt = $cycleData['expires_at'];
                    
                    $licenseStmt = $db->prepare("UPDATE licenses SET expires_at = ?, updated_at = NOW() WHERE subscriber_id = ?");
                    $licenseStmt->execute([$expiresAt, $subscriberId]);
                    
                    error_log("Plan gratuito activado - Nuevo ciclo creado: " . $billingCycleId);
                }
            } catch (Exception $e) {
                error_log("Error activando plan gratuito: " . $e->getMessage());
            }
            ?>
            
            <!-- Mensaje para plan gratuito -->
            <div style="text-align: center; padding: 2rem; background: #f0fdf4; border-radius: 12px; border: 2px solid #10b981;">
                <div style="font-size: 48px; color: #10b981; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 style="color: #1f2937; margin-bottom: 1rem;"><?php echo t('¡Cuenta Creada Exitosamente!', 'Account Created Successfully!'); ?></h3>
                <p style="color: #6b7280; margin-bottom: 1.5rem;">
                    <?php echo t('Tu cuenta gratuita ha sido activada. Ya puedes comenzar a usar todos los servicios incluidos en tu plan.', 'Your free account has been activated. You can now start using all the services included in your plan.'); ?>
                </p>
                <a href="dashboard.php?lang=<?php echo $currentLang; ?>" class="payment-button" style="display: inline-block;">
                    <i class="fas fa-tachometer-alt" style="margin-right: 10px;"></i>
                    <?php echo t('Ir al Dashboard', 'Go to Dashboard'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function showLoading() {
            // Mostrar overlay de carga
            const overlay = document.getElementById('loadingOverlay');
            const btn = document.getElementById('freePlanBtn');
            
            overlay.classList.add('show');
            btn.classList.add('loading');
            
            // Opcional: Agregar un timeout de seguridad para ocultar el loading si algo falla
            setTimeout(function() {
                // Si después de 30 segundos sigue cargando, permitir que el usuario intente de nuevo
                if (overlay.classList.contains('show')) {
                    overlay.classList.remove('show');
                    btn.classList.remove('loading');
                    
                    // Mostrar mensaje de error opcional
                    alert('<?php echo t("El proceso está tardando más de lo esperado. Por favor, intenta de nuevo.", "The process is taking longer than expected. Please try again."); ?>');
                }
            }, 30000);
        }

        // Función para ocultar el loading manualmente (por si acaso)
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            const btn = document.getElementById('freePlanBtn');
            
            overlay.classList.remove('show');
            btn.classList.remove('loading');
        }

        // Si el usuario vuelve atrás en el navegador, asegurarse de que el loading esté oculto
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                hideLoading();
            }
        });
    </script>
</body>
</html>