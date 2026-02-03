<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

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

requireLogin();

$subscriber_id = $_SESSION['subscriber_id'];
$license_key = $_SESSION['license_key'] ?? '';

// Obtener información del usuario y facturas
if (isset($_SESSION['subscriber_id'])) {
    $subscriber_id = $_SESSION['subscriber_id'];
    
    try {
        $db = getDatabase();
        $stmt = $db->prepare("SELECT id, plan_type, status, amount, created_at FROM billing_cycles WHERE subscriber_id = ? AND status = 'paid' ORDER BY created_at DESC");
        $stmt->execute([$subscriber_id]);
        $paidInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Error al obtener facturas
        $paidInvoices = [];
    }
} else {
    $paidInvoices = [];
}

// Verificar si hay factura (cualquier registro en billing_cycles)
$has_active_invoice = false;
try {
    $db = getDatabase();
    $stmt = $db->prepare("
        SELECT * FROM billing_cycles 
        WHERE subscriber_id = ? AND status = 'paid'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$subscriber_id]);
    $billing_cycle_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($billing_cycle_data) {
        $has_active_invoice = true;
    }
} catch (Exception $e) {
    // En caso de error, asumir que no hay factura
    $has_active_invoice = false;
}

// Obtener información del suscriptor
$pdo = getDatabase();

$subscriber = $pdo->prepare("SELECT * FROM subscribers WHERE id = ?");
$subscriber->execute([$subscriber_id]);
$subscriber_data = $subscriber->fetch(PDO::FETCH_ASSOC);

// Obtener ciclos de facturación del suscriptor (filtrar según reglas)
$billing_cycles = $pdo->prepare("
    SELECT bc.*, s.plan_type as current_plan_type
    FROM billing_cycles bc
    JOIN subscribers s ON bc.subscriber_id = s.id
    WHERE bc.subscriber_id = ? 
    AND bc.status != 'cancelled'
    AND (
        (s.plan_type = 'free' AND bc.status = 'pending') OR
        (s.plan_type != 'free')
    )
    ORDER BY bc.created_at DESC 
    LIMIT 20
");
$billing_cycles->execute([$subscriber_id]);
$billing_cycles_data = $billing_cycles->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Facturación', 'Billing'); ?> - dwoosync</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo t('Gestiona tu facturación y suscripciones de dwoosync. Historial de pagos, planes y facturas.', 'Manage your dwoosync billing and subscriptions. Payment history, plans and invoices.'); ?>">
    <meta name="keywords" content="<?php echo t('facturación dwoosync, gestión pagos, suscripciones, historial facturas', 'dwoosync billing, payment management, subscriptions, invoice history'); ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo t('Facturación - dwoosync', 'Billing - dwoosync'); ?>">
    <meta property="og:description" content="<?php echo t('Gestiona tu facturación y suscripciones de dwoosync.', 'Manage your dwoosync billing and subscriptions.'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://dwoosync.com/subscribe/pages/billing.php">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://dwoosync.com/subscribe/pages/billing.php">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php if (!$has_active_invoice): ?>
    <!-- Notificación global de suscripción incompleta -->
    <div id="globalSubscriptionNotification" style="
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        padding: 12px 20px;
            text-align: center;
        z-index: 9999;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    ">
        <div style="display: flex; align-items: center; justify-content: center; gap: 10px; max-width: 1200px; margin: 0 auto;">
            <div style="font-size: 1.2rem;">⚠️</div>
            <div style="flex: 1;">
                <strong><?php echo t('¡Completa tu suscripción!', 'Complete your subscription!'); ?></strong> 
                <?php echo t('Selecciona un plan para continuar usando dwoosync.', 'Select a plan to continue using dwoosync.'); ?>
            </div>
            <a href="checkout.php?plan=free&lang=<?php echo $currentLang; ?>" style="
                background: rgba(255,255,255,0.2);
            color: white;
                padding: 8px 16px;
                border-radius: 6px;
            text-decoration: none;
                font-weight: 500;
            transition: all 0.3s ease;
                border: 1px solid rgba(255,255,255,0.3);
            " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                <?php echo t('Completar Ahora', 'Complete Now'); ?>
            </a>
            <button onclick="hideNotification()" style="
                background: none;
            border: none;
                color: white;
                font-size: 1.5rem;
            cursor: pointer;
                padding: 0;
                margin-left: 10px;
            " title="Cerrar notificación">×</button>
        </div>
    </div>
    
    <!-- Ajustar el body para la notificación -->
    <style>
        body {
            padding-top: 60px !important;
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 80px !important;
            }
            
            #globalSubscriptionNotification {
                padding: 15px 10px;
            }
            
            #globalSubscriptionNotification > div {
                flex-direction: column;
                gap: 10px;
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
    </style>
    
    <script>
        function hideNotification() {
            document.getElementById('globalSubscriptionNotification').style.display = 'none';
            document.body.style.paddingTop = '0px';
        }
    </script>
    <?php endif; ?>
    
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
                    <a href="dashboard.php?lang=<?php echo $currentLang; ?>" class="nav-link">🏠 <?php echo t('Inicio', 'Home'); ?></a>
                    <a href="billing.php?lang=<?php echo $currentLang; ?>" class="nav-link btn-primary">💳 <?php echo t('Facturación', 'Billing'); ?></a>
                    <a href="tutorials.php?lang=<?php echo $currentLang; ?>" class="nav-link">🎥 <?php echo t('Tutoriales', 'Tutorials'); ?></a>
                    <a href="plugin-config.php?lang=<?php echo $currentLang; ?>" class="nav-link">⚙️ <?php echo t('Configurar Plugin', 'Configure Plugin'); ?></a>
                    
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
                            <a href="?lang=es" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; border-bottom: 1px solid #f3f4f6; <?php echo !$isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                                <span style="font-size: 1.1em; margin-right: 8px;">🇪🇸</span> Español
                            </a>
                            <a href="?lang=en" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; <?php echo $isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
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

    <!-- Main Content -->
    <main class="main" style="padding-top: 120px;">
    <div class="container">
            <div class="card">

        <?php if (!$has_active_invoice): ?>
            <!-- Mensaje para completar suscripción -->
                    <div style="text-align: center; padding: 2rem; background: #fef3c7; border: 2px solid #f59e0b; border-radius: 12px; margin-bottom: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">⚠️</div>
                        <h2 style="color: #1f2937; margin-bottom: 1rem;"><?php echo t('Completa tu Suscripción', 'Complete your Subscription'); ?></h2>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">
                            <?php echo t('No se ha completado la configuración de tu suscripción. Selecciona un plan para continuar:', 'Your subscription setup has not been completed. Select a plan to continue:'); ?>
                        </p>
                        <button onclick="openPlansModal()" class="btn btn-primary">
                    <i class="fas fa-crown"></i> <?php echo t('Seleccionar Plan', 'Select Plan'); ?>
                </button>
                    </div>
        <?php elseif (($subscriber_data['plan_type'] ?? 'free') === 'free'): ?>
            <!-- Mensaje para Plan Free (solo si ya tiene factura) -->
                    <div style="text-align: center; padding: 2rem; background: #f9fafb; border-radius: 12px; margin-bottom: 2rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">🎵</div>
                        <h2 style="color: #1f2937; margin-bottom: 1rem;"><?php echo t('Plan Gratuito', 'Free Plan'); ?></h2>
                        <p style="color: #6b7280; margin-bottom: 1.5rem;">
                            <?php echo t('Actualmente tienes el plan gratuito de dwoosync.', 'You currently have the free plan of dwoosync.'); ?>
                        </p>
                        <button onclick="openPlansModal()" class="btn btn-primary">
                    <i class="fas fa-arrow-up"></i> <?php echo t('Mejorar Plan', 'Upgrade Plan'); ?>
                </button>
                        </div>
                    <?php endif; ?>
                    
        <?php if (!empty($billing_cycles_data)): ?>
                    <!-- Estadísticas -->
                    <div class="grid grid-3" style="margin-bottom: 2rem;">
                        <div style="text-align: center; padding: 1.5rem; background: #f9fafb; border-radius: 8px;">
                            <div style="font-size: 2.5rem; color: #d97706; margin-bottom: 0.5rem;">
                                <i class="fas fa-clock"></i>
                    </div>
                            <h3 style="color: #1f2937; margin-bottom: 0.5rem;"><?php echo t('Facturas Pendientes', 'Pending Invoices'); ?></h3>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #d97706;">
                                $<?php 
                                $pending_total = 0;
                                foreach ($billing_cycles_data as $invoice) {
                                    if ($invoice['status'] === 'pending' || $invoice['status'] === 'unpaid') {
                                        $pending_total += $invoice['amount'];
                                    }
                                }
                                echo number_format($pending_total, 2);
                        ?>
                </div>
            </div>

                        <div style="text-align: center; padding: 1.5rem; background: #f9fafb; border-radius: 8px;">
                            <div style="font-size: 2.5rem; color: #059669; margin-bottom: 0.5rem;">
                                <i class="fas fa-receipt"></i>
                    </div>
                            <h3 style="color: #1f2937; margin-bottom: 0.5rem;"><?php echo t('Facturas', 'Invoices'); ?></h3>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #059669;">
                                <?php echo count($billing_cycles_data); ?>
                    </div>
                    </div>
                    
                        <div style="text-align: center; padding: 1.5rem; background: #f9fafb; border-radius: 8px;">
                            <div style="font-size: 2.5rem; color: #059669; margin-bottom: 0.5rem;">
                                <i class="fas fa-check-circle"></i>
                    </div>
                            <h3 style="color: #1f2937; margin-bottom: 0.5rem;"><?php echo t('Pagadas', 'Paid'); ?></h3>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #059669;">
                                <?php 
                                $paid_count = 0;
                                foreach ($billing_cycles_data as $invoice) {
                                    if ($invoice['status'] === 'paid') {
                                        $paid_count++;
                                    }
                                }
                                echo $paid_count;
                                ?>
                        </div>
            </div>
        </div>

                    <!-- Historial de Facturas -->
                    <div class="card">
                        <h2 style="margin-bottom: 1.5rem; color: #1f2937;">
                            <i class="fas fa-file-invoice"></i> <?php echo t('Historial de Facturas', 'Invoice History'); ?>
                </h2>
                
                        <?php if (!empty($billing_cycles_data)): ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                            <thead>
                                        <tr style="background: #f8fafc; border-bottom: 2px solid #e5e7eb;">
                                            <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;"><?php echo t('Número', 'Number'); ?></th>
                                            <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;"><?php echo t('Monto', 'Amount'); ?></th>
                                            <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;"><?php echo t('Estado', 'Status'); ?></th>
                                            <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;"><?php echo t('Vencimiento', 'Due Date'); ?></th>
                                            <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;"><?php echo t('Pago', 'Payment'); ?></th>
                                            <th style="padding: 1rem; text-align: left; font-weight: 600; color: #374151;"><?php echo t('Período', 'Period'); ?></th>
                                            <th style="padding: 1rem; text-align: center; font-weight: 600; color: #374151;"><?php echo t('Acciones', 'Actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                        <?php foreach ($billing_cycles_data as $invoice): ?>
                                            <tr style="border-bottom: 1px solid #e5e7eb;">
                                                <td style="padding: 1rem;">
                                                    <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <strong>$<?php echo number_format($invoice['amount'], 2); ?></strong>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <?php
                                                    $status = $invoice['status'];
                                                    $status_class = '';
                                                    $status_icon = '';
                                                    
                                                    switch ($status) {
                                                        case 'paid':
                                                            $status_class = 'background: #d1fae5; color: #065f46;';
                                                            $status_icon = '✅';
                                                            break;
                                                        case 'pending':
                                                            $due_date = new DateTime($invoice['due_date']);
                                                            $today = new DateTime();
                                                            if ($today > $due_date) {
                                                                $status_class = 'background: #fee2e2; color: #991b1b;';
                                                                $status_icon = '⚠️';
                                                            } else {
                                                                $status_class = 'background: #fef3c7; color: #92400e;';
                                                                $status_icon = '⏳';
                                                            }
                                                            break;
                                                        case 'cancelled':
                                                            $status_class = 'background: #f3f4f6; color: #6b7280;';
                                                            $status_icon = '❌';
                                                            break;
                                                        case 'refunded':
                                                            $status_class = 'background: #e0e7ff; color: #3730a3;';
                                                            $status_icon = '🔄';
                                                            break;
                                                    }
                                                    ?>
                                                    <span style="padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; <?php echo $status_class; ?>">
                                                        <?php echo $status_icon; ?> <?php echo strtoupper($status); ?>
                                            </span>
                                        </td>
                                                <td style="padding: 1rem;">
                                                    <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                                                    <?php if ($status === 'pending'): ?>
                                                        <?php
                                                        $due_date = new DateTime($invoice['due_date']);
                                                        $today = new DateTime();
                                                        if ($today > $due_date) {
                                                            $days_overdue = $today->diff($due_date)->days;
                                                            echo '<br><small style="color: #dc2626;">' . t('Vencida hace', 'Overdue by') . ' ' . $days_overdue . ' ' . t('días', 'days') . '</small>';
                                                        } else {
                                                            $days_remaining = $today->diff($due_date)->days;
                                                            echo '<br><small style="color: #059669;">' . t('Vence en', 'Due in') . ' ' . $days_remaining . ' ' . t('días', 'days') . '</small>';
                                                        }
                                                        ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <?php 
                                                    if ($invoice['paid_date']) {
                                                        echo date('d/m/Y', strtotime($invoice['paid_date']));
                                                    } else {
                                                        echo '<span style="color: #6b7280;">-</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td style="padding: 1rem;">
                                                    <?php if ($invoice['cycle_start_date'] && $invoice['cycle_end_date']): ?>
                                                        <?php echo date('d/m/Y', strtotime($invoice['cycle_start_date'])); ?> - 
                                                        <?php echo date('d/m/Y', strtotime($invoice['cycle_end_date'])); ?>
                <?php else: ?>
                                                        <span style="color: #6b7280;">-</span>
                <?php endif; ?>
                                        </td>
                                                <td style="padding: 1rem; text-align: center;">
                                                    <?php if ($status === 'pending'): ?>
                                                        <a href="checkout.php?plan=<?php echo urlencode($invoice['plan_type']); ?>&billing_cycle_id=<?php echo $invoice['id']; ?>" 
                                                           style="background: #059669; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.9rem; font-weight: 500; display: inline-block; transition: all 0.3s ease;"
                                                           onmouseover="this.style.background='#047857'; this.style.transform='translateY(-1px)'"
                                                           onmouseout="this.style.background='#059669'; this.style.transform='translateY(0)'">
                                                            <i class="fas fa-credit-card"></i> <?php echo t('Pagar', 'Pay'); ?>
                                                        </a>
                                                    <?php elseif ($status === 'paid'): ?>
                                                        <span style="color: #059669; font-weight: 500;">
                                                            <i class="fas fa-check-circle"></i> <?php echo t('Pagada', 'Paid'); ?>
                                            </span>
                                                    <?php else: ?>
                                                        <span style="color: #6b7280;">
                                                            <i class="fas fa-ban"></i> <?php echo t('No disponible', 'Not available'); ?>
                                            </span>
                                                    <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                            <div style="text-align: center; padding: 3rem; color: #6b7280;">
                                <i class="fas fa-file-invoice" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                <p><?php echo t('No hay facturas registradas', 'No invoices registered'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
    </main>

    <script src="../assets/js/script.js"></script>

    <!-- Modal de Planes -->
    <div id="plansModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-crown"></i> <?php echo t('Selecciona tu Plan', 'Select your Plan'); ?></h2>
                <button class="modal-close" onclick="closePlansModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="plans-grid">
                    <!-- Plan Free -->
                    <div class="plan-card" data-plan="free" onclick="selectPlan('free')">
                        <div class="plan-header">
                            <h3><?php echo t('Gratuito', 'Free'); ?></h3>
                            <div class="plan-price">$0<span>/<?php echo t('mes', 'month'); ?></span></div>
                        </div>
                        <div class="plan-features">
                            <ul>
                                <li><i class="fas fa-check"></i> <?php echo t('10 importaciones/mes', '10 imports/month'); ?></li>
                                <li><i class="fas fa-times" style="color: #dc2626;"></i> <?php echo t('Sin soporte', 'No support'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Documentación básica', 'Basic documentation'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('1 Dominio/Sitio Web', '1 Domain/Website'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Actualizaciones', 'Updates'); ?></li>
                                <li><i class="fas fa-times" style="color: #dc2626;"></i> <?php echo t('Estadística detallada', 'Detailed statistics'); ?></li>
                                <li><i class="fas fa-times" style="color: #dc2626;"></i> <?php echo t('Widget Spotify', 'Spotify Widget'); ?></li>
                            </ul>
                        </div>
                        <div class="plan-button">
                            <?php if ($has_active_invoice && ($subscriber_data['plan_type'] ?? 'free') === 'free'): ?>
                                <span class="current-plan"><?php echo t('Plan Actual', 'Current Plan'); ?></span>
                            <?php else: ?>
                                <button class="btn-select"><?php echo t('Seleccionar', 'Select'); ?></button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Plan Premium -->
                    <div class="plan-card premium" data-plan="premium" onclick="selectPlan('premium')">
                        <div class="plan-badge"><?php echo t('Más Popular', 'Most Popular'); ?></div>
                        <div class="plan-header">
                            <h3>Premium</h3>
                            <div class="plan-price">$22<span>/<?php echo t('mes', 'month'); ?></span></div>
                        </div>
                        <div class="plan-features">
                            <ul>
                                <li><i class="fas fa-check"></i> <?php echo t('Importaciones ilimitadas', 'Unlimited imports'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Soporte prioritario', 'Priority support'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('1 Dominio/Sitio Web', '1 Domain/Website'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Actualizaciones', 'Updates'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Estadística detallada', 'Detailed statistics'); ?></li>
                                <li><i class="fas fa-times" style="color: #dc2626;"></i> <?php echo t('Widget Spotify', 'Spotify Widget'); ?></li>
                            </ul>
                        </div>
                        <div class="plan-button">
                            <button class="btn-select"><?php echo t('Seleccionar', 'Select'); ?></button>
                        </div>
                    </div>

                    <!-- Plan +Spotify -->
                    <div class="plan-card enterprise" data-plan="enterprise" onclick="selectPlan('enterprise')">
                        <div class="plan-header">
                            <h3><?php echo t('+Spotify', '+Spotify'); ?></h3>
                            <div class="plan-price">$29<span>/<?php echo t('mes', 'month'); ?></span></div>
                        </div>
                        <div class="plan-features">
                            <ul>
                                <li><i class="fas fa-check"></i> <?php echo t('Importaciones ilimitadas', 'Unlimited imports'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Integración con Spotify', 'Spotify integration'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Soporte prioritario', 'Priority support'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('1 Dominio/Sitio Web', '1 Domain/Website'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Actualizaciones', 'Updates'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Estadística detallada', 'Detailed statistics'); ?></li>
                                <li><i class="fas fa-check"></i> <?php echo t('Widget Spotify', 'Spotify Widget'); ?></li>
                            </ul>
                        </div>
                        <div class="plan-button">
                            <button class="btn-select"><?php echo t('Seleccionar', 'Select'); ?></button>
                        </div>
                    </div>
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

        // Funciones del modal de planes
        function openPlansModal() {
            const hasActiveInvoice = <?php echo $has_active_invoice ? 'true' : 'false'; ?>;
            const currentPlan = '<?php echo $subscriber_data['plan_type'] ?? 'free'; ?>';
            const planCards = document.querySelectorAll('.plan-card');
            
            planCards.forEach(card => {
                const planType = card.getAttribute('data-plan');
                
                if (!hasActiveInvoice) {
                    // Si no hay factura, mostrar todos los planes
                    card.style.display = 'block';
                } else if (currentPlan === 'free') {
                    // Si tiene factura y es free, mostrar solo premium y enterprise
                    card.style.display = (planType === 'premium' || planType === 'enterprise') ? 'block' : 'none';
                } else if (currentPlan === 'premium') {
                    // Si es premium, mostrar solo enterprise
                    card.style.display = (planType === 'enterprise') ? 'block' : 'none';
                } else {
                    // Si es enterprise, ocultar todos
                    card.style.display = 'none';
                }
            });
            
            document.getElementById('plansModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closePlansModal() {
            document.getElementById('plansModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function selectPlan(planId) {
            // Cerrar el modal
            closePlansModal();
            
            // Obtener el idioma actual
            const currentLang = '<?php echo $currentLang; ?>';
            
            // Redirigir al checkout con el plan seleccionado manteniendo el idioma
            window.location.href = `checkout.php?plan=${planId}&renewal=true&lang=${currentLang}`;
        }

        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('plansModal');
            if (event.target === modal) {
                closePlansModal();
            }
        }

        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePlansModal();
            }
        });
    </script>

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

        /* Estilos del Modal */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.2);
        }

        .modal-body {
            padding: 30px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .plan-card {
            background: #f8f9fa;
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .plan-card.premium {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
        }

        .plan-card.enterprise {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
        }

        .plan-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(45deg, #f59e0b, #d97706);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .plan-header h3 {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #059669;
            margin-bottom: 20px;
        }

        .plan-price span {
            font-size: 1rem;
            color: #6b7280;
            font-weight: normal;
        }

        .plan-features ul {
            list-style: none;
            padding: 0;
            margin: 0 0 25px 0;
        }

        .plan-features li {
            padding: 8px 0;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .plan-features i {
            color: #10b981;
            font-size: 0.9rem;
        }

        .plan-button {
            margin-top: auto;
        }

        .btn-select {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-select:hover {
            background: linear-gradient(45deg, #5a67d8, #6b46c1);
            transform: translateY(-2px);
        }

        .current-plan {
            background: #6b7280;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
            width: 100%;
        }

        @media (max-width: 768px) {
            .modal-content {
                margin: 2% auto;
                width: 95%;
            }
            
            .plans-grid {
                grid-template-columns: 1fr;
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
        
        /* MENÚ MÓVIL - ESTILOS */
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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
        }

        .nav-link:hover {
            color: #059669;
        }

        .nav-toggle {
            display: none;
            flex-direction: column;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            z-index: 10000;
        }

        .hamburger {
            width: 25px;
            height: 3px;
            background: #059669;
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 2px;
        }

        .nav-toggle.active .hamburger:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }

        .nav-toggle.active .hamburger:nth-child(2) {
            opacity: 0;
        }

        .nav-toggle.active .hamburger:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }

        @media (max-width: 768px) {
            .nav-toggle {
                display: flex !important;
                background: #f0f0f0 !important;
                border: 2px solid #059669 !important;
            }
            
            .nav-menu {
                position: fixed !important;
                top: 0 !important;
                right: -100% !important;
                width: 100% !important;
                height: 100vh !important;
                background: rgba(255, 255, 255, 0.98) !important;
                backdrop-filter: blur(10px) !important;
                flex-direction: column !important;
                justify-content: center !important;
                align-items: center !important;
                gap: 2rem !important;
                z-index: 9999 !important;
                padding-top: 80px !important;
                overflow-y: auto !important;
            }
            
            .nav-menu.active {
                right: 0 !important;
            }
            
            .nav-link {
                font-size: 1.2rem !important;
                padding: 1rem 0 !important;
                text-align: center !important;
                width: 100% !important;
                border-bottom: 1px solid #e5e7eb !important;
            }
            
            .nav-link:last-child {
                border-bottom: none !important;
            }
        }
    </style>
    
    <!-- Script del menú móvil -->
    <script>
        function initMobileMenu() {
            console.log('=== INICIANDO MENÚ MÓVIL EN BILLING ===');
            
            const navToggle = document.getElementById('nav-toggle');
            const navMenu = document.getElementById('nav-menu');
            
            console.log('navToggle encontrado:', !!navToggle);
            console.log('navMenu encontrado:', !!navMenu);
            
            if (navToggle && navMenu) {
                console.log('Agregando event listener al botón hamburguesa');
                
                navToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Botón hamburguesa clickeado!');
                    navMenu.classList.toggle('active');
                    navToggle.classList.toggle('active');
                    console.log('Menú activo:', navMenu.classList.contains('active'));
                });
                
                // Cerrar menú al hacer clic en enlaces (excluyendo selector de idioma)
                const navLinks = navMenu.querySelectorAll('.nav-link:not(.language-dropdown button)');
                console.log('Enlaces encontrados:', navLinks.length);
                navLinks.forEach(function(link, index) {
                    console.log('Agregando listener al enlace', index, 'href:', link.href);
                    link.onclick = function(e) {
                        console.log('Enlace clickeado:', link.href);
                        console.log('Cerrando menú...');
                        navMenu.classList.remove('active');
                        navToggle.classList.remove('active');
                        console.log('Menú cerrado, permitiendo navegación');
                        return true; // Permitir navegación
                    };
                });
                
                console.log('Menú móvil inicializado correctamente');
            } else {
                console.error('No se encontraron los elementos del menú móvil');
            }
        }
        
        // Inicializar menú móvil
        setTimeout(function() {
            try {
                console.log('=== INICIALIZANDO MENÚ MÓVIL CON TIMEOUT ===');
                initMobileMenu();
            } catch (error) {
                console.error('Error al inicializar menú móvil:', error);
            }
        }, 1000);
    </script>
</body>
</html>
