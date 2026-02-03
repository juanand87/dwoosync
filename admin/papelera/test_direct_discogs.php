<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Directo de Discogs</h1>";

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
        
        echo "<h3>2. Probando búsqueda directa sin caché...</h3>";
        
        // Hacer la petición directamente sin usar searchMasters
        $url = 'https://api.discogs.com/database/search?' . http_build_query([
            'q' => 'Pink Floyd',
            'type' => 'master',
            'per_page' => 5,
            'key' => 'RHwdshtjSotKMFGXAwRe',
            'secret' => 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ'
        ]);
        
        echo "URL: " . $url . "<br><br>";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: DiscogsImporter/1.0.0',
                'Accept: application/json'
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
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "✅ Búsqueda directa exitosa<br>";
                echo "Resultados: " . count($data['results']) . "<br>";
                if (count($data['results']) > 0) {
                    echo "Primer resultado: " . $data['results'][0]['title'] . "<br>";
                }
            } else {
                echo "❌ Error JSON: " . json_last_error_msg() . "<br>";
            }
        } else {
            echo "❌ Error HTTP: " . $httpCode . "<br>";
        }
        
        echo "<h3>3. Probando endpoint HTTP...</h3>";
        $apiUrl = 'http://localhost/api_discogs/api/search?' . http_build_query([
            'q' => 'Pink Floyd',
            'license_key' => $license['license_key'],
            'domain' => 'localhost',
            'discogs_api_key' => 'RHwdshtjSotKMFGXAwRe',
            'discogs_api_secret' => 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ'
        ]);
        
        echo "API URL: " . $apiUrl . "<br><br>";
        
        $apiResponse = file_get_contents($apiUrl);
        $apiData = json_decode($apiResponse, true);
        
        if ($apiData && isset($apiData['success']) && $apiData['success']) {
            echo "✅ Endpoint HTTP funciona<br>";
            echo "Resultados HTTP: " . count($apiData['results']) . "<br>";
        } else {
            echo "❌ Error en endpoint HTTP: " . ($apiData['error'] ?? 'Desconocido') . "<br>";
            echo "Respuesta: " . htmlspecialchars($apiResponse) . "<br>";
        }
        
    } else {
        echo "❌ Error de validación: " . ($auth['error'] ?? 'Desconocido') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

