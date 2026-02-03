<?php
/**
 * Página de registro de suscripción - Versión nueva
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

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

// Si ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$plan = $_GET['plan'] ?? 'free';
$plans = getSubscriptionPlans($currentLang);

// Buscar el plan seleccionado
$selectedPlan = $plans[0];
foreach ($plans as $planData) {
    if ($planData['id'] === $plan) {
        $selectedPlan = $planData;
        break;
    }
}

$errors = [];
$success = '';

// Generar token CSRF
$csrfToken = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Actualizar idioma si viene en el POST
    if (isset($_POST['lang'])) {
        $currentLang = $_POST['lang'];
        $isEnglish = ($currentLang === 'en');
        $_SESSION['selected_language'] = $currentLang;
    }
    
    // Verificar token CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = t('Token de seguridad inválido', 'Invalid security token');
    } else {
        // Sanitizar datos
        $data = sanitizeInput($_POST);
        
        // Validaciones
        if (empty($data['first_name'])) {
            $errors[] = t('El nombre es obligatorio', 'First name is required');
        }
        
        if (empty($data['last_name'])) {
            $errors[] = t('El apellido es obligatorio', 'Last name is required');
        }
        
        if (empty($data['email']) || !isValidEmail($data['email'])) {
            $errors[] = t('El email es obligatorio y debe ser válido', 'Email is required and must be valid');
        }
        
        if (empty($data['password']) || strlen($data['password']) < 6) {
            $errors[] = t('La contraseña debe tener al menos 6 caracteres', 'Password must be at least 6 characters');
        }
        
        if ($data['password'] !== $data['confirm_password']) {
            $errors[] = t('Las contraseñas no coinciden', 'Passwords do not match');
        }
        
        if (empty($data['domain'])) {
            $errors[] = t('El dominio es obligatorio', 'Domain is required');
        }
        
        // Verificar si el dominio ya existe
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                $existingDomain = $db->fetch('SELECT id FROM subscribers WHERE domain = ?', [$data['domain']]);
                
                if ($existingDomain) {
                    $error = t('El dominio "', 'The domain "') . htmlspecialchars($data['domain']) . t('" ya se encuentra registrado. ', '" is already registered. ');
                    $error .= t('Puedes ', 'You can ') . '<a href="login.php" style="color: #059669; text-decoration: underline;">' . t('iniciar sesión', 'login') . '</a> ' . t('o ', 'or ') . '<a href="login.php" style="color: #059669; text-decoration: underline;">' . t('recuperar contraseña', 'recover password') . '</a>. ';
                    $error .= t('Para más información puedes contactar a ', 'For more information you can contact ') . '<a href="contact.php" style="color: #059669; text-decoration: underline;">' . t('soporte', 'support') . '</a>.';
                    $errors[] = $error;
                }
            } catch (Exception $e) {
                $errors[] = t('Error al verificar el dominio: ', 'Error verifying domain: ') . $e->getMessage();
            }
        }
        
        // Si no hay errores, crear el usuario temporal y redirigir al checkout
        if (empty($errors)) {
            try {
                $db = Database::getInstance();
                
                // Hash de la contraseña
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                
                // Crear usuario con status 'pending' hasta completar el pago
                $userId = $db->insert('subscribers', [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'password' => $hashedPassword,
                    'domain' => $data['domain'],
                    'company' => $data['company'] ?? '',
                    'city' => $data['city'] ?? '',
                    'country' => $data['country'] ?? '',
                    'phone' => $data['phone'] ?? '',
                    'plan_type' => $selectedPlan['id'],
                    'status' => 'pending', // Pendiente hasta completar pago
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Crear licencia temporal con límite basado en el plan
                $licenseKey = 'DISC' . strtoupper(substr(md5($userId . time()), 0, 8));
                $subscriptionCode = 'SUB' . strtoupper(substr(md5($userId . time() . rand()), 0, 12));
                
                // Obtener límite de uso desde la base de datos
                $plan = $db->query("SELECT requests_per_month FROM subscription_plans WHERE plan_type = '{$selectedPlan['id']}'")->fetch();
                $usageLimit = $plan ? $plan['requests_per_month'] : 10;
                
                $db->insert('licenses', [
                    'subscriber_id' => $userId,
                    'subscription_code' => $subscriptionCode,
                    'license_key' => $licenseKey,
                    'domain' => $data['domain'],
                    'status' => 'pending', // Pendiente hasta completar pago
                    'usage_count' => 0,
                    'usage_limit' => $usageLimit,
                    'created_at' => date('Y-m-d H:i:s'),
                    'expires_at' => $selectedPlan['id'] === 'enterprise' ? null : 
                                   date('Y-m-d H:i:s', strtotime('+1 year'))
                ]);
                
                // NO crear factura en signup - se creará al pagar o crear cuenta gratuita
                $cycleId = null;
                
                // Guardar datos en sesión para el checkout
                $_SESSION['subscriber_id'] = $userId;
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $data['email'];
                $_SESSION['billing_cycle_id'] = $cycleId; // null para planes pagos, ID para free
                $_SESSION['user_name'] = $data['first_name'] . ' ' . $data['last_name'];
                $_SESSION['user_domain'] = $data['domain'];
                $_SESSION['user_plan'] = $selectedPlan['id'];
                $_SESSION['login_time'] = time();
                $_SESSION['license_key'] = $licenseKey;
                $_SESSION['signup_data'] = $data; // Datos completos para el checkout
                
                // Redirigir al checkout manteniendo el idioma
                header('Location: checkout.php?plan=' . $selectedPlan['id'] . '&lang=' . $currentLang);
                exit;
                
            } catch (Exception $e) {
                $errors[] = 'Error al crear la cuenta: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - DiscogsSync</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
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
                
                <!-- Botón hamburguesa para móvil -->
                <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation">
                    <span class="hamburger"></span>
                    <span class="hamburger"></span>
                    <span class="hamburger"></span>
                </button>
                
                <div class="nav-menu" id="nav-menu">
                    <a href="../index.php" class="nav-link"><?php echo t('Inicio', 'Home'); ?></a>
                    <a href="login.php" class="nav-link btn-login"><?php echo t('Iniciar Sesión', 'Login'); ?></a>
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
                            <a href="?plan=<?php echo $plan; ?>&lang=es" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; border-bottom: 1px solid #f3f4f6; <?php echo !$isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                                <span style="font-size: 1.1em; margin-right: 8px;">🇪🇸</span> Español
                            </a>
                            <a href="?plan=<?php echo $plan; ?>&lang=en" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; <?php echo $isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                                <span style="font-size: 1.1em; margin-right: 8px;">🇬🇧</span> English
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main style="padding-top: 100px; min-height: 100vh; background: #f8fafc;">
        <div class="container" style="max-width: 800px;">
            <div style="background: white; border-radius: 16px; padding: 3rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                <!-- Plan Selection -->
                <div style="margin-bottom: 2rem;">
                    <h2 style="text-align: center; margin-bottom: 2rem; color: #1f2937;"><?php echo t('Selecciona tu Plan', 'Select Your Plan'); ?></h2>
                    
                    <div class="plans-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                        <?php foreach ($plans as $planData): ?>
                            <div class="plan-card" 
                                 style="border: 2px solid <?php echo $planData['id'] === $selectedPlan['id'] ? '#059669' : '#e5e7eb'; ?>; 
                                        border-radius: 12px; padding: 1.5rem; text-align: center; cursor: pointer; 
                                        transition: all 0.3s ease; background: <?php echo $planData['id'] === $selectedPlan['id'] ? '#f0fdf4' : 'white'; ?>;"
                                 data-plan="<?php echo $planData['id']; ?>"
                                 onclick="selectPlan('<?php echo $planData['id']; ?>')">
                                
                                <?php if ($planData['featured']): ?>
                                    <div style="background: #059669; color: white; padding: 0.5rem; border-radius: 8px; margin-bottom: 1rem; font-weight: bold;">
                                        <?php echo t('Más Popular', 'Most Popular'); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <h3 style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #1f2937;">
                                    <?php echo htmlspecialchars($planData['name']); ?>
                                </h3>
                                
                                <div style="font-size: 2rem; font-weight: bold; color: #059669; margin-bottom: 1rem;">
                                    $<?php echo number_format($planData['price']); ?><?php echo t('/mes', '/month'); ?>
                                </div>
                                
                                <div style="margin-top: 1rem;">
                                    <div style="text-align: center; margin-bottom: 0.75rem;">
                                        <span style="color: #059669; cursor: pointer; text-decoration: underline; font-size: 1rem; font-weight: 500;"
                                              onclick="showPlanInfo('<?php echo $planData['id']; ?>')"
                                              onmouseover="this.style.color='#047857'; this.style.textDecoration='none';"
                                              onmouseout="this.style.color='#059669'; this.style.textDecoration='underline';">
                                            <?php echo t('+ Info', '+ Info'); ?>
                                        </span>
                                    </div>
                                    
                                    <button type="button" 
                                            style="background: <?php echo $planData['id'] === $selectedPlan['id'] ? '#059669' : '#6b7280'; ?>; 
                                                   color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; 
                                                   cursor: pointer; font-weight: bold; width: 100%;"
                                            onclick="selectPlan('<?php echo $planData['id']; ?>')">
                                        <?php echo $planData['id'] === $selectedPlan['id'] ? t('Seleccionado', 'Selected') : t('Seleccionar', 'Select'); ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                </div>

                <!-- Form -->
                <h2 style="text-align: center; margin-bottom: 2rem; color: #1f2937;"><?php echo t('Crear Cuenta', 'Create Account'); ?></h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error" style="background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <ul style="margin: 0; padding-left: 1.5rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" data-validate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="lang" value="<?php echo $currentLang; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name"><?php echo t('Nombre *', 'First Name *'); ?></label>
                            <input type="text" id="first_name" name="first_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name"><?php echo t('Apellido *', 'Last Name *'); ?></label>
                            <input type="text" id="last_name" name="last_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email"><?php echo t('Email *', 'Email *'); ?></label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password"><?php echo t('Contraseña *', 'Password *'); ?></label>
                            <div style="position: relative;">
                                <input type="password" id="password" name="password" class="form-control" required>
                                <button type="button" class="password-toggle" tabindex="-1" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password"><?php echo t('Confirmar Contraseña *', 'Confirm Password *'); ?></label>
                            <div style="position: relative;">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                <button type="button" class="password-toggle" tabindex="-1" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="domain"><?php echo t('Dominio de tu sitio *', 'Your site domain *'); ?></label>
                        <input type="text" id="domain" name="domain" class="form-control" 
                               placeholder="<?php echo t('ejemplo.com', 'example.com'); ?>" 
                               value="<?php echo htmlspecialchars($_POST['domain'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="company"><?php echo t('Empresa *', 'Company *'); ?></label>
                        <input type="text" id="company" name="company" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city"><?php echo t('Ciudad', 'City'); ?></label>
                            <input type="text" id="city" name="city" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="country"><?php echo t('País', 'Country'); ?></label>
                            <input type="text" id="country" name="country" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['country'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone"><?php echo t('Teléfono', 'Phone'); ?></label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>

                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-user-plus"></i> <?php echo t('Crear Cuenta', 'Create Account'); ?>
                        </button>
                    </div>

                    <div style="text-align: center; margin-top: 1rem;">
                        <p><?php echo t('¿Ya tienes cuenta?', 'Already have an account?'); ?> <a href="login.php" style="color: #2563eb;"><?php echo t('Iniciar Sesión', 'Login'); ?></a></p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Modal de Información del Plan -->
    <div id="planInfoModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 16px; padding: 2rem; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative;">
            <button onclick="closePlanInfo()" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">
                <i class="fas fa-times"></i>
            </button>
            
            <div id="planInfoContent">
                <!-- Contenido se llena dinámicamente -->
            </div>
        </div>
    </div>

    <script src="../assets/js/script.js"></script>
    
    <style>
        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }
        }
        
        @media (max-width: 1024px) and (min-width: 769px) {
            .plans-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 1rem !important;
            }
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
            
            .language-dropdown {
                margin-left: 0 !important;
            }
        }
    </style>
    
    <script>
        // Menú hamburguesa para móviles
        document.addEventListener('DOMContentLoaded', function() {
            const navToggle = document.querySelector('.nav-toggle');
            const navList = document.querySelector('.nav-list');
            
            if (navToggle && navList) {
                navToggle.addEventListener('click', function() {
                    navToggle.classList.toggle('active');
                    navList.classList.toggle('active');
                });
                
                // Cerrar menú al hacer clic en un enlace
                const navLinks = document.querySelectorAll('.nav-list a');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        navToggle.classList.remove('active');
                        navList.classList.remove('active');
                    });
                });
                
                // Cerrar menú al hacer clic fuera de él
                document.addEventListener('click', function(event) {
                    if (!navToggle.contains(event.target) && !navList.contains(event.target)) {
                        navToggle.classList.remove('active');
                        navList.classList.remove('active');
                    }
                });
            }
        });

        // Datos de los planes y configuración de idioma
        const plans = <?php echo json_encode($plans); ?>;
        const isEnglish = <?php echo json_encode($isEnglish); ?>;
        const translations = {
            selected: isEnglish ? 'Selected' : 'Seleccionado',
            select: isEnglish ? 'Select' : 'Seleccionar'
        };
        
        // Función para seleccionar un plan
        function selectPlan(planId) {
            // Actualizar URL sin recargar la página manteniendo el idioma
            const url = new URL(window.location);
            url.searchParams.set('plan', planId);
            // Mantener el parámetro lang si existe
            const currentLang = url.searchParams.get('lang');
            if (currentLang) {
                url.searchParams.set('lang', currentLang);
            }
            window.history.replaceState({}, '', url);
            
            // Actualizar visualmente las tarjetas
            document.querySelectorAll('.plan-card').forEach(card => {
                const isSelected = card.dataset.plan === planId;
                card.style.borderColor = isSelected ? '#059669' : '#e5e7eb';
                card.style.background = isSelected ? '#f0fdf4' : 'white';
                
                const button = card.querySelector('button');
                button.textContent = isSelected ? translations.selected : translations.select;
                button.style.background = isSelected ? '#059669' : '#6b7280';
            });
            
            // Actualizar información del plan seleccionado
            const selectedPlan = plans.find(plan => plan.id === planId);
            if (selectedPlan) {
                document.getElementById('selected-plan-name').textContent = selectedPlan.name;
                document.getElementById('selected-plan-price').textContent = '$' + selectedPlan.price + '/mes';
            }
        }
        
        // Función para mostrar información del plan
        function showPlanInfo(planId) {
            const plan = plans.find(p => p.id === planId);
            if (!plan) return;
            
            // Definir características que deben mostrar X roja
            const negativeFeatures = {
                'free': ['Sin soporte', 'Widget Spotify'],
                'premium': ['Widget Spotify'],
                'enterprise': []
            };
            
            const modal = document.getElementById('planInfoModal');
            const content = document.getElementById('planInfoContent');
            
            content.innerHTML = `
                <h2 style="margin-bottom: 1.5rem; color: #1f2937; text-align: center; font-size: 1.5rem;">
                    ${plan.name}
                </h2>
                
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.75rem; color: #374151; font-size: 1.1rem;">
                        <i class="fas fa-star"></i> <?php echo t('Características incluidas:', 'Included features:'); ?>
                    </h3>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        ${plan.features.map(feature => {
                            const isNegative = negativeFeatures[planId] && negativeFeatures[planId].includes(feature);
                            return `
                                <li style="padding: 0.4rem 0; display: flex; align-items: center;">
                                    <i class="fas ${isNegative ? 'fa-times' : 'fa-check'}" 
                                       style="color: ${isNegative ? '#dc2626' : '#059669'}; margin-right: 0.5rem; font-size: 0.9rem;"></i>
                                    <span style="color: #374151; font-size: 0.95rem;">${feature}</span>
                                </li>
                            `;
                        }).join('')}
                    </ul>
                </div>
                
                <div style="text-align: center;">
                    <button onclick="selectPlan('${planId}'); closePlanInfo();" 
                            style="background: #059669; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; 
                                   cursor: pointer; font-weight: bold; font-size: 1rem; margin-right: 0.75rem;">
                        <i class="fas fa-check"></i> <?php echo t('Seleccionar', 'Select'); ?>
                    </button>
                    <button onclick="closePlanInfo()" 
                            style="background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; padding: 0.75rem 1.5rem; border-radius: 6px; 
                                   cursor: pointer; font-weight: bold; font-size: 1rem;">
                        <i class="fas fa-times"></i> <?php echo t('Cerrar', 'Close'); ?>
                    </button>
                </div>
            `;
            
            modal.style.display = 'flex';
        }
        
        // Función para cerrar el modal
        function closePlanInfo() {
            document.getElementById('planInfoModal').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera de él
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('planInfoModal');
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closePlanInfo();
                }
            });
            
            // Funcionalidad del dropdown de idioma
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
    </script>
</body>
</html>
