<?php
// Definir constante para acceso a la API
define('API_ACCESS', true);

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar sesión
session_start();
if (!isset($_SESSION['subscriber_id'])) {
    header('Location: ../index.php');
    exit;
}

// Detectar idioma del navegador
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
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

$subscriber_id = $_SESSION['subscriber_id'];
$license_key = $_SESSION['license_key'] ?? '';

// Obtener información del suscriptor
$subscriber = $pdo->prepare("SELECT * FROM subscribers WHERE id = ?");
$subscriber->execute([$subscriber_id]);
$subscriber_data = $subscriber->fetch(PDO::FETCH_ASSOC);

// Obtener información de la licencia
$license = $pdo->prepare("SELECT * FROM licenses WHERE subscriber_id = ? AND license_key = ?");
$license->execute([$subscriber_id, $license_key]);
$license_data = $license->fetch(PDO::FETCH_ASSOC);

// Obtener ciclo activo actual
$current_billing_cycle = $pdo->prepare("
    SELECT * FROM billing_cycles 
    WHERE subscriber_id = ? AND status = 'paid' AND is_active = 1
");
$current_billing_cycle->execute([$subscriber_id]);
$cycle_data = $current_billing_cycle->fetch(PDO::FETCH_ASSOC);

// Obtener ciclo de facturación más reciente del suscriptor
$current_billing_cycle = $pdo->prepare("
    SELECT * FROM billing_cycles 
    WHERE subscriber_id = ? AND status = 'paid'
     ORDER BY created_at DESC 
    LIMIT 1
");
$current_billing_cycle->execute([$subscriber_id]);
$billing_cycle_data = $current_billing_cycle->fetch(PDO::FETCH_ASSOC);

// Verificar si hay factura (cualquier registro en billing_cycles)
$has_active_invoice = false;
if ($billing_cycle_data) {
    $has_active_invoice = true;
}

// Verificar si viene de un pago exitoso
$payment_success = isset($_GET['payment_success']) && $_GET['payment_success'] === 'true';
$payment_method = $_GET['method'] ?? '';


// Obtener historial de ciclos (últimos 6)
$billing_cycle_history = $pdo->prepare("
    SELECT * FROM billing_cycles 
    WHERE subscriber_id = ? AND license_key = ? 
    ORDER BY cycle_start_date DESC 
    LIMIT 6
");
$billing_cycle_history->execute([$subscriber_id, $license_key]);
$history_data = $billing_cycle_history->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas de uso diario (últimos 30 días)
$daily_stats = $pdo->prepare("
    SELECT date, requests_count, requests_successful, requests_failed 
    FROM usage_stats 
     WHERE subscriber_id = ? 
    ORDER BY date DESC 
    LIMIT 30
");
$daily_stats->execute([$subscriber_id]);
$daily_data = $daily_stats->fetchAll(PDO::FETCH_ASSOC);


// Calcular días restantes del ciclo actual
$days_remaining = 0;
if ($cycle_data && $cycle_data['cycle_end_date']) {
    $end_date = new DateTime($cycle_data['cycle_end_date']);
    $today = new DateTime();
    $days_remaining = $today->diff($end_date)->days;
    if ($today > $end_date) {
        $days_remaining = 0; // Ciclo expirado
    }
}

// Calcular porcentajes de uso (solo para importaciones, las llamadas API no tienen límite)
$sync_percentage = 0;
if ($license_data && $cycle_data) {
    $sync_percentage = ($cycle_data['sync_count'] / $license_data['usage_limit']) * 100;
}


// Obtener planes de suscripción desde la base de datos
$subscription_plans = [];
try {
    $plans_stmt = $pdo->prepare("SELECT * FROM subscription_plans ORDER BY price ASC");
    $plans_stmt->execute();
    $plans_data = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($plans_data as $plan) {
        $subscription_plans[$plan['plan_type']] = [
            'name' => $plan['plan_name'],
            'price' => $plan['price']
        ];
    }
} catch (Exception $e) {
    // Fallback a precios por defecto si hay error
    $subscription_plans = [
        'free' => ['name' => 'Gratuito', 'price' => 0],
        'premium' => ['name' => 'Premium', 'price' => 22],
        'enterprise' => ['name' => '+Spotify', 'price' => 29]
    ];
}
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Dashboard - dwoosync', 'Dashboard - dwoosync'); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo t('Panel de control de dwoosync. Gestiona tu suscripción, licencias y configuración del plugin Discogs-WooCommerce.', 'dwoosync control panel. Manage your subscription, licenses and Discogs-WooCommerce plugin configuration.'); ?>">
    <meta name="keywords" content="<?php echo t('dashboard dwoosync, panel control, gestión suscripción, licencias plugin', 'dwoosync dashboard, control panel, subscription management, plugin licenses'); ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?php echo t('Dashboard - dwoosync', 'Dashboard - dwoosync'); ?>">
    <meta property="og:description" content="<?php echo t('Panel de control de dwoosync para gestionar suscripciones y licencias.', 'dwoosync control panel for managing subscriptions and licenses.'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://dwoosync.com/subscribe/pages/dashboard.php">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://dwoosync.com/subscribe/pages/dashboard.php">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="../assets/css/mobile-menu.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(45deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: #333;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-logout {
            background: linear-gradient(45deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-logout:hover {
            background: linear-gradient(45deg, #dc2626, #b91c1c);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .dashboard-header h1 {
            background: linear-gradient(45deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            font-size: 2.5rem;
        }
        
        .dashboard-header p {
            color: #666;
            font-size: 1.2rem;
        }
        
        .welcome-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 20px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
        }
        
        .welcome-info h2 {
            color: #333;
            font-size: 1.5rem;
        }
        
        .welcome-info .plan-badge {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .plan-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .plan-badge.free {
            background: linear-gradient(45deg, #6b7280, #4b5563);
        }
        
        .btn-upgrade {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-upgrade:hover {
            background: linear-gradient(45deg, #d97706, #b45309);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3);
        }
        
        .plan-info {
            text-align: center;
            padding: 20px;
        }
        
        .plan-badge-large {
            display: inline-block;
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 15px 30px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        
        .plan-badge-large.free {
            background: linear-gradient(45deg, #6b7280, #4b5563);
            box-shadow: 0 8px 20px rgba(107, 114, 128, 0.3);
        }
        
        .plan-badge-large.premium {
            background: linear-gradient(45deg, #10b981, #059669);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .plan-badge-large.enterprise {
            background: linear-gradient(45deg, #8b5cf6, #7c3aed);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
        }
        
        .plan-description {
            color: #6b7280;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .btn-upgrade-spotify {
            background: linear-gradient(45deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-upgrade-spotify:hover {
            background: linear-gradient(45deg, #7c3aed, #6d28d9);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(139, 92, 246, 0.4);
        }
        
        .sync-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .sync-stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 15px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .sync-stat-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #059669;
        }
        
        .sync-stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 8px;
            background: linear-gradient(45deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sync-stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .sync-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        
        .sync-stats h4 {
            color: #1f2937;
            margin: 0 0 15px 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .sync-count-simple {
            margin-top: 15px;
            padding: 10px 15px;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #bae6fd;
            border-radius: 8px;
            color: #0c4a6e;
            font-size: 0.95rem;
            text-align: center;
        }
        
        .sync-info {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border: 1px solid #93c5fd;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .sync-info p {
            margin: 0;
            color: #1e40af;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 10px 0;
            transition: all 0.3s ease;
            border-left: 4px solid #e2e8f0;
        }
        
        .payment-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-date {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }
        
        .payment-id {
            font-size: 0.8rem;
            color: #6b7280;
            font-family: 'Courier New', monospace;
        }
        
        .payment-details {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .payment-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #10b981;
        }
        
        .payment-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .payment-status.completed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .payment-status.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .payment-status.failed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .payment-method {
            font-size: 0.8rem;
            color: #6b7280;
            text-transform: capitalize;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .dashboard-card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cycle-info {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .cycle-info h4 {
            font-size: 1.2rem;
            margin-bottom: 15px;
        }
        
        .cycle-dates {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .cycle-date {
            text-align: center;
            padding: 10px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }
        
        .cycle-date-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .cycle-date-value {
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        .days-remaining {
            text-align: center;
            font-size: 1.3rem;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            transition: transform 0.3s ease;
        }
        
        .stat-item:hover {
            transform: scale(1.05);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: bold;
            background: linear-gradient(45deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .progress-container {
            margin: 20px 0;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .progress-label {
            font-weight: 600;
            color: #333;
        }
        
        .progress-percentage {
            font-weight: bold;
            color: #059669;
        }
        
        .progress-bar {
            width: 100%;
            height: 25px;
            background: #e5e7eb;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 15px;
            transition: width 0.5s ease;
            position: relative;
        }
        
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            border-left: 4px solid #f59e0b;
        }
        
        .danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            border-left: 4px solid #dc2626;
        }
        
        .success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            border-left: 4px solid #10b981;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 10px 0;
            transition: background 0.3s ease;
        }
        
        .history-item:hover {
            background: #e9ecef;
        }
        
        .history-period {
            font-weight: 600;
            color: #333;
        }
        
        .history-stats {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        
        .recent-activity {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
            gap: 15px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .activity-sync {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .activity-api {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .activity-info {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 2px;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: #666;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-align: center;
            white-space: nowrap;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(45deg, #6b7280, #4b5563);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 10px 20px rgba(107, 114, 128, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #dc2626, #b91c1c);
        }
        
        .btn-danger:hover {
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(45deg, #10b981, #059669);
        }
        
        .btn-success:hover {
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }
        
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
        
        
        
        .footer {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            color: #333;
            text-align: center;
            padding: 30px;
            margin-top: 50px;
        }
        
        .chart-container {
            height: 300px;
            margin: 20px 0;
        }
        
        .no-data {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 40px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        .info-value {
            color: #666;
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #e9ecef;
            word-break: break-all;
            max-width: 300px;
            text-align: right;
        }
        
        .license-key-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .license-key-value {
            color: #059669;
            font-family: 'Courier New', monospace;
            background: #f0f4ff;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            word-break: break-all;
            flex: 1;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .btn-copy {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        
        .btn-copy:hover {
            background: linear-gradient(45deg, #059669, #047857);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }
        
        .btn-copy.copied {
            background: linear-gradient(45deg, #f59e0b, #d97706);
        }
        
        .status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .cycle-dates {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .nav {
                flex-direction: column;
                gap: 20px;
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
        
        /* MENÚ MÓVIL - ESTILOS FINALES */
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
            transition: color 0.3s ease;
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
        
        /* Estilos específicos para el botón de idioma */
        .language-btn {
            background: #1db954 !important;
            color: white !important;
            padding: 8px 16px !important;
            border-radius: 6px !important;
            border: none !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            gap: 5px !important;
            position: relative !important;
            z-index: 10002 !important;
            font-size: 14px !important;
            font-weight: 500 !important;
            transition: background-color 0.3s ease !important;
        }
        
        .language-btn:hover {
            background: #16a34a !important;
        }
        
        .language-btn:active {
            background: #15803d !important;
        }
        
        .language-btn:focus {
            outline: 2px solid #059669 !important;
            outline-offset: 2px !important;
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
                transition: right 0.3s ease !important;
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
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="dashboard.php?lang=<?php echo $currentLang; ?>" style="text-decoration: none; color: inherit;">
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
                    <a href="dashboard.php" class="nav-link">🏠 <?php echo t('Inicio', 'Home'); ?></a>
                    <a href="billing.php" class="nav-link">💳 <?php echo t('Facturación', 'Billing'); ?></a>
                    <a href="tutorials.php" class="nav-link">🎥 <?php echo t('Tutoriales', 'Tutorials'); ?></a>
                    <a href="plugin-config.php" class="nav-link">⚙️ <?php echo t('Configurar Plugin', 'Configure Plugin'); ?></a>
                    
                    <!-- Selector de idioma -->
                    <div class="language-dropdown" style="position: relative; margin-left: 10px;">
                        <button class="language-btn" style="background: #1db954; color: white; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <?php if ($isEnglish): ?>
                                <span style="font-size: 1.2em;">🇬🇧</span>
                            <?php else: ?>
                                <span style="font-size: 1.2em;">🇪🇸</span>
                            <?php endif; ?>
                            <span><?php echo $isEnglish ? 'EN' : 'ES'; ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.8em;"></i>
                        </button>
                        <div class="language-menu" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10001; min-width: 140px;">
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
                            <a href="profile.php" class="user-menu-item">
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

    <main style="padding-top: 100px; min-height: 100vh; background: #f8fafc;">
        <div class="container">
            <div class="card">
                    <div class="plan-container">
                        <div class="plan-badge <?php echo ($subscriber_data['plan_type'] ?? 'free') === 'free' ? 'free' : ''; ?>">
                            <?php 
                            $plan_type = $subscriber_data['plan_type'] ?? 'free';
                            echo $plan_type === 'enterprise' ? '+Spotify' : ucfirst($plan_type);
                            ?>
                        </div>
            </div>

                <!-- Cuadros de información principal -->
                <div class="grid grid-3" style="margin-top: 2rem;">
                    <!-- Cuadro 1: Importaciones realizadas -->
                    <div style="text-align: center; padding: 1.5rem; background: #f9fafb; border-radius: 8px;">
                        <div style="font-size: 2.5rem; color: #059669; margin-bottom: 0.5rem;">
                            <i class="fas fa-sync-alt"></i>
                </div>
                        <h3 style="color: #1f2937; margin-bottom: 0.5rem;"><?php echo t('Importaciones Realizadas', 'Imports Completed'); ?></h3>
                        <div style="font-size: 2rem; font-weight: bold; color: #059669; margin-bottom: 0.5rem;">
                            <?php echo $cycle_data['sync_count'] ?? 0; ?>
                </div>
                        <p style="color: #6b7280; margin: 0; font-size: 0.9rem;">
                            <?php if (($subscriber_data['plan_type'] ?? 'free') === 'free'): ?>
                                <?php echo t('de 10 disponibles', 'of 10 available'); ?>
                            <?php else: ?>
                                <?php echo t('ilimitadas', 'unlimited'); ?>
                            <?php endif; ?>
                        </p>
                </div>

                    <!-- Cuadro 2: Fecha de renovación -->
                    <div style="text-align: center; padding: 1.5rem; background: #f9fafb; border-radius: 8px;">
                        <div style="font-size: 2.5rem; color: #059669; margin-bottom: 0.5rem;">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 style="color: #1f2937; margin-bottom: 0.5rem;"><?php echo t('Estado de Suscripción', 'Subscription Status'); ?></h3>
                        <div style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">
                        <?php 
                            if (($subscriber_data['plan_type'] ?? 'free') === 'free') {
                                echo '<span style="color: #6b7280;">' . t('Plan Gratuito', 'Free Plan') . '</span>';
                            } else {
                                // Para planes pagos, verificar el estado de la factura
                                if ($billing_cycle_data) {
                                    $invoice_status = $billing_cycle_data['status'];
                                    
                                    if ($invoice_status === 'paid') {
                                        // Factura pagada - verificar si el ciclo está vencido
                                        if ($cycle_data && $cycle_data['cycle_end_date']) {
                                            $today = new DateTime();
                                            $cycle_end = new DateTime($cycle_data['cycle_end_date']);
                                            $grace_end = clone $cycle_end;
                                            $grace_end->add(new DateInterval('P3D')); // +3 días de gracia
                                            
                                            if ($today > $grace_end) {
                                                echo '<span style="color: #dc2626;">' . t('Suspendido', 'Suspended') . '</span>';
                                            } elseif ($today > $cycle_end) {
                                                echo '<span style="color: #f59e0b;">' . t('Período de Gracia', 'Grace Period') . '</span>';
                                            } else {
                                                $days_left = $today->diff($cycle_end)->days;
                                                if ($days_left <= 3) {
                                                    echo '<span style="color: #f59e0b;">' . t('Próximo a Vencer', 'Expiring Soon') . '</span>';
                                                } else {
                                                    echo '<span style="color: #059669;">' . t('Activo', 'Active') . '</span>';
                                                }
                                            }
                                        } else {
                                            echo '<span style="color: #059669;">' . t('Activo', 'Active') . '</span>';
                                        }
                                    } elseif ($invoice_status === 'pending') {
                                        // Factura pendiente - verificar si está vencida
                                        $today = new DateTime();
                                        $due_date = new DateTime($billing_cycle_data['due_date']);
                                        
                                        if ($today > $due_date) {
                                            echo '<span style="color: #dc2626;">' . t('Vencido', 'Expired') . '</span>';
                                        } else {
                                            echo '<span style="color: #f59e0b;">' . t('Pendiente de Pago', 'Pending Payment') . '</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #dc2626;">' . t('Inactivo', 'Inactive') . '</span>';
                                    }
                                } else {
                                    // Buscar cualquier factura del suscriptor
                                    $any_invoice = $pdo->prepare("
                                        SELECT status, due_date FROM billing_cycles 
                                        WHERE subscriber_id = ? 
                                        ORDER BY created_at DESC 
                                        LIMIT 1
                                    ");
                                    $any_invoice->execute([$subscriber_id]);
                                    $any_invoice_data = $any_invoice->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($any_invoice_data) {
                                        if ($any_invoice_data['status'] === 'pending') {
                                            $today = new DateTime();
                                            $due_date = new DateTime($any_invoice_data['due_date']);
                                            
                                            if ($today > $due_date) {
                                                echo '<span style="color: #dc2626;">' . t('Vencido', 'Expired') . '</span>';
                                            } else {
                                                echo '<span style="color: #f59e0b;">' . t('Pendiente de Pago', 'Pending Payment') . '</span>';
                                            }
                                        } else {
                                            echo '<span style="color: #dc2626;">' . t('Inactivo', 'Inactive') . '</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #dc2626;">' . t('Sin Factura', 'No Invoice') . '</span>';
                                    }
                                }
                            }
                        ?>
                    </div>
                        <p style="color: #6b7280; margin: 0; font-size: 0.9rem;">
                            <?php 
                            if (($subscriber_data['plan_type'] ?? 'free') === 'free') {
                                echo t('Sin renovación requerida', 'No renewal required');
                            } else {
                                // Usar la misma lógica mejorada para el mensaje descriptivo
                                if ($billing_cycle_data) {
                                    $invoice_status = $billing_cycle_data['status'];
                                    
                                    if ($invoice_status === 'paid') {
                                        // Factura pagada - mostrar información del ciclo
                                        if ($cycle_data && $cycle_data['cycle_end_date']) {
                                            $today = new DateTime();
                                            $cycle_end = new DateTime($cycle_data['cycle_end_date']);
                                            $grace_end = clone $cycle_end;
                                            $grace_end->add(new DateInterval('P3D'));
                                            
                                            if ($today > $grace_end) {
                                                echo t('Suscripción expirada', 'Subscription expired');
                                            } elseif ($today > $cycle_end) {
                                                $grace_days = $today->diff($grace_end)->days;
                                                echo $grace_days . ' ' . t('días de gracia restantes', 'grace days remaining');
                                            } else {
                                                $days_left = $today->diff($cycle_end)->days;
                                                if ($days_left > 0) {
                                                    echo t('Vence el', 'Expires on') . ' ' . $cycle_end->format('d/m/Y');
                                                } else {
                                                    echo t('Vence hoy', 'Expires today');
                                                }
                                            }
                                        } else {
                                            echo t('Suscripción activa', 'Active subscription');
                                        }
                                    } elseif ($invoice_status === 'pending') {
                                        // Factura pendiente - mostrar fecha de vencimiento
                                        $today = new DateTime();
                                        $due_date = new DateTime($billing_cycle_data['due_date']);
                                        
                                        if ($today > $due_date) {
                                            echo t('Vence el', 'Expires on') . ' ' . $due_date->format('d/m/Y') . ' - ' . t('Debe pagar o enviar comprobante a soporte', 'Must pay or send receipt to support');
                                        } else {
                                            $days_left = $today->diff($due_date)->days;
                                            echo t('Vence el', 'Expires on') . ' ' . $due_date->format('d/m/Y') . ' (' . $days_left . ' ' . t('días', 'days') . ')';
                                        }
                                    } else {
                                        echo t('Contacta soporte para reactivar', 'Contact support to reactivate');
                                    }
                                } else {
                                    // Buscar cualquier factura del suscriptor para el mensaje descriptivo
                                    $any_invoice_msg = $pdo->prepare("
                                        SELECT status, due_date FROM billing_cycles 
                                        WHERE subscriber_id = ? 
                                        ORDER BY created_at DESC 
                                        LIMIT 1
                                    ");
                                    $any_invoice_msg->execute([$subscriber_id]);
                                    $any_invoice_msg_data = $any_invoice_msg->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($any_invoice_msg_data) {
                                        if ($any_invoice_msg_data['status'] === 'pending') {
                                            $today = new DateTime();
                                            $due_date = new DateTime($any_invoice_msg_data['due_date']);
                                            
                                            if ($today > $due_date) {
                                                echo t('Vence el', 'Expires on') . ' ' . $due_date->format('d/m/Y') . ' - ' . t('Debe pagar o enviar comprobante a soporte', 'Must pay or send receipt to support');
                                            } else {
                                                $days_left = $today->diff($due_date)->days;
                                                echo t('Vence el', 'Expires on') . ' ' . $due_date->format('d/m/Y') . ' (' . $days_left . ' ' . t('días', 'days') . ')';
                                            }
                                        } else {
                                            echo t('Contacta soporte para reactivar', 'Contact support to reactivate');
                                        }
                                    } else {
                                        echo t('Sin factura generada', 'No invoice generated');
                                    }
                                }
                            }
                            ?>
                        </p>
                        <?php if (($subscriber_data['plan_type'] ?? 'free') !== 'enterprise'): ?>
                            <div style="text-align: center; margin-top: 1rem;">
                                <button onclick="openPlansModal()" class="btn-upgrade" style="background: linear-gradient(45deg, #f59e0b, #d97706); color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; text-decoration: none; transition: all 0.3s ease; border: none; cursor: pointer;"><?php echo t('Mejorar Plan', 'Upgrade Plan'); ?></button>
                </div>
                        <?php endif; ?>
            </div>

                    <!-- Cuadro 3: Datos de la cuenta -->
                    <div style="text-align: center; padding: 1.5rem; background: #f9fafb; border-radius: 8px;">
                        <div style="font-size: 2.5rem; color: #059669; margin-bottom: 0.5rem;">
                            <i class="fas fa-user-circle"></i>
                    </div>
                        <h3 style="color: #1f2937; margin-bottom: 1rem;"><?php echo t('Datos de la Cuenta', 'Account Data'); ?></h3>
                        <div style="text-align: left; margin-bottom: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span style="color: #6b7280; font-weight: 500;"><?php echo t('Nombre:', 'Name:'); ?></span>
                                <span style="color: #1f2937; font-weight: bold;">
                                    <?php echo htmlspecialchars($subscriber_data['first_name'] ?? 'N/A'); ?>
                                        </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                <span style="color: #6b7280; font-weight: 500;"><?php echo t('Dominio:', 'Domain:'); ?></span>
                                <span style="color: #1f2937; font-weight: bold;">
                                    <?php echo htmlspecialchars($subscriber_data['domain'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="color: #6b7280; font-weight: 500;"><?php echo t('Licencia:', 'License:'); ?></span>
                                <span style="color: #1f2937; font-weight: bold; font-family: monospace; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($license_key ?? 'N/A'); ?>
                                </span>
                                </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Alertas de Estado -->
                                        <?php 
        // Verificar estado del ciclo
        $cycle_status = 'active';
        $grace_days_remaining = 0;
        
        if ($cycle_data) {
            $today = new DateTime();
            $cycle_end = new DateTime($cycle_data['cycle_end_date']);
            $grace_end = clone $cycle_end;
            $grace_end->add(new DateInterval('P3D')); // +3 días
            
            if ($today > $grace_end) {
                $cycle_status = 'expired';
            } elseif ($today > $cycle_end) {
                $cycle_status = 'grace_period';
                $grace_days_remaining = $today->diff($grace_end)->days;
            } elseif ($today->diff($cycle_end)->days <= 3) {
                $cycle_status = 'warning';
            }
        }
        ?>
        
        <?php 
        // Verificar si hay factura pendiente
        $has_pending_invoice = false;
        if ($billing_cycle_data && $billing_cycle_data['status'] === 'pending') {
            $has_pending_invoice = true;
        }
        
        // Verificar días de expiración del ciclo
        $show_warning = false;
        $warning_message = '';
        
        if ($has_pending_invoice) {
            $show_warning = true;
            $warning_message = 'Tienes una factura pendiente de pago.';
        } elseif ($cycle_data && $cycle_data['cycle_end_date']) {
            $today = new DateTime();
            $cycle_end = new DateTime($cycle_data['cycle_end_date']);
            $days_remaining = $today->diff($cycle_end)->days;
            
            if ($today <= $cycle_end && $days_remaining <= 7) {
                $show_warning = true;
                $warning_message = t('Tu licencia expira en', 'Your license expires in') . ' ' . $days_remaining . ' ' . t('días.', 'days.');
            }
        }
        ?>
        
        <?php if ($show_warning): ?>
            <div class="warning">
                ⚠️ <strong><?php echo t('Advertencia:', 'Warning:'); ?></strong> <?php echo $warning_message; ?> 
                <a href="billing.php" style="color: #f59e0b; text-decoration: underline; font-weight: bold;"><?php echo t('Pagar acá', 'Pay here'); ?></a> <?php echo t('para continuar usando el servicio.', 'to continue using the service.'); ?>
                                </div>
        <?php elseif ($cycle_status === 'grace_period'): ?>
            <div class="warning" style="background: linear-gradient(135deg, #fef3c7, #fde68a); border-left: 4px solid #f59e0b;">
                ⏰ <strong><?php echo t('Período de Gracia:', 'Grace Period:'); ?></strong> <?php echo t('Tu suscripción expiró pero tienes', 'Your subscription expired but you have'); ?> <?php echo $grace_days_remaining; ?> <?php echo t('días de gracia restantes.', 'grace days remaining.'); ?> 
                <a href="checkout.php?plan=<?php echo $subscriber_data['plan_type']; ?>&renewal=true" style="color: #f59e0b; text-decoration: underline; font-weight: bold;"><?php echo t('Renueva ahora', 'Renew now'); ?></a> <?php echo t('para evitar la suspensión.', 'to avoid suspension.'); ?>
                            </div>
        <?php elseif ($cycle_status === 'expired'): ?>
            <div class="danger">
                🚨 <strong><?php echo t('Suspendido:', 'Suspended:'); ?></strong> <?php echo t('Tu período de gracia ha expirado.', 'Your grace period has expired.'); ?> 
                <a href="checkout.php?plan=<?php echo $subscriber_data['plan_type']; ?>&renewal=true" style="color: #dc2626; text-decoration: underline; font-weight: bold;"><?php echo t('Renueva inmediatamente', 'Renew immediately'); ?></a> <?php echo t('para reactivar el servicio.', 'to reactivate the service.'); ?>
                                </div>
        <?php endif; ?>

        <!-- Alerta para cuentas inactivas -->
        <?php if (isset($_SESSION['account_status']) && $_SESSION['account_status'] !== 'active'): ?>
            <div class="danger">
                🚨 <strong><?php echo t('Cuenta Inactiva:', 'Inactive Account:'); ?></strong> <?php echo t('Tu cuenta no está activa (Estado:', 'Your account is not active (Status:'); ?> <?php echo ucfirst($_SESSION['account_status']); ?>). 
                                    <?php 
                $current_plan = $subscriber_data['plan_type'] ?? 'free';
                if ($current_plan === 'free'): 
                        ?>
                    <a href="plans.php" style="color: #dc2626; text-decoration: underline; font-weight: bold;"><?php echo t('Haz clic aquí para elegir un plan', 'Click here to choose a plan'); ?></a> <?php echo t('y activar tu cuenta.', 'and activate your account.'); ?>
                <?php else: ?>
                    <a href="checkout.php?plan=<?php echo $current_plan; ?>&renewal=true" style="color: #dc2626; text-decoration: underline; font-weight: bold;"><?php echo t('Haz clic aquí para Pagar', 'Click here to Pay'); ?></a> <?php echo t('y reactivar tu cuenta. Si ya realizaste el pago envía el comprobante de pago a', 'and reactivate your account. If you already made the payment send the payment receipt to'); ?> <a href="mailto:contact@dwoosync.com" style="color: #dc2626; text-decoration: underline; font-weight: bold;">contact@dwoosync.com</a>
                <?php endif; ?>
                                </div>
        <?php endif; ?>

        <!-- Alerta de límite de uso para plan Free -->
        <?php if (($subscriber_data['plan_type'] ?? 'free') === 'free' && $cycle_data && $sync_percentage > 80): ?>
            <div class="warning">
                ⚠️ <strong><?php echo t('Límite de Uso:', 'Usage Limit:'); ?></strong> <?php echo t('Has utilizado el', 'You have used'); ?> <?php echo round($sync_percentage); ?>% <?php echo t('de tus importaciones del ciclo actual', 'of your imports for the current cycle'); ?> (<?php echo $cycle_data['sync_count']; ?>/10). 
                <a href="https://www.dwoosync.com" target="_blank" style="color: #f59e0b; text-decoration: underline; font-weight: bold;"><?php echo t('Mejora tu plan', 'Upgrade your plan'); ?></a> <?php echo t('para obtener importaciones ilimitadas.', 'to get unlimited imports.'); ?>
                            </div>
        <?php endif; ?>



        <div class="dashboard-grid">
            <!-- Información del Ciclo Actual -->
            <div class="dashboard-card">
                <h3>📅 <?php echo t('Ciclo de Suscripción Actual', 'Current Subscription Cycle'); ?></h3>
                <?php if ($cycle_data): ?>
                    <div class="cycle-info">
                        <h4><?php echo t('Período Activo', 'Active Period'); ?></h4>
                        <div class="cycle-dates">
                            <div class="cycle-date">
                                <div class="cycle-date-label"><?php echo t('Inicio del Ciclo', 'Cycle Start'); ?></div>
                                <div class="cycle-date-value"><?php echo date('d/m/Y', strtotime($cycle_data['cycle_start_date'])); ?></div>
                                </div>
                            <div class="cycle-date">
                                <div class="cycle-date-label"><?php echo t('Fin del Ciclo', 'Cycle End'); ?></div>
                                <div class="cycle-date-value"><?php echo date('d/m/Y', strtotime($cycle_data['cycle_end_date'])); ?></div>
                                </div>
                </div>
                        <div class="days-remaining">
                            <?php if ($days_remaining > 0): ?>
                                <?php echo $days_remaining; ?> <?php echo t('días restantes', 'days remaining'); ?>
                <?php else: ?>
                                <?php echo t('Ciclo expirado', 'Cycle expired'); ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="sync-count-simple">
                                <?php echo t('Sincronizaciones realizadas:', 'Synchronizations performed:'); ?> <strong><?php echo number_format($cycle_data['sync_count']); ?></strong>
                        </div>
                        </div>
                <?php else: ?>
                    <div class="no-data">
                        <?php echo t('No hay ciclo activo. Contacta soporte.', 'No active cycle. Contact support.'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Plan Actual -->
            <div class="dashboard-card">
                <h3>📊 <?php echo t('Plan Actual', 'Current Plan'); ?></h3>
                <div class="plan-info">
                    <div class="plan-badge-large <?php echo $subscriber_data['plan_type'] ?? 'free'; ?>">
                                    <?php 
                        $plan_type = $subscriber_data['plan_type'] ?? 'free';
                        echo $plan_type === 'enterprise' ? '+Spotify' : ucfirst($plan_type);
                        ?>
                        </div>
                    
                    <div class="plan-description">
                        <?php 
                        switch($plan_type) {
                            case 'free':
                                echo t('Plan gratuito con funcionalidades básicas e importaciones ilimitadas', 'Free plan with basic features and unlimited imports');
                                break;
                            case 'premium':
                                echo t('Plan premium con soporte prioritario e importaciones ilimitadas', 'Premium plan with priority support and unlimited imports');
                                break;
                            case 'enterprise':
                                echo t('Plan +Spotify con integración avanzada e importaciones ilimitadas', '+Spotify plan with advanced integration and unlimited imports');
                                break;
                            default:
                                echo t('Plan personalizado', 'Custom plan');
                        }
                        ?>
                </div>
                            
                    <?php if ($plan_type !== 'enterprise'): ?>
                        <div style="margin-top: 20px;">
                            <button onclick="openPlansModal()" class="btn-upgrade-spotify">
                                <i class="fas fa-arrow-up"></i> <?php echo t('Mejorar a Plan +Spotify', 'Upgrade to +Spotify Plan'); ?>
                            </button>
                                </div>
                    <?php else: ?>
                        <div style="margin-top: 20px; text-align: center; color: #10b981; font-weight: 600;">
                            <i class="fas fa-crown"></i> <?php echo t('¡Tienes el plan más avanzado!', 'You have the most advanced plan!'); ?>
            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                            
            <!-- Información de la Licencia -->
            <div class="dashboard-card">
                <h3>🔑 <?php echo t('Información de Licencia', 'License Information'); ?></h3>
                <?php if ($license_data): ?>
                <div class="info-item">
                        <span class="info-label"><?php echo t('Clave de Licencia:', 'License Key:'); ?></span>
                        <div class="license-key-container">
                            <span class="license-key-value" id="licenseKey"><?php echo htmlspecialchars($license_data['license_key']); ?></span>
                            <button class="btn-copy" onclick="copyLicenseKey()" id="copyBtn">
                                📋 <?php echo t('Copiar', 'Copy'); ?>
                            </button>
                </div>
            </div>
                <div class="info-item">
                    <span class="info-label"><?php echo t('Estado:', 'Status:'); ?></span>
                        <span class="status status-<?php echo $license_data['status']; ?>">
                            <?php echo ucfirst($license_data['status']); ?>
                        </span>
                    </div>
                <div class="info-item">
                        <span class="info-label"><?php echo t('Versión del Plugin:', 'Plugin Version:'); ?></span>
                        <span class="info-value"><?php echo $license_data['plugin_version']; ?></span>
                                </div>
                <div class="info-item">
                        <span class="info-label"><?php echo t('Último Uso:', 'Last Used:'); ?></span>
                        <span class="info-value">
                            <?php echo $license_data['last_used'] ? date('d/m/Y H:i', strtotime($license_data['last_used'])) : t('Nunca', 'Never'); ?>
                        </span>
                    </div>
                <div class="info-item">
                        <span class="info-label"><?php echo t('Límite de Uso:', 'Usage Limit:'); ?></span>
                        <span class="info-value"><?php echo $license_data['usage_limit'] > 999 ? t('Ilimitado', 'Unlimited') : number_format($license_data['usage_limit']) . ' ' . t('por ciclo', 'per cycle'); ?></span>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <?php echo t('No se encontró información de licencia.', 'No license information found.'); ?>
            </div>
            <?php endif; ?>
            </div>




                    </div>


    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> DWooSync. <?php echo t('Todos los derechos reservados.', 'All rights reserved.'); ?></p>
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

        // Función para copiar la clave de licencia
        function copyLicenseKey() {
            const licenseKey = document.getElementById('licenseKey').textContent;
            const copyBtn = document.getElementById('copyBtn');
            
            // Intentar usar la API moderna del portapapeles
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(licenseKey).then(function() {
                    showCopySuccess(copyBtn);
                }).catch(function(err) {
                    console.error('Error al copiar: ', err);
                    fallbackCopyTextToClipboard(licenseKey, copyBtn);
                });
            } else {
                // Fallback para navegadores más antiguos
                fallbackCopyTextToClipboard(licenseKey, copyBtn);
            }
        }

        // Función de respaldo para copiar texto
        function fallbackCopyTextToClipboard(text, button) {
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
                    showCopySuccess(button);
                } else {
                    showCopyError(button);
                }
            } catch (err) {
                console.error('Fallback: Error al copiar', err);
                showCopyError(button);
            }
            
            document.body.removeChild(textArea);
        }

        // Mostrar éxito al copiar
        function showCopySuccess(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '✅ <?php echo t('Copiado', 'Copied'); ?>';
            button.classList.add('copied');
            
            setTimeout(function() {
                button.innerHTML = originalText;
                button.classList.remove('copied');
            }, 2000);
        }

        // Mostrar error al copiar
        function showCopyError(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '❌ <?php echo t('Error', 'Error'); ?>';
            button.style.background = 'linear-gradient(45deg, #dc2626, #b91c1c)';
            
            setTimeout(function() {
                button.innerHTML = originalText;
                button.style.background = '';
            }, 2000);
        }

        // Auto-refresh cada 5 minutos
        // setInterval(refreshData, 300000); // Comentado temporalmente - función no definida

        // Funciones del modal de planes
        function openPlansModal() {
            // Filtrar planes según el plan actual
            const currentPlan = '<?php echo $subscriber_data['plan_type'] ?? 'free'; ?>';
            const planCards = document.querySelectorAll('.plan-card');
            
            planCards.forEach(card => {
                const planType = card.getAttribute('data-plan') || card.onclick.toString().match(/selectPlan\('(\w+)'\)/)?.[1];
                
                if (currentPlan === 'free') {
                    // Mostrar solo premium y enterprise
                    card.style.display = (planType === 'premium' || planType === 'enterprise') ? 'block' : 'none';
                } else if (currentPlan === 'premium') {
                    // Mostrar solo enterprise
                    card.style.display = (planType === 'enterprise') ? 'block' : 'none';
                } else {
                    // Ocultar todos si ya es enterprise
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
            
            // Redirigir al checkout con el plan seleccionado
            window.location.href = `checkout.php?plan=${planId}&renewal=true`;
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

    <!-- Modal de Planes -->
    <div id="plansModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-crown"></i> Selecciona tu Plan</h2>
                <button class="modal-close" onclick="closePlansModal()">&times;</button>
                    </div>
            <div class="modal-body">
                <div class="plans-grid">
                    <!-- Plan Free -->
                    <div class="plan-card" data-plan="free" onclick="selectPlan('free')">
                        <div class="plan-header">
                            <h3>Free</h3>
                            <div class="plan-price">€0<span>/mes</span></div>
                    </div>
                        <div class="plan-features">
                            <ul>
                                <li><i class="fas fa-check"></i> Importaciones ilimitadas</li>
                                <li><i class="fas fa-check"></i> Soporte por email</li>
                                <li><i class="fas fa-check"></i> Actualizaciones básicas</li>
                            </ul>
                    </div>
                        <div class="plan-button">
                            <span class="current-plan">Plan Actual</span>
                </div>
            </div>

                    <!-- Plan Premium -->
                    <div class="plan-card premium" data-plan="premium" onclick="selectPlan('premium')">
                        <div class="plan-badge">Más Popular</div>
                        <div class="plan-header">
                            <h3>Premium</h3>
                            <div class="plan-price">€22<span>/mes</span></div>
        </div>
                        <div class="plan-features">
                            <ul>
                                <li><i class="fas fa-check"></i> Importaciones ilimitadas</li>
                                <li><i class="fas fa-check"></i> Soporte prioritario</li>
                                <li><i class="fas fa-check"></i> Todas las actualizaciones</li>
                                <li><i class="fas fa-check"></i> Estadísticas avanzadas</li>
                                <li><i class="fas fa-times" style="color: #dc2626;"></i> Widget Spotify</li>
                            </ul>
                        </div>
                        <div class="plan-button">
                            <button class="btn-select">Seleccionar</button>
                        </div>
                    </div>

                    <!-- Plan +Spotify -->
                    <div class="plan-card enterprise" data-plan="enterprise" onclick="selectPlan('enterprise')">
                        <div class="plan-header">
                            <h3>+Spotify</h3>
                            <div class="plan-price">€29<span>/mes</span></div>
                        </div>
                        <div class="plan-features">
                            <ul>
                                <li><i class="fas fa-check"></i> Importaciones ilimitadas</li>
                                <li><i class="fas fa-check"></i> Integración con Spotify</li>
                                <li><i class="fas fa-check"></i> Soporte prioritario</li>
                                <li><i class="fas fa-check"></i> Todas las actualizaciones</li>
                                <li><i class="fas fa-check"></i> Estadísticas avanzadas</li>
                            </ul>
                        </div>
                        <div class="plan-button">
                            <button class="btn-select">Seleccionar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
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
    </style>
    
    <script>
        
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
    
    <?php if (!$has_active_invoice): ?>
    <!-- Modal obligatorio para completar suscripción -->
    <div id="subscriptionModal" class="modal" style="display: block; position: fixed; z-index: 10000; background: rgba(0,0,0,0.8); top: 0; left: 0; width: 100%; height: 100%;">
        <div class="modal-content" style="background: white; margin: 5% auto; padding: 2rem; border-radius: 16px; width: 90%; max-width: 600px; position: relative;">
            <!-- Botón de idioma -->
            <div style="position: absolute; top: 1rem; right: 1rem;">
                <div class="language-dropdown" style="position: relative;">
                    <button class="language-btn" style="background: #1db954; color: white; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                        <?php if ($isEnglish): ?>
                            <span style="font-size: 1.2em;">🇬🇧</span>
                        <?php else: ?>
                            <span style="font-size: 1.2em;">🇪🇸</span>
                        <?php endif; ?>
                        <span><?php echo $isEnglish ? 'EN' : 'ES'; ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 0.8em;"></i>
                    </button>
                    <div class="language-menu" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10001; min-width: 140px;">
                        <a href="?lang=es" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; border-bottom: 1px solid #f3f4f6; <?php echo !$isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                            <span style="font-size: 1.1em; margin-right: 8px;">🇪🇸</span> Español
                        </a>
                        <a href="?lang=en" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; <?php echo $isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                            <span style="font-size: 1.1em; margin-right: 8px;">🇬🇧</span> English
                        </a>
                    </div>
                </div>
            </div>
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 3rem; color: #f59e0b; margin-bottom: 1rem;">⚠️</div>
                <h2 style="color: #1f2937; margin-bottom: 1rem;"><?php echo t('Completa tu Suscripción', 'Complete your Subscription'); ?></h2>
                <p style="color: #6b7280; font-size: 1.1rem;"><?php echo t('No se ha completado la configuración de tu suscripción. Selecciona un plan para continuar:', 'Your subscription setup has not been completed. Select a plan to continue:'); ?></p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div class="plan-card" onclick="selectSubscriptionPlan('free')" style="border: 2px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.3s ease;">
                    <div style="font-size: 2rem; color: #10b981; margin-bottom: 0.5rem;">🆓</div>
                    <h3 style="color: #1f2937; margin-bottom: 0.5rem;"><?php echo t('Gratuito', 'Free'); ?></h3>
                    <div style="font-size: 1.5rem; font-weight: bold; color: #10b981; margin-bottom: 0.5rem;">$<?php echo $subscription_plans['free']['price'] ?? 0; ?>/<?php echo t('mes', 'month'); ?></div>
                    <div style="font-size: 0.9rem; color: #6b7280;"><?php echo t('10 importaciones/mes', '10 imports/month'); ?></div>
                </div>
                
                <div class="plan-card" onclick="selectSubscriptionPlan('premium')" style="border: 2px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.3s ease;">
                    <div style="font-size: 2rem; color: #3b82f6; margin-bottom: 0.5rem;">🎵</div>
                    <h3 style="color: #1f2937; margin-bottom: 0.5rem;"><?php echo t('Premium', 'Premium'); ?></h3>
                    <div style="font-size: 1.5rem; font-weight: bold; color: #3b82f6; margin-bottom: 0.5rem;">$<?php echo $subscription_plans['premium']['price'] ?? 22; ?>/<?php echo t('mes', 'month'); ?></div>
                    <div style="font-size: 0.9rem; color: #6b7280;"><?php echo t('Importaciones ilimitadas', 'Unlimited imports'); ?></div>
                </div>
                
                <div class="plan-card" onclick="selectSubscriptionPlan('enterprise')" style="border: 2px solid #e5e7eb; border-radius: 12px; padding: 1.5rem; text-align: center; cursor: pointer; transition: all 0.3s ease;">
                    <div style="font-size: 2rem; color: #1db954; margin-bottom: 0.5rem;">🎧</div>
                    <h3 style="color: #1f2937; margin-bottom: 0.5rem;"><?php echo t('Plan +Spotify', 'Plan +Spotify'); ?></h3>
                    <div style="font-size: 1.5rem; font-weight: bold; color: #1db954; margin-bottom: 0.5rem;">$<?php echo $subscription_plans['enterprise']['price'] ?? 29; ?>/<?php echo t('mes', 'month'); ?></div>
                    <div style="font-size: 0.9rem; color: #6b7280;"><?php echo t('Todo + Spotify', 'Everything + Spotify'); ?></div>
                </div>
            </div>
            
            <div style="text-align: center;">
                <button onclick="proceedToCheckout()" id="proceedBtn" disabled style="background: #6b7280; color: white; border: none; padding: 1rem 2rem; border-radius: 8px; font-size: 1.1rem; cursor: not-allowed; transition: all 0.3s ease;">
                    <i class="fas fa-arrow-right"></i> <?php echo t('Continuar al Checkout', 'Continue to Checkout'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        let selectedPlan = null;
        
        function selectSubscriptionPlan(plan) {
            selectedPlan = plan;
            
            // Remover selección anterior
            document.querySelectorAll('.plan-card').forEach(card => {
                card.style.borderColor = '#e5e7eb';
                card.style.backgroundColor = 'white';
            });
            
            // Seleccionar nuevo plan
            event.target.closest('.plan-card').style.borderColor = '#3b82f6';
            event.target.closest('.plan-card').style.backgroundColor = '#f0f9ff';
            
            // Habilitar botón
            const proceedBtn = document.getElementById('proceedBtn');
            proceedBtn.disabled = false;
            proceedBtn.style.background = '#3b82f6';
            proceedBtn.style.cursor = 'pointer';
        }
        
        function proceedToCheckout() {
            if (selectedPlan) {
                const currentLang = '<?php echo $currentLang; ?>';
                window.location.href = `checkout.php?plan=${selectedPlan}&lang=${currentLang}`;
            }
        }
        
        // Funcionalidad del dropdown de idioma - versión que no interfiere con menú móvil
        function initLanguageSelector() {
            console.log('=== INICIALIZANDO SELECTOR DE IDIOMA ===');
            
            setTimeout(function() {
                // Solo configurar botones que NO estén en el menú móvil
                const allButtons = document.querySelectorAll('.language-btn');
                const allMenus = document.querySelectorAll('.language-menu');
                
                console.log('Total botones encontrados:', allButtons.length);
                console.log('Total menús encontrados:', allMenus.length);
                
                allButtons.forEach(function(btn, i) {
                    const menu = allMenus[i];
                    if (btn && menu) {
                        // Verificar si el botón está en el menú móvil
                        const isInMobileMenu = btn.closest('#nav-menu');
                        
                        if (!isInMobileMenu) {
                            console.log('Configurando botón principal', i);
                            
                            btn.onclick = function(e) {
                                console.log('Botón principal clickeado!');
                                e.preventDefault();
                                e.stopPropagation();
                                
                                const visible = menu.style.display === 'block';
                                menu.style.display = visible ? 'none' : 'block';
                                console.log('Menú principal visible:', !visible);
                                
                                return false;
                            };
                            
                            menu.onclick = function(e) {
                                e.stopPropagation();
                            };
                        } else {
                            console.log('Botón en menú móvil, saltando configuración principal', i);
                        }
                    }
                });
                
                // Cerrar al hacer clic fuera (solo menús principales)
                document.onclick = function(e) {
                    if (!e.target.closest('.language-dropdown')) {
                        allMenus.forEach(function(m) {
                            const isInMobileMenu = m.closest('#nav-menu');
                            if (!isInMobileMenu) {
                                m.style.display = 'none';
                            }
                        });
                    }
                };
                
                console.log('=== SELECTOR PRINCIPAL INICIALIZADO ===');
            }, 1500); // Aumentar delay para evitar conflictos
        }
        
        // Inicializar
        initLanguageSelector();
    </script>
    <?php endif; ?>
    
    <!-- Notificación de pago exitoso -->
    <?php if ($payment_success): ?>
    <div id="paymentSuccessNotification" class="payment-success-notification" style="position: fixed; top: 20px; right: 20px; z-index: 10001; background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 20px; border-radius: 12px; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); max-width: 400px; animation: slideInRight 0.5s ease-out;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center;">
                <i class="fas fa-check-circle" style="font-size: 1.5rem; margin-right: 12px;"></i>
                    <div>
                    <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 4px;"><?php echo t('¡Pago Procesado Exitosamente!', 'Payment Processed Successfully!'); ?></div>
                    <div style="font-size: 0.9rem; opacity: 0.9;">
                        <?php echo t('Tu suscripción', 'Your subscription'); ?> <?php echo $payment_method === 'mercadopago' ? 'Premium (MercadoPago)' : 'Premium (PayPal)'; ?> <?php echo t('ha sido activada.', 'has been activated.'); ?>
                    </div>
                </div>
            </div>
            <button onclick="closePaymentNotification()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0; margin-left: 15px;">&times;</button>
        </div>
    </div>
    
    <style>
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
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
        console.log('=== SCRIPT DEL DASHBOARD INICIADO ===');
        console.log('Timestamp:', new Date().toISOString());
        
        function closePaymentNotification() {
            const notification = document.getElementById('paymentSuccessNotification');
            if (notification) {
                notification.style.animation = 'slideInRight 0.3s ease-out reverse';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }
        
        // Auto-cerrar después de 8 segundos
        setTimeout(() => {
            closePaymentNotification();
        }, 8000);
    </script>
    <?php endif; ?>
    
    <!-- Script del menú móvil - siempre ejecutar -->
    <script>
        console.log('=== SCRIPT DEL MENÚ MÓVIL INICIADO ===');
        
        // Menú móvil - versión simplificada
        function initMobileMenu() {
            console.log('=== INICIANDO MENÚ MÓVIL ===');
            console.log('Estado del DOM:', document.readyState);
            
            const navToggle = document.getElementById('nav-toggle');
            const navMenu = document.getElementById('nav-menu');
            
            console.log('navToggle encontrado:', !!navToggle);
            console.log('navMenu encontrado:', !!navMenu);
            
            if (navToggle) {
                console.log('Elemento navToggle:', navToggle);
                console.log('Clases de navToggle:', navToggle.className);
            }
            
            if (navMenu) {
                console.log('Elemento navMenu:', navMenu);
                console.log('Clases de navMenu:', navMenu.className);
            }
            
            if (navToggle && navMenu) {
                console.log('Agregando event listener al botón hamburguesa');
                
                // Usar addEventListener en lugar de onclick para evitar conflictos
                navToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Botón hamburguesa clickeado!');
                    navMenu.classList.toggle('active');
                    navToggle.classList.toggle('active');
                    console.log('Menú activo:', navMenu.classList.contains('active'));
                });
                
                // Cerrar menú al hacer clic en enlaces (excluyendo selector de idioma)
                const navLinks = navMenu.querySelectorAll('.nav-link:not(.language-btn)');
                console.log('Enlaces encontrados:', navLinks.length);
                navLinks.forEach(function(link, index) {
                    console.log('Agregando listener al enlace', index, 'href:', link.href);
                    // Usar onclick en lugar de addEventListener para evitar conflictos
                    link.onclick = function(e) {
                        // Verificar si es un enlace de idioma
                        if (link.href && link.href.includes('lang=')) {
                            console.log('Enlace de idioma clickeado, no cerrando menú');
                            return true; // Permitir navegación sin cerrar menú
                        }
                        console.log('Enlace clickeado:', link.href);
                        console.log('Evento:', e);
                        console.log('Cerrando menú...');
                        navMenu.classList.remove('active');
                        navToggle.classList.remove('active');
                        console.log('Menú cerrado, permitiendo navegación');
                        return true; // Permitir navegación
                    };
                });
                
                // Agregar event listener específico para el botón de idioma
                const languageButton = navMenu.querySelector('.language-btn');
                if (languageButton) {
                    console.log('Agregando listener al botón de idioma');
                    languageButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Botón de idioma clickeado en menú móvil');
                        
                        // Toggle del menú de idioma
                        const languageMenu = languageButton.parentElement.querySelector('.language-menu');
                        if (languageMenu) {
                            const isVisible = languageMenu.style.display === 'block';
                            languageMenu.style.display = isVisible ? 'none' : 'block';
                            console.log('Menú de idioma toggled:', !isVisible);
                        }
                        
                        // No cerrar el menú móvil
                    });
                }
                
                console.log('Menú móvil inicializado correctamente');
            } else {
                console.error('No se encontraron los elementos del menú móvil');
                console.error('navToggle:', navToggle);
                console.error('navMenu:', navMenu);
            }
        }
        
        // Inicializar menú móvil al final de todo
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