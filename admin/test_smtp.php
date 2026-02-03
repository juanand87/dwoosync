<?php
/**
 * Prueba de configuración SMTP
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración
require_once __DIR__ . '/subscribe/includes/config.php';
require_once __DIR__ . '/subscribe/includes/email.php';

echo "<h2>Prueba de Configuración SMTP</h2>";

// Verificar configuración
echo "<h3>Configuración actual:</h3>";
echo "<ul>";
echo "<li><strong>SMTP_HOST:</strong> " . (defined('SMTP_HOST') ? SMTP_HOST : 'No definido') . "</li>";
echo "<li><strong>SMTP_PORT:</strong> " . (defined('SMTP_PORT') ? SMTP_PORT : 'No definido') . "</li>";
echo "<li><strong>SMTP_USERNAME:</strong> " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'No definido') . "</li>";
echo "<li><strong>SMTP_PASSWORD:</strong> " . (defined('SMTP_PASSWORD') && !empty(SMTP_PASSWORD) ? 'Configurado' : 'No configurado') . "</li>";
echo "<li><strong>FROM_EMAIL:</strong> " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'No definido') . "</li>";
echo "<li><strong>FROM_NAME:</strong> " . (defined('FROM_NAME') ? FROM_NAME : 'No definido') . "</li>";
echo "</ul>";

// Verificar si PHPMailer está disponible
echo "<h3>Verificación de PHPMailer:</h3>";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "<p style='color: green;'>✅ PHPMailer está disponible</p>";
} else {
    echo "<p style='color: red;'>❌ PHPMailer NO está disponible</p>";
}

// Probar envío de email (solo si las credenciales están configuradas)
if (defined('SMTP_USERNAME') && !empty(SMTP_USERNAME) && SMTP_USERNAME !== 'your-email@gmail.com') {
    echo "<h3>Prueba de envío de email:</h3>";
    
    $testEmail = 'juanand87@gmail.com'; // Cambiar por tu email de prueba
    $subject = 'Prueba SMTP - DWooSync';
    $message = '<h2>Prueba de configuración SMTP</h2><p>Este es un email de prueba para verificar que la configuración SMTP funciona correctamente.</p>';
    
    echo "<p>Enviando email de prueba a: <strong>$testEmail</strong></p>";
    
    try {
        $result = sendEmailSMTP($testEmail, $subject, $message);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Email enviado exitosamente</p>";
        } else {
            echo "<p style='color: red;'>❌ Error al enviar email</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<h3>⚠️ Configuración pendiente:</h3>";
    echo "<p>Para probar el envío de emails, necesitas configurar las credenciales SMTP en <code>subscribe/includes/config.php</code>:</p>";
    echo "<ul>";
    echo "<li>Cambiar <code>SMTP_USERNAME</code> por tu email de Gmail</li>";
    echo "<li>Cambiar <code>SMTP_PASSWORD</code> por tu contraseña de aplicación de Gmail</li>";
    echo "</ul>";
    echo "<p><strong>Nota:</strong> Para Gmail, necesitas generar una 'contraseña de aplicación' en lugar de usar tu contraseña normal.</p>";
}

echo "<hr>";
echo "<p><a href='subscribe/pages/contact.php'>Ir al formulario de contacto</a></p>";
?>



