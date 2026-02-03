<?php
/**
 * Diagnóstico específico de PHPMailer SMTP
 */

echo "<h2>Diagnóstico de PHPMailer SMTP</h2>";

// Cargar PHPMailer desde Composer
require_once __DIR__ . '/vendor/autoload.php';

// Verificar si PHPMailer está disponible
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "<p style='color: red;'><strong>✗ PHPMailer NO está disponible</strong></p>";
    echo "<p>Necesitas instalar PHPMailer:</p>";
    echo "<pre>composer require phpmailer/phpmailer</pre>";
    exit;
}

echo "<p style='color: green;'><strong>✓ PHPMailer está disponible</strong></p>";

// Configuración SMTP
$smtp_host = 'mail.dwoosync.com';
$smtp_port = 465;
$smtp_username = 'support@dwoosync.com';
$smtp_password = '5802863aA$$';
$from_email = 'support@dwoosync.com';
$from_name = 'DWooSync';

echo "<h3>Configuración SMTP:</h3>";
echo "<p><strong>Host:</strong> $smtp_host</p>";
echo "<p><strong>Puerto:</strong> $smtp_port</p>";
echo "<p><strong>Usuario:</strong> $smtp_username</p>";
echo "<p><strong>Contraseña:</strong> " . str_repeat('*', strlen($smtp_password)) . "</p>";

// Probar diferentes configuraciones
$configurations = [
    [
        'name' => 'SMTPS (Puerto 465)',
        'host' => $smtp_host,
        'port' => 465,
        'secure' => 'ssl',
        'auth' => true
    ],
    [
        'name' => 'STARTTLS (Puerto 587)',
        'host' => $smtp_host,
        'port' => 587,
        'secure' => 'tls',
        'auth' => true
    ],
    [
        'name' => 'SMTP (Puerto 25)',
        'host' => $smtp_host,
        'port' => 25,
        'secure' => '',
        'auth' => true
    ],
    [
        'name' => 'Sin autenticación',
        'host' => $smtp_host,
        'port' => 587,
        'secure' => 'tls',
        'auth' => false
    ]
];

$testEmail = 'test@example.com'; // Cambia por tu email real
$subject = 'Test PHPMailer SMTP';
$message = '<h3>Test PHPMailer</h3><p>This is a test email from PHPMailer SMTP.</p>';

echo "<h3>Probando diferentes configuraciones:</h3>";

foreach ($configurations as $config) {
    echo "<h4>" . $config['name'] . ":</h4>";
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuración básica
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->SMTPAuth = $config['auth'];
        
        if ($config['secure']) {
            $mail->SMTPSecure = $config['secure'];
        }
        
        if ($config['auth']) {
            $mail->Username = $smtp_username;
            $mail->Password = $smtp_password;
        }
        
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 30;
        
        // Configurar remitente y destinatario
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($testEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // Habilitar debug
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            echo "<pre style='background: #f5f5f5; padding: 10px; margin: 5px 0; border-radius: 4px; font-size: 12px;'>$str</pre>";
        };
        
        echo "<p>Intentando conectar...</p>";
        
        // Intentar envío
        $result = $mail->send();
        
        if ($result) {
            echo "<p style='color: green;'><strong>✓ Exitoso con " . $config['name'] . "</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>✗ Falló con " . $config['name'] . "</strong></p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>✗ Error con " . $config['name'] . ": " . $e->getMessage() . "</strong></p>";
    }
    
    echo "<hr>";
}

// Verificar conectividad del servidor
echo "<h3>Verificación de conectividad:</h3>";

$ports = [25, 587, 465];
foreach ($ports as $port) {
    $connection = @fsockopen($smtp_host, $port, $errno, $errstr, 10);
    if ($connection) {
        echo "<p style='color: green;'>✓ Puerto $port está abierto</p>";
        fclose($connection);
    } else {
        echo "<p style='color: red;'>✗ Puerto $port está cerrado o no accesible</p>";
    }
}

// Verificar DNS
echo "<h3>Verificación DNS:</h3>";
$mx_records = dns_get_record($smtp_host, DNS_MX);
if ($mx_records) {
    echo "<p style='color: green;'>✓ Registros MX encontrados:</p>";
    foreach ($mx_records as $mx) {
        echo "<p>- " . $mx['target'] . " (prioridad: " . $mx['pri'] . ")</p>";
    }
} else {
    echo "<p style='color: red;'>✗ No se encontraron registros MX para $smtp_host</p>";
}

// Verificar configuración de PHP
echo "<h3>Configuración de PHP:</h3>";
echo "<p><strong>OpenSSL:</strong> " . (extension_loaded('openssl') ? "✓ Disponible" : "✗ No disponible") . "</p>";
echo "<p><strong>Socket:</strong> " . (extension_loaded('sockets') ? "✓ Disponible" : "✗ No disponible") . "</p>";
echo "<p><strong>cURL:</strong> " . (function_exists('curl_init') ? "✓ Disponible" : "✗ No disponible") . "</p>";

// Información del servidor
echo "<h3>Información del servidor:</h3>";
echo "<p><strong>Servidor:</strong> " . $_SERVER['SERVER_NAME'] . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>OS:</strong> " . php_uname() . "</p>";
echo "<p><strong>IP del servidor:</strong> " . $_SERVER['SERVER_ADDR'] . "</p>";

// Verificar si el servidor puede hacer conexiones salientes
echo "<h3>Verificación de conexiones salientes:</h3>";
$testHosts = [
    'google.com' => 80,
    'smtp.gmail.com' => 587,
    'mail.dwoosync.com' => 587
];

foreach ($testHosts as $host => $port) {
    $connection = @fsockopen($host, $port, $errno, $errstr, 5);
    if ($connection) {
        echo "<p style='color: green;'>✓ Puede conectar a $host:$port</p>";
        fclose($connection);
    } else {
        echo "<p style='color: red;'>✗ No puede conectar a $host:$port ($errstr)</p>";
    }
}
?>
