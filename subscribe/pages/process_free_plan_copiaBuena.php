<?php
/**
// Comenzar output buffer
ob_start();

// Array para capturar debug
$debugLog = [];

// Determinar idioma primero
session_start();
$currentLang = $_GET['lang'] ?? ($_SESSION['selected_language'] ?? 'en');
$isEnglish = ($currentLang === 'en');

// Guardar idioma seleccionado en sesión
if (isset($_GET['lang'])) {
    $_SESSION['selected_language'] = $_GET['lang'];
}

echo "<!DOCTYPE html>
<html lang='" . $currentLang . "'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . ($isEnglish ? 'Activating Free Plan - DWooSync' : 'Activando Plan Gratuito - DWooSync') . "</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' rel='stylesheet'>";tivación de plan gratuito - Versión limpia y funcional
 */

// Forzar mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Comenzar output buffer
ob_start();

// Array para capturar debug
$debugLog = [];

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Activando Plan Gratuito - DWooSync</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 40px;
            text-align: center;
            max-width: 700px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .main-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(102, 126, 234, 0.05), transparent);
            transform: rotate(45deg);
            animation: shimmer 3s ease-in-out infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }

        .content {
            position: relative;
            z-index: 1;
        }

        .loading-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
            animation: loadingPulse 2s ease-in-out infinite;
        }

        @keyframes loadingPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .loading-icon i {
            color: white;
            font-size: 2.5rem;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .subtitle {
            font-size: 1.1rem;
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4ade80, #22c55e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
        }

        .success-icon i {
            color: white;
            font-size: 2.5rem;
        }

        .license-info {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 1px solid #22c55e;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            box-shadow: 0 4px 12px rgba(34, 197, 94, 0.1);
            text-align: left;
        }

        .license-info h3 {
            color: #15803d;
            font-size: 1.3rem;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .license-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #bbf7d0;
        }

        .license-item:last-child {
            border-bottom: none;
        }

        .license-label {
            font-weight: 500;
            color: #166534;
        }

        .license-value {
            color: #0f172a;
            font-family: monospace;
            background: rgba(34, 197, 94, 0.1);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 30px 0;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(34, 197, 94, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
            transform: translateY(-1px);
        }

        .debug-toggle {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
            margin-top: 30px;
        }

        .debug-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(107, 114, 128, 0.4);
        }

        .debug-section {
            background: #1f2937;
            color: #e5e7eb;
            border-radius: 12px;
            padding: 25px;
            margin-top: 20px;
            text-align: left;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }

        .debug-section.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .debug-section h4 {
            color: #60a5fa;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .countdown {
            color: #6b7280;
            font-style: italic;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .title {
                font-size: 2rem;
            }

            .actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class='main-container'>
        <div class='content'>
            <div class='loading-icon'>
                <i class='fas fa-compact-disc'></i>
            </div>
            
            <h1 class='title'>DWooSync</h1>
            <p class='subtitle'>Activando tu plan gratuito...</p>";

function addDebug($message) {
    global $debugLog;
    $debugLog[] = date('H:i:s') . " - " . $message;
}

try {
    addDebug("Iniciando proceso de activación");
    
    // Cargar archivos
    addDebug("Cargando configuración");
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
    addDebug("Configuración cargada");
    
    // Iniciar sesión
    addDebug("Iniciando sesión");
    session_start();
    addDebug("Sesión iniciada");
    
    // Verificar datos de sesión
    addDebug("Verificando datos de sesión");
    if (!isset($_SESSION['signup_data'])) {
        // Si no hay signup_data pero hay sesión activa, cargar desde BD
        if (isset($_SESSION['subscriber_id'])) {
            addDebug("No hay signup_data, pero hay subscriber_id. Cargando desde BD...");
            try {
                $pdo = getDatabase();
                $stmt = $pdo->prepare("SELECT first_name, last_name, email, domain, country FROM subscribers WHERE id = ?");
                $stmt->execute([$_SESSION['subscriber_id']]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($userData) {
                    $_SESSION['signup_data'] = $userData;
                    addDebug("Datos cargados desde BD: " . json_encode($userData));
                } else {
                    throw new Exception('No se encontraron datos del usuario en la base de datos');
                }
            } catch (Exception $e) {
                addDebug("Error cargando datos desde BD: " . $e->getMessage());
                throw new Exception('No hay datos de registro en la sesión y no se pudieron cargar desde la base de datos');
            }
        } else {
            throw new Exception('No hay datos de registro en la sesión');
        }
    }
    $signupData = $_SESSION['signup_data'];
    
    addDebug("Datos encontrados: " . json_encode($signupData));
    
    // Mapear campos correctamente
    $mappedData = [
        'email' => $signupData['email'] ?? '',
        'firstName' => $signupData['first_name'] ?? '',
        'lastName' => $signupData['last_name'] ?? '',
        'country' => $signupData['country'] ?? 'CL',
        'domain' => $signupData['domain'] ?? ''
    ];
    
    // Validar campos requeridos
    $requiredFields = ['email', 'firstName', 'lastName', 'domain'];
    foreach ($requiredFields as $field) {
        if (empty($mappedData[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    $signupData = $mappedData;
    addDebug("Datos de sesión validados y mapeados");
    
    // Conectar a la base de datos
    addDebug("Conectando a la base de datos");
    $db = getDatabase();
    addDebug("Conexión a BD establecida");
    
    // Comenzar transacción
    addDebug("Iniciando transacción");
    $db->beginTransaction();
    addDebug("Transacción iniciada");
    
    // Insertar o obtener suscriptor existente
    addDebug("Verificando/creando suscriptor");
    
    // Primero verificar si ya existe
    $stmt = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
    $stmt->execute([$signupData['email']]);
    $existingSubscriber = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingSubscriber) {
        $subscriber_id = $existingSubscriber['id'];
        addDebug("Suscriptor existente encontrado con ID: $subscriber_id");
    } else {
        // Crear nuevo suscriptor
        $stmt = $db->prepare("
            INSERT INTO subscribers (email, first_name, last_name, country, domain, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $signupData['email'],
            $signupData['firstName'],
            $signupData['lastName'],
            $signupData['country'],
            $signupData['domain']
        ]);
        $subscriber_id = $db->lastInsertId();
        addDebug("Nuevo suscriptor creado con ID: $subscriber_id");
    }
    
    addDebug("Suscriptor listo con ID: $subscriber_id");
    
    // Obtener datos del suscriptor
    addDebug("Obteniendo datos del suscriptor");
    $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
    $stmt->execute([$subscriber_id]);
    $subscriberData = $stmt->fetch(PDO::FETCH_ASSOC);
    addDebug("Datos del suscriptor obtenidos");
    
    // Activar suscriptor
    addDebug("Activando suscriptor");
    $stmt = $db->prepare("UPDATE subscribers SET status = 'active' WHERE id = ?");
    $stmt->execute([$subscriber_id]);
    addDebug("Suscriptor activado");
    
    // Generar clave de licencia
    addDebug("Generando clave de licencia");
    $new_license_key = 'FREE' . strtoupper(substr(md5($subscriberData['email'] . time()), 0, 14));
    addDebug("Clave generada: $new_license_key");
    
    // Calcular fechas del ciclo
    addDebug("Calculando fechas del ciclo");
    $cycle_start = date('Y-m-d H:i:s');
    $cycle_end = date('Y-m-d H:i:s', strtotime('+1 month'));
    $cycle_end_date = date('d/m/Y', strtotime('+1 month'));
    
    // Para plan free: licencia expira en 1 mes (igual que el ciclo)
    $license_expires = date('Y-m-d H:i:s', strtotime('+1 month'));
    $license_expires_date = date('d/m/Y', strtotime('+1 month'));
    
    addDebug("Ciclo: $cycle_start hasta $cycle_end (1 mes)");
    addDebug("Licencia expira: $license_expires (1 mes)");
    
    // Actualizar ciclo de facturación existente
    addDebug("Actualizando ciclo de facturación");
    
    // Buscar ciclo pendiente para este suscriptor
    $stmt = $db->prepare("SELECT id FROM billing_cycles WHERE subscriber_id = ? AND status = 'pending' AND plan_type = 'free' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$subscriber_id]);
    $pendingCycle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pendingCycle) {
        // Actualizar el ciclo pendiente
        $billing_cycle_id = $pendingCycle['id'];
        $stmt = $db->prepare("
            UPDATE billing_cycles 
            SET status = 'paid', is_active = 1, cycle_start_date = ?, cycle_end_date = ?, paid_date = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$cycle_start, $cycle_end, $billing_cycle_id]);
        addDebug("Ciclo de facturación actualizado (ID: $billing_cycle_id)");
    } else {
        // Si no hay ciclo pendiente, crear uno nuevo (fallback)
        $stmt = $db->prepare("
            INSERT INTO billing_cycles (subscriber_id, plan_type, amount, status, cycle_start_date, cycle_end_date, is_active, currency, paid_date) 
            VALUES (?, 'free', 0.00, 'paid', ?, ?, 1, 'USD', NOW())
        ");
        $stmt->execute([$subscriber_id, $cycle_start, $cycle_end]);
        $billing_cycle_id = $db->lastInsertId();
        addDebug("Nuevo ciclo de facturación creado (ID: $billing_cycle_id)");
    }
    
    // Actualizar licencia existente
    addDebug("Actualizando licencia");
    
    // Buscar licencia existente para este suscriptor
    $stmt = $db->prepare("SELECT id FROM licenses WHERE subscriber_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$subscriber_id]);
    $existingLicense = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingLicense) {
        // Actualizar licencia existente con configuración de plan free
        $license_id = $existingLicense['id'];
        $stmt = $db->prepare("
            UPDATE licenses 
            SET license_key = ?, domain = ?, status = 'active', expires_at = ?, usage_limit = 10, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_license_key, $subscriberData['domain'], $license_expires, $license_id]);
        addDebug("Licencia actualizada (ID: $license_id, status: active, usage_limit: 10, expires: $license_expires_date)");
    } else {
        // Si no hay licencia existente, crear una nueva (fallback)
        $subscription_code = 'SUB-' . strtoupper(substr(md5($subscriberData['email'] . time() . rand()), 0, 12));
        $stmt = $db->prepare("
            INSERT INTO licenses (subscriber_id, subscription_code, license_key, domain, status, usage_limit, created_at, expires_at) 
            VALUES (?, ?, ?, ?, 'active', 10, NOW(), ?)
        ");
        $stmt->execute([$subscriber_id, $subscription_code, $new_license_key, $subscriberData['domain'], $license_expires]);
        $license_id = $db->lastInsertId();
        addDebug("Nueva licencia creada (ID: $license_id, status: active, usage_limit: 10, expires: $license_expires_date)");
    }
    
    // Confirmar transacción
    addDebug("Confirmando transacción");
    $db->commit();
    addDebug("Transacción confirmada");
    
    // Establecer sesión de usuario
    addDebug("Configurando sesión de usuario");
    $_SESSION['subscriber_id'] = $subscriber_id;
    $_SESSION['email'] = $subscriberData['email'];
    $_SESSION['first_name'] = $subscriberData['first_name'];
    $_SESSION['domain'] = $subscriberData['domain'];
    $_SESSION['plan_type'] = 'free';
    $_SESSION['license_key'] = $new_license_key;
    $_SESSION['is_logged_in'] = true;
    addDebug("Sesión configurada");
    
    // Enviar correo de bienvenida
    addDebug("Enviando correo de bienvenida");
    
    try {
        addDebug("Cargando PHPMailer");
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        addDebug("Creando instancia PHPMailer");
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = 'mail.dwoosync.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'support@dwoosync.com';
        $mail->Password = '5802863aA$$';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Timeout = 30;
        
        addDebug("Configuración SMTP establecida");
        
        // Configurar correo
        $mail->setFrom('support@dwoosync.com', 'DWooSync');
        $mail->addReplyTo('support@dwoosync.com', 'DWooSync');
        $mail->addAddress($subscriberData['email'], $subscriberData['first_name']);
        
        // Headers adicionales
        $mail->addCustomHeader('X-Mailer', 'DWooSync System v1.0');
        $mail->addCustomHeader('X-Priority', '3');
        $mail->addCustomHeader('Return-Path', 'support@dwoosync.com');
        
        addDebug("Preparando contenido");
        
        $planName = $isEnglish ? 'Free' : 'Gratuito';
        $subject = $isEnglish ? 'Welcome to DWooSync! - Your account has been activated' : '¡Bienvenido a DWooSync! - Tu cuenta ha sido activada';
        
        $message = $isEnglish ? 
            "<h3>Hello {$subscriberData['first_name']}!</h3><p>Welcome to DWooSync! Your account has been successfully activated.</p><p><strong>Plan:</strong> $planName<br><strong>License Key:</strong> $new_license_key<br><strong>Domain:</strong> {$subscriberData['domain']}<br><strong>Usage Limit:</strong> 10 syncs<br><strong>Expires:</strong> $license_expires_date</p><p>Thank you for choosing DWooSync!</p>" :
            "<h3>¡Hola {$subscriberData['first_name']}!</h3><p>¡Bienvenido a DWooSync! Tu cuenta ha sido activada exitosamente.</p><p><strong>Plan:</strong> $planName<br><strong>Clave de licencia:</strong> $new_license_key<br><strong>Dominio:</strong> {$subscriberData['domain']}<br><strong>Límite de uso:</strong> 10 sincronizaciones<br><strong>Expira:</strong> $license_expires_date</p><p>¡Gracias por elegir DWooSync!</p>";
        
        // Configurar contenido
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
        
        addDebug("Enviando correo");
        $result = $mail->send();
        
        if ($result) {
            addDebug("Correo enviado exitosamente a {$subscriberData['email']}");
            $emailSent = true;
        } else {
            addDebug("Error enviando correo: " . $mail->ErrorInfo);
            $emailSent = false;
        }
        
    } catch (Exception $e) {
        addDebug("Error con correo (proceso continúa): " . $e->getMessage());
        $emailSent = false;
    }
    
    addDebug("Proceso completado exitosamente");
    
    // Cambiar icono a éxito
    echo '<script>
        document.querySelector(".loading-icon").innerHTML = "<i class=\"fas fa-check\"></i>";
        document.querySelector(".loading-icon").className = "success-icon";
        document.querySelector(".title").innerHTML = "<i class=\"fas fa-compact-disc\" style=\"animation: spin 2s linear infinite;\"></i> ' . ($isEnglish ? 'Account Activated!' : '¡Cuenta Activada!') . '";
        document.querySelector(".subtitle").innerHTML = "' . ($isEnglish ? 'Your free plan has been successfully activated' : 'Tu plan gratuito ha sido activado exitosamente') . '";
    </script>';
    
    // Mostrar información de la licencia
    echo '<div class="license-info">';
    echo '<h3>📋 ' . ($isEnglish ? 'Your License Information' : 'Información de tu Licencia') . '</h3>';
    
    echo '<div class="license-item">';
    echo '<span class="license-label">' . ($isEnglish ? 'Plan:' : 'Plan:') . '</span>';
    echo '<span class="license-value">' . ($isEnglish ? 'Free' : 'Gratuito') . '</span>';
    echo '</div>';
    
    echo '<div class="license-item">';
    echo '<span class="license-label">' . ($isEnglish ? 'License Key:' : 'Clave de licencia:') . '</span>';
    echo '<span class="license-value">' . $new_license_key . '</span>';
    echo '</div>';
    
    echo '<div class="license-item">';
    echo '<span class="license-label">' . ($isEnglish ? 'Domain:' : 'Dominio:') . '</span>';
    echo '<span class="license-value">' . $subscriberData['domain'] . '</span>';
    echo '</div>';
    
    echo '<div class="license-item">';
    echo '<span class="license-label">' . ($isEnglish ? 'Usage Limit:' : 'Límite de uso:') . '</span>';
    echo '<span class="license-value">10 ' . ($isEnglish ? 'syncs/month' : 'sincronizaciones/mes') . '</span>';
    echo '</div>';
    
    echo '<div class="license-item">';
    echo '<span class="license-label">' . ($isEnglish ? 'Valid until:' : 'Válida hasta:') . '</span>';
    echo '<span class="license-value">' . $license_expires_date . '</span>';
    echo '</div>';
    
    echo '</div>';
    
    if (isset($emailSent) && $emailSent) {
        echo '<div style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #22c55e; border-radius: 10px; padding: 15px; margin: 20px 0; color: #15803d;">';
        echo '<i class="fas fa-envelope-check"></i> ' . ($isEnglish ? 'Welcome email sent successfully!' : '¡Correo de bienvenida enviado exitosamente!');
        echo '</div>';
    } else {
        echo '<div style="background: linear-gradient(135deg, #fefce8, #fef3c7); border: 1px solid #f59e0b; border-radius: 10px; padding: 15px; margin: 20px 0; color: #92400e;">';
        echo '<i class="fas fa-exclamation-triangle"></i> ' . ($isEnglish ? 'Account activated but email failed to send.' : 'Cuenta activada pero el correo no se pudo enviar.');
        echo '</div>';
    }
    
    echo '<div class="actions">';
    echo '<a href="dashboard.php?lang=' . $currentLang . '" class="btn btn-primary">';
    echo '<i class="fas fa-tachometer-alt"></i> ' . ($isEnglish ? 'Go to Dashboard' : 'Ir al Dashboard');
    echo '</a>';
    
    echo '<a href="plugin-config.php?lang=' . $currentLang . '" class="btn btn-secondary">';
    echo '<i class="fas fa-cogs"></i> ' . ($isEnglish ? 'Configure Plugin' : 'Configurar Plugin');
    echo '</a>';
    echo '</div>';
    
    echo '<p class="countdown" id="countdown">' . ($isEnglish ? 'You will be redirected to the dashboard in 10 seconds...' : 'Serás redirigido al dashboard en 10 segundos...') . '</p>';
    
    echo '<button class="btn debug-toggle" onclick="toggleDebug()">';
    echo '<i class="fas fa-bug"></i> ' . ($isEnglish ? 'View Technical Info' : 'Ver Información Técnica');
    echo '</button>';
    
    // Sección de debug oculta
    echo '<div id="debug-section" class="debug-section">';
    echo '<h4>🔧 ' . ($isEnglish ? 'Debug Information' : 'Información de Debug') . '</h4>';
    foreach ($debugLog as $log) {
        echo htmlspecialchars($log) . '<br>';
    }
    echo '</div>';
    
    
    echo '</div></div>'; // Cerrar content y main-container
    
    // JavaScript para countdown y debug
    echo '<script>
    var timeLeft = 10;
    var countdown = document.getElementById("countdown");
    
    var timer = setInterval(function(){
        timeLeft--;
        if (timeLeft <= 0) {
            clearInterval(timer);
            window.location.href = "dashboard.php?lang=' . $currentLang . '";
        } else {
            countdown.innerHTML = "' . ($isEnglish ? 'You will be redirected to the dashboard in ' : 'Serás redirigido al dashboard en ') . '" + timeLeft + "' . ($isEnglish ? ' seconds...' : ' segundos...') . '";
        }
    }, 1000);
    
    function toggleDebug() {
        var debug = document.getElementById("debug-section");
        debug.classList.toggle("show");
    }
    </script>';
    
    echo '</body></html>';
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    
    echo '<script>
        document.querySelector(".loading-icon").innerHTML = "<i class=\"fas fa-exclamation-triangle\"></i>";
        document.querySelector(".loading-icon").style.background = "linear-gradient(135deg, #ef4444, #dc2626)";
        document.querySelector(".title").innerHTML = "<i class=\"fas fa-exclamation-triangle\"></i> ' . ($isEnglish ? 'Activation Error' : 'Error de Activación') . '";
        document.querySelector(".subtitle").innerHTML = "' . ($isEnglish ? 'There was an error activating your plan' : 'Hubo un error al activar tu plan') . '";
    </script>';
    
    echo '<div style="background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 1px solid #ef4444; border-radius: 10px; padding: 20px; margin: 20px 0; color: #991b1b;">';
    echo '<h3><i class="fas fa-exclamation-triangle"></i> ' . ($isEnglish ? 'Error' : 'Error') . '</h3>';
    echo '<p>' . ($isEnglish ? 'Error: ' : 'Error: ') . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
    
    echo '<div class="actions">';
    echo '<a href="signup.php?lang=' . $currentLang . '" class="btn btn-primary">';
    echo '<i class="fas fa-redo"></i> ' . ($isEnglish ? 'Try Again' : 'Intentar de Nuevo');
    echo '</a>';
    echo '</div>';
    
    echo '</div></div></body></html>';
}

ob_end_flush();
?>