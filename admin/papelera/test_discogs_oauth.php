<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Discogs con OAuth</h1>";

// Configuración OAuth básica
$consumerKey = 'RHwdshtjSotKMFGXAwRe';
$consumerSecret = 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ';

echo "<h3>1. Configuración OAuth:</h3>";
echo "Consumer Key: " . $consumerKey . "<br>";
echo "Consumer Secret: " . $consumerSecret . "<br><br>";

// Probar con autenticación OAuth básica
echo "<h3>2. Probando con OAuth básico...</h3>";

$url = 'https://api.discogs.com/database/search';
$params = [
    'q' => 'Pink Floyd',
    'type' => 'master',
    'per_page' => 5
];

// Generar nonce y timestamp
$nonce = md5(uniqid(rand(), true));
$timestamp = time();

// Crear string de parámetros para la firma
$paramString = http_build_query($params);
$baseString = 'GET&' . rawurlencode($url) . '&' . rawurlencode($paramString);

// Crear firma (método PLAINTEXT)
$signature = rawurlencode($consumerSecret) . '&';

$headers = [
    'User-Agent: DiscogsImporter/1.0.0',
    'Accept: application/json',
    'Authorization: OAuth oauth_consumer_key="' . $consumerKey . '", oauth_nonce="' . $nonce . '", oauth_signature="' . $signature . '", oauth_signature_method="PLAINTEXT", oauth_timestamp="' . $timestamp . '", oauth_version="1.0"'
];

echo "URL: " . $url . '?' . $paramString . "<br>";
echo "Headers: " . print_r($headers, true) . "<br><br>";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url . '?' . $paramString,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>3. Resultado:</h3>";
echo "HTTP Code: " . $httpCode . "<br>";
echo "Error: " . ($error ?: 'None') . "<br>";
echo "Response: " . htmlspecialchars($response) . "<br><br>";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ Respuesta JSON válida<br>";
        echo "Resultados: " . (isset($data['results']) ? count($data['results']) : 'No results field') . "<br>";
        if (isset($data['results']) && count($data['results']) > 0) {
            echo "Primer resultado: " . $data['results'][0]['title'] . "<br>";
        }
    } else {
        echo "❌ Error JSON: " . json_last_error_msg() . "<br>";
    }
} else {
    echo "❌ Error HTTP: " . $httpCode . "<br>";
    
    // Probar con método más simple
    echo "<h3>4. Probando método alternativo...</h3>";
    
    // Usar las claves como parámetros en la URL (método obsoleto pero puede funcionar)
    $url2 = 'https://api.discogs.com/database/search?' . http_build_query(array_merge($params, [
        'key' => $consumerKey,
        'secret' => $consumerSecret
    ]));
    
    echo "URL alternativa: " . $url2 . "<br>";
    
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $url2,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: DiscogsImporter/1.0.0',
            'Accept: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    $error2 = curl_error($ch2);
    curl_close($ch2);
    
    echo "HTTP Code (alternativo): " . $httpCode2 . "<br>";
    echo "Error: " . ($error2 ?: 'None') . "<br>";
    echo "Response: " . htmlspecialchars($response2) . "<br>";
}
?>

