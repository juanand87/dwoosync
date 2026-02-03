<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Final Simplificado</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/LicenseManager.php';
    require_once __DIR__ . '/classes/CacheManager.php';
    require_once __DIR__ . '/classes/DiscogsAPI.php';
    
    echo "<h3>1. Verificando licencia...</h3>";
    $db = Database::getInstance();
    $license = $db->fetch("SELECT * FROM licenses WHERE id = 1");
    echo "✓ License Key: " . substr($license['license_key'], 0, 10) . "...<br>";
    
    $licenseManager = new LicenseManager();
    $auth = $licenseManager->validateLicense($license['license_key'], 'localhost');
    
    if ($auth['valid']) {
        echo "✓ Validación exitosa<br><br>";
        
        echo "<h3>2. Probando búsqueda en Discogs...</h3>";
        $discogs = new DiscogsAPI('RHwdshtjSotKMFGXAwRe', 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ');
        $result = $discogs->searchMasters('Pink Floyd', []);
        
        if ($result && isset($result['success']) && $result['success']) {
            echo "✅ Búsqueda exitosa<br>";
            echo "Resultados: " . count($result['results']) . "<br>";
            if (count($result['results']) > 0) {
                echo "Primer resultado: " . $result['results'][0]['title'] . "<br>";
            }
        } else {
            echo "❌ Error en búsqueda: " . ($result['error'] ?? 'Desconocido') . "<br>";
        }
        
        echo "<h3>3. Probando endpoint HTTP...</h3>";
        $url = 'http://localhost/api_discogs/api/search?' . http_build_query([
            'q' => 'Pink Floyd',
            'license_key' => $license['license_key'],
            'domain' => 'localhost',
            'discogs_api_key' => 'RHwdshtjSotKMFGXAwRe',
            'discogs_api_secret' => 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ'
        ]);
        
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if ($data && isset($data['success']) && $data['success']) {
            echo "✅ Endpoint HTTP funciona<br>";
            echo "Resultados HTTP: " . count($data['results']) . "<br>";
        } else {
            echo "❌ Error en endpoint HTTP: " . ($data['error'] ?? 'Desconocido') . "<br>";
        }
        
    } else {
        echo "❌ Error de validación: " . ($auth['error'] ?? 'Desconocido') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

