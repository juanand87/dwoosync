<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug de Discogs API</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/CacheManager.php';
    require_once __DIR__ . '/classes/DiscogsAPI.php';
    
    echo "<h3>1. Verificando configuración...</h3>";
    echo "DISCOGS_API_URL: " . DISCOGS_API_URL . "<br>";
    echo "DISCOGS_USER_AGENT: " . DISCOGS_USER_AGENT . "<br>";
    echo "API_CACHE_ENABLED: " . (API_CACHE_ENABLED ? 'true' : 'false') . "<br><br>";
    
    echo "<h3>2. Creando instancia de DiscogsAPI...</h3>";
    $discogs = new DiscogsAPI('RHwdshtjSotKMFGXAwRe');
    echo "✅ DiscogsAPI creado<br><br>";
    
    echo "<h3>3. Probando búsqueda con debug...</h3>";
    
    // Simular la búsqueda paso a paso
    $query = 'Pink Floyd';
    $filters = [];
    $cacheKey = 'search_masters_' . md5($query . serialize($filters));
    
    echo "Query: " . $query . "<br>";
    echo "Cache Key: " . $cacheKey . "<br>";
    
    // Verificar caché
    if (API_CACHE_ENABLED) {
        $cache = new CacheManager();
        $cached = $cache->get($cacheKey);
        echo "Cached result: " . ($cached !== false ? 'Found' : 'Not found') . "<br>";
    }
    
    // Construir parámetros
    $params = array_merge([
        'q' => $query,
        'type' => 'master',
        'per_page' => 25
    ], $filters);
    
    echo "Params: " . print_r($params, true) . "<br>";
    
    // Hacer la petición
    $url = DISCOGS_API_URL . 'database/search?' . http_build_query($params);
    echo "URL: " . $url . "<br><br>";
    
    // Probar la petición directamente
    echo "<h3>4. Probando petición directa...</h3>";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: ' . DISCOGS_USER_AGENT,
            'Accept: application/json',
            'Authorization: Discogs key=RHwdshtjSotKMFGXAwRe'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: " . $httpCode . "<br>";
    echo "Error: " . ($error ?: 'None') . "<br>";
    echo "Response length: " . strlen($response) . " bytes<br>";
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✅ Respuesta JSON válida<br>";
            echo "Resultados: " . (isset($data['results']) ? count($data['results']) : 'No results field') . "<br>";
        } else {
            echo "❌ Error JSON: " . json_last_error_msg() . "<br>";
        }
    } else {
        echo "❌ Error HTTP: " . $httpCode . "<br>";
        echo "Response: " . htmlspecialchars(substr($response, 0, 500)) . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>
