<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Directo de Discogs API</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/CacheManager.php';
    require_once __DIR__ . '/classes/DiscogsAPI.php';
    
    echo "<h3>1. Creando instancia de DiscogsAPI...</h3>";
    $discogs = new DiscogsAPI('RHwdshtjSotKMFGXAwRe');
    echo "✅ DiscogsAPI creado<br><br>";
    
    echo "<h3>2. Probando búsqueda simple...</h3>";
    $result = $discogs->searchMasters('Pink Floyd', []);
    
    echo "Tipo de resultado: " . gettype($result) . "<br>";
    echo "¿Es array? " . (is_array($result) ? 'Sí' : 'No') . "<br>";
    
    if (is_array($result)) {
        echo "Claves del array: " . implode(', ', array_keys($result)) . "<br>";
        
        if (isset($result['success'])) {
            echo "Success: " . ($result['success'] ? 'true' : 'false') . "<br>";
            if (isset($result['error'])) {
                echo "Error: " . $result['error'] . "<br>";
            }
            if (isset($result['results'])) {
                echo "Resultados: " . count($result['results']) . "<br>";
            }
        } else {
            echo "No hay campo 'success' en el resultado<br>";
        }
    } else {
        echo "El resultado no es un array<br>";
    }
    
    echo "<h3>3. Resultado completo:</h3>";
    echo "<pre>" . htmlspecialchars(print_r($result, true)) . "</pre>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>

