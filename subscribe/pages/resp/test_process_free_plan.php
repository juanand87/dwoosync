<?php
/**
 * Test de diagnóstico para process_free_plan.php
 */

// Simular el entorno básico
require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h2>🔍 Diagnóstico de process_free_plan.php</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 8px;'>";

// Test 1: Verificar autoload
echo "<h3>1. Verificar Composer Autoload</h3>";
try {
    require_once __DIR__ . '/../../vendor/autoload.php';
    echo "✅ Composer autoload cargado correctamente<br>";
} catch (Exception $e) {
    echo "❌ Error cargando autoload: " . $e->getMessage() . "<br>";
}

// Test 2: Verificar PHPMailer
echo "<h3>2. Verificar PHPMailer</h3>";
try {
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        echo "✅ PHPMailer disponible<br>";
    } else {
        echo "❌ PHPMailer NO disponible<br>";
    }
} catch (Exception $e) {
    echo "❌ Error verificando PHPMailer: " . $e->getMessage() . "<br>";
}

// Test 3: Verificar email_phpmailer_smtp.php
echo "<h3>3. Verificar archivo email_phpmailer_smtp.php</h3>";
try {
    require_once __DIR__ . '/email_phpmailer_smtp.php';
    echo "✅ email_phpmailer_smtp.php cargado correctamente<br>";
    
    if (function_exists('sendEmail')) {
        echo "✅ Función sendEmail disponible<br>";
    } else {
        echo "❌ Función sendEmail NO disponible<br>";
    }
} catch (Exception $e) {
    echo "❌ Error cargando email_phpmailer_smtp.php: " . $e->getMessage() . "<br>";
}

// Test 4: Verificar funciones básicas
echo "<h3>4. Verificar funciones básicas</h3>";
try {
    if (function_exists('startSecureSession')) {
        echo "✅ startSecureSession disponible<br>";
    } else {
        echo "❌ startSecureSession NO disponible<br>";
    }
    
    if (function_exists('getDatabase')) {
        echo "✅ getDatabase disponible<br>";
    } else {
        echo "❌ getDatabase NO disponible<br>";
    }
} catch (Exception $e) {
    echo "❌ Error verificando funciones: " . $e->getMessage() . "<br>";
}

// Test 5: Simular envío (sin enviar realmente)
echo "<h3>5. Test de configuración de correo</h3>";
try {
    if (function_exists('sendEmail')) {
        echo "✅ Función sendEmail lista para usar<br>";
        echo "⚠️ Para probar el envío real, usa la página principal<br>";
    } else {
        echo "❌ Función sendEmail no disponible<br>";
    }
} catch (Exception $e) {
    echo "❌ Error en test de correo: " . $e->getMessage() . "<br>";
}

echo "</div>";
echo "<br><p><a href='process_free_plan.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Probar process_free_plan.php</a></p>";
?>