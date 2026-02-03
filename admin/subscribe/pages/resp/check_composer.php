<?php
/**
 * Verificar si Composer está disponible en el servidor
 */

echo "<h2>Verificación de Composer</h2>";

// Método 1: Verificar si composer está en PATH
echo "<h3>1. Verificar comando composer:</h3>";
$composerPath = shell_exec('which composer 2>/dev/null');
if ($composerPath) {
    echo "<p style='color: green;'>✓ Composer encontrado en: " . trim($composerPath) . "</p>";
} else {
    echo "<p style='color: red;'>✗ Comando 'composer' no encontrado en PATH</p>";
}

// Método 2: Verificar si composer.phar existe
echo "<h3>2. Verificar composer.phar:</h3>";
$composerPhar = shell_exec('find / -name "composer.phar" 2>/dev/null | head -5');
if ($composerPhar) {
    echo "<p style='color: green;'>✓ composer.phar encontrado:</p>";
    echo "<pre>" . $composerPhar . "</pre>";
} else {
    echo "<p style='color: red;'>✗ composer.phar no encontrado</p>";
}

// Método 3: Verificar si existe en ubicaciones comunes
echo "<h3>3. Verificar ubicaciones comunes:</h3>";
$commonPaths = [
    '/usr/local/bin/composer',
    '/usr/bin/composer',
    '/opt/composer/composer.phar',
    '/home/*/composer.phar',
    '/var/www/composer.phar',
    '/var/www/html/composer.phar'
];

foreach ($commonPaths as $path) {
    if (file_exists($path)) {
        echo "<p style='color: green;'>✓ Encontrado en: $path</p>";
    } else {
        echo "<p style='color: gray;'>- No encontrado en: $path</p>";
    }
}

// Método 4: Verificar si composer está en el directorio actual
echo "<h3>4. Verificar en directorio actual:</h3>";
$currentDir = getcwd();
$composerInCurrent = file_exists($currentDir . '/composer.phar');
if ($composerInCurrent) {
    echo "<p style='color: green;'>✓ composer.phar encontrado en directorio actual</p>";
} else {
    echo "<p style='color: red;'>✗ composer.phar no encontrado en directorio actual</p>";
}

// Método 5: Verificar si composer.json existe
echo "<h3>5. Verificar composer.json:</h3>";
$composerJson = file_exists($currentDir . '/composer.json');
if ($composerJson) {
    echo "<p style='color: green;'>✓ composer.json encontrado en directorio actual</p>";
    echo "<pre>" . htmlspecialchars(file_get_contents($currentDir . '/composer.json')) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ composer.json no encontrado en directorio actual</p>";
}

// Método 6: Verificar si vendor/autoload.php existe
echo "<h3>6. Verificar vendor/autoload.php:</h3>";
$vendorAutoload = file_exists($currentDir . '/vendor/autoload.php');
if ($vendorAutoload) {
    echo "<p style='color: green;'>✓ vendor/autoload.php encontrado (Composer ya ejecutado)</p>";
} else {
    echo "<p style='color: red;'>✗ vendor/autoload.php no encontrado</p>";
}

// Método 7: Intentar ejecutar composer --version
echo "<h3>7. Intentar ejecutar composer --version:</h3>";
$composerVersion = shell_exec('composer --version 2>&1');
if ($composerVersion && !strpos($composerVersion, 'command not found')) {
    echo "<p style='color: green;'>✓ Composer ejecutado exitosamente:</p>";
    echo "<pre>" . $composerVersion . "</pre>";
} else {
    echo "<p style='color: red;'>✗ No se pudo ejecutar composer --version</p>";
    echo "<pre>Error: " . $composerVersion . "</pre>";
}

// Método 8: Verificar permisos de escritura
echo "<h3>8. Verificar permisos de escritura:</h3>";
$writable = is_writable($currentDir);
if ($writable) {
    echo "<p style='color: green;'>✓ Directorio actual es escribible</p>";
} else {
    echo "<p style='color: red;'>✗ Directorio actual NO es escribible</p>";
}

// Información del servidor
echo "<h3>Información del servidor:</h3>";
echo "<p><strong>Directorio actual:</strong> $currentDir</p>";
echo "<p><strong>Usuario:</strong> " . get_current_user() . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>OS:</strong> " . php_uname() . "</p>";

// Verificar si se puede descargar composer
echo "<h3>9. Verificar si se puede descargar composer:</h3>";
if (function_exists('curl_init')) {
    echo "<p style='color: green;'>✓ cURL disponible - Se puede descargar composer</p>";
} else {
    echo "<p style='color: red;'>✗ cURL no disponible - No se puede descargar composer</p>";
}

// Instrucciones de instalación
echo "<h3>Instrucciones de instalación:</h3>";
if (!$composerPath && !$composerPhar) {
    echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Para instalar Composer:</h4>";
    echo "<ol>";
    echo "<li><strong>Descargar composer.phar:</strong><br>";
    echo "<code>curl -sS https://getcomposer.org/installer | php</code></li>";
    echo "<li><strong>Hacer ejecutable:</strong><br>";
    echo "<code>chmod +x composer.phar</code></li>";
    echo "<li><strong>Instalar PHPMailer:</strong><br>";
    echo "<code>php composer.phar require phpmailer/phpmailer</code></li>";
    echo "</ol>";
    echo "</div>";
}
?>

