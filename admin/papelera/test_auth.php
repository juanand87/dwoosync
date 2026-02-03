<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Autenticación</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    require_once __DIR__ . '/classes/LicenseManager.php';
    
    // Obtener license key
    $db = Database::getInstance();
    $license = $db->fetch("SELECT license_key FROM licenses ORDER BY created_at DESC LIMIT 1");
    
    if ($license) {
        $licenseKey = $license['license_key'];
        echo "✓ License Key: " . substr($licenseKey, 0, 10) . "...<br>";
        
        // Probar autenticación paso a paso
        echo "<h3>Paso 1: Verificando licencia en base de datos...</h3>";
        $licenseData = $db->fetch("SELECT * FROM licenses WHERE license_key = ?", [$licenseKey]);
        if ($licenseData) {
            echo "✓ Licencia encontrada en BD<br>";
            echo "Subscriber ID: " . $licenseData['subscriber_id'] . "<br>";
            echo "Plan: " . $licenseData['plan_type'] . "<br>";
            echo "Estado: " . ($licenseData['is_active'] ? 'Activa' : 'Inactiva') . "<br>";
        } else {
            echo "❌ Licencia no encontrada en BD<br>";
        }
        
        echo "<h3>Paso 2: Verificando suscriptor...</h3>";
        $subscriber = $db->fetch("SELECT * FROM subscribers WHERE id = ?", [$licenseData['subscriber_id']]);
        if ($subscriber) {
            echo "✓ Suscriptor encontrado<br>";
            echo "Email: " . $subscriber['email'] . "<br>";
            echo "Dominio: " . $subscriber['domain'] . "<br>";
        } else {
            echo "❌ Suscriptor no encontrado<br>";
        }
        
        echo "<h3>Paso 3: Verificando plan de suscripción...</h3>";
        $plan = $db->fetch("SELECT * FROM subscription_plans WHERE plan_type = ? AND is_active = 1", [$licenseData['plan_type']]);
        if ($plan) {
            echo "✓ Plan encontrado<br>";
            echo "Tipo: " . $plan['plan_type'] . "<br>";
            echo "Límite diario: " . $plan['requests_per_day'] . "<br>";
            echo "Límite mensual: " . $plan['requests_per_month'] . "<br>";
        } else {
            echo "❌ Plan no encontrado<br>";
        }
        
        echo "<h3>Paso 4: Probando autenticación completa...</h3>";
        $licenseManager = new LicenseManager();
        $auth = $licenseManager->authenticate($licenseKey, 'localhost');
        
        if ($auth['authenticated']) {
            echo "✓ Autenticación exitosa<br>";
            echo "Subscriber ID: " . $auth['subscriber_id'] . "<br>";
            echo "Plan: " . $auth['plan_type'] . "<br>";
        } else {
            echo "❌ Error de autenticación: " . ($auth['error'] ?? 'Desconocido') . "<br>";
        }
        
    } else {
        echo "❌ No hay licencias en la base de datos<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>

