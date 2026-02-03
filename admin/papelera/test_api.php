<?php
// Habilitar visualización de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de API con Suscripción</h1>";

// Obtener license key de la base de datos
try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    
    $db = Database::getInstance();
    $license = $db->fetch("SELECT license_key FROM licenses ORDER BY created_at DESC LIMIT 1");
    
    if (!$license) {
        echo "<p>❌ No hay licencias en la base de datos. Ejecuta primero test_subscription.php</p>";
        exit;
    }
    
    $licenseKey = $license['license_key'];
    echo "<p>✓ License Key encontrada: " . substr($licenseKey, 0, 10) . "...</p>";
    
    // Cargar configuración de Discogs
    $discogs_config = require_once __DIR__ . '/test_config_discogs.php';
    
    if ($discogs_config['api_key'] === 'TU_CLAVE_DE_DISCOGS_AQUI') {
        echo "<p>❌ <strong>IMPORTANTE:</strong> Debes configurar tus claves de Discogs en test_config_discogs.php</p>";
        echo "<p>1. Ve a <a href='https://www.discogs.com/settings/developers' target='_blank'>https://www.discogs.com/settings/developers</a></p>";
        echo "<p>2. Crea una nueva aplicación</p>";
        echo "<p>3. Copia tu Consumer Key y Consumer Secret</p>";
        echo "<p>4. Reemplaza los valores en test_config_discogs.php</p>";
        exit;
    }
    
    // Probar API de búsqueda
    echo "<h3>Probando búsqueda en Discogs...</h3>";
    
    $url = 'http://localhost/api_discogs/api/search?' . http_build_query([
        'q' => 'Pink Floyd',
        'license_key' => $licenseKey,
        'domain' => 'localhost',
        'discogs_api_key' => $discogs_config['api_key'],
        'discogs_api_secret' => $discogs_config['api_secret']
    ]);
    
    echo "<p><strong>URL de prueba:</strong> " . htmlspecialchars($url) . "</p>";
    
    $response = file_get_contents($url);
    
    echo "<h4>Respuesta cruda de la API:</h4>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "<p>❌ Error decodificando JSON: " . json_last_error_msg() . "</p>";
        echo "<p><strong>Respuesta completa:</strong></p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } elseif ($data && isset($data['success'])) {
        if ($data['success']) {
            echo "<p>✅ API funciona correctamente</p>";
            echo "<p><strong>Resultados encontrados:</strong> " . count($data['results']) . "</p>";
            
            if (!empty($data['results'])) {
                echo "<h4>Primer resultado:</h4>";
                echo "<pre>" . htmlspecialchars(json_encode($data['results'][0], JSON_PRETTY_PRINT)) . "</pre>";
            }
        } else {
            echo "<p>❌ Error en API: " . ($data['error'] ?? 'Error desconocido') . "</p>";
        }
    } else {
        echo "<p>❌ Respuesta inesperada de la API</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
