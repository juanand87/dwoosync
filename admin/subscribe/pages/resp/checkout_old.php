<?php
/**
 * Página de checkout para suscripciones mensuales
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión de forma segura
startSecureSession();

// Verificar si es renovación o nuevo registro
$isRenewal = isset($_GET['renewal']) && $_GET['renewal'] === 'true';
$planType = $_GET['plan'] ?? 'free';
$billingCycleId = $_GET['billing_cycle_id'] ?? null;

// Si se pasa billing_cycle_id, obtener el plan de esa factura
if ($billingCycleId) {
    try {
        $db = getDatabase();
        $stmt = $db->prepare("SELECT plan_type FROM billing_cycles WHERE id = ? AND subscriber_id = ?");
        $stmt->execute([$billingCycleId, $_SESSION['subscriber_id'] ?? 0]);
        $billingCycle = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($billingCycle) {
            $planType = $billingCycle['plan_type'];
        }
    } catch (Exception $e) {
        error_log('Error obteniendo plan del ciclo de facturación: ' . $e->getMessage());
    }
}

// Variable para JavaScript
$subscriberId = $_SESSION['subscriber_id'] ?? '';

if ($isRenewal) {
    // Para renovaciones, verificar que el usuario esté logueado
    if (!isset($_SESSION['subscriber_id'])) {
        header('Location: login.php');
        exit;
    }
    
    // Obtener datos del suscriptor existente
    $db = getDatabase();
    $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
    $stmt->execute([$_SESSION['subscriber_id']]);
    $subscriberData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscriberData) {
        header('Location: login.php');
        exit;
    }
    
    // Simular datos de signup para compatibilidad
    $signupData = [
        'first_name' => $subscriberData['first_name'],
        'last_name' => $subscriberData['last_name'],
        'email' => $subscriberData['email'],
        'password' => '', // No necesitamos la contraseña para renovación
        'domain' => $subscriberData['domain'],
        'company' => $subscriberData['company'],
        'city' => $subscriberData['city'],
        'country' => $subscriberData['country'],
        'phone' => $subscriberData['phone'],
        'plan_type' => $planType
    ];
} else {
    // Para nuevos registros, verificar que el usuario esté autenticado
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['subscriber_id'])) {
        header('Location: signup.php');
        exit;
    }
    
    // Obtener datos del suscriptor desde la base de datos
    $db = getDatabase();
    $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
    $stmt->execute([$_SESSION['subscriber_id']]);
    $subscriberData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscriberData) {
        header('Location: signup.php');
        exit;
    }
    
    // Simular datos de signup para compatibilidad
    $signupData = [
        'first_name' => $subscriberData['first_name'],
        'last_name' => $subscriberData['last_name'],
        'email' => $subscriberData['email'],
        'password' => '', // No necesitamos la contraseña
        'domain' => $subscriberData['domain'],
        'company' => $subscriberData['company'],
        'city' => $subscriberData['city'],
        'country' => $subscriberData['country'],
        'phone' => $subscriberData['phone'],
        'plan_type' => $subscriberData['plan_type']
    ];
}

// Obtener información del plan
$plans = getSubscriptionPlans();
$selectedPlan = $plans[array_search($planType, array_column($plans, 'id'))] ?? $plans[0];

$errors = [];
$success = '';

// Manejar actualización de plan via AJAX (solo para mostrar, no actualizar BD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_plan') {
    // Solo retornar éxito para actualizar la interfaz, sin cambiar la BD
    echo json_encode(['success' => true, 'message' => 'Plan seleccionado para visualización']);
    exit;
}

// Actualizar plan si se cambió en la URL (para recargas de página)
if (isset($_SESSION['subscriber_id']) && !$isRenewal) {
    $signupData = $_SESSION['signup_data'];
    if ($signupData['plan_type'] !== $planType) {
        try {
            $db = getDatabase();
            
            // Actualizar plan del suscriptor
            $stmt = $db->prepare("UPDATE subscribers SET plan_type = ? WHERE id = ?");
            $stmt->execute([$planType, $_SESSION['subscriber_id']]);
            
            // Actualizar límite de uso en la licencia basado en el plan
            $plan = $db->query("SELECT requests_per_month FROM subscription_plans WHERE plan_type = '$planType'")->fetch();
            $usageLimit = $plan ? $plan['requests_per_month'] : 10;
            
            $stmt = $db->prepare("
                UPDATE licenses 
                SET usage_limit = ?, status = ? 
                WHERE subscriber_id = ?
            ");
            $stmt->execute([$usageLimit, $planType === 'free' ? 'active' : 'inactive', $_SESSION['subscriber_id']]);
            
            // Actualizar datos de sesión
            $signupData['plan_type'] = $planType;
            $_SESSION['signup_data'] = $signupData;
            
        } catch (Exception $e) {
            $errors[] = 'Error al actualizar el plan: ' . $e->getMessage();
        }
    }
}

// Procesar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        $db = getDatabase();
        $db->beginTransaction();
        
        if ($isRenewal) {
            // Para renovaciones, usar el suscriptor existente
            $subscriberId = $_SESSION['subscriber_id'];
            
            // Actualizar plan del suscriptor
            $stmt = $db->prepare("UPDATE subscribers SET plan_type = ? WHERE id = ?");
            $stmt->execute([$planType, $subscriberId]);
        } else {
            // Para nuevos registros, usar el suscriptor ya creado en signup
            $subscriberId = $_SESSION['subscriber_id'];
            
            // Actualizar plan del suscriptor si es diferente
            if ($signupData['plan_type'] !== $planType) {
                $stmt = $db->prepare("UPDATE subscribers SET plan_type = ? WHERE id = ?");
                $stmt->execute([$planType, $subscriberId]);
            }
        }
        
        // Usar la licencia ya creada en signup
        $licenseKey = $_SESSION['license_key'];
        
        // Buscar la licencia existente
        $stmt = $db->prepare("SELECT * FROM licenses WHERE subscriber_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$subscriberId]);
        $existingLicense = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingLicense) {
            $licenseId = $existingLicense['id'];
            
            // Actualizar límite de uso según el plan desde la base de datos
            $plan = $db->query("SELECT requests_per_month FROM subscription_plans WHERE plan_type = '$planType'")->fetch();
            $usageLimit = $plan ? $plan['requests_per_month'] : 10;
            
            $stmt = $db->prepare("
                UPDATE licenses 
                SET usage_limit = ?, status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$usageLimit, $planType === 'free' ? 'active' : 'inactive', $licenseId]);
        }
        
        // Simular procesamiento de pago
        $paymentStatus = 'pending';
        $paymentId = 'PAY_' . time() . '_' . $subscriberId;
        
        // Para plan free, activar inmediatamente
        if ($planType === 'free') {
            $paymentStatus = 'completed';
            
            // Activar suscriptor y licencia
            $stmt = $db->prepare("UPDATE subscribers SET status = 'active' WHERE id = ?");
            $stmt->execute([$subscriberId]);
            
            $stmt = $db->prepare("UPDATE licenses SET status = 'active' WHERE id = ?");
            $stmt->execute([$licenseId]);
            
            // Desactivar ciclos anteriores
            $stmt = $db->prepare("UPDATE billing_cycles SET is_active = 0 WHERE subscriber_id = ?");
            $stmt->execute([$subscriberId]);
            
            // Crear nuevo ciclo de facturación
            $stmt = $db->prepare("
                INSERT INTO billing_cycles (subscriber_id, plan_type, license_key, cycle_start_date, cycle_end_date, due_date, is_active, status, sync_count, api_calls_count, products_synced, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $cycleStart = date('Y-m-d');
            $cycleEnd = date('Y-m-d', strtotime('+30 days'));
            $dueDate = date('Y-m-d', strtotime($cycleEnd . ' +3 days')); // 3 días después del ciclo
            
            $stmt->execute([
                $subscriberId,
                $planType, // plan_type
                $licenseKey,
                $cycleStart,
                $cycleEnd,
                $dueDate, // due_date = cycle_end_date + 3 días
                1, // is_active = 1 (true)
                'paid', // status = 'paid' para planes free
                0, // sync_count
                0, // api_calls_count
                0, // products_synced
                date('Y-m-d H:i:s')
            ]);
        }
        
        // Registrar pago
        $stmt = $db->prepare("
            INSERT INTO payments (
                payment_id,
                subscriber_id,
                amount,
                currency,
                payment_method,
                status,
                payment_date
            ) VALUES (?, ?, ?, 'USD', 'paypal', ?, NOW())
        ");
        $stmt->execute([
            $paymentId,
            $subscriberId,
            $selectedPlan['price'],
            $paymentStatus
        ]);
        
        $db->commit();
        
        // Limpiar datos de sesión
        unset($_SESSION['signup_data']);
        
        // Iniciar sesión
        $_SESSION['user_id'] = $subscriberId;
        $_SESSION['user_email'] = $signupData['email'];
        $_SESSION['license_key'] = $licenseKey;
        $_SESSION['user_name'] = $signupData['first_name'] . ' ' . $signupData['last_name'];
        $_SESSION['domain'] = $signupData['domain'];
        $_SESSION['plan_type'] = $planType;
        
        // Redirigir según el estado del pago
        if ($planType === 'free' || $paymentStatus === 'completed') {
            if ($isRenewal) {
                // Para renovaciones, limpiar el estado de cuenta inactiva
                unset($_SESSION['account_status']);
                header('Location: dashboard.php?renewal=true');
            } else {
                header('Location: dashboard.php');
            }
        } else {
            if ($isRenewal) {
                header('Location: dashboard.php?renewal=true');
            } else {
                header('Location: dashboard.php');
            }
        }
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $errors[] = 'Error al procesar el pago: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Pago</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        .checkout-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .checkout-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .plan-summary {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .payment-method {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }
        .payment-method.selected {
            border-color: #10b981;
            background: #f0fdf4;
        }
        .btn-checkout {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: bold;
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
                    <?php if (isset($_SESSION['subscriber_id'])): ?>
                        <a href="dashboard.php" class="nav-link">🏠 Inicio</a>
                        <a href="profile.php" class="nav-link">👤 Perfil</a>
                        <a href="billing.php" class="nav-link">💳 Facturación</a>
                        <a href="plugin-config.php" class="nav-link btn-primary">⚙️ Configurar Plugin</a>
                        <a href="logout.php" class="nav-link btn-logout">🚪 Cerrar Sesión</a>
                    <?php else: ?>
                    <a href="../index.php" class="nav-link">Inicio</a>
                    <a href="login.php" class="nav-link btn-login">Iniciar Sesión</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main style="padding-top: 100px; min-height: 100vh; background: #f8fafc;">
        <div class="checkout-container">
            <div class="checkout-card">
                <h2 style="text-align: center; margin-bottom: 2rem; color: #1f2937;">
                    <i class="fas fa-credit-card"></i> 
                    <?php if ($isRenewal): ?>
                        Renovar Suscripción
                    <?php else: ?>
                        Checkout - Pago
                    <?php endif; ?>
                </h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul style="margin: 0; padding-left: 1rem;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Selector de Planes (Oculto por defecto) -->
                <div id="planSelector" style="display: none; background: #f8fafc; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; border: 2px solid #e5e7eb;">
                    <h4 style="margin: 0 0 1rem 0; color: #1f2937;">
                        <i class="fas fa-list"></i> Selecciona tu Plan
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <?php foreach ($plans as $plan): ?>
                            <div class="plan-option" 
                                 style="background: <?php echo $plan['id'] === $planType ? '#dbeafe' : 'white'; ?>; 
                                        border: 2px solid <?php echo $plan['id'] === $planType ? '#3b82f6' : '#e5e7eb'; ?>; 
                                        border-radius: 8px; padding: 1rem; cursor: pointer; transition: all 0.3s ease;"
                                 data-plan-id="<?php echo htmlspecialchars($plan['id']); ?>"
                                 data-plan-name="<?php echo htmlspecialchars($plan['name']); ?>"
                                 data-plan-price="<?php echo $plan['price']; ?>"
                                 data-plan-features="<?php echo htmlspecialchars(json_encode($plan['features'])); ?>"
                                 onclick="updatePlanSelection(this)">
                                <h5 style="margin: 0 0 0.5rem 0; color: #374151;"><?php echo htmlspecialchars($plan['name']); ?></h5>
                                <div style="font-size: 1.2rem; font-weight: bold; color: #10b981; margin-bottom: 0.5rem;">
                                    €<?php echo $plan['price']; ?>/mes
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
                            Resumen de tu Suscripción
                        <?php endif; ?>
                    </h3>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div>
                                    <h4 id="plan-title" style="margin: 0; color: #374151;">Plan <?php echo htmlspecialchars($selectedPlan['name']); ?></h4>
                                    <p style="margin: 0.5rem 0 0 0; color: #6b7280;">Suscripción mensual</p>
                                </div>
                                <button type="button" onclick="togglePlanSelector()" 
                                        style="background: #f3f4f6; color: #374151; padding: 8px 16px; border-radius: 6px; font-size: 0.9rem; font-weight: 500; border: 1px solid #d1d5db; transition: all 0.3s ease; cursor: pointer;"
                                        onmouseover="this.style.background='#e5e7eb'; this.style.borderColor='#9ca3af';"
                                        onmouseout="this.style.background='#f3f4f6'; this.style.borderColor='#d1d5db';">
                                    <i class="fas fa-exchange-alt" style="margin-right: 5px;"></i>
                                    Cambiar Plan
                                </button>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div id="plan-price" style="font-size: 1.5rem; font-weight: bold; color: #10b981;">
                                €<?php echo $selectedPlan['price']; ?>/mes
                            </div>
                        </div>
                    </div>
                    
                    <div style="border-top: 1px solid #e5e7eb; padding-top: 1rem;">
                        <h5 style="margin: 0 0 0.5rem 0; color: #374151;">Características incluidas:</h5>
                        <ul id="plan-features" style="margin: 0; padding-left: 1rem; color: #6b7280;">
                            <?php foreach ($selectedPlan['features'] as $feature): ?>
                                <li style="margin-bottom: 0.25rem;">
                                    <?php if ($selectedPlan['id'] === 'free' && ($feature === 'Sin soporte' || $feature === 'Estadística detallada' || $feature === 'Widget Spotify')): ?>
                                        <i class="fas fa-times" style="color: #dc2626; margin-right: 8px;"></i>
                                    <?php elseif ($selectedPlan['id'] === 'premium' && $feature === 'Widget Spotify'): ?>
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
                        <i class="fas fa-user"></i> Información de la Cuenta
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <strong>Nombre:</strong><br>
                            <?php echo htmlspecialchars($signupData['first_name'] . ' ' . $signupData['last_name']); ?>
                        </div>
                        <div>
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($signupData['email']); ?>
                        </div>
                        <div>
                            <strong>Dominio:</strong><br>
                            <?php echo htmlspecialchars($signupData['domain']); ?>
                        </div>
                        <div>
                            <strong>Empresa:</strong><br>
                            <?php echo htmlspecialchars($signupData['company']); ?>
                        </div>
                    </div>
                </div>

                <!-- Métodos de Pago -->
                <div>
                    <h4 style="margin: 0 0 1rem 0; color: #1f2937;">
                        <i class="fas fa-credit-card"></i> Método de Pago
                    </h4>
                    
                    <?php if ($planType === 'free'): ?>
                        <!-- Plan gratuito - procesar directamente -->
                        <form method="POST">
                            <div class="payment-methods">
                                <div class="payment-method selected" data-method="free">
                                    <i class="fas fa-gift" style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;"></i>
                                    <div style="font-weight: bold; margin-bottom: 0.25rem;">Plan Gratuito</div>
                                    <div style="font-size: 0.9rem; color: #6b7280;">Sin costo, activación inmediata</div>
                                </div>
                            </div>
                            
                            <input type="hidden" name="payment_method" value="free">
                            <input type="hidden" name="isRenewal" value="<?php echo $isRenewal ? 'true' : 'false'; ?>">
                            <input type="hidden" name="subscriber_id" value="<?php echo $subscriberId ?? ''; ?>">
                            
                            <div style="text-align: center; margin-top: 2rem;">
                                <button type="submit" name="process_payment" class="btn btn-primary btn-checkout">
                                    <i class="fas fa-check"></i> 
                                    <?php if ($isRenewal): ?>
                                        Renovar a Plan Gratuito
                                    <?php else: ?>
                                        Crear Cuenta Gratuita
                                    <?php endif; ?>
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Planes pagos - botón PayPal original -->
                        <div class="payment-methods">
                            <div class="payment-method selected" data-method="paypal">
                                <i class="fab fa-paypal" style="font-size: 2rem; color: #0070ba; margin-bottom: 0.5rem;"></i>
                                <div style="font-weight: bold; margin-bottom: 0.25rem;">PayPal</div>
                                <div style="font-size: 0.9rem; color: #6b7280;">Suscripción mensual - Pago manual</div>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 2rem;">
                            <!-- Botón de Pago -->
                            <a href="payment.php?plan=<?php echo $planType; ?><?php echo isset($_GET['billing_cycle_id']) ? '&billing_cycle_id=' . $_GET['billing_cycle_id'] : ''; ?>" 
                               style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
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
                                      cursor: pointer;"
                               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(16, 185, 129, 0.4)';"
                               onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(16, 185, 129, 0.3)';">
                                <i class="fas fa-credit-card" style="margin-right: 10px;"></i>
                                Proceder al Pago
                            </a>
                            
                            <div style="margin-top: 15px; font-size: 14px; color: #6b7280;">
                                <i class="fas fa-shield-alt" style="margin-right: 5px;"></i>
                                Pago 100% seguro y protegido
                            </div>
                        </div>
                        
                        <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 15px; margin-top: 20px; text-align: center;">
                            <i class="fas fa-info-circle" style="color: #f59e0b; margin-right: 8px;"></i>
                            <strong>Proceso Manual:</strong> Después del pago, un administrador revisará y activará tu suscripción manualmente.
                        </div>
                    <?php endif; ?>
                        
                        <div style="text-align: center; margin-top: 1rem; font-size: 0.9rem; color: #6b7280;">
                            <i class="fas fa-shield-alt"></i> 
                            Pago 100% seguro y protegido
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sección de Simulación de Pago (Separada) -->
        <div class="checkout-container" style="margin-top: 2rem;">
            <div class="checkout-card" style="background: #fef3c7; border: 1px solid #f59e0b;">
                <h3 style="text-align: center; margin-bottom: 1rem; color: #92400e;">
                    <i class="fas fa-flask"></i> Modo de Prueba
                </h3>
                <p style="text-align: center; color: #92400e; margin-bottom: 1.5rem;">
                    Para propósitos de prueba, puedes simular un pago exitoso y activar tu cuenta inmediatamente.
                </p>
                <div style="text-align: center;">
                    <button onclick="simulatePayment()" style="background: #f59e0b; color: white; border: none; padding: 1rem 2rem; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.3s ease;" onmouseover="this.style.background='#d97706'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#f59e0b'; this.style.transform='translateY(0)'">
                        <i class="fas fa-credit-card"></i> Simular Pago y Activar Cuenta
                    </button>
                    <p style="margin-top: 0.75rem; color: #92400e; font-size: 0.9rem; font-weight: 500;">
                        ⚠️ Solo para pruebas - No se procesará ningún pago real
                    </p>
                </div>
            </div>
        </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/script.js"></script>
    <script>
        // Función para mostrar/ocultar selector de planes
        function togglePlanSelector() {
            const selector = document.getElementById('planSelector');
            console.log('togglePlanSelector - Elemento encontrado:', !!selector);
            
            if (!selector) {
                console.log('ERROR: No se encontró el elemento planSelector');
                return;
            }
            
            console.log('togglePlanSelector - Display actual:', selector.style.display);
            
            if (selector.style.display === 'none' || selector.style.display === '') {
                selector.style.display = 'block';
                console.log('togglePlanSelector - Mostrando selector');
            } else {
                selector.style.display = 'none';
                console.log('togglePlanSelector - Ocultando selector');
            }
        }

        // Selección de método de pago
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethods = document.querySelectorAll('.payment-method');
            
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    // Remover selección anterior
                    paymentMethods.forEach(m => m.classList.remove('selected'));
                    
                    // Seleccionar actual
                    this.classList.add('selected');
                    
                    // Actualizar input hidden
                    const methodInput = document.querySelector('input[name="payment_method"]');
                    methodInput.value = this.dataset.method;
                });
            });
        });


        // Función para seleccionar un plan
        function selectPlan(planId, planName, planPrice, planFeatures) {
            // Actualizar la URL para recargar con el nuevo plan
            const url = new URL(window.location);
            url.searchParams.set('plan', planId);
            window.location.href = url.toString();
        }

        // Función para actualizar la sección de métodos de pago
        function updatePaymentSection(planId, planName, planPrice) {
            const paymentSection = document.querySelector('.payment-methods').parentElement;
            
            if (planId === 'free') {
                // Plan gratuito - mostrar solo botón suscribirse
                paymentSection.innerHTML = `
                    <div class="payment-methods">
                        <div class="payment-method selected" data-method="free">
                            <i class="fas fa-gift" style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;"></i>
                            <div style="font-weight: bold; margin-bottom: 0.25rem;">Plan Gratuito</div>
                            <div style="font-size: 0.9rem; color: #6b7280;">Sin costo, activación inmediata</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="payment_method" value="free">
                    
                    <div style="text-align: center; margin-top: 2rem;">
                        <button type="submit" name="process_payment" class="btn btn-primary btn-checkout">
                            <i class="fas fa-check"></i> 
                            ${document.querySelector('input[name="isRenewal"]') && document.querySelector('input[name="isRenewal"]').value === 'true' ? 'Renovar a Plan Gratuito' : 'Crear Cuenta Gratuita'}
                        </button>
                    </div>
                `;
            } else {
                // Planes pagos - mostrar PayPal
                let paypalButton = '';
                
                if (planId === 'premium') {
                    // Botón PayPal para Plan Premium
                    paypalButton = `<div id="paypal-button-container-P-6BB75141LH256054MNDENVZQ"></div>`;
                } else if (planId === 'enterprise') {
                    // Botón PayPal para Plan +Spotify
                    paypalButton = `<div id="paypal-button-container-P-45V717624S037913FNDENTUY"></div>`;
                }
                
                paymentSection.innerHTML = `
                    <div class="payment-methods">
                        <div class="payment-method selected" data-method="paypal">
                            <i class="fab fa-paypal" style="font-size: 2rem; color: #0070ba; margin-bottom: 0.5rem;"></i>
                            <div style="font-weight: bold; margin-bottom: 0.25rem;">PayPal</div>
                            <div style="font-size: 0.9rem; color: #6b7280;">Suscripción mensual - Pago manual</div>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin-top: 2rem;">
                        ${paypalButton}
                    </div>
                    
                    <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 15px; margin-top: 20px; text-align: center;">
                        <i class="fas fa-info-circle" style="color: #f59e0b; margin-right: 8px;"></i>
                        <strong>Proceso Manual:</strong> Después del pago, un administrador revisará y activará tu suscripción manualmente.
                    </div>
                `;
                
                // Si es plan Premium o +Spotify, cargar el script de PayPal
                if (planId === 'premium' || planId === 'enterprise') {
                    // Remover script anterior si existe
                    const existingScript = document.querySelector('script[src*="paypal.com/sdk/js"]');
                    if (existingScript) {
                        existingScript.remove();
                    }
                    
                    // Cargar nuevo script
                    const script = document.createElement('script');
                    script.src = 'https://www.paypal.com/sdk/js?client-id=ARYSVZGuaEdigjhdE3zwIGLhwu9zjbJGXNWdmBSx_JFGR20EkSBva6H1IhFYVzL3Tcej7ndeA5D5Hkky&vault=true&intent=subscription';
                    script.setAttribute('data-sdk-integration-source', 'button-factory');
                    document.head.appendChild(script);
                    
                    script.onload = function() {
                        if (planId === 'premium') {
                            // Botón PayPal para Plan Premium
                            paypal.Buttons({
                                style: {
                                    shape: 'rect',
                                    color: 'gold',
                                    layout: 'vertical',
                                    label: 'subscribe'
                                },
                                createSubscription: function(data, actions) {
                                    return actions.subscription.create({
                                        plan_id: 'P-6BB75141LH256054MNDENVZQ'
                                    });
                                },
                                onApprove: function(data, actions) {
                                    alert('Suscripción Premium creada: ' + data.subscriptionID + '. Redirigiendo al dashboard...');
                                    window.location.href = 'dashboard.php';
                                }
                            }).render('#paypal-button-container-P-6BB75141LH256054MNDENVZQ');
                        } else if (planId === 'enterprise') {
                            // Botón PayPal para Plan +Spotify
                            paypal.Buttons({
                                style: {
                                    shape: 'rect',
                                    color: 'gold',
                                    layout: 'vertical',
                                    label: 'subscribe'
                                },
                                createSubscription: function(data, actions) {
                                    return actions.subscription.create({
                                        plan_id: 'P-45V717624S037913FNDENTUY'
                                    });
                                },
                                onApprove: function(data, actions) {
                                    alert('Suscripción +Spotify creada: ' + data.subscriptionID + '. Redirigiendo al dashboard...');
                                    window.location.href = 'dashboard.php';
                                }
                            }).render('#paypal-button-container-P-45V717624S037913FNDENTUY');
                        }
                    };
                }
            }
        }

        // Función alternativa para actualizar el plan sin recargar
        function updatePlanDisplay(element) {
            // Obtener datos de los atributos data
            const planId = element.dataset.planId;
            const planName = element.dataset.planName;
            const planPrice = element.dataset.planPrice;
            const planFeatures = JSON.parse(element.dataset.planFeatures);
            
            // Actualizar la información mostrada del plan
            document.getElementById('plan-title').textContent = 'Plan ' + planName;
            document.getElementById('plan-price').textContent = '€' + planPrice + '/mes';
            
            // Actualizar características del plan
            const featuresList = document.getElementById('plan-features');
            if (featuresList) {
                featuresList.innerHTML = '';
                planFeatures.forEach(feature => {
                    const li = document.createElement('li');
                    li.innerHTML = '<i class="fas fa-check" style="color: #10b981; margin-right: 8px;"></i>' + feature;
                    featuresList.appendChild(li);
                });
            }
            
            // Actualizar el enlace del botón de pago
            const paymentButton = document.querySelector('a[href*="payment.php"]');
            if (paymentButton) {
                const currentUrl = new URL(paymentButton.href);
                currentUrl.searchParams.set('plan', planId);
                paymentButton.href = currentUrl.toString();
            }
        }

        function updatePlanSelection(element) {
            // Obtener datos de los atributos data
            const planId = element.dataset.planId;
            const planName = element.dataset.planName;
            const planPrice = element.dataset.planPrice;
            const planFeatures = JSON.parse(element.dataset.planFeatures);
            
            console.log('Actualizando plan:', planId, planName, planPrice, planFeatures);
            
            // Actualizar plan en la base de datos via AJAX
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update_plan&plan_type=' + encodeURIComponent(planId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Plan actualizado en la base de datos:', data.message);
                } else {
                    console.error('Error al actualizar plan:', data.message);
                }
            })
            .catch(error => {
                console.error('Error en la llamada AJAX:', error);
            });
            // Actualizar el título del plan en el resumen
            const planTitle = document.getElementById('plan-title');
            if (planTitle) {
                planTitle.textContent = 'Plan ' + planName;
            }
            
            // Actualizar el precio
            const planPriceElement = document.getElementById('plan-price');
            if (planPriceElement) {
                planPriceElement.innerHTML = '€' + planPrice + '/mes';
            }
            
            // Actualizar las características
            const featuresList = document.getElementById('plan-features');
            if (featuresList && planFeatures) {
                featuresList.innerHTML = '';
                planFeatures.forEach(feature => {
                    const li = document.createElement('li');
                    li.style.marginBottom = '0.25rem';
                    
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
            
            // Actualizar el plan seleccionado en el selector
            const planOptions = document.querySelectorAll('.plan-option');
            planOptions.forEach(option => {
                // Remover selección anterior de TODOS los planes
                option.style.background = 'white';
                option.style.borderColor = '#e5e7eb';
                
                // Remover TODOS los indicadores de seleccionado (incluyendo los generados por PHP)
                const selectedIndicators = option.querySelectorAll('.selected-indicator');
                selectedIndicators.forEach(indicator => {
                    indicator.remove();
                });
                
                // También remover cualquier div que contenga "Seleccionado" (por si acaso)
                const allDivs = option.querySelectorAll('div');
                allDivs.forEach(div => {
                    if (div.textContent.includes('Seleccionado')) {
                        div.remove();
                    }
                });
                
                // Si es el plan seleccionado, marcarlo
                if (option.dataset.planId === planId) {
                    option.style.background = '#dbeafe';
                    option.style.borderColor = '#3b82f6';
                    
                    // Agregar indicador de seleccionado
                    const indicator = document.createElement('div');
                    indicator.className = 'selected-indicator';
                    indicator.style.textAlign = 'center';
                    indicator.style.marginTop = '0.5rem';
                    indicator.style.color = '#3b82f6';
                    indicator.style.fontWeight = 'bold';
                    indicator.innerHTML = '<i class="fas fa-check-circle"></i> Seleccionado';
                    option.appendChild(indicator);
                }
            });
            
            // Actualizar la URL sin recargar
            const url = new URL(window.location);
            url.searchParams.set('plan', planId);
            window.history.replaceState({}, '', url.toString());
            
            // Actualizar la sección de métodos de pago según el plan
            updatePaymentSection(planId, planName, planPrice);
            
            
            // Llamar a updatePlanDisplay para actualizar los botones de pago
            updatePlanDisplay(element);
            
            // Ocultar el selector
            document.getElementById('planSelector').style.display = 'none';
        }

        // Hover effects para opciones de plan
        document.addEventListener('DOMContentLoaded', function() {
            // Preseleccionar el plan correcto si viene de una factura
            const currentPlanType = '<?php echo $planType; ?>';
            const planOptions = document.querySelectorAll('.plan-option');
            
            planOptions.forEach(option => {
                const planId = option.dataset.planId;
                if (planId === currentPlanType) {
                    // Resaltar el plan seleccionado
                    option.style.background = '#dbeafe';
                    option.style.borderColor = '#3b82f6';
                    
                    // Actualizar la información del plan mostrada
                    updatePlanDisplay(option);
                }
                
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
    </script>
    <script>
        // Esperar a que el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
        // Botón PayPal para Plan Premium
            const premiumContainer = document.getElementById('paypal-button-container-P-4BX32647SP6212626NDIASRA');
            if (premiumContainer) {
        paypal.Buttons({
            style: {
                shape: 'rect',
                color: 'gold',
                layout: 'vertical',
                label: 'subscribe'
            },
            createSubscription: function(data, actions) {
                return actions.subscription.create({
                    plan_id: 'P-4BX32647SP6212626NDIASRA'
                });
            },
            onApprove: function(data, actions) {
                console.log('PayPal Subscription Approved:', data);
                
                // Mostrar loading
                const buttonContainer = document.getElementById('paypal-button-container-P-4BX32647SP6212626NDIASRA');
                buttonContainer.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Procesando pago...</div>';
                
                // Crear o actualizar ciclo pendiente
                createPendingBillingCycle().then(response => {
                    if (response && response.billing_cycle_id) {
                        // Redirigir a página de confirmación
                        const confirmUrl = 'payment-confirmation.php?payment_id=' + data.subscriptionID + '&external_reference=<?php echo $_SESSION['subscriber_id'] ?? 'test'; ?>&status=approved&method=paypal';
                        window.location.href = confirmUrl;
                    } else {
                        alert('Error al procesar la factura. Por favor, intenta nuevamente.');
                        location.reload();
                    }
                });
            },
            onError: function(err) {
                console.error('PayPal Error:', err);
                alert('Error en el proceso de pago: ' + err.message);
            }
                }).render('#paypal-button-container-P-4BX32647SP6212626NDIASRA');
            }
            
            // Botón PayPal para Plan Enterprise
            const enterpriseContainer = document.getElementById('paypal-button-container-P-45V717624S037913FNDENTUY');
            if (enterpriseContainer) {
        paypal.Buttons({
            style: {
                shape: 'rect',
                color: 'gold',
                layout: 'vertical',
                label: 'subscribe'
            },
            createSubscription: function(data, actions) {
                return actions.subscription.create({
                    plan_id: 'P-45V717624S037913FNDENTUY'
                });
            },
            onApprove: function(data, actions) {
                // Mostrar loading
                const buttonContainer = document.getElementById('paypal-button-container-P-45V717624S037913FNDENTUY');
                buttonContainer.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Procesando pago...</div>';
                
                // Crear o actualizar ciclo pendiente
                createPendingBillingCycle().then(response => {
                    if (response && response.billing_cycle_id) {
                        // Redirigir a página de confirmación
                        const confirmUrl = 'payment-confirmation.php?payment_id=' + data.subscriptionID + '&external_reference=<?php echo $_SESSION['subscriber_id'] ?? 'test'; ?>&status=approved&method=paypal';
                        window.location.href = confirmUrl;
                    } else {
                        alert('Error al procesar la factura. Por favor, intenta nuevamente.');
                        location.reload();
                    }
                });
            }
        }).render('#paypal-button-container-P-45V717624S037913FNDENTUY');
            }
        });
    </script>
    
    <script>
        function simulatePayment() {
            console.log('[SIMULATE_PAYMENT] Función llamada');
            if (confirm('¿Simular pago y activar la cuenta? Esto activará tu suscripción inmediatamente.')) {
                console.log('[SIMULATE_PAYMENT] Confirmado, enviando petición');
                // Simular pago exitoso
                const planType = '<?php echo $planType; ?>';
                fetch('simulate-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=simulate_payment&plan_type=' + encodeURIComponent(planType)
                })
                .then(response => response.json())
                .then(data => {
                    console.log('[SIMULATE_PAYMENT] Respuesta recibida:', data);
                    if (data.success) {
                        alert('¡Pago simulado exitosamente! Tu cuenta ha sido activada. Redirigiendo a configuración del plugin...');
                        window.location.href = 'plugin-config.php';
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
    
    <!-- Script MercadoPago para Planes Premium y Enterprise -->
    <style>
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
        }
        
        .blue-button:hover {
            background-color: #2a68c8;
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
    
    <script type="text/javascript">
        (function() {
            function $MPC_load() {
                window.$MPC_loaded !== true && (function() {
                    var s = document.createElement("script");
                    s.type = "text/javascript";
                    s.async = true;
                    s.src = document.location.protocol + "//secure.mlstatic.com/mptools/render.js";
                    var x = document.getElementsByTagName('script')[0];
                    x.parentNode.insertBefore(s, x);
                    window.$MPC_loaded = true;
                })();
            }
            window.$MPC_loaded !== true ? (window.attachEvent ? window.attachEvent('onload', $MPC_load) : window.addEventListener('load', $MPC_load, false)) : null;
        })();
        
        // Función para crear ciclo de facturación pendiente usando Fetch
        function createPendingBillingCycle() {
            return new Promise((resolve, reject) => {
                // Obtener el plan actualmente seleccionado
                const selectedPlan = document.querySelector('.plan-option[style*="background: #dbeafe"]');
                const planType = selectedPlan ? selectedPlan.dataset.planId : '<?php echo $planType; ?>';
                const subscriberId = '<?php echo $subscriberId; ?>';
                
                console.log('Creando ciclo de facturación pendiente...');
                console.log('Plan Type:', planType);
                console.log('Subscriber ID:', subscriberId);
                
                const formData = new FormData();
                formData.append('plan_type', planType);
                formData.append('subscriber_id', subscriberId);
                
                fetch('create-pending-billing.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin' // Importante para enviar cookies de sesión
                })
                .then(response => {
                    console.log('Respuesta recibida, status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Datos recibidos:', data);
                    
                    if (data.success) {
                        if (data.no_action_needed) {
                            console.log('Factura ya existe para este plan:', data.message);
                        } else if (data.replaced_existing) {
                            console.log('Factura actualizada:', data.message);
                        } else {
                            console.log('Nueva factura creada:', data.message);
                        }
                        resolve(data); // Retornar toda la respuesta
                    } else {
                        console.error('Error creando ciclo de facturación:', data.message);
                        resolve(null);
                    }
                })
                .catch(error => {
                    console.error('Error en la petición:', error);
                    resolve(null);
                });
            });
        }

        // Función para manejar clic en MercadoPago
        function handleMercadoPagoClick(event) {
            event.preventDefault();
            
            // Crear modal personalizado
            createMercadoPagoModal();
            
            // Crear o actualizar ciclo pendiente
            createPendingBillingCycle().then(response => {
                if (response && response.billing_cycle_id) {
                    // Mostrar mensaje informativo según el caso
                    if (response.no_action_needed) {
                        console.log('Factura ya existe para este plan:', response.message);
                    } else if (response.replaced_existing) {
                        console.log('Factura actualizada:', response.message);
                    } else {
                        console.log('Nueva factura creada:', response.message);
                    }
                    
                    // Abrir MercadoPago en el modal
                    const mpUrl = 'https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=90334be51787402bad7d41110e0904fb&success_url=https%3A%2F%2Fda7a140816ec.ngrok-free.app%2Fapi_discogs%2Fsubscribe%2Fpages%2Fpayment-confirmation.php&failure_url=https%3A%2F%2Fda7a140816ec.ngrok-free.app%2Fapi_discogs%2Fsubscribe%2Fpages%2Fcheckout.php%3Fplan%3Dpremium%26error%3Dpayment_failed&external_reference=<?php echo $_SESSION['subscriber_id'] ?? 'test'; ?>';
                    openMercadoPagoInModal(mpUrl);
                } else {
                    closeMercadoPagoModal();
                    alert('Error al procesar la factura. Por favor, intenta nuevamente.');
                }
            }).catch(error => {
                closeMercadoPagoModal();
                console.error('Error:', error);
                alert('Error al procesar la factura. Por favor, intenta nuevamente.');
            });
        }
        
        // Función para crear el modal de MercadoPago
        function createMercadoPagoModal() {
            // Crear el modal si no existe
            if (document.getElementById('mercadopagoModal')) {
                return;
            }
            
            const modalHTML = `
                <div id="mercadopagoModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div style="position: relative; background-color: white; margin: 3% auto; padding: 0; border-radius: 10px; width: 95%; max-width: 1200px; height: 85%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="background: #3483FA; color: white; padding: 15px 20px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; font-size: 18px; font-weight: bold;">Pago con MercadoPago</h3>
                            <button onclick="closeMercadoPagoModal()" style="color: white; font-size: 28px; font-weight: bold; cursor: pointer; line-height: 1; padding: 0; background: none; border: none; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background-color 0.3s;">&times;</button>
                        </div>
                        <div style="height: calc(100% - 60px); overflow: hidden;">
                            <div id="loadingDiv" style="display: flex; justify-content: center; align-items: center; height: 100%; font-size: 18px; color: #6b7280;">
                                <div style="border: 4px solid #f3f4f6; border-top: 4px solid #3483FA; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin-right: 15px;"></div>
                                Creando ciclo de facturación...
                            </div>
                            <iframe id="mercadopagoIframe" style="width: 100%; height: 100%; border: none; border-radius: 0 0 10px 10px; display: none;"></iframe>
                        </div>
                    </div>
                </div>
                <style>
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
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
        
        // Función para abrir MercadoPago en el modal
        function openMercadoPagoInModal(url) {
            const modal = document.getElementById('mercadopagoModal');
            const loadingDiv = document.getElementById('loadingDiv');
            const iframe = document.getElementById('mercadopagoIframe');
            
            // Mostrar modal
            modal.style.display = 'block';
            loadingDiv.style.display = 'flex';
            iframe.style.display = 'none';
            
            // Simular carga
            setTimeout(() => {
                loadingDiv.style.display = 'none';
                iframe.style.display = 'block';
                iframe.src = url;
            }, 1000);
        }
        
        // Función para cerrar el modal
        function closeMercadoPagoModal() {
            const modal = document.getElementById('mercadopagoModal');
            const iframe = document.getElementById('mercadopagoIframe');
            
            if (modal) {
                modal.style.display = 'none';
                if (iframe) {
                    iframe.src = '';
                }
            }
        }
        
        // Cerrar modal al hacer clic fuera de él
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('mercadopagoModal');
            if (event.target === modal) {
                closeMercadoPagoModal();
            }
        });
        
        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMercadoPagoModal();
            }
        });

        // Función para recibir mensaje cuando se cierra el modal de MercadoPago
        function $MPC_message(event) {
            console.log('MercadoPago message received:', event.data);
            
            // Aquí puedes procesar la respuesta de MercadoPago
            if (event.data && event.data.preapproval_id) {
                console.log('Preapproval ID:', event.data.preapproval_id);
                
                // Redirigir a la página de confirmación
                const confirmUrl = 'payment-confirmation.php?preapproval_id=' + event.data.preapproval_id + '&external_reference=<?php echo $_SESSION['subscriber_id'] ?? 'test'; ?>&status=approved&method=mercadopago';
                window.location.href = confirmUrl;
            }
        }
        
        // Escuchar mensajes de MercadoPago
        window.addEventListener("message", $MPC_message);
    </script>
    
</body>
</html>
