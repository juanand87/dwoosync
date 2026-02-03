<?php
/**
 * Test final del sistema de correo con PHPMailer SMTP
 */

echo "<h2>Test Final del Sistema de Correo</h2>";

// Cargar PHPMailer
require_once 'email_phpmailer_smtp.php';

// Email de prueba (cambia por tu email real)
$testEmail = 'test@example.com'; // CAMBIA ESTO POR TU EMAIL REAL
$testName = 'Usuario de Prueba';

echo "<h3>1. Probando correo de bienvenida (Plan Gratuito):</h3>";

$result1 = sendWelcomeEmail(
    $testEmail,
    $testName,
    'Gratuito',
    'TEST-LICENSE-12345',
    'test.dwoosync.com',
    '2025-11-02',
    false // Español
);

if ($result1) {
    echo "<p style='color: green;'>✓ Correo de bienvenida (español) enviado exitosamente</p>";
} else {
    echo "<p style='color: red;'>✗ Error enviando correo de bienvenida (español)</p>";
}

echo "<h3>2. Probando correo de bienvenida (Plan Premium - Inglés):</h3>";

$result2 = sendWelcomeEmail(
    $testEmail,
    $testName,
    'Premium',
    'TEST-LICENSE-67890',
    'test.dwoosync.com',
    '2025-11-02',
    true // Inglés
);

if ($result2) {
    echo "<p style='color: green;'>✓ Correo de bienvenida (inglés) enviado exitosamente</p>";
} else {
    echo "<p style='color: red;'>✗ Error enviando correo de bienvenida (inglés)</p>";
}

echo "<h3>3. Probando correo de contacto:</h3>";

$result3 = sendContactEmail(
    'Juan Pérez',
    'juan@example.com',
    'Consulta sobre el plugin',
    'Hola, tengo una pregunta sobre la configuración del plugin DWooSync. ¿Podrían ayudarme?'
);

if ($result3) {
    echo "<p style='color: green;'>✓ Correo de contacto enviado exitosamente</p>";
} else {
    echo "<p style='color: red;'>✗ Error enviando correo de contacto</p>";
}

echo "<h3>4. Resumen de resultados:</h3>";
echo "<ul>";
echo "<li>Correo bienvenida (español): " . ($result1 ? "✅ Exitoso" : "❌ Falló") . "</li>";
echo "<li>Correo bienvenida (inglés): " . ($result2 ? "✅ Exitoso" : "❌ Falló") . "</li>";
echo "<li>Correo contacto: " . ($result3 ? "✅ Exitoso" : "❌ Falló") . "</li>";
echo "</ul>";

if ($result1 && $result2 && $result3) {
    echo "<h3 style='color: green;'>🎉 ¡Todos los correos funcionan correctamente!</h3>";
    echo "<p>El sistema de correo con PHPMailer SMTP está funcionando perfectamente.</p>";
    echo "<p><strong>Los correos ahora deberían llegar:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Sin marca de spam</li>";
    echo "<li>✅ Autenticados correctamente</li>";
    echo "<li>✅ Con formato HTML profesional</li>";
    echo "<li>✅ En español e inglés</li>";
    echo "</ul>";
} else {
    echo "<h3 style='color: red;'>❌ Algunos correos fallaron</h3>";
    echo "<p>Revisa los logs del servidor para más detalles.</p>";
}

echo "<hr>";
echo "<p><strong>Nota:</strong> Cambia la variable \$testEmail por tu email real para recibir los correos de prueba.</p>";
echo "<p><a href='debug_phpmailer.php'>← Volver al diagnóstico de PHPMailer</a></p>";
?>

