<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug de la API</h1>";

// Test 1: Verificar que las clases se cargan
echo "<h3>1. Cargando clases...</h3>";
try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    echo "✓ Config cargado<br>";
    
    require_once __DIR__ . '/classes/Database.php';
    echo "✓ Database cargado<br>";
    
    require_once __DIR__ . '/classes/LicenseManager.php';
    echo "✓ LicenseManager cargado<br>";
    
    require_once __DIR__ . '/classes/CacheManager.php';
    echo "✓ CacheManager cargado<br>";
    
    require_once __DIR__ . '/classes/DiscogsAPI.php';
    echo "✓ DiscogsAPI cargado<br>";
    
    require_once __DIR__ . '/classes/Logger.php';
    echo "✓ Logger cargado<br>";
    
} catch (Exception $e) {
    echo "❌ Error cargando clases: " . $e->getMessage() . "<br>";
    exit;
}

// Test 2: Verificar conexión a base de datos
echo "<h3>2. Probando conexión a base de datos...</h3>";
try {
    $db = Database::getInstance();
    $result = $db->fetch("SELECT COUNT(*) as count FROM licenses");
    echo "✓ Base de datos conectada. Licencias: " . $result['count'] . "<br>";
} catch (Exception $e) {
    echo "❌ Error de base de datos: " . $e->getMessage() . "<br>";
    exit;
}

// Test 3: Probar DiscogsAPI
echo "<h3>3. Probando DiscogsAPI...</h3>";
try {
    $discogs = new DiscogsAPI('RHwdshtjSotKMFGXAwRe');
    echo "✓ DiscogsAPI creado<br>";
    
    // Probar búsqueda simple
    $result = $discogs->searchMasters('Pink Floyd', []);
    if ($result && isset($result['success'])) {
        echo "✓ Búsqueda en Discogs funciona<br>";
        echo "Resultados: " . count($result['results']) . "<br>";
    } else {
        echo "❌ Búsqueda en Discogs falló<br>";
    }
} catch (Exception $e) {
    echo "❌ Error en DiscogsAPI: " . $e->getMessage() . "<br>";
}

// Test 4: Probar endpoint de la API
echo "<h3>4. Probando endpoint de la API...</h3>";
try {
    // Simular petición
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api_discogs/api/search';
    $_GET = [
        'q' => 'test',
        'license_key' => 'LIC_68BF89D32E968_FBC2DE6A',
        'domain' => 'localhost',
        'discogs_api_key' => 'RHwdshtjSotKMFGXAwRe',
        'discogs_api_secret' => 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ'
    ];
    
    ob_start();
    include 'api/index.php';
    $output = ob_get_clean();
    
    echo "Respuesta de la API:<br>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    $data = json_decode($output, true);
    if ($data && isset($data['success'])) {
        echo "✓ Endpoint funciona<br>";
    } else {
        echo "❌ Endpoint no funciona: " . json_last_error_msg() . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error en endpoint: " . $e->getMessage() . "<br>";
}
?>

