<?php
define('API_ACCESS', true);
echo "<h1>Test de Autenticación Discogs</h1>";

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/CacheManager.php';
require_once __DIR__ . '/classes/DiscogsAPI.php';

$apiKey = 'RHwdshtjSotKMFGXAwRe';
$apiSecret = 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ';

echo "<h3>1. Probando autenticación con diferentes métodos...</h3>";

// Método 1: Sin autenticación
echo "<h4>Método 1: Sin autenticación</h4>";
$url1 = "https://api.discogs.com/database/search?q=Pink+Floyd&type=master&per_page=5";
$response1 = file_get_contents($url1);
echo "HTTP Code: " . $http_response_header[0] . "<br>";
echo "Response: " . substr($response1, 0, 200) . "...<br><br>";

// Método 2: Con User-Agent solamente
echo "<h4>Método 2: Con User-Agent</h4>";
$context2 = stream_context_create([
    'http' => [
        'header' => "User-Agent: DiscogsImporter/1.0.0 +http://localhost"
    ]
]);
$response2 = file_get_contents($url1, false, $context2);
echo "HTTP Code: " . $http_response_header[0] . "<br>";
echo "Response: " . substr($response2, 0, 200) . "...<br><br>";

// Método 3: Con parámetros key y secret
echo "<h4>Método 3: Con parámetros key y secret</h4>";
$url3 = "https://api.discogs.com/database/search?q=Pink+Floyd&type=master&per_page=5&key=" . $apiKey . "&secret=" . $apiSecret;
$context3 = stream_context_create([
    'http' => [
        'header' => "User-Agent: DiscogsImporter/1.0.0 +http://localhost"
    ]
]);
$response3 = file_get_contents($url3, false, $context3);
echo "HTTP Code: " . $http_response_header[0] . "<br>";
echo "Response: " . substr($response3, 0, 200) . "...<br><br>";

// Método 4: Con cURL
echo "<h4>Método 4: Con cURL</h4>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url3);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "DiscogsImporter/1.0.0 +http://localhost");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/vnd.discogs.v2.discogs+json'
]);
$response4 = curl_exec($ch);
$httpCode4 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode4 . "<br>";
echo "Response: " . substr($response4, 0, 200) . "...<br><br>";

// Método 5: Usando nuestra clase DiscogsAPI
echo "<h4>Método 5: Usando DiscogsAPI</h4>";
try {
    $discogs = new DiscogsAPI($apiKey, $apiSecret);
    $result = $discogs->searchMasters('Pink Floyd', []);
    
    if ($result['success']) {
        echo "✅ Búsqueda exitosa<br>";
        echo "Resultados: " . count($result['results']) . "<br>";
    } else {
        echo "❌ Error en búsqueda<br>";
        echo "Error: " . ($result['error'] ?? 'Desconocido') . "<br>";
        echo "HTTP Code: " . ($result['http_code'] ?? 'N/A') . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Excepción: " . $e->getMessage() . "<br>";
}
?>

