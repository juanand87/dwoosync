<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Autenticación Discogs</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    
    echo "<h3>1. Verificando clave de API...</h3>";
    $apiKey = 'RHwdshtjSotKMFGXAwRe';
    echo "API Key: " . $apiKey . "<br>";
    echo "Length: " . strlen($apiKey) . " caracteres<br><br>";
    
    echo "<h3>2. Probando autenticación con Discogs...</h3>";
    
    // Probar con diferentes formatos de autenticación
    $url = 'https://api.discogs.com/database/search?q=Pink+Floyd&type=master&per_page=5';
    
    $headers = [
        'User-Agent: DiscogsImporter/1.0.0',
        'Accept: application/json',
        'Authorization: Discogs key=' . $apiKey
    ];
    
    echo "URL: " . $url . "<br>";
    echo "Headers: " . print_r($headers, true) . "<br><br>";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_VERBOSE => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    echo "<h3>3. Resultado de la petición:</h3>";
    echo "HTTP Code: " . $httpCode . "<br>";
    echo "Error: " . ($error ?: 'None') . "<br>";
    echo "Response length: " . strlen($response) . " bytes<br>";
    echo "Content Type: " . ($info['content_type'] ?? 'Unknown') . "<br><br>";
    
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
        echo "Response: " . htmlspecialchars($response) . "<br>";
        
        // Probar con formato de autenticación alternativo
        echo "<h3>4. Probando formato alternativo de autenticación...</h3>";
        
        $headers2 = [
            'User-Agent: DiscogsImporter/1.0.0',
            'Accept: application/json',
            'Authorization: Discogs token=' . $apiKey
        ];
        
        $ch2 = curl_init();
        curl_setopt_array($ch2, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers2,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $error2 = curl_error($ch2);
        curl_close($ch2);
        
        echo "HTTP Code (token): " . $httpCode2 . "<br>";
        echo "Error: " . ($error2 ?: 'None') . "<br>";
        echo "Response: " . htmlspecialchars($response2) . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

