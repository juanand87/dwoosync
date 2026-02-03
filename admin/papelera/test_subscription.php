<?php
// Habilitar visualización de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Suscripción</h1>";

// Definir constante para acceso
define('API_ACCESS', true);

try {
    // Incluir clases
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/LicenseManager.php';
    
    echo "<p>✓ Clases cargadas</p>";
    
    // Crear suscripción de prueba
    $licenseManager = new LicenseManager();
    
    $subscriptionData = [
        'email' => 'test@example.com',
        'domain' => 'localhost',
        'first_name' => 'Usuario',
        'last_name' => 'Prueba',
        'city' => 'Ciudad',
        'country' => 'México',
        'plan_type' => 'premium'
    ];
    
    echo "<p>Creando suscripción de prueba...</p>";
    
    $result = $licenseManager->createSubscription($subscriptionData);
    
    if ($result['success']) {
        echo "<h2>✅ Suscripción creada exitosamente</h2>";
        echo "<p><strong>License Key:</strong> " . $result['license_key'] . "</p>";
        echo "<p><strong>Subscription Code:</strong> " . $result['subscription_code'] . "</p>";
        echo "<p><strong>Subscriber ID:</strong> " . $result['subscriber_id'] . "</p>";
        echo "<p><strong>License ID:</strong> " . $result['license_id'] . "</p>";
        
        // Probar validación de licencia
        echo "<h3>Probando validación de licencia...</h3>";
        $validation = $licenseManager->validateLicense($result['license_key'], 'localhost');
        
        if ($validation['valid']) {
            echo "<p>✅ Licencia válida</p>";
            echo "<p><strong>Plan:</strong> " . $validation['license']['plan_type'] . "</p>";
            echo "<p><strong>Email:</strong> " . $validation['license']['email'] . "</p>";
        } else {
            echo "<p>❌ Error validando licencia: " . $validation['error'] . "</p>";
        }
        
    } else {
        echo "<h2>❌ Error creando suscripción</h2>";
        echo "<p><strong>Error:</strong> " . $result['error'] . "</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error:</h2>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Línea:</strong> " . $e->getLine() . "</p>";
}
?>

