<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Completo de Autenticación</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/LicenseManager.php';
    
    $db = Database::getInstance();
    $licenseManager = new LicenseManager();
    
    // Obtener license key
    $license = $db->fetch("SELECT * FROM licenses WHERE id = 1");
    $licenseKey = $license['license_key'];
    
    echo "<h3>1. Datos de la licencia:</h3>";
    echo "License Key: " . $licenseKey . "<br>";
    echo "Status: " . $license['status'] . "<br>";
    echo "Domain: " . $license['domain'] . "<br>";
    echo "Subscriber ID: " . $license['subscriber_id'] . "<br><br>";
    
    // Verificar suscriptor
    echo "<h3>2. Datos del suscriptor:</h3>";
    $subscriber = $db->fetch("SELECT * FROM subscribers WHERE id = ?", [$license['subscriber_id']]);
    if ($subscriber) {
        echo "Email: " . $subscriber['email'] . "<br>";
        echo "Status: " . $subscriber['status'] . "<br>";
        echo "First Name: " . $subscriber['first_name'] . "<br>";
        echo "Last Name: " . $subscriber['last_name'] . "<br><br>";
    } else {
        echo "❌ Suscriptor no encontrado<br><br>";
    }
    
    // Verificar planes
    echo "<h3>3. Planes disponibles:</h3>";
    $plans = $db->fetchAll("SELECT * FROM subscription_plans");
    foreach ($plans as $plan) {
        echo "- " . $plan['plan_type'] . " (activo: " . ($plan['is_active'] ? 'Sí' : 'No') . ")<br>";
    }
    echo "<br>";
    
    // Probar la consulta SQL del LicenseManager
    echo "<h3>4. Probando consulta SQL del LicenseManager:</h3>";
    $sql = "SELECT l.*, s.email, s.first_name, s.last_name, s.status as subscriber_status, sp.requests_per_hour, sp.requests_per_day, sp.requests_per_month, sp.features, sp.plan_type
            FROM licenses l
            JOIN subscribers s ON l.subscriber_id = s.id
            JOIN subscription_plans sp ON sp.plan_type = 'premium'
            WHERE l.license_key = :license_key 
            AND l.domain = :domain 
            AND l.status = 'active'
            AND s.status = 'active'
            AND (l.expires_at IS NULL OR l.expires_at > NOW())";
    
    $result = $db->fetch($sql, [
        'license_key' => $licenseKey,
        'domain' => 'localhost'
    ]);
    
    if ($result) {
        echo "✅ Consulta SQL exitosa<br>";
        echo "Plan Type: " . $result['plan_type'] . "<br>";
        echo "Requests per day: " . $result['requests_per_day'] . "<br>";
    } else {
        echo "❌ Consulta SQL falló<br>";
    }
    
    // Probar validación de licencia
    echo "<h3>5. Probando validación de licencia:</h3>";
    $auth = $licenseManager->validateLicense($licenseKey, 'localhost');
    
    if ($auth['valid']) {
        echo "✅ Validación exitosa<br>";
        echo "License ID: " . $auth['license']['id'] . "<br>";
        echo "Plan: " . $auth['license']['plan_type'] . "<br>";
        echo "Email: " . $auth['license']['email'] . "<br>";
    } else {
        echo "❌ Error de validación: " . ($auth['error'] ?? 'Desconocido') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>
