<?php
define('API_ACCESS', true);
echo "<h1>Debug de DiscogsAPI en Endpoint</h1>";

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/CacheManager.php';
require_once __DIR__ . '/classes/DiscogsAPI.php';

$apiKey = 'RHwdshtjSotKMFGXAwRe';
$apiSecret = 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ';

echo "<h3>1. Probando DiscogsAPI directamente...</h3>";
try {
    $discogs = new DiscogsAPI($apiKey, $apiSecret);
    echo "✅ Instancia creada correctamente<br>";
    
    // Probar makeRequest directamente
    echo "<h4>Probando makeRequest directamente...</h4>";
    $reflection = new ReflectionClass($discogs);
    $makeRequestMethod = $reflection->getMethod('makeRequest');
    $makeRequestMethod->setAccessible(true);
    
    $result = $makeRequestMethod->invoke($discogs, 'database/search', [
        'q' => 'Pink Floyd',
        'type' => 'master',
        'per_page' => 5
    ]);
    
    echo "Resultado de makeRequest: <pre>" . print_r($result, true) . "</pre>";
    
    if ($result['success']) {
        echo "✅ makeRequest exitoso<br>";
    } else {
        echo "❌ Error en makeRequest: " . ($result['error'] ?? 'Desconocido') . "<br>";
        if (isset($result['http_code'])) {
            echo "HTTP Code: " . $result['http_code'] . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Excepción: " . $e->getMessage() . "<br>";
}

echo "<br><hr><br>";

echo "<h3>2. Probando con URL construida manualmente...</h3>";
$url = "https://api.discogs.com/database/search?q=Pink+Floyd&type=master&per_page=5&key=" . $apiKey . "&secret=" . $apiSecret;
echo "URL: " . $url . "<br><br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "DiscogsImporter/1.0.0 +http://localhost");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "<br>";
if ($error) {
    echo "cURL Error: " . $error . "<br>";
}
echo "Response: " . substr($response, 0, 200) . "...<br>";
?>

