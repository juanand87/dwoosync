<?php
/**
 * Endpoint para obtener planes de suscripción
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración y clases
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    
    // Obtener todos los planes activos
    $plans = $db->query("
        SELECT plan_type, plan_name, price, currency, duration_days
        FROM subscription_plans 
        WHERE is_active = 1 
        ORDER BY price ASC
    ")->fetchAll();
    
    // Formatear la respuesta
    $formatted_plans = [];
    foreach ($plans as $plan) {
        $formatted_plans[$plan['plan_type']] = [
            'plan_type' => $plan['plan_type'],
            'plan_name' => $plan['plan_name'],
            'price' => number_format($plan['price'], 2),
            'currency' => $plan['currency'],
            'duration_days' => $plan['duration_days']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'plans' => $formatted_plans
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>



