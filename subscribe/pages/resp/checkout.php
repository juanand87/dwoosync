<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }

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
    
// Obtener el plan desde la URL o usar premium por defecto
$planType = $_GET['plan'] ?? 'premium';
$billingCycleId = $_GET['billing_cycle_id'] ?? null;

// Validar que el plan sea válido
$validPlans = ['free', 'premium', 'enterprise'];
if (!in_array($planType, $validPlans)) {
    $planType = 'premium';
}

// Obtener billing_cycle_id de la sesión si existe
$billingCycleId = $_SESSION['billing_cycle_id'] ?? $billingCycleId;

// Debug visible en la página
echo "<!-- DEBUG CHECKOUT -->";
echo "<!-- subscriber_id: " . ($_SESSION['subscriber_id'] ?? 'NULL') . " -->";
echo "<!-- plan_type: " . $planType . " -->";
echo "<!-- billing_cycle_id: " . ($billingCycleId ?? 'NULL') . " -->";
echo "<!-- /DEBUG CHECKOUT -->";

// Función para obtener características por defecto
function getDefaultFeatures($planType) {
    global $isEnglish;
    
    switch ($planType) {
        case 'free':
            return $isEnglish ? [
                'Up to 10 imports per month',
                'No support',
                'Basic documentation',
                '1 Domain/Website',
                'Updates',
                'Detailed statistics',
                'Spotify Widget'
            ] : [
                'Hasta 10 importaciones por mes',
                'Sin soporte',
                'Documentación básica',
                '1 Dominio/Sitio Web',
                'Actualizaciones',
                'Estadística detallada',
                'Widget Spotify'
            ];
        case 'premium':
            return $isEnglish ? [
                'Unlimited imports',
                'Priority support',
                '1 Domain/Website',
                'Updates',
                'Detailed statistics',
                'Spotify Widget'
            ] : [
                'Importaciones ilimitadas',
                'Soporte prioritario',
                '1 Dominio/Sitio Web',
                'Actualizaciones',
                'Estadística detallada',
                'Widget Spotify'
            ];
        case 'enterprise':
            return $isEnglish ? [
                'Unlimited imports',
                'Priority support',
                'Unlimited domains',
                'Updates',
                'Detailed statistics',
                'Spotify Widget'
            ] : [
                'Importaciones ilimitadas',
                'Soporte prioritario',
                'Dominios ilimitados',
                'Actualizaciones',
                'Estadística detallada',
                'Widget Spotify'
            ];
        default:
            return [];
    }
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
    
    // Obtener todos los planes para el selector
    $allPlansStmt = $db->prepare("SELECT * FROM subscription_plans ORDER BY price ASC");
    $allPlansStmt->execute();
    $allPlans = $allPlansStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir a formato esperado
    $plans = [];
    foreach ($allPlans as $plan) {
        // Usar características por defecto para evitar problemas con JSON
        $features = getDefaultFeatures($plan['plan_type']);
        
        // Traducir nombres de planes
        $translatedNames = [
            'free' => $isEnglish ? 'Free' : 'Gratuito',
            'premium' => 'Premium',
            'enterprise' => $isEnglish ? 'Enterprise' : 'Enterprise'
        ];
        
        $plans[$plan['plan_type']] = [
            'id' => $plan['plan_type'],
            'name' => $translatedNames[$plan['plan_type']] ?? $plan['plan_name'],
            'price' => $plan['price'],
            'features' => $features
        ];
    }
    
    
    $selectedPlan = $plans[$planType];
        
    } catch (Exception $e) {
    error_log('Error obteniendo planes: ' . $e->getMessage());
    // Fallback a planes por defecto
    $plans = [
        'free' => [
            'id' => 'free',
            'name' => $isEnglish ? 'Free' : 'Gratuito',
            'price' => 0,
            'features' => getDefaultFeatures('free')
        ],
        'premium' => [
            'id' => 'premium',
            'name' => 'Premium',
            'price' => 22,
            'features' => getDefaultFeatures('premium')
        ],
        'enterprise' => [
            'id' => 'enterprise',
            'name' => 'Enterprise',
            'price' => 45,
            'features' => getDefaultFeatures('enterprise')
        ]
    ];
    $selectedPlan = $plans[$planType];
}

// Obtener información del suscriptor
$signupData = $_SESSION['signup_data'] ?? [];

// Verificar si es una renovación
$isRenewal = isset($_GET['renewal']) && $_GET['renewal'] === 'true';
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Checkout', 'Checkout'); ?> - <?php echo htmlspecialchars($selectedPlan['name']); ?></title>
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

        /* Estilos para selector de idioma */
        .language-selector {
            position: relative;
            display: inline-block;
            margin-left: 15px;
        }
        
        .lang-button {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .lang-button:hover {
            color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
        
        .lang-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            min-width: 140px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        
        .lang-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .lang-option {
            display: block;
            padding: 10px 14px;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.2s ease;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.9rem;
        }
        
        .lang-option:last-child {
            border-bottom: none;
        }
        
        .lang-option:hover {
            background: #f8fafc;
            color: #1f2937;
        }
        
        .lang-option.active {
            background: #eff6ff;
            color: #3b82f6;
            font-weight: 500;
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

        .spinning-disc {
            animation: spin 3s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
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
        .plan-option {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .plan-option:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.1);
        }
        .plan-option.selected {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        .payment-button {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            border: none;
            cursor: pointer;
        }
        .payment-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            color: white;
            text-decoration: none;
        }
        
        /* Estilos para navegación responsive */
        .nav-toggle {
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 10001;
        }
        
        .hamburger {
            width: 30px;
            height: 3px;
            background: #374151;
            border-radius: 10px;
            transition: all 0.3s linear;
            position: relative;
            transform-origin: 1px;
        }
        
        .nav-toggle.active .hamburger:nth-child(1) {
            transform: rotate(45deg);
            background: #374151;
        }
        
        .nav-toggle.active .hamburger:nth-child(2) {
            opacity: 0;
        }
        
        .nav-toggle.active .hamburger:nth-child(3) {
            transform: rotate(-45deg);
            background: #374151;
        }
        
        @media (max-width: 768px) {
            .nav-toggle {
                display: flex;
            }
            
            .nav-menu {
                position: fixed;
                top: 0;
                right: -100%;
                width: 100%;
                height: 100vh;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(10px);
                flex-direction: column;
                justify-content: center;
                align-items: center;
                gap: 2rem;
                transition: right 0.3s ease;
                z-index: 9999;
                padding-top: 80px;
                overflow-y: auto;
            }
            
            .nav-menu.active {
                right: 0;
            }
            
            .nav-link {
                font-size: 1.2rem;
                padding: 1rem 0;
                text-align: center;
                width: 100%;
                border-bottom: 1px solid #e5e7eb;
            }
            
            .nav-link:last-child {
                border-bottom: none;
            }
            
            .user-dropdown {
                margin-left: 0 !important;
            }
            
            .language-selector {
                margin-left: 0 !important;
                margin-top: 1rem;
            }
            
            .lang-menu {
                position: relative;
                top: auto;
                right: auto;
                width: 100%;
                box-shadow: none;
                background: transparent;
                margin-top: 10px;
            }
            
            .lang-option {
                text-align: center;
                padding: 15px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
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
                
                <!-- Botón hamburguesa para móvil -->
                <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation">
                    <span class="hamburger"></span>
                    <span class="hamburger"></span>
                    <span class="hamburger"></span>
                </button>
                
                <div class="nav-menu" id="nav-menu">
                <a href="dashboard.php?lang=<?php echo $currentLang; ?>" class="nav-link">🏠 <?php echo t('Inicio', 'Home'); ?></a>
                <a href="billing.php?lang=<?php echo $currentLang; ?>" class="nav-link">💳 <?php echo t('Facturación', 'Billing'); ?></a>
                <a href="tutorials.php?lang=<?php echo $currentLang; ?>" class="nav-link">🎥 <?php echo t('Tutoriales', 'Tutorials'); ?></a>
                <a href="plugin-config.php?lang=<?php echo $currentLang; ?>" class="nav-link btn-primary">⚙️ <?php echo t('Configurar Plugin', 'Configure Plugin'); ?></a>
                
                <!-- Selector de idioma -->
                <div class="language-selector">
                    <button class="lang-button" onclick="toggleLanguageMenu()">
                        <i class="fas fa-globe"></i>
                        <span><?php echo $isEnglish ? 'EN' : 'ES'; ?></span>
                    </button>
                    <div class="lang-menu" id="langMenu">
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['lang' => 'es'])); ?>" class="lang-option <?php echo !$isEnglish ? 'active' : ''; ?>">
                            🇪🇸 Español
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['lang' => 'en'])); ?>" class="lang-option <?php echo $isEnglish ? 'active' : ''; ?>">
                            🇺🇸 English
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
                        <a href="logout.php" class="user-menu-item logout">
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
                <h1><i class="fas fa-shopping-cart"></i> <?php echo t('Checkout', 'Checkout'); ?></h1>
                <p>Revisa tu plan y procede al pago</p>
            </div>

        <div class="content">
            <!-- Selector de Planes -->
                <div id="planSelector" style="display: none; background: #f8fafc; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; border: 2px solid #e5e7eb;">
                    <h4 style="margin: 0 0 1rem 0; color: #1f2937;">
                        <i class="fas fa-list"></i> Selecciona tu Plan
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <?php foreach ($plans as $plan): ?>
                            <div class="plan-option" 
                                 style="background: <?php echo $plan['id'] === $planType ? '#dbeafe' : 'white'; ?>; 
                                    border: 2px solid <?php echo $plan['id'] === $planType ? '#3b82f6' : '#e5e7eb'; ?>;"
                                 data-plan-id="<?php echo htmlspecialchars($plan['id']); ?>"
                                 data-plan-name="<?php echo htmlspecialchars($plan['name']); ?>"
                                 data-plan-price="<?php echo $plan['price']; ?>"
                                 data-plan-features="<?php echo htmlspecialchars(json_encode($plan['features'])); ?>"
                                 onclick="updatePlanSelection(this)">
                                <h5 style="margin: 0 0 0.5rem 0; color: #374151;"><?php echo htmlspecialchars($plan['name']); ?></h5>
                                <div style="font-size: 1.2rem; font-weight: bold; color: #10b981; margin-bottom: 0.5rem;">
                                 $<?php echo $plan['price']; ?>/mes
                                </div>
                                <div style="font-size: 0.8rem; color: #6b7280;">
                                    <?php echo implode(', ', array_slice($plan['features'], 0, 2)); ?>
                                </div>
                                <?php if ($plan['id'] === $planType): ?>
                                    <div class="selected-indicator" style="text-align: center; margin-top: 0.5rem; color: #3b82f6; font-weight: bold;">
                                        <i class="fas fa-check-circle"></i> Seleccionado
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Resumen del Plan -->
                <div class="plan-summary">
                    <h3 style="margin: 0 0 1rem 0; color: #1f2937;">
                        <i class="fas fa-box"></i> 
                        <?php if ($isRenewal): ?>
                            Renovar tu Suscripción
                        <?php else: ?>
                            <?php echo t('Resumen de tu Suscripción', 'Your Subscription Summary'); ?>
                        <?php endif; ?>
                    </h3>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div>
                                    <h4 id="plan-title" style="margin: 0; color: #374151;"><?php echo htmlspecialchars($selectedPlan['name']); ?></h4>
                                    <p style="margin: 0.5rem 0 0 0; color: #6b7280;"><?php echo t('Suscripción mensual', 'Monthly subscription'); ?></p>
                                </div>
                                <button type="button" onclick="togglePlanSelector()" 
                                        style="background: #f3f4f6; color: #374151; padding: 8px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: 500; border: 1px solid #d1d5db; transition: all 0.3s ease; cursor: pointer;"
                                        onmouseover="this.style.background='#e5e7eb'; this.style.borderColor='#9ca3af';"
                                        onmouseout="this.style.background='#f3f4f6'; this.style.borderColor='#d1d5db';">
                                    <i class="fas fa-exchange-alt" style="margin-right: 5px;"></i>
                                    <?php echo t('Cambiar Plan', 'Change Plan'); ?>
                                </button>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div id="plan-price" style="font-size: 1.5rem; font-weight: bold; color: #10b981;">
                             $<?php echo $selectedPlan['price']; ?>/mes
                            </div>
                        </div>
                    </div>
                    
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 1rem;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #374151;"><?php echo t('Características incluidas:', 'Included features:'); ?></h5>
                        <ul id="plan-features" style="margin: 0; padding-left: 1rem; color: #6b7280;">
                            <?php foreach ($selectedPlan['features'] as $feature): ?>
                                <li style="margin-bottom: 0.25rem;">
                                    <?php 
                                    // Características que no incluye el plan free
                                    $freeExclusions = $isEnglish ? ['No support', 'Detailed statistics', 'Spotify Widget'] : ['Sin soporte', 'Estadística detallada', 'Widget Spotify'];
                                    // Características que no incluye el plan premium  
                                    $premiumExclusions = $isEnglish ? ['Spotify Widget'] : ['Widget Spotify'];
                                    
                                    $showX = false;
                                    if ($selectedPlan['id'] === 'free' && in_array($feature, $freeExclusions)) {
                                        $showX = true;
                                    } elseif ($selectedPlan['id'] === 'premium' && in_array($feature, $premiumExclusions)) {
                                        $showX = true;
                                    }
                                    ?>
                                    <?php if ($showX): ?>
                                        <i class="fas fa-times" style="color: #dc2626; margin-right: 8px;"></i>
                                    <?php else: ?>
                                        <i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($feature); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Información del Usuario -->
                <div style="background: #f8fafc; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem;">
                    <h4 style="margin: 0 0 1rem 0; color: #1f2937;">
                        <i class="fas fa-user"></i> <?php echo t('Información de la Cuenta', 'Account Information'); ?>
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <strong><?php echo t('Nombre:', 'Name:'); ?></strong><br>
                            <?php echo htmlspecialchars($signupData['first_name'] . ' ' . $signupData['last_name']); ?>
                        </div>
                        <div>
                            <strong><?php echo t('Email:', 'Email:'); ?></strong><br>
                            <?php echo htmlspecialchars($signupData['email']); ?>
                        </div>
                        <div>
                            <strong><?php echo t('Dominio:', 'Domain:'); ?></strong><br>
                            <?php echo htmlspecialchars($signupData['domain']); ?>
                        </div>
                    </div>
                </div>

            <!-- Botón de Pago -->
            <div style="text-align: center; margin-top: 2rem;">
                <form method="POST" action="process_checkout.php" style="display: inline;" id="checkoutForm">
                    <input type="hidden" name="plan" id="selectedPlanInput" value="<?php echo $planType; ?>">
                    <input type="hidden" name="billing_cycle_id" value="<?php echo $billingCycleId ?? ''; ?>">
                    <button type="submit" class="payment-button" style="border: none; cursor: pointer;" id="checkoutButton">
                        <i class="fas fa-<?php echo $planType === 'free' ? 'user-plus' : 'credit-card'; ?>" style="margin-right: 10px;"></i>
                        <span id="buttonText"><?php echo $planType === 'free' ? t('Crear Cuenta', 'Create Account') : t('Proceder al Pago', 'Proceed to Payment'); ?></span>
                    </button>
                </form>
                
                <div style="margin-top: 15px; font-size: 14px; color: #6b7280;">
                    <i class="fas fa-shield-alt" style="margin-right: 5px;"></i>
                    <?php echo t('Pago 100% seguro y protegido', '100% secure and protected payment'); ?>
                                </div>
                            </div>
                            
            </div>
        </div>

    <script>
        // Función para toggle del menú de usuario
        function toggleUserMenu() {
            const userMenu = document.getElementById('userMenu');
            userMenu.classList.toggle('show');
        }
        
        // Función para toggle del menú de idioma
        function toggleLanguageMenu() {
            const langMenu = document.getElementById('langMenu');
            langMenu.classList.toggle('show');
        }
        
        // Cerrar menú al hacer click fuera
        document.addEventListener('click', function(event) {
            const userDropdown = document.querySelector('.user-dropdown');
            const userMenu = document.getElementById('userMenu');
            const languageSelector = document.querySelector('.language-selector');
            const langMenu = document.getElementById('langMenu');
            
            if (!userDropdown.contains(event.target)) {
                userMenu.classList.remove('show');
            }
            
            if (!languageSelector.contains(event.target)) {
                langMenu.classList.remove('show');
            }
        });

        // Función para mostrar/ocultar selector de planes
        function togglePlanSelector() {
            const selector = document.getElementById('planSelector');
            if (selector.style.display === 'none' || selector.style.display === '') {
                selector.style.display = 'block';
            } else {
                selector.style.display = 'none';
            }
        }

        // Función para actualizar la información del plan mostrada
        function updatePlanDisplay(element) {
            const planId = element.dataset.planId;
            const planName = element.dataset.planName;
            const planPrice = element.dataset.planPrice;
            const planFeatures = JSON.parse(element.dataset.planFeatures);
            
            // Actualizar la información mostrada del plan
            document.getElementById('plan-title').textContent = planName;
            document.getElementById('plan-price').textContent = '$' + planPrice + '/mes';
            
             // Actualizar características del plan
            const featuresList = document.getElementById('plan-features');
             if (featuresList) {
                featuresList.innerHTML = '';
                planFeatures.forEach(feature => {
                    const li = document.createElement('li');
                    
                    // Crear icono según el plan y la característica
                    const icon = document.createElement('i');
                    icon.style.marginRight = '8px';
                    
                    if (planId === 'free' && (feature === 'Sin soporte' || feature === 'Estadística detallada' || feature === 'Widget Spotify')) {
                        icon.className = 'fas fa-times';
                        icon.style.color = '#dc2626';
                    } else if (planId === 'premium' && feature === 'Widget Spotify') {
                        icon.className = 'fas fa-times';
                        icon.style.color = '#dc2626';
                    } else {
                        icon.className = 'fas fa-check';
                        icon.style.color = '#10b981';
                    }
                    
                    li.appendChild(icon);
                    li.appendChild(document.createTextNode(feature));
                    featuresList.appendChild(li);
                });
            }
            
            // Actualizar el formulario del botón de pago
            const planInput = document.getElementById('selectedPlanInput');
            if (planInput) {
                planInput.value = planId;
                console.log('Plan actualizado en formulario a:', planId);
            }
            
            // Actualizar el texto del botón
            const buttonText = document.getElementById('buttonText');
            if (buttonText) {
                buttonText.textContent = planId === 'free' ? 'Crear Cuenta' : 'Proceder al Pago';
            }
            
            // Actualizar el icono del botón
            const buttonIcon = document.querySelector('#checkoutButton i');
            if (buttonIcon) {
                buttonIcon.className = 'fas fa-' + (planId === 'free' ? 'user-plus' : 'credit-card');
                buttonIcon.style.marginRight = '10px';
            }
        }

        function updatePlanSelection(element) {
            // Obtener datos de los atributos data
            const planId = element.dataset.planId;
            const planName = element.dataset.planName;
            const planPrice = element.dataset.planPrice;
            const planFeatures = JSON.parse(element.dataset.planFeatures);
            
            // Actualizar la información del plan usando updatePlanDisplay
            updatePlanDisplay(element);
            
            // Actualizar indicadores de selección en todos los planes
            const planOptions = document.querySelectorAll('.plan-option');
            planOptions.forEach(option => {
                // Remover selección anterior
                option.style.background = 'white';
                option.style.borderColor = '#e5e7eb';
                
                // Remover indicador "Seleccionado" anterior
                const existingIndicator = option.querySelector('.selected-indicator');
                if (existingIndicator) {
                    existingIndicator.remove();
                }
                
                // Si es el plan seleccionado, marcarlo
                if (option.dataset.planId === planId) {
                    option.style.background = '#dbeafe';
                    option.style.borderColor = '#3b82f6';
                    
                    // Agregar indicador "Seleccionado"
                    const indicator = document.createElement('div');
                    indicator.className = 'selected-indicator';
                    indicator.style.cssText = 'text-align: center; margin-top: 0.5rem; color: #3b82f6; font-weight: bold;';
                    indicator.innerHTML = '<i class="fas fa-check-circle"></i> Seleccionado';
                    option.appendChild(indicator);
                }
            });
            
            // Actualizar la URL sin recargar la página
            const url = new URL(window.location);
            url.searchParams.set('plan', planId);
            window.history.replaceState({}, '', url.toString());
            
            // Ocultar el selector
            document.getElementById('planSelector').style.display = 'none';
        }

        // Hover effects para opciones de plan
        document.addEventListener('DOMContentLoaded', function() {
            const planOptions = document.querySelectorAll('.plan-option');
            
            planOptions.forEach(option => {
                option.addEventListener('mouseenter', function() {
                    if (!this.style.background.includes('#dbeafe')) {
                        this.style.background = '#f8fafc';
                        this.style.borderColor = '#9ca3af';
                    }
                });
                
                option.addEventListener('mouseleave', function() {
                    if (!this.style.background.includes('#dbeafe')) {
                        this.style.background = 'white';
                        this.style.borderColor = '#e5e7eb';
                    }
                });
            });
        });
        
        // Funcionalidad del menú hamburguesa
        document.addEventListener('DOMContentLoaded', function() {
            const navToggle = document.getElementById('nav-toggle');
            const navMenu = document.getElementById('nav-menu');
            
            if (navToggle && navMenu) {
                navToggle.addEventListener('click', function() {
                    navToggle.classList.toggle('active');
                    navMenu.classList.toggle('active');
                });
                
                // Cerrar menú al hacer clic en un enlace
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.addEventListener('click', () => {
                        navToggle.classList.remove('active');
                        navMenu.classList.remove('active');
                    });
                });
                
                // Cerrar menú al hacer clic fuera
                document.addEventListener('click', function(event) {
                    if (!navToggle.contains(event.target) && !navMenu.contains(event.target)) {
                        navToggle.classList.remove('active');
                        navMenu.classList.remove('active');
                    }
                });
            }
        });
    </script>
        </div>
    </main>
</body>
</html>
