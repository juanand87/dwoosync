<?php
/**
 * Configurar Composer y instalar PHPMailer (versión corregida)
 */

echo "<h2>Configuración de Composer y PHPMailer (Corregida)</h2>";

$currentDir = getcwd();
$composerPhar = $currentDir . '/composer.phar';

echo "<p><strong>Directorio actual:</strong> $currentDir</p>";
echo "<p><strong>Composer phar:</strong> $composerPhar</p>";

// Configurar variables de entorno
echo "<h3>1. Configurando variables de entorno:</h3>";
putenv('HOME=' . $currentDir);
putenv('COMPOSER_HOME=' . $currentDir . '/.composer');

echo "<p>✓ HOME configurado a: " . getenv('HOME') . "</p>";
echo "<p>✓ COMPOSER_HOME configurado a: " . getenv('COMPOSER_HOME') . "</p>";

// Crear directorio .composer si no existe
$composerHome = $currentDir . '/.composer';
if (!is_dir($composerHome)) {
    mkdir($composerHome, 0755, true);
    echo "<p>✓ Directorio .composer creado</p>";
}

// Crear composer.json
echo "<h3>2. Creando composer.json:</h3>";
$composerJson = [
    "require" => [
        "phpmailer/phpmailer" => "^6.8"
    ],
    "config" => [
        "optimize-autoloader" => true,
        "cache-dir" => $composerHome . "/cache"
    ]
];

$composerJsonPath = $currentDir . '/composer.json';
file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT));
echo "<p style='color: green;'>✓ composer.json creado</p>";

// Ejecutar composer install con variables de entorno
echo "<h3>3. Instalando PHPMailer:</h3>";
$command = "HOME='$currentDir' COMPOSER_HOME='$composerHome' php $composerPhar install --no-dev --optimize-autoloader 2>&1";
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
    
    // Intentar método alternativo
    echo "<h3>4. Intentando método alternativo (descarga directa):</h3>";
    
    // Crear directorio vendor
    $vendorDir = $currentDir . '/vendor';
    if (!is_dir($vendorDir)) {
        mkdir($vendorDir, 0755, true);
    }
    
    // Descargar PHPMailer directamente
    $phpmailerUrl = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.8.1.zip';
    $zipFile = $currentDir . '/phpmailer.zip';
    
    echo "<p>Descargando PHPMailer desde GitHub...</p>";
    $zipContent = file_get_contents($phpmailerUrl);
    
    if ($zipContent) {
        file_put_contents($zipFile, $zipContent);
        echo "<p style='color: green;'>✓ PHPMailer descargado</p>";
        
        // Extraer ZIP
        $zip = new ZipArchive();
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($vendorDir);
            $zip->close();
            echo "<p style='color: green;'>✓ PHPMailer extraído</p>";
            
            // Renombrar directorio
            $extractedDir = $vendorDir . '/PHPMailer-6.8.1';
            $phpmailerDir = $vendorDir . '/phpmailer/phpmailer';
            
            if (is_dir($extractedDir)) {
                rename($extractedDir, $phpmailerDir);
                echo "<p style='color: green;'>✓ Directorio renombrado</p>";
            }
            
            // Crear autoload.php
            $autoloadContent = "<?php\n";
            $autoloadContent .= "// Autoloader generado automáticamente\n";
            $autoloadContent .= "require_once __DIR__ . '/phpmailer/phpmailer/src/Exception.php';\n";
            $autoloadContent .= "require_once __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php';\n";
            $autoloadContent .= "require_once __DIR__ . '/phpmailer/phpmailer/src/SMTP.php';\n";
            
            file_put_contents($vendorDir . '/autoload.php', $autoloadContent);
            echo "<p style='color: green;'>✓ autoload.php creado</p>";
            
            // Limpiar
            unlink($zipFile);
            echo "<p style='color: green;'>✓ Archivos temporales limpiados</p>";
        } else {
            echo "<p style='color: red;'>✗ Error extrayendo ZIP</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Error descargando PHPMailer</p>";
    }
}

// Verificar instalación
echo "<h3>5. Verificando instalación:</h3>";

// Verificar vendor/autoload.php
$vendorAutoload = $currentDir . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    echo "<p style='color: green;'>✓ vendor/autoload.php encontrado</p>";
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
echo "<h3>6. Probando PHPMailer:</h3>";
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
echo "<h3>7. Estructura de archivos creada:</h3>";
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

echo "<h3>8. Próximos pasos:</h3>";
echo "<ol>";
echo "<li>Ejecuta <a href='debug_phpmailer.php'>debug_phpmailer.php</a> para probar PHPMailer</li>";
echo "<li>Si funciona, los correos deberían llegar autenticados</li>";
echo "<li>El problema de 'no autenticado' debería resolverse</li>";
echo "</ol>";
?>

