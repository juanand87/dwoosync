<?php
/**
 * Script de diagnóstico para verificar la configuración de exito.php
 * Sube este archivo al servidor y ejecuta: https://dwoosync.com/subscribe/pages/diagnostic_exito.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE EXITO.PHP ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Verificar que exito.php existe
$exitoPath = __DIR__ . '/exito.php';
echo "1. ARCHIVO EXITO.PHP\n";
echo "Ruta: $exitoPath\n";
echo "Existe: " . (file_exists($exitoPath) ? 'SÍ' : 'NO') . "\n";
if (file_exists($exitoPath)) {
    echo "Tamaño: " . filesize($exitoPath) . " bytes\n";
    echo "Última modificación: " . date('Y-m-d H:i:s', filemtime($exitoPath)) . "\n";
}
echo "\n";

// 2. Verificar si tiene la programación diferida
echo "2. VERIFICACIÓN DE CÓDIGO DIFERIDO\n";
if (file_exists($exitoPath)) {
    $content = file_get_contents($exitoPath);
    
    $hasDeferredEmail = strpos($content, '$deferredEmail') !== false;
    $hasPhpMailerRequire = strpos($content, 'email_phpmailer_smtp.php') !== false;
    $hasFastCGI = strpos($content, 'fastcgi_finish_request') !== false;
    $hasDebugLogs = strpos($content, 'DEFERRED EMAIL') !== false;
    
    echo "Tiene \$deferredEmail: " . ($hasDeferredEmail ? 'SÍ' : 'NO') . "\n";
    echo "Incluye PHPMailer: " . ($hasPhpMailerRequire ? 'SÍ' : 'NO') . "\n";
    echo "Usa fastcgi_finish_request: " . ($hasFastCGI ? 'SÍ' : 'NO') . "\n";
    echo "Tiene logs DEFERRED EMAIL: " . ($hasDebugLogs ? 'SÍ' : 'NO') . "\n";
    
    if (!$hasDeferredEmail) {
        echo "\n⚠️  PROBLEMA: exito.php NO tiene la programación diferida\n";
        echo "El archivo en el servidor es diferente al local.\n";
    }
} else {
    echo "No se puede leer el archivo\n";
}
echo "\n";

// 3. Verificar PHPMailer
echo "3. VERIFICACIÓN DE PHPMAILER\n";
$phpmailerPath = __DIR__ . '/email_phpmailer_smtp.php';
echo "Archivo PHPMailer: " . (file_exists($phpmailerPath) ? 'SÍ' : 'NO') . "\n";

// Verificar composer autoload
$composerPath = __DIR__ . '/../../vendor/autoload.php';
echo "Composer autoload: " . (file_exists($composerPath) ? 'SÍ' : 'NO') . "\n";

// Intentar cargar PHPMailer
try {
    if (file_exists($composerPath)) {
        require_once $composerPath;
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            echo "Clase PHPMailer: DISPONIBLE\n";
        } else {
            echo "Clase PHPMailer: NO DISPONIBLE\n";
        }
    }
} catch (Exception $e) {
    echo "Error cargando PHPMailer: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Verificar logs
echo "4. VERIFICACIÓN DE LOGS\n";
$logPath = __DIR__ . '/../logs/email.log';
echo "Log de email: " . (file_exists($logPath) ? 'SÍ' : 'NO') . "\n";
if (file_exists($logPath)) {
    echo "Tamaño del log: " . filesize($logPath) . " bytes\n";
    echo "Última modificación: " . date('Y-m-d H:i:s', filemtime($logPath)) . "\n";
    
    // Mostrar últimas 10 líneas
    $lines = file($logPath);
    if ($lines) {
        echo "\nÚltimas 10 líneas del log:\n";
        echo "----------------------------------------\n";
        $lastLines = array_slice($lines, -10);
        foreach ($lastLines as $line) {
            echo $line;
        }
        echo "----------------------------------------\n";
    }
}
echo "\n";

// 5. Test de función sendEmail
echo "5. TEST DE FUNCIÓN SENDEMAIL\n";
try {
    if (file_exists($phpmailerPath)) {
        include_once $phpmailerPath;
        if (function_exists('sendEmail')) {
            echo "Función sendEmail: DISPONIBLE\n";
            
            // Test básico (sin enviar realmente)
            echo "Intentando test básico...\n";
            
            // Solo verificar que la función existe y se puede llamar
            echo "La función sendEmail está lista para usar\n";
        } else {
            echo "Función sendEmail: NO DISPONIBLE\n";
        }
    } else {
        echo "No se puede cargar email_phpmailer_smtp.php\n";
    }
} catch (Exception $e) {
    echo "Error en test de sendEmail: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Información del servidor
echo "6. INFORMACIÓN DEL SERVIDOR\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Desconocido') . "\n";
echo "Script Path: " . __DIR__ . "\n";
echo "\n";

// 7. Verificar permisos de escritura
echo "7. PERMISOS DE ESCRITURA\n";
$testFile = __DIR__ . '/../logs/test_write.txt';
try {
    if (file_put_contents($testFile, 'test')) {
        echo "Escritura en logs: OK\n";
        unlink($testFile);
    } else {
        echo "Escritura en logs: FALLO\n";
    }
} catch (Exception $e) {
    echo "Escritura en logs: ERROR - " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
echo "\nSi ves 'exito.php NO tiene la programación diferida', necesitas subir el archivo actualizado al servidor.\n";
echo "Si todo está OK pero no envía correos, revisa los logs del servidor de correo (mail.dwoosync.com).\n";
?>