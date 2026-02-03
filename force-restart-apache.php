<?php
/**
 * Reinicio forzado de Apache
 */

echo "<h1>Reinicio Forzado de Apache</h1>";

// Función para ejecutar comandos
function runCommand($command, $description) {
    echo "<h3>$description</h3>";
    echo "<p>Comando: <code>$command</code></p>";
    
    $output = [];
    $return_code = 0;
    
    exec($command . " 2>&1", $output, $return_code);
    
    echo "<p><strong>Resultado (código $return_code):</strong></p>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
    
    return $return_code === 0;
}

echo "<h2>1. Matando TODOS los procesos de Apache</h2>";
runCommand("taskkill /F /IM httpd.exe", "Matando procesos httpd.exe");

echo "<h2>2. Matando procesos relacionados</h2>";
runCommand("taskkill /F /IM apache.exe", "Matando procesos apache.exe");
runCommand("taskkill /F /IM apache2.exe", "Matando procesos apache2.exe");

echo "<h2>3. Esperando 5 segundos...</h2>";
sleep(5);

echo "<h2>4. Verificando que no queden procesos</h2>";
runCommand("tasklist /FI \"IMAGENAME eq httpd.exe\" /FO TABLE", "Procesos httpd.exe restantes");

echo "<h2>5. Deteniendo servicio Apache</h2>";
runCommand("net stop Apache2.4", "Deteniendo servicio Apache2.4");

echo "<h2>6. Esperando 3 segundos...</h2>";
sleep(3);

echo "<h2>7. Iniciando servicio Apache</h2>";
runCommand("net start Apache2.4", "Iniciando servicio Apache2.4");

echo "<h2>8. Esperando 5 segundos...</h2>";
sleep(5);

echo "<h2>9. Verificando estado final</h2>";
runCommand("tasklist /FI \"IMAGENAME eq httpd.exe\" /FO TABLE", "Procesos Apache finales");
runCommand("netstat -an | findstr :80", "Puerto 80");

echo "<h2>10. Probando conexión</h2>";
try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/api_discogs/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        echo "<p style='color: green; font-weight: bold;'>✅ Apache responde correctamente (HTTP $http_code)</p>";
    } else {
        echo "<p style='color: orange; font-weight: bold;'>⚠️ Apache responde con código $http_code</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Error probando Apache: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='subscribe/pages/test-billing.php?plan=premium'>Probar creación de ciclo</a></p>";
echo "<p><a href='check-services.php'>Verificar estado de servicios</a></p>";
?>


