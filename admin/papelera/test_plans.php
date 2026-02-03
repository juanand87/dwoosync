<?php
require_once 'subscribe/includes/functions.php';

$plans = getSubscriptionPlans();

echo "=== PLANES DE SUSCRIPCIÓN ===\n\n";

foreach($plans as $plan) {
    echo "Plan: " . $plan['name'] . " (ID: " . $plan['id'] . ")\n";
    echo "Precio: $" . $plan['price'] . "/mes\n";
    echo "Destacado: " . ($plan['featured'] ? 'Sí' : 'No') . "\n";
    echo "Características:\n";
    foreach($plan['features'] as $feature) {
        echo "  - " . $feature . "\n";
    }
    echo "\n" . str_repeat("-", 50) . "\n\n";
}
?>
