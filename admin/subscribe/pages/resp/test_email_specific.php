<?php
/**
 * Test específico para el envío de correo desde process_free_plan
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/functions.php';

startSecureSession();

echo "<h2>🔍 Test de Correo Específico</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

// Datos del usuario actual
$subscriber_id = $_SESSION['subscriber_id'] ?? null;
echo "1. Subscriber ID: $subscriber_id<br>";

if ($subscriber_id) {
    try {
        $db = getDatabase();
        $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
        $stmt->execute([$subscriber_id]);
        $subscriberData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscriberData) {
            echo "2. ✅ Datos del usuario:<br>";
            echo "   - Email: {$subscriberData['email']}<br>";
            echo "   - Nombre: {$subscriberData['first_name']}<br>";
            echo "   - Dominio: {$subscriberData['domain']}<br>";
            
            echo "<br>3. Testeando PHPMailer...<br>";
            
            try {
                require_once __DIR__ . '/email_phpmailer_smtp.php';
                echo "3a. ✅ PHPMailer cargado<br>";
                
                // Test simple primero
                echo "3b. Test simple de sendEmail...<br>";
                $testResult = sendEmail(
                    $subscriberData['email'],
                    'Test de DWooSync - Activación',
                    '<h3>Test Simple</h3><p>Este es un correo de prueba desde DWooSync.</p>',
                    true,
                    'DWooSync'
                );
                
                if ($testResult) {
                    echo "3b. ✅ Test simple exitoso<br>";
                } else {
                    echo "3b. ❌ Test simple falló<br>";
                }
                
                echo "<br>3c. Test con template completo...<br>";
                
                $new_license_key = 'FREE-TEST123';
                $cycle_end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
                $isEnglish = true;
                
                $planName = $isEnglish ? 'Free' : 'Gratuito';
                $subject = $isEnglish ? 'Welcome to DWooSync! - Your account has been activated' : '¡Bienvenido a DWooSync! - Tu cuenta ha sido activada';
                
                $message = $isEnglish ? 
                    "<h3>Hello {$subscriberData['first_name']}!</h3><p>Welcome to DWooSync! Your account has been successfully activated.</p><p><strong>Plan:</strong> $planName<br><strong>License Key:</strong> $new_license_key<br><strong>Domain:</strong> {$subscriberData['domain']}<br><strong>Expires:</strong> $cycle_end_date</p><p>Thank you for choosing DWooSync!</p>" :
                    "<h3>¡Hola {$subscriberData['first_name']}!</h3><p>¡Bienvenido a DWooSync! Tu cuenta ha sido activada exitosamente.</p><p><strong>Plan:</strong> $planName<br><strong>Clave de licencia:</strong> $new_license_key<br><strong>Dominio:</strong> {$subscriberData['domain']}<br><strong>Expira:</strong> $cycle_end_date</p><p>¡Gracias por elegir DWooSync!</p>";
                
                echo "3c. Enviando correo completo...<br>";
                flush();
                
                $fullResult = sendEmail($subscriberData['email'], $subject, $message, true, 'DWooSync');
                
                if ($fullResult) {
                    echo "3c. ✅ Correo completo enviado exitosamente<br>";
                    echo "<br><strong>🎉 El correo debería llegar en unos minutos</strong><br>";
                    echo "Revisa tu bandeja de entrada y spam.<br>";
                } else {
                    echo "3c. ❌ Error enviando correo completo<br>";
                }
                
            } catch (Exception $e) {
                echo "❌ Error con PHPMailer: " . $e->getMessage() . "<br>";
                echo "Archivo: " . $e->getFile() . "<br>";
                echo "Línea: " . $e->getLine() . "<br>";
            }
            
        } else {
            echo "2. ❌ No se encontró el usuario<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error de BD: " . $e->getMessage() . "<br>";
    }
} else {
    echo "1. ❌ No hay subscriber_id en sesión<br>";
}

echo "<br><h3>📋 Verificaciones adicionales:</h3>";

// Verificar DNS
echo "4. Verificando configuración DNS...<br>";
try {
    $dnsConfig = checkEmailDNSConfiguration('dwoosync.com');
    echo "   - SPF: " . ($dnsConfig['spf'] !== 'NO CONFIGURADO' ? '✅ Configurado' : '❌ No configurado') . "<br>";
    echo "   - DMARC: " . ($dnsConfig['dmarc'] !== 'NO CONFIGURADO' ? '✅ Configurado' : '❌ No configurado') . "<br>";
    echo "   - MX: " . ($dnsConfig['mx'] === 'CONFIGURADO' ? '✅ Configurado' : '❌ No configurado') . "<br>";
} catch (Exception $e) {
    echo "   - Error verificando DNS: " . $e->getMessage() . "<br>";
}

echo "</div>";
?>