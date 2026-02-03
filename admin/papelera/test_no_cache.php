<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test sin Caché</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/LicenseManager.php';
    require_once __DIR__ . '/classes/CacheManager.php';
    require_once __DIR__ . '/classes/DiscogsAPI.php';
    
    $db = Database::getInstance();
    $license = $db->fetch("SELECT * FROM licenses WHERE id = 1");
    
    echo "Licencia: " . $license['license_key'] . "<br>";
    echo "Status: " . $license['status'] . "<br><br>";
    
    // Probar validación de licencia
    $licenseManager = new LicenseManager();
    $auth = $licenseManager->validateLicense($license['license_key'], 'localhost');
    
    if ($auth['valid']) {
        echo "✅ Validación exitosa<br>";
        echo "License ID: " . $auth['license']['id'] . "<br>";
        echo "Plan: " . $auth['license']['plan_type'] . "<br><br>";
        
        // Limpiar caché
        echo "Limpiando caché...<br>";
        $cache = new CacheManager();
        $cache->delete('search_masters_d991fcbfe8b723181292fa993e7a98aa');
        echo "✅ Caché limpiado<br><br>";
        
        // Probar búsqueda en Discogs
        echo "Probando búsqueda en Discogs...<br>";
        $discogs = new DiscogsAPI('RHwdshtjSotKMFGXAwRe', 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ');
        $result = $discogs->searchMasters('Pink Floyd', []);
        
        echo "Tipo de resultado: " . gettype($result) . "<br>";
        echo "¿Es array? " . (is_array($result) ? 'Sí' : 'No') . "<br>";
        
        if (is_array($result)) {
            echo "Claves del array: " . implode(', ', array_keys($result)) . "<br>";
            
            if (isset($result['success'])) {
                echo "Success: " . ($result['success'] ? 'true' : 'false') . "<br>";
                if (isset($result['results'])) {
                    echo "Resultados: " . count($result['results']) . "<br>";
                }
                if (isset($result['error'])) {
                    echo "Error: " . $result['error'] . "<br>";
                }
            } else {
                echo "No hay campo 'success' en el resultado<br>";
            }
        } else {
            echo "El resultado no es un array<br>";
        }
        
        echo "<h3>Resultado completo:</h3>";
        echo "<pre>" . htmlspecialchars(print_r($result, true)) . "</pre>";
        
    } else {
        echo "❌ Error de validación: " . ($auth['error'] ?? 'Desconocido') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>

