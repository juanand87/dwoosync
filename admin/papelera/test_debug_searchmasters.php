<?php
define('API_ACCESS', true);
echo "<h1>Debug de searchMasters</h1>";

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/CacheManager.php';
require_once __DIR__ . '/classes/DiscogsAPI.php';

$apiKey = 'RHwdshtjSotKMFGXAwRe';
$apiSecret = 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ';

echo "<h3>1. Probando searchMasters directamente...</h3>";
$discogs = new DiscogsAPI($apiKey, $apiSecret);
$result = $discogs->searchMasters('Pink Floyd', []);

echo "Resultado de searchMasters: <pre>" . print_r($result, true) . "</pre>";

echo "<h3>2. Verificando estructura del resultado...</h3>";
if (isset($result['success']) && $result['success']) {
    echo "✅ searchMasters exitoso<br>";
    echo "Tiene 'results': " . (isset($result['results']) ? 'Sí' : 'No') . "<br>";
    echo "Tiene 'data': " . (isset($result['data']) ? 'Sí' : 'No') . "<br>";
    
    if (isset($result['data'])) {
        echo "Estructura de 'data': <pre>" . print_r($result['data'], true) . "</pre>";
    }
} else {
    echo "❌ Error en searchMasters: " . ($result['error'] ?? 'Desconocido') . "<br>";
}

echo "<h3>3. Probando processSearchResults...</h3>";
if (isset($result['success']) && $result['success'] && isset($result['data'])) {
    $processed = $discogs->processSearchResults($result['data']);
    echo "Resultado procesado: <pre>" . print_r($processed, true) . "</pre>";
} else {
    echo "No se puede procesar porque searchMasters falló<br>";
}
?>