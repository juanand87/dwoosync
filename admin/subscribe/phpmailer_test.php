<?php
/**
 * Test simple para verificar PHPMailer
 */

echo "<h2>🔧 Test Simple PHPMailer</h2>";

// Intentar cargar autoload
echo "<h3>1. Cargando Vendor Autoload:</h3>";
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
    echo "✅ Autoload cargado<br>";
} else {
    echo "❌ Autoload no encontrado<br>";
}

// Verificar si PHPMailer está disponible
echo "<h3>2. Verificando PHPMailer:</h3>";
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "✅ PHPMailer clase encontrada<br>";
    
    // Crear instancia
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        echo "✅ Instancia de PHPMailer creada exitosamente<br>";
        echo "📧 Versión: " . $mail::VERSION . "<br>";
    } catch (Exception $e) {
        echo "❌ Error creando instancia: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ PHPMailer clase no encontrada<br>";
    
    // Intentar cargar manualmente
    echo "<h3>3. Carga Manual:</h3>";
    $phpmailerPath = '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    if (file_exists($phpmailerPath)) {
        require_once '../vendor/phpmailer/phpmailer/src/Exception.php';
        require_once '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once '../vendor/phpmailer/phpmailer/src/SMTP.php';
        echo "✅ Archivos PHPMailer cargados manualmente<br>";
        
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "✅ PHPMailer ahora disponible<br>";
        } else {
            echo "❌ PHPMailer aún no disponible<br>";
        }
    } else {
        echo "❌ Archivos PHPMailer no encontrados en: $phpmailerPath<br>";
    }
}

// Verificar constantes SMTP
echo "<h3>3. Verificando Configuración SMTP:</h3>";
require_once 'includes/config.php';

$constants = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'FROM_EMAIL'];
foreach ($constants as $const) {
    echo "<li><strong>$const:</strong> " . (defined($const) ? '✅ Definido' : '❌ No definido') . "</li>";
}
?>