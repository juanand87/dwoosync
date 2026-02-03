<?php
/**
 * Página de perfil del usuario
 */

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

$subscriber_id = $_SESSION['subscriber_id'];
$license_key = $_SESSION['license_key'] ?? '';

// Obtener conexión a la base de datos
$db = getDatabase();

// Verificar si hay factura (cualquier registro en billing_cycles)
$has_active_invoice = false;
try {
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
$subscriber = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
$subscriber->execute([$subscriber_id]);
$subscriber_data = $subscriber->fetch(PDO::FETCH_ASSOC);

// Obtener información de la licencia
$license = $db->prepare("SELECT * FROM licenses WHERE subscriber_id = ? AND license_key = ?");
$license->execute([$subscriber_id, $license_key]);
$license_data = $license->fetch(PDO::FETCH_ASSOC);

// Obtener ciclo activo actual
$current_billing_cycle = $db->prepare("
    SELECT * FROM billing_cycles 
    WHERE subscriber_id = ? AND status = 'paid' AND is_active = 1
");
$current_billing_cycle->execute([$subscriber_id]);
$cycle_data = $current_billing_cycle->fetch(PDO::FETCH_ASSOC);

$success_message = '';
$error_message = '';

// Procesar actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $company = trim($_POST['company']);
        $city = trim($_POST['city']);
        $country = trim($_POST['country']);
        $phone = trim($_POST['phone']);
        
        // Validaciones básicas
        if (empty($first_name) || empty($last_name) || empty($email)) {
            throw new Exception(t('Los campos nombre, apellido y email son obligatorios', 'Name, last name and email fields are required'));
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception(t('El email no tiene un formato válido', 'Email format is not valid'));
        }
        
        // Verificar si el email ya existe en otro usuario
        $check_email = $db->prepare("SELECT id FROM subscribers WHERE email = ? AND id != ?");
        $check_email->execute([$email, $subscriber_id]);
        if ($check_email->fetch()) {
            throw new Exception(t('Este email ya está registrado por otro usuario', 'This email is already registered by another user'));
        }
        
        // Actualizar datos del suscriptor
        $update_stmt = $db->prepare("
            UPDATE subscribers 
            SET first_name = ?, last_name = ?, email = ?, company = ?, city = ?, country = ?, phone = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $update_stmt->execute([
            $first_name, $last_name, $email, $company, $city, $country, $phone, $subscriber_id
        ]);
        
        // Actualizar datos de sesión
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        $_SESSION['user_email'] = $email;
        
        // Actualizar datos locales
        $subscriber_data['first_name'] = $first_name;
        $subscriber_data['last_name'] = $last_name;
        $subscriber_data['email'] = $email;
        $subscriber_data['company'] = $company;
        $subscriber_data['city'] = $city;
        $subscriber_data['country'] = $country;
        $subscriber_data['phone'] = $phone;
        
        $success_message = t('Perfil actualizado correctamente', 'Profile updated successfully');
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validaciones
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception(t('Todos los campos de contraseña son obligatorios', 'All password fields are required'));
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception(t('Las contraseñas nuevas no coinciden', 'New passwords do not match'));
        }
        
        if (strlen($new_password) < 6) {
            throw new Exception(t('La nueva contraseña debe tener al menos 6 caracteres', 'New password must be at least 6 characters long'));
        }
        
        // Verificar contraseña actual
        if (!password_verify($current_password, $subscriber_data['password'])) {
            throw new Exception('La contraseña actual es incorrecta');
        }
        
        // Actualizar contraseña
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_password = $db->prepare("UPDATE subscribers SET password = ?, updated_at = NOW() WHERE id = ?");
        $update_password->execute([$new_password_hash, $subscriber_id]);
        
        $success_message = t('Contraseña actualizada correctamente', 'Password updated successfully');
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Mi Perfil', 'My Profile'); ?> - DWooSync</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
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
            background: linear-gradient(45deg, #667eea, #764ba2);
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
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-logout {
            background: linear-gradient(45deg, #dc2626, #b91c1c);
            color: white !important;
        }
        
        .btn-logout:hover {
            background: linear-gradient(45deg, #b91c1c, #991b1b);
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .profile-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            margin-bottom: 10px;
        }
        
        .profile-subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
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
        
        .user-menu-item.active {
            background: #eff6ff;
            color: #3b82f6;
        }
        
        .user-menu-item.logout:hover {
            background: #fef2f2;
            color: #dc2626;
        }
        
        .btn-secondary {
            background: linear-gradient(45deg, #6b7280, #4b5563);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 10px 20px rgba(107, 114, 128, 0.3);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        .account-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #374151;
        }
        
        .info-value {
            color: #6b7280;
        }
        
        .plan-badge {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .plan-badge.free {
            background: linear-gradient(45deg, #6b7280, #4b5563);
        }
        
        .license-display {
            background: #f0f4ff;
            border: 2px solid #d1d5db;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            font-weight: 600;
            color: #667eea;
            word-break: break-all;
        }
        
        .btn-copy {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-left: 10px;
        }
        
        .btn-copy:hover {
            background: linear-gradient(45deg, #059669, #047857);
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
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
        
        .language-dropdown .language-menu a:hover {
            background: #f9fafb;
        }
        
        .language-btn:hover {
            background: #16a34a !important;
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
    </style>
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
            <a href="checkout.php?plan=free" style="
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
            " title="<?php echo t('Cerrar notificación', 'Close notification'); ?>">×</button>
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
                    <a href="dashboard.php" class="nav-link">🏠 <?php echo t('Inicio', 'Home'); ?></a>
                    <a href="billing.php" class="nav-link">💳 <?php echo t('Facturación', 'Billing'); ?></a>
                    <a href="tutorials.php" class="nav-link">🎥 <?php echo t('Tutoriales', 'Tutorials'); ?></a>
                    <a href="plugin-config.php" class="nav-link">⚙️ <?php echo t('Configurar Plugin', 'Configure Plugin'); ?></a>
                    
                    <!-- Selector de idioma -->
                    <div class="language-dropdown" style="position: relative; margin-left: 10px;">
                        <button class="language-btn" onclick="toggleLanguageMenu()" style="background: #1db954; color: white; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <?php if ($isEnglish): ?>
                                <span style="font-size: 1.2em;">🇬🇧</span>
                            <?php else: ?>
                                <span style="font-size: 1.2em;">🇪🇸</span>
                            <?php endif; ?>
                            <span><?php echo $isEnglish ? 'EN' : 'ES'; ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.8em;"></i>
                        </button>
                        <div class="language-menu" id="languageMenu" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10001; min-width: 140px;">
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
                            <a href="profile.php" class="user-menu-item active">
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

    <div class="container">
        <div class="profile-header">
            <h1 class="profile-title"><?php echo t('Mi Perfil', 'My Profile'); ?></h1>
            <p class="profile-subtitle"><?php echo t('Gestiona tu información personal y configuración de cuenta', 'Manage your personal information and account settings'); ?></p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Información Personal -->
            <div class="profile-card">
                <h2 class="card-title">
                    <i class="fas fa-user"></i> <?php echo t('Información Personal', 'Personal Information'); ?>
                </h2>
                
                <form method="POST">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="first_name"><?php echo t('Nombre', 'First Name'); ?> *</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($subscriber_data['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="last_name"><?php echo t('Apellido', 'Last Name'); ?> *</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($subscriber_data['last_name'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($subscriber_data['email'] ?? ''); ?>" 
                               autocomplete="off" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="company"><?php echo t('Empresa', 'Company'); ?></label>
                        <input type="text" id="company" name="company" class="form-input" 
                               value="<?php echo htmlspecialchars($subscriber_data['company'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="city"><?php echo t('Ciudad', 'City'); ?></label>
                            <input type="text" id="city" name="city" class="form-input" 
                                   value="<?php echo htmlspecialchars($subscriber_data['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="country"><?php echo t('País', 'Country'); ?></label>
                            <input type="text" id="country" name="country" class="form-input" 
                                   value="<?php echo htmlspecialchars($subscriber_data['country'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="phone"><?php echo t('Teléfono', 'Phone'); ?></label>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                               value="<?php echo htmlspecialchars($subscriber_data['phone'] ?? ''); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> <?php echo t('Actualizar Perfil', 'Update Profile'); ?>
                    </button>
                </form>
            </div>

            <!-- Cambiar Contraseña -->
            <div class="profile-card">
                <h2 class="card-title">
                    <i class="fas fa-lock"></i> <?php echo t('Cambiar Contraseña', 'Change Password'); ?>
                </h2>
                
                <form method="POST">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label class="form-label" for="current_password"><?php echo t('Contraseña Actual', 'Current Password'); ?> *</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="new_password"><?php echo t('Nueva Contraseña', 'New Password'); ?> *</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password"><?php echo t('Confirmar Nueva Contraseña', 'Confirm New Password'); ?> *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-key"></i> <?php echo t('Cambiar Contraseña', 'Change Password'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Información de la Cuenta -->
        <div class="profile-card">
            <h2 class="card-title">
                <i class="fas fa-info-circle"></i> <?php echo t('Información de la Cuenta', 'Account Information'); ?>
            </h2>
            
            <div class="account-info">
                <div class="info-item">
                    <span class="info-label"><?php echo t('ID de Usuario:', 'User ID:'); ?></span>
                    <span class="info-value">#<?php echo $subscriber_id; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php echo t('Plan Actual:', 'Current Plan:'); ?></span>
                    <span class="plan-badge <?php echo ($subscriber_data['plan_type'] ?? 'free') === 'free' ? 'free' : ''; ?>">
                        <?php echo ucfirst($subscriber_data['plan_type'] ?? 'free'); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php echo t('Estado de la Cuenta:', 'Account Status:'); ?></span>
                    <span class="info-value" style="color: <?php echo ($subscriber_data['status'] ?? 'inactive') === 'active' ? '#10b981' : '#dc2626'; ?>">
                        <?php echo ucfirst($subscriber_data['status'] ?? 'inactive'); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php echo t('Dominio:', 'Domain:'); ?></span>
                    <span class="info-value"><?php echo htmlspecialchars($subscriber_data['domain'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php echo t('Miembro desde:', 'Member since:'); ?></span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($subscriber_data['created_at'] ?? 'now')); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label"><?php echo t('Última actualización:', 'Last update:'); ?></span>
                    <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($subscriber_data['updated_at'] ?? $subscriber_data['created_at'] ?? 'now')); ?></span>
                </div>
            </div>
            
            <?php if ($license_data): ?>
                <div class="license-display">
                    <strong><?php echo t('Clave de Licencia:', 'License Key:'); ?></strong><br>
                    <?php echo htmlspecialchars($license_key); ?>
                    <button class="btn-copy" onclick="copyLicenseKey()">
                        <i class="fas fa-copy"></i> <?php echo t('Copiar', 'Copy'); ?>
                    </button>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> <?php echo t('Volver al Dashboard', 'Back to Dashboard'); ?>
                </a>
                <?php if (($subscriber_data['plan_type'] ?? 'free') === 'free'): ?>
                    <button onclick="openPlansModal()" class="btn" style="background: linear-gradient(45deg, #f59e0b, #d97706); border: none; cursor: pointer;">
                        <i class="fas fa-arrow-up"></i> <?php echo t('Mejorar Plan', 'Upgrade Plan'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
        
        // Función para toggle del menú de idioma
        function toggleLanguageMenu() {
            const languageMenu = document.getElementById('languageMenu');
            languageMenu.style.display = languageMenu.style.display === 'none' || languageMenu.style.display === '' ? 'block' : 'none';
        }
        
        // Cerrar menú al hacer click fuera
        document.addEventListener('click', function(event) {
            const userDropdown = document.querySelector('.user-dropdown');
            const userMenu = document.getElementById('userMenu');
            const languageDropdown = document.querySelector('.language-dropdown');
            const languageMenu = document.getElementById('languageMenu');
            
            if (!userDropdown.contains(event.target)) {
                userMenu.classList.remove('show');
            }
            
            if (!languageDropdown.contains(event.target)) {
                languageMenu.style.display = 'none';
            }
        });

        function copyLicenseKey() {
            const licenseKey = '<?php echo addslashes($license_key); ?>';
            navigator.clipboard.writeText(licenseKey).then(function() {
                // Cambiar el botón temporalmente
                const btn = event.target.closest('.btn-copy');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> <?php echo t('¡Copiado!', 'Copied!'); ?>';
                btn.style.background = 'linear-gradient(45deg, #10b981, #059669)';
                
                setTimeout(function() {
                    btn.innerHTML = originalText;
                }, 2000);
            }).catch(function(err) {
                alert('<?php echo t('Error al copiar:', 'Error copying:'); ?> ' + err);
            });
        }
        
        // Validación de contraseñas
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('<?php echo t('Las contraseñas no coinciden', 'Passwords do not match'); ?>');
            } else {
                this.setCustomValidity('');
            }
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
</body>
</html>

