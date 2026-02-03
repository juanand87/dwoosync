<?php
/**
 * Procesar activación de plan gratuito - Versión limpia y funcional
 */

// Forzar mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Comenzar output buffer
ob_start();

echo "<!DOCTYPE html><html><head><title>Activando Plan Gratuito</title></head><body>";
echo "<h2>🚀 Activando Plan Gratuito</h2>";
echo "<div style='font-family: Arial; padding: 20px; line-height: 1.6;'>";

try {
    echo "1. ✅ Iniciando proceso...<br>";
    flush();
    
    // Cargar archivos
    echo "2. Cargando configuración...<br>";
    require_once '../includes/config.php';
    require_once '../includes/functions.php';
    echo "2. ✅ Configuración cargada<br>";
    flush();
    
    // Iniciar sesión
    echo "3. Iniciando sesión...<br>";
    session_start();
    echo "3. ✅ Sesión iniciada<br>";
    flush();
    
    // Verificar datos de sesión
    echo "4. Verificando datos de sesión...<br>";
    if (!isset($_SESSION['signup_data'])) {
        throw new Exception('No hay datos de registro en la sesión');
    }
    $signupData = $_SESSION['signup_data'];
    echo "4. ✅ Datos de sesión encontrados<br>";
    flush();
    
    // Conectar a la base de datos
    echo "5. Conectando a la base de datos...<br>";
    $db = getDatabase();
    echo "5. ✅ Conexión a BD establecida<br>";
    flush();
    
    // Determinar idioma
    $currentLang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'es';
    $isEnglish = ($currentLang === 'en');
    echo "6. ✅ Idioma configurado: " . ($isEnglish ? 'English' : 'Español') . "<br>";
    flush();
    
    // Comenzar transacción
    echo "7. Iniciando transacción...<br>";
    $db->beginTransaction();
    echo "7. ✅ Transacción iniciada<br>";
    flush();
    
    // Insertar suscriptor
    echo "8. Creando suscriptor...<br>";
    $stmt = $db->prepare("
        INSERT INTO subscribers (email, first_name, last_name, country, domain, marketing_consent, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $signupData['email'],
        $signupData['firstName'],
        $signupData['lastName'],
        $signupData['country'],
        $signupData['domain'],
        isset($signupData['marketing']) ? 1 : 0
    ]);
    $subscriber_id = $db->lastInsertId();
    echo "8. ✅ Suscriptor creado con ID: $subscriber_id<br>";
    flush();
    
    // Obtener datos del suscriptor
    echo "9. Obteniendo datos del suscriptor...<br>";
    $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
    $stmt->execute([$subscriber_id]);
    $subscriberData = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "9. ✅ Datos del suscriptor obtenidos<br>";
    flush();
    
    // Generar clave de licencia
    echo "10. Generando clave de licencia...<br>";
    $new_license_key = 'FREE-' . strtoupper(substr(md5($subscriberData['email'] . time()), 0, 20));
    echo "10. ✅ Clave generada: $new_license_key<br>";
    flush();
    
    // Calcular fechas del ciclo
    echo "11. Calculando fechas del ciclo...<br>";
    $cycle_start = date('Y-m-d H:i:s');
    $cycle_end = date('Y-m-d H:i:s', strtotime('+1 year'));
    $cycle_end_date = date('d/m/Y', strtotime('+1 year'));
    echo "11. ✅ Ciclo: $cycle_start hasta $cycle_end<br>";
    flush();
    
    // Crear ciclo de facturación
    echo "12. Creando ciclo de facturación...<br>";
    $stmt = $db->prepare("
        INSERT INTO billing_cycles (subscriber_id, plan_type, amount, status, created_at, cycle_start, cycle_end) 
        VALUES (?, 'free', 0.00, 'paid', NOW(), ?, ?)
    ");
    $stmt->execute([$subscriber_id, $cycle_start, $cycle_end]);
    $billing_cycle_id = $db->lastInsertId();
    echo "12. ✅ Ciclo de facturación creado con ID: $billing_cycle_id<br>";
    flush();
    
    // Crear licencia
    echo "13. Creando licencia...<br>";
    $stmt = $db->prepare("
        INSERT INTO licenses (subscriber_id, billing_cycle_id, license_key, domain, plan_type, status, created_at, expires_at) 
        VALUES (?, ?, ?, ?, 'free', 'active', NOW(), ?)
    ");
    $stmt->execute([$subscriber_id, $billing_cycle_id, $new_license_key, $subscriberData['domain'], $cycle_end]);
    $license_id = $db->lastInsertId();
    echo "13. ✅ Licencia creada con ID: $license_id<br>";
    flush();
    
    // Confirmar transacción
    echo "14. Confirmando transacción...<br>";
    $db->commit();
    echo "14. ✅ Transacción confirmada<br>";
    flush();
    
    // Establecer sesión de usuario
    echo "15. Configurando sesión de usuario...<br>";
    $_SESSION['subscriber_id'] = $subscriber_id;
    $_SESSION['email'] = $subscriberData['email'];
    $_SESSION['first_name'] = $subscriberData['first_name'];
    $_SESSION['domain'] = $subscriberData['domain'];
    $_SESSION['plan_type'] = 'free';
    $_SESSION['license_key'] = $new_license_key;
    $_SESSION['is_logged_in'] = true;
    echo "15. ✅ Sesión configurada<br>";
    flush();
    
    // Enviar correo de bienvenida
    echo "16. Enviando correo de bienvenida...<br>";
    flush();
    
    try {
        echo "16a. Cargando PHPMailer...<br>";
        flush();
        
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        echo "16b. Creando instancia PHPMailer...<br>";
        flush();
        
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
        
        echo "16c. Configuración SMTP establecida...<br>";
        flush();
        
        // Configurar correo
        $mail->setFrom('support@dwoosync.com', 'DWooSync');
        $mail->addReplyTo('support@dwoosync.com', 'DWooSync');
        $mail->addAddress($subscriberData['email'], $subscriberData['first_name']);
        
        // Headers adicionales
        $mail->addCustomHeader('X-Mailer', 'DWooSync System v1.0');
        $mail->addCustomHeader('X-Priority', '3');
        $mail->addCustomHeader('Return-Path', 'support@dwoosync.com');
        
        echo "16d. Preparando contenido...<br>";
        flush();
        
        $planName = $isEnglish ? 'Free' : 'Gratuito';
        $subject = $isEnglish ? 'Welcome to DWooSync! - Your account has been activated' : '¡Bienvenido a DWooSync! - Tu cuenta ha sido activada';
        
        $message = $isEnglish ? 
            "<h3>Hello {$subscriberData['first_name']}!</h3><p>Welcome to DWooSync! Your account has been successfully activated.</p><p><strong>Plan:</strong> $planName<br><strong>License Key:</strong> $new_license_key<br><strong>Domain:</strong> {$subscriberData['domain']}<br><strong>Expires:</strong> $cycle_end_date</p><p>Thank you for choosing DWooSync!</p>" :
            "<h3>¡Hola {$subscriberData['first_name']}!</h3><p>¡Bienvenido a DWooSync! Tu cuenta ha sido activada exitosamente.</p><p><strong>Plan:</strong> $planName<br><strong>Clave de licencia:</strong> $new_license_key<br><strong>Dominio:</strong> {$subscriberData['domain']}<br><strong>Expira:</strong> $cycle_end_date</p><p>¡Gracias por elegir DWooSync!</p>";
        
        // Configurar contenido
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
        
        echo "16e. Enviando correo...<br>";
        flush();
        
        $result = $mail->send();
        
        if ($result) {
            echo "16. ✅ Correo enviado exitosamente a {$subscriberData['email']}<br>";
        } else {
            echo "16. ❌ Error enviando correo: " . $mail->ErrorInfo . "<br>";
        }
        
    } catch (Exception $e) {
        echo "16. ⚠️ Error con correo (proceso continúa): " . $e->getMessage() . "<br>";
    }
    
    flush();
    
    // Redireccionar al panel de usuario
    echo "17. ✅ Proceso completado exitosamente<br>";
    echo "<br><h3>🎉 ¡Cuenta Activada!</h3>";
    echo "<p><strong>Plan:</strong> " . ($isEnglish ? 'Free' : 'Gratuito') . "</p>";
    echo "<p><strong>Clave de licencia:</strong> $new_license_key</p>";
    echo "<p><strong>Dominio:</strong> {$subscriberData['domain']}</p>";
    echo "<p><strong>Válida hasta:</strong> $cycle_end_date</p>";
    echo "<br><p>Serás redirigido al panel de usuario en 5 segundos...</p>";
    echo "<script>setTimeout(function(){ window.location.href = 'dashboard.php?lang=$currentLang'; }, 5000);</script>";
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo "<br>❌ ERROR: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div></body></html>";
ob_end_flush();
?>