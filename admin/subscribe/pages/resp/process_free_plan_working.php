<?php
/**
 * Procesar activación de plan gratuito - Versión con debug completo
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
    startSecureSession();
    echo "3. ✅ Sesión iniciada<br>";
    flush();
    
    // Detectar idioma
    $currentLang = $_SESSION['selected_language'] ?? $_GET['lang'] ?? 'es';
    $isEnglish = ($currentLang === 'en');
    echo "4. ✅ Idioma: $currentLang<br>";
    flush();
    
    // Verificar parámetros de URL
    $billing_cycle_id = $_GET['billing_cycle_id'] ?? null;
    echo "5. Parámetros URL - billing_cycle_id: " . ($billing_cycle_id ?? 'NULL') . "<br>";
    flush();
    
    // Verificar login
    echo "6. Verificando login...<br>";
    if (!isLoggedIn()) {
        echo "6. ❌ Usuario no logueado, redirigiendo...<br>";
        echo "<a href='login.php?lang=$currentLang'>Ir a Login</a>";
        exit;
    }
    echo "6. ✅ Usuario logueado<br>";
    flush();
    
    // Obtener datos de sesión
    $subscriber_id = $_SESSION['subscriber_id'] ?? null;
    $license_key = $_SESSION['license_key'] ?? null;
    echo "7. Datos sesión - subscriber_id: $subscriber_id, license_key: " . ($license_key ?? 'NULL') . "<br>";
    flush();
    
    if (!$subscriber_id) {
        echo "7. ❌ No hay subscriber_id<br>";
        exit;
    }
    
    // Conectar a BD
    echo "8. Conectando a base de datos...<br>";
    $db = getDatabase();
    echo "8. ✅ Conectado a BD<br>";
    flush();
    
    // Verificar subscriber
    echo "9. Verificando subscriber en BD...<br>";
    $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
    $stmt->execute([$subscriber_id]);
    $subscriberData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subscriberData) {
        echo "9. ❌ Subscriber no existe<br>";
        exit;
    }
    echo "9. ✅ Subscriber existe: " . $subscriberData['email'] . "<br>";
    flush();
    
    // Iniciar transacción
    echo "10. Iniciando transacción...<br>";
    $db->beginTransaction();
    echo "10. ✅ Transacción iniciada<br>";
    flush();
    
    // Actualizar subscriber
    echo "11. Actualizando subscriber...<br>";
    $stmt = $db->prepare("UPDATE subscribers SET status = 'active', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$subscriber_id]);
    echo "11. ✅ Subscriber actualizado<br>";
    flush();
    
    // Actualizar licencia si existe
    if ($license_key) {
        echo "12. Actualizando licencia existente...<br>";
        $stmt = $db->prepare("UPDATE licenses SET status = 'active', usage_limit = 10, updated_at = NOW() WHERE license_key = ?");
        $stmt->execute([$license_key]);
        echo "12. ✅ Licencia actualizada<br>";
    } else {
        echo "12. ⚠️ No hay license_key para actualizar<br>";
    }
    flush();
    
    // Calcular fechas
    $cycle_start_date = date('Y-m-d H:i:s');
    $cycle_end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
    echo "13. ✅ Fechas calculadas: $cycle_start_date a $cycle_end_date<br>";
    flush();
    
    // Desactivar ciclos anteriores
    echo "14. Desactivando ciclos anteriores...<br>";
    $stmt = $db->prepare("UPDATE billing_cycles SET is_active = 0 WHERE subscriber_id = ?");
    $stmt->execute([$subscriber_id]);
    echo "14. ✅ Ciclos anteriores desactivados<br>";
    flush();
    
    // Generar nueva license key
    $new_license_key = 'FREE-' . strtoupper(substr(md5(uniqid($subscriber_id, true)), 0, 12));
    echo "15. ✅ Nueva license key: $new_license_key<br>";
    flush();
    
    // Crear nuevo ciclo de facturación
    echo "16. Creando ciclo de facturación...<br>";
    $stmt = $db->prepare("
        INSERT INTO billing_cycles 
        (subscriber_id, plan_type, amount, license_key, cycle_start_date, cycle_end_date, due_date, status, is_active, created_at) 
        VALUES (?, 'free', 0.00, ?, ?, ?, ?, 'paid', 1, NOW())
    ");
    $stmt->execute([$subscriber_id, $new_license_key, $cycle_start_date, $cycle_end_date, $cycle_end_date]);
    echo "16. ✅ Ciclo de facturación creado<br>";
    flush();
    
    // Actualizar/crear licencia
    echo "17. Actualizando licencia con nueva key...<br>";
    $stmt = $db->prepare("
        UPDATE licenses 
        SET license_key = ?, status = 'active', expires_at = ?, usage_limit = 10, updated_at = NOW()
        WHERE subscriber_id = ?
    ");
    $stmt->execute([$new_license_key, $cycle_end_date, $subscriber_id]);
    
    // Si no existe licencia, crearla
    if ($stmt->rowCount() == 0) {
        echo "17a. Creando nueva licencia...<br>";
        $stmt = $db->prepare("
            INSERT INTO licenses 
            (subscriber_id, license_key, status, expires_at, usage_limit, created_at) 
            VALUES (?, ?, 'active', ?, 10, NOW())
        ");
        $stmt->execute([$subscriber_id, $new_license_key, $cycle_end_date]);
        echo "17a. ✅ Nueva licencia creada<br>";
    } else {
        echo "17. ✅ Licencia actualizada<br>";
    }
    flush();
    
    // Confirmar transacción
    echo "18. Confirmando transacción...<br>";
    $db->commit();
    echo "18. ✅ Transacción confirmada<br>";
    flush();
    
    // Enviar correo
    echo "19. Enviando correo de bienvenida...<br>";
    flush();
    
    try {
        // Timeout para el correo
        set_time_limit(30);
        
        require_once __DIR__ . '/email_phpmailer_smtp.php';
        
        $planName = $isEnglish ? 'Free' : 'Gratuito';
        $subject = $isEnglish ? 'Welcome to DWooSync! - Your account has been activated' : '¡Bienvenido a DWooSync! - Tu cuenta ha sido activada';
        
        $message = $isEnglish ? 
            "<h3>Hello {$subscriberData['first_name']}!</h3><p>Welcome to DWooSync! Your account has been successfully activated.</p><p><strong>Plan:</strong> $planName<br><strong>License Key:</strong> $new_license_key<br><strong>Domain:</strong> {$subscriberData['domain']}<br><strong>Expires:</strong> $cycle_end_date</p><p>Thank you for choosing DWooSync!</p>" :
            "<h3>¡Hola {$subscriberData['first_name']}!</h3><p>¡Bienvenido a DWooSync! Tu cuenta ha sido activada exitosamente.</p><p><strong>Plan:</strong> $planName<br><strong>Clave de licencia:</strong> $new_license_key<br><strong>Dominio:</strong> {$subscriberData['domain']}<br><strong>Expira:</strong> $cycle_end_date</p><p>¡Gracias por elegir DWooSync!</p>";
        
        $emailResult = sendEmail($subscriberData['email'], $subject, $message, true, 'DWooSync');
        
        if ($emailResult) {
            echo "19. ✅ Correo enviado exitosamente a {$subscriberData['email']}<br>";
        } else {
            echo "19. ⚠️ Error enviando correo (proceso continuará)<br>";
        }
        
    } catch (Exception $e) {
        echo "19. ⚠️ Error con correo: " . $e->getMessage() . " (proceso continuará)<br>";
    }
    
    // Forzar continuar
    flush();
    
    echo "<br><h3>🎉 ¡PROCESO COMPLETADO EXITOSAMENTE!</h3>";
    echo "<p>Tu plan gratuito ha sido activado correctamente.</p>";
    echo "<p>Redirigiendo al dashboard en 3 segundos...</p>";
    echo "<script>setTimeout(function(){ window.location.href='dashboard.php?lang=$currentLang&success=account_activated'; }, 3000);</script>";
    echo "<a href='dashboard.php?lang=$currentLang&success=account_activated' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>Ir al Dashboard Ahora</a>";
    
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