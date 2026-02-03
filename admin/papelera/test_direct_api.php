<?php
// Test directo de la API sin pasar por HTTP
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Directo de la API</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/LicenseManager.php';
    require_once __DIR__ . '/classes/CacheManager.php';
    require_once __DIR__ . '/classes/DiscogsAPI.php';
    
    echo "<p>✓ Clases cargadas</p>";
    
    // Obtener license key
    $db = Database::getInstance();
    $license = $db->fetch("SELECT license_key FROM licenses ORDER BY created_at DESC LIMIT 1");
    
    if (!$license) {
        echo "<p>❌ No hay licencias</p>";
        exit;
    }
    
    $licenseKey = $license['license_key'];
    echo "<p>✓ License Key: " . substr($licenseKey, 0, 10) . "...</p>";
    
    // Validar licencia
    $licenseManager = new LicenseManager();
    $validation = $licenseManager->validateLicense($licenseKey, 'localhost');
    
    if (!$validation['valid']) {
        echo "<p>❌ Licencia inválida: " . $validation['error'] . "</p>";
        exit;
    }
    
    echo "<p>✅ Licencia válida</p>";
    
    // Cargar configuración de Discogs
    $discogs_config = require_once __DIR__ . '/test_config_discogs.php';
    
    // Probar Discogs API directamente
    echo "<h3>Probando Discogs API directamente...</h3>";
    
    $discogs = new DiscogsAPI($discogs_config['api_key']);
    $result = $discogs->searchMasters('Pink Floyd');
    
    if ($result['success']) {
        echo "<p>✅ Discogs API funciona</p>";
        echo "<p><strong>Resultados:</strong> " . count($result['data']['results']) . "</p>";
        
        if (!empty($result['data']['results'])) {
            echo "<h4>Primer resultado:</h4>";
            echo "<pre>" . htmlspecialchars(json_encode($result['data']['results'][0], JSON_PRETTY_PRINT)) . "</pre>";
        }
    } else {
        echo "<p>❌ Error en Discogs API: " . $result['error'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
}
?>
