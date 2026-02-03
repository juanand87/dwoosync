<?php
define('API_ACCESS', true);
echo "<h1>Debug del Endpoint</h1>";

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/LicenseManager.php';
require_once __DIR__ . '/classes/CacheManager.php';
require_once __DIR__ . '/classes/DiscogsAPI.php';

echo "<h3>1. Simulando el endpoint search...</h3>";

// Simular los parámetros que llegan al endpoint
$params = [
    'q' => 'Pink Floyd',
    'license_key' => 'LIC_68BF89D32E968_FBC2DE6A',
    'domain' => 'localhost',
    'discogs_api_key' => 'RHwdshtjSotKMFGXAwRe',
    'discogs_api_secret' => 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ'
];

echo "Parámetros recibidos: <pre>" . print_r($params, true) . "</pre>";

echo "<h3>2. Validando autenticación...</h3>";
$licenseManager = new LicenseManager();
$auth = $licenseManager->validateLicense($params['license_key'], $params['domain']);

if ($auth['valid']) {
    echo "✅ Validación de licencia exitosa<br>";
    echo "Plan: " . $auth['license']['plan_type'] . "<br>";
    echo "Subscriber ID: " . $auth['license']['subscriber_id'] . "<br>";
} else {
    echo "❌ Error en validación: " . $auth['error'] . "<br>";
    exit;
}

echo "<h3>3. Verificando claves de Discogs...</h3>";
$discogsApiKey = $params['discogs_api_key'] ?? '';
$discogsApiSecret = $params['discogs_api_secret'] ?? '';

echo "API Key: " . $discogsApiKey . "<br>";
echo "API Secret: " . $discogsApiSecret . "<br>";

if (empty($discogsApiKey)) {
    echo "❌ Clave de API de Discogs requerida<br>";
    exit;
}

echo "<h3>4. Creando instancia de DiscogsAPI...</h3>";
try {
    $discogs = new DiscogsAPI($discogsApiKey, $discogsApiSecret);
    echo "✅ Instancia creada correctamente<br>";
    
    // Verificar las propiedades internas
    $reflection = new ReflectionClass($discogs);
    $consumerKeyProperty = $reflection->getProperty('consumerKey');
    $consumerKeyProperty->setAccessible(true);
    $consumerSecretProperty = $reflection->getProperty('consumerSecret');
    $consumerSecretProperty->setAccessible(true);
    
    echo "Consumer Key interno: " . $consumerKeyProperty->getValue($discogs) . "<br>";
    echo "Consumer Secret interno: " . $consumerSecretProperty->getValue($discogs) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Error creando instancia: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>5. Probando búsqueda...</h3>";
$filters = array_intersect_key($params, array_flip(['format', 'country', 'year', 'label', 'genre', 'style']));
echo "Filtros: <pre>" . print_r($filters, true) . "</pre>";

$result = $discogs->searchMasters($params['q'], $filters);

if ($result['success']) {
    echo "✅ Búsqueda exitosa<br>";
    echo "Resultados: " . count($result['results']) . "<br>";
    echo "Primer resultado: " . ($result['results'][0]['title'] ?? 'N/A') . "<br>";
} else {
    echo "❌ Error en búsqueda<br>";
    echo "Error: " . ($result['error'] ?? 'Desconocido') . "<br>";
    if (isset($result['http_code'])) {
        echo "HTTP Code: " . $result['http_code'] . "<br>";
    }
    echo "Resultado completo: <pre>" . print_r($result, true) . "</pre>";
}
?>

