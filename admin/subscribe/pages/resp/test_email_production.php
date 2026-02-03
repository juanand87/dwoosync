<?php
/**
 * Test de envío de correo usando la misma configuración que exito.php
 * Sube este archivo y ejecuta: https://dwoosync.com/subscribe/pages/test_email_production.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST DE ENVÍO DE CORREO EN PRODUCCIÓN ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Email de destino para la prueba (cambia por uno tuyo)
$testEmail = 'juanand87@gmail.com'; // Cambia por tu email real
$testSubject = 'Test DWooSync - ' . date('Y-m-d H:i:s');
$testMessage = '<h3>Prueba de correo desde DWooSync</h3><p>Este es un correo de prueba enviado desde el servidor de producción usando PHPMailer.</p><p>Fecha: ' . date('Y-m-d H:i:s') . '</p>';

echo "Enviando correo de prueba a: $testEmail\n";
echo "Asunto: $testSubject\n\n";

// 1. Verificar que existe el archivo PHPMailer
$phpmailerPath = __DIR__ . '/email_phpmailer_smtp.php';
if (!file_exists($phpmailerPath)) {
    echo "❌ ERROR: No existe $phpmailerPath\n";
    echo "El archivo PHPMailer no está en el servidor.\n";
    exit;
}

echo "✅ Archivo PHPMailer encontrado\n";

// 2. Cargar PHPMailer
try {
    require_once $phpmailerPath;
    echo "✅ PHPMailer cargado correctamente\n";
} catch (Exception $e) {
    echo "❌ ERROR cargando PHPMailer: " . $e->getMessage() . "\n";
    exit;
}

// 3. Verificar función sendEmail
if (!function_exists('sendEmail')) {
    echo "❌ ERROR: La función sendEmail() no existe\n";
    echo "El archivo PHPMailer no tiene la función correcta.\n";
    exit;
}

echo "✅ Función sendEmail() disponible\n";

// 4. Intentar envío
echo "\n--- INICIANDO ENVÍO ---\n";
$startTime = microtime(true);

try {
    $result = sendEmail($testEmail, $testSubject, $testMessage, true, 'DWooSync Test');
    
    $duration = round(microtime(true) - $startTime, 2);
    
    if ($result) {
        echo "✅ ÉXITO: Correo enviado correctamente\n";
        echo "Duración: {$duration}s\n";
        echo "\nRevisa tu bandeja de entrada (y spam) en: $testEmail\n";
        
        // Registrar en el log
        $logPath = __DIR__ . '/../logs/email.log';
        $logLine = "[" . date('Y-m-d H:i:s') . "] TEST EMAIL SUCCESS TO: $testEmail DURATION: {$duration}s\n";
        @file_put_contents($logPath, $logLine, FILE_APPEND);
        
    } else {
        echo "❌ FALLO: No se pudo enviar el correo\n";
        echo "Duración: {$duration}s\n";
        echo "Revisa los logs del servidor para más detalles.\n";
        
        // Registrar en el log
        $logPath = __DIR__ . '/../logs/email.log';
        $logLine = "[" . date('Y-m-d H:i:s') . "] TEST EMAIL FAILED TO: $testEmail DURATION: {$duration}s\n";
        @file_put_contents($logPath, $logLine, FILE_APPEND);
    }
    
} catch (Exception $e) {
    $duration = round(microtime(true) - $startTime, 2);
    echo "❌ EXCEPCIÓN: " . $e->getMessage() . "\n";
    echo "Duración: {$duration}s\n";
    
    // Registrar en el log
    $logPath = __DIR__ . '/../logs/email.log';
    $logLine = "[" . date('Y-m-d H:i:s') . "] TEST EMAIL EXCEPTION TO: $testEmail ERROR: " . $e->getMessage() . "\n";
    @file_put_contents($logPath, $logLine, FILE_APPEND);
}

echo "\n--- FIN DEL TEST ---\n";

// 5. Mostrar últimas líneas del log
echo "\nÚltimas líneas del log de email:\n";
echo "----------------------------------------\n";
$logPath = __DIR__ . '/../logs/email.log';
if (file_exists($logPath)) {
    $lines = file($logPath);
    if ($lines) {
        $lastLines = array_slice($lines, -5);
        foreach ($lastLines as $line) {
            echo $line;
        }
    }
} else {
    echo "No se encontró el archivo de log\n";
}
echo "----------------------------------------\n";

echo "\nSi el test falla, revisa:\n";
echo "1. Credenciales SMTP en email_phpmailer_smtp.php\n";
echo "2. Logs del servidor de correo (mail.dwoosync.com)\n";
echo "3. Firewall/puertos (465 para SMTP SSL)\n";
echo "4. DNS y autenticación del dominio\n";

?>