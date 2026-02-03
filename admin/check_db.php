<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificación de Base de Datos</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    
    $db = Database::getInstance();
    
    // Verificar licencias
    echo "<h3>1. Licencias:</h3>";
    $licenses = $db->fetchAll("SELECT * FROM licenses");
    echo "Total: " . count($licenses) . "<br>";
    foreach ($licenses as $license) {
        echo "- ID: " . $license['id'] . ", Key: " . substr($license['license_key'], 0, 10) . "..., Plan: " . $license['plan_type'] . ", Activa: " . ($license['is_active'] ? 'Sí' : 'No') . "<br>";
    }
    
    // Verificar suscriptores
    echo "<h3>2. Suscriptores:</h3>";
    $subscribers = $db->fetchAll("SELECT * FROM subscribers");
    echo "Total: " . count($subscribers) . "<br>";
    foreach ($subscribers as $subscriber) {
        echo "- ID: " . $subscriber['id'] . ", Email: " . $subscriber['email'] . ", Dominio: " . $subscriber['domain'] . "<br>";
    }
    
    // Verificar planes
    echo "<h3>3. Planes de Suscripción:</h3>";
    $plans = $db->fetchAll("SELECT * FROM subscription_plans");
    echo "Total: " . count($plans) . "<br>";
    foreach ($plans as $plan) {
        echo "- ID: " . $plan['id'] . ", Tipo: " . $plan['plan_type'] . ", Activo: " . ($plan['is_active'] ? 'Sí' : 'No') . "<br>";
    }
    
    // Verificar si hay datos en las tablas
    echo "<h3>4. Verificación de datos:</h3>";
    $licenseCount = $db->fetch("SELECT COUNT(*) as count FROM licenses")['count'];
    $subscriberCount = $db->fetch("SELECT COUNT(*) as count FROM subscribers")['count'];
    $planCount = $db->fetch("SELECT COUNT(*) as count FROM subscription_plans")['count'];
    
    echo "Licencias: " . $licenseCount . "<br>";
    echo "Suscriptores: " . $subscriberCount . "<br>";
    echo "Planes: " . $planCount . "<br>";
    
    if ($planCount == 0) {
        echo "<p style='color: red;'>❌ No hay planes de suscripción. Necesitamos ejecutar el script de creación de tablas.</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

