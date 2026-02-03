<?php
/**
 * Test paso a paso de PHPMailer
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(60); // 60 segundos de timeout

echo "<h2>🔍 Test Específico de PHPMailer</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

try {
    echo "1. Verificando autoload...<br>";
    flush();
    
    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    echo "   Ruta: $autoloadPath<br>";
    
    if (file_exists($autoloadPath)) {
        echo "1. ✅ Archivo autoload existe<br>";
        flush();
        
        echo "2. Cargando autoload...<br>";
        require_once $autoloadPath;
        echo "2. ✅ Autoload cargado<br>";
        flush();
        
        echo "3. Verificando clases PHPMailer...<br>";
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "3. ✅ Clase PHPMailer disponible<br>";
            flush();
            
            echo "4. Creando instancia PHPMailer...<br>";
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            echo "4. ✅ Instancia PHPMailer creada<br>";
            flush();
            
            echo "5. Configurando SMTP básico...<br>";
            $mail->isSMTP();
            $mail->Host = 'mail.dwoosync.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'support@dwoosync.com';
            $mail->Password = '5802863aA$$';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';
            echo "5. ✅ Configuración SMTP establecida<br>";
            flush();
            
            echo "6. Configurando correo de prueba...<br>";
            $mail->setFrom('support@dwoosync.com', 'DWooSync Test');
            $mail->addAddress('juanand87@gmail.com', 'Pedro');
            $mail->isHTML(true);
            $mail->Subject = 'Test PHPMailer - DWooSync';
            $mail->Body = '<h3>Test PHPMailer</h3><p>Este es un correo de prueba para verificar PHPMailer.</p>';
            echo "6. ✅ Correo configurado<br>";
            flush();
            
            echo "7. Enviando correo (esto puede tomar unos segundos)...<br>";
            flush();
            
            $result = $mail->send();
            
            if ($result) {
                echo "7. ✅ Correo enviado exitosamente<br>";
                echo "<br><strong>🎉 PHPMailer está funcionando correctamente</strong><br>";
                echo "El correo debería llegar en unos minutos.<br>";
            } else {
                echo "7. ❌ Error enviando correo<br>";
                echo "Info: " . $mail->ErrorInfo . "<br>";
            }
            
        } else {
            echo "3. ❌ Clase PHPMailer NO disponible<br>";
            echo "Clases disponibles:<br>";
            $classes = get_declared_classes();
            $phpmailerClasses = array_filter($classes, function($class) {
                return strpos($class, 'PHPMailer') !== false;
            });
            echo "<pre>" . print_r($phpmailerClasses, true) . "</pre>";
        }
        
    } else {
        echo "1. ❌ Archivo autoload NO existe<br>";
        echo "Verificar que composer está instalado en el directorio raíz<br>";
    }
    
} catch (Exception $e) {
    echo "<br>❌ ERROR EXCEPTION: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<br>❌ ERROR FATAL: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<br><h3>📋 Información adicional:</h3>";
echo "- PHP Version: " . phpversion() . "<br>";
echo "- Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "- Max Execution Time: " . ini_get('max_execution_time') . "<br>";
echo "- SMTP Extension: " . (extension_loaded('openssl') ? '✅ OpenSSL disponible' : '❌ OpenSSL no disponible') . "<br>";

echo "</div>";
?>