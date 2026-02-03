<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Ultra Simple</h1>";

try {
    echo "Paso 1: Incluyendo archivos...<br>";
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    echo "✓ Config incluido<br>";
    
    require_once __DIR__ . '/classes/Database.php';
    echo "✓ Database incluido<br>";
    
    require_once __DIR__ . '/classes/CacheManager.php';
    echo "✓ CacheManager incluido<br>";
    
    require_once __DIR__ . '/classes/DiscogsAPI.php';
    echo "✓ DiscogsAPI incluido<br><br>";
    
    echo "Paso 2: Creando instancia...<br>";
    $discogs = new DiscogsAPI('RHwdshtjSotKMFGXAwRe', 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ');
    echo "✓ Instancia creada<br><br>";
    
    echo "Paso 3: Probando makeRequest directamente...<br>";
    
    // Usar reflexión para acceder al método privado
    $reflection = new ReflectionClass($discogs);
    $makeRequestMethod = $reflection->getMethod('makeRequest');
    $makeRequestMethod->setAccessible(true);
    
    $response = $makeRequestMethod->invoke($discogs, 'database/search', [
        'q' => 'Pink Floyd',
        'type' => 'master',
        'per_page' => 5
    ]);
    
    echo "✓ makeRequest completado<br>";
    echo "Response type: " . gettype($response) . "<br>";
    echo "Response success: " . (isset($response['success']) ? ($response['success'] ? 'true' : 'false') : 'not set') . "<br>";
    
    if (isset($response['success']) && $response['success']) {
        echo "✓ Respuesta exitosa<br>";
        echo "Resultados: " . count($response['data']['results']) . "<br>";
    } else {
        echo "❌ Error en makeRequest: " . ($response['error'] ?? 'Desconocido') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
} catch (Error $e) {
    echo "❌ Error fatal: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>

