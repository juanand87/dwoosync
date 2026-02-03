<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Clase DiscogsAPI</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/CacheManager.php';
    require_once __DIR__ . '/classes/DiscogsAPI.php';
    
    echo "<h3>1. Creando instancia de DiscogsAPI...</h3>";
    $discogs = new DiscogsAPI('RHwdshtjSotKMFGXAwRe', 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ');
    echo "✅ DiscogsAPI creado<br><br>";
    
    echo "<h3>2. Probando búsqueda paso a paso...</h3>";
    
    // Simular el proceso interno de searchMasters
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
    
    // Hacer la petición usando el método makeRequest
    echo "<h3>3. Probando makeRequest...</h3>";
    
    // Usar reflexión para acceder al método privado
    $reflection = new ReflectionClass($discogs);
    $makeRequestMethod = $reflection->getMethod('makeRequest');
    $makeRequestMethod->setAccessible(true);
    
    $response = $makeRequestMethod->invoke($discogs, 'database/search', $params);
    
    echo "Response type: " . gettype($response) . "<br>";
    echo "Response: " . print_r($response, true) . "<br>";
    
    if (is_array($response) && isset($response['success'])) {
        if ($response['success']) {
            echo "✅ makeRequest exitoso<br>";
            echo "Data keys: " . implode(', ', array_keys($response['data'])) . "<br>";
            if (isset($response['data']['results'])) {
                echo "Resultados: " . count($response['data']['results']) . "<br>";
            }
        } else {
            echo "❌ Error en makeRequest: " . ($response['error'] ?? 'Desconocido') . "<br>";
        }
    } else {
        echo "❌ Respuesta inesperada de makeRequest<br>";
    }
    
    // Probar searchMasters directamente
    echo "<h3>4. Probando searchMasters directamente...</h3>";
    $result = $discogs->searchMasters('Pink Floyd', []);
    
    echo "Result type: " . gettype($result) . "<br>";
    echo "Result: " . print_r($result, true) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>

