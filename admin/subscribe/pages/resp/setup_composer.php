<?php
/**
 * Configurar Composer y instalar PHPMailer
 */

echo "<h2>Configuración de Composer y PHPMailer</h2>";

$currentDir = getcwd();
$composerPhar = $currentDir . '/composer.phar';

echo "<p><strong>Directorio actual:</strong> $currentDir</p>";
echo "<p><strong>Composer phar:</strong> $composerPhar</p>";

// Verificar si composer.phar existe
if (!file_exists($composerPhar)) {
    echo "<p style='color: red;'>✗ composer.phar no encontrado. Descargando...</p>";
    
    // Descargar composer.phar
    $composerUrl = 'https://getcomposer.org/composer.phar';
    $composerContent = file_get_contents($composerUrl);
    
    if ($composerContent) {
        file_put_contents($composerPhar, $composerContent);
        chmod($composerPhar, 0755);
        echo "<p style='color: green;'>✓ composer.phar descargado exitosamente</p>";
    } else {
        echo "<p style='color: red;'>✗ Error descargando composer.phar</p>";
        exit;
    }
} else {
    echo "<p style='color: green;'>✓ composer.phar ya existe</p>";
}

// Crear composer.json
echo "<h3>1. Creando composer.json:</h3>";
$composerJson = [
    "require" => [
        "phpmailer/phpmailer" => "^6.8"
    ],
    "config" => [
        "optimize-autoloader" => true
    ]
];

$composerJsonPath = $currentDir . '/composer.json';
file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT));
echo "<p style='color: green;'>✓ composer.json creado</p>";

// Ejecutar composer install
echo "<h3>2. Instalando PHPMailer:</h3>";
$command = "php $composerPhar install --no-dev --optimize-autoloader 2>&1";
echo "<p><strong>Ejecutando:</strong> $command</p>";

$output = [];
$returnCode = 0;
exec($command, $output, $returnCode);

echo "<h4>Salida del comando:</h4>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px;'>";
foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}
echo "</pre>";

if ($returnCode === 0) {
    echo "<p style='color: green;'>✓ PHPMailer instalado exitosamente</p>";
} else {
    echo "<p style='color: red;'>✗ Error instalando PHPMailer (código: $returnCode)</p>";
}

// Verificar instalación
echo "<h3>3. Verificando instalación:</h3>";

// Verificar vendor/autoload.php
$vendorAutoload = $currentDir . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    echo "<p style='color: green;'>✓ vendor/autoload.php creado</p>";
} else {
    echo "<p style='color: red;'>✗ vendor/autoload.php no encontrado</p>";
}

// Verificar PHPMailer
if (file_exists($currentDir . '/vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    echo "<p style='color: green;'>✓ PHPMailer instalado</p>";
} else {
    echo "<p style='color: red;'>✗ PHPMailer no encontrado</p>";
}

// Probar PHPMailer
echo "<h3>4. Probando PHPMailer:</h3>";
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
    
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo "<p style='color: green;'>✓ PHPMailer cargado correctamente</p>";
        
        // Probar configuración SMTP
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'mail.dwoosync.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'support@dwoosync.com';
            $mail->Password = '5802863aA$$';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->CharSet = 'UTF-8';
            
            echo "<p style='color: green;'>✓ PHPMailer configurado correctamente</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error configurando PHPMailer: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ PHPMailer no se pudo cargar</p>";
    }
} else {
    echo "<p style='color: red;'>✗ No se puede cargar vendor/autoload.php</p>";
}

// Mostrar estructura de archivos
echo "<h3>5. Estructura de archivos creada:</h3>";
$files = [
    'composer.json',
    'composer.phar',
    'vendor/autoload.php',
    'vendor/phpmailer/phpmailer/src/PHPMailer.php'
];

foreach ($files as $file) {
    $fullPath = $currentDir . '/' . $file;
    if (file_exists($fullPath)) {
        echo "<p style='color: green;'>✓ $file</p>";
    } else {
        echo "<p style='color: red;'>✗ $file</p>";
    }
}

// Instrucciones de uso
echo "<h3>6. Instrucciones de uso:</h3>";
echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<p><strong>Para usar PHPMailer en tus scripts:</strong></p>";
echo "<pre>";
echo "require_once __DIR__ . '/vendor/autoload.php';\n";
echo "\n";
echo "use PHPMailer\\PHPMailer\\PHPMailer;\n";
echo "use PHPMailer\\PHPMailer\\SMTP;\n";
echo "use PHPMailer\\PHPMailer\\Exception;\n";
echo "\n";
echo "\$mail = new PHPMailer(true);\n";
echo "\$mail->isSMTP();\n";
echo "\$mail->Host = 'mail.dwoosync.com';\n";
echo "\$mail->SMTPAuth = true;\n";
echo "\$mail->Username = 'support@dwoosync.com';\n";
echo "\$mail->Password = '5802863aA$$';\n";
echo "\$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;\n";
echo "\$mail->Port = 465;\n";
echo "</pre>";
echo "</div>";

echo "<h3>7. Próximos pasos:</h3>";
echo "<ol>";
echo "<li>Ejecuta <a href='debug_phpmailer.php'>debug_phpmailer.php</a> para probar PHPMailer</li>";
echo "<li>Si funciona, actualiza los archivos de envío de correos</li>";
echo "<li>Los correos deberían llegar autenticados</li>";
echo "</ol>";
?>

