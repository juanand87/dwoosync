<?php
/**
 * Verificador de configuración DNS para email
 */

require_once 'email_phpmailer_smtp.php';

echo "<h2>🔍 Diagnóstico de Configuración DNS para Email</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 8px;'>";

$domain = 'dwoosync.com';
$config = checkEmailDNSConfiguration($domain);

echo "<h3>📧 Dominio: $domain</h3>";

// SPF
echo "<div style='margin: 10px 0; padding: 10px; background: " . 
     ($config['spf'] !== 'NO CONFIGURADO' ? '#d4edda' : '#f8d7da') . 
     "; border-radius: 5px;'>";
echo "<strong>SPF Record:</strong><br>";
echo $config['spf'] === 'NO CONFIGURADO' ? 
     "❌ NO CONFIGURADO" : 
     "✅ " . htmlspecialchars($config['spf']);
echo "</div>";

// DMARC
echo "<div style='margin: 10px 0; padding: 10px; background: " . 
     ($config['dmarc'] !== 'NO CONFIGURADO' ? '#d4edda' : '#f8d7da') . 
     "; border-radius: 5px;'>";
echo "<strong>DMARC Record:</strong><br>";
echo $config['dmarc'] === 'NO CONFIGURADO' ? 
     "❌ NO CONFIGURADO" : 
     "✅ " . htmlspecialchars($config['dmarc']);
echo "</div>";

// MX
echo "<div style='margin: 10px 0; padding: 10px; background: " . 
     ($config['mx'] === 'CONFIGURADO' ? '#d4edda' : '#f8d7da') . 
     "; border-radius: 5px;'>";
echo "<strong>MX Records:</strong><br>";
echo $config['mx'] === 'CONFIGURADO' ? "✅ CONFIGURADO" : "❌ NO CONFIGURADO";
echo "</div>";

// DKIM
echo "<div style='margin: 10px 0; padding: 10px; background: " . 
     ($config['dkim'] === 'CONFIGURADO' ? '#d4edda' : '#f8d7da') . 
     "; border-radius: 5px;'>";
echo "<strong>DKIM Record:</strong><br>";
if ($config['dkim'] === 'CONFIGURADO') {
    echo "✅ CONFIGURADO (Selector: " . $config['dkim_selector'] . ")<br>";
    echo "<small>Record: " . htmlspecialchars(substr($config['dkim_record'], 0, 100)) . "...</small>";
} else {
    echo "❌ NO ENCONTRADO<br>";
    echo "<small>Verificado selectores: default, mail, dkim, selector1, selector2, google, k1</small>";
}
echo "</div>";

echo "<h3>📋 Recomendaciones:</h3>";

if ($config['spf'] === 'NO CONFIGURADO') {
    echo "<div style='margin: 5px 0; color: #dc3545;'>• Configurar registro SPF</div>";
}

if ($config['dmarc'] === 'NO CONFIGURADO') {
    echo "<div style='margin: 5px 0; color: #dc3545;'>• Configurar registro DMARC</div>";
}

if ($config['mx'] === 'NO CONFIGURADO') {
    echo "<div style='margin: 5px 0; color: #dc3545;'>• Configurar registros MX</div>";
}

if ($config['dkim'] === 'NO ENCONTRADO') {
    echo "<div style='margin: 5px 0; color: #dc3545;'>• Configurar DKIM en tu servidor de correo</div>";
    echo "<div style='margin: 5px 0; color: #6c757d;'>• Contacta a tu proveedor de hosting para habilitar DKIM</div>";
} else {
    echo "<div style='margin: 5px 0; color: #28a745;'>✅ DKIM configurado correctamente</div>";
}

// Evaluación general
$score = 0;
if ($config['spf'] !== 'NO CONFIGURADO') $score++;
if ($config['dmarc'] !== 'NO CONFIGURADO') $score++;
if ($config['mx'] === 'CONFIGURADO') $score++;
if ($config['dkim'] === 'CONFIGURADO') $score++;

echo "<div style='margin: 15px 0; padding: 15px; border-radius: 8px; background: ";
if ($score == 4) echo "#d4edda; border: 2px solid #28a745;'><h4 style='color: #28a745; margin:0;'>🎉 Configuración Excelente ($score/4)</h4><p style='margin:5px 0 0 0;'>Tus correos deberían llegar a la bandeja principal.</p>";
elseif ($score == 3) echo "#fff3cd; border: 2px solid #ffc107;'><h4 style='color: #856404; margin:0;'>⚡ Configuración Buena ($score/4)</h4><p style='margin:5px 0 0 0;'>Muy probable que lleguen a la bandeja principal.</p>";
elseif ($score >= 2) echo "#f8d7da; border: 2px solid #dc3545;'><h4 style='color: #721c24; margin:0;'>⚠️ Configuración Regular ($score/4)</h4><p style='margin:5px 0 0 0;'>Pueden llegar a spam. Completa la configuración.</p>";
else echo "#f8d7da; border: 2px solid #dc3545;'><h4 style='color: #721c24; margin:0;'>❌ Configuración Insuficiente ($score/4)</h4><p style='margin:5px 0 0 0;'>Muy probable que lleguen a spam.</p>";
echo "</div>";

echo "<div style='margin: 5px 0; color: #6c757d;'>• Considerar usar un servicio de email como SendGrid para máxima confiabilidad</div>";

echo "</div>";

// Test de envío
echo "<h3>🧪 Test de Envío (Opcional)</h3>";
echo "<form method='post' style='margin: 20px 0;'>";
echo "<input type='email' name='test_email' placeholder='tu-email@example.com' required style='padding: 8px; width: 250px;'>";
echo "<button type='submit' name='send_test' style='padding: 8px 16px; margin-left: 10px; background: #007bff; color: white; border: none; border-radius: 4px;'>Enviar Test</button>";
echo "</form>";

if (isset($_POST['send_test']) && !empty($_POST['test_email'])) {
    $testEmail = $_POST['test_email'];
    $result = sendEmail(
        $testEmail, 
        'Test de Configuración DNS - DWooSync', 
        '<h3>✅ Test exitoso</h3><p>Si recibes este correo, la configuración básica funciona.</p><p><strong>Revisa si llegó a spam o bandeja principal.</strong></p>',
        true
    );
    
    echo "<div style='padding: 10px; margin: 10px 0; border-radius: 5px; background: " . 
         ($result ? '#d4edda' : '#f8d7da') . ";'>";
    echo $result ? 
         "✅ Test enviado exitosamente a $testEmail" : 
         "❌ Error enviando test a $testEmail";
    echo "</div>";
}
?>