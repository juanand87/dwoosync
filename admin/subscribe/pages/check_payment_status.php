<?php
/**
 * Endpoint para verificar el estado de un pago via AJAX
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Verificar que sea una petición AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$billingCycleId = $_GET['billing_cycle_id'] ?? null;

if (!$billingCycleId) {
    echo json_encode(['error' => 'billing_cycle_id requerido']);
    exit;
}

try {
    $db = getDatabase();
    
    // Consultar el estado del billing cycle
    $stmt = $db->prepare("SELECT status, plan_type, amount, created_at FROM billing_cycles WHERE id = ?");
    $stmt->execute([$billingCycleId]);
    $cycle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cycle) {
        echo json_encode(['error' => 'Billing cycle no encontrado']);
        exit;
    }
    
    // Devolver el estado
    echo json_encode([
        'status' => $cycle['status'],
        'plan_type' => $cycle['plan_type'],
        'amount' => $cycle['amount'],
        'created_at' => $cycle['created_at']
    ]);
    
} catch (Exception $e) {
    error_log('Error verificando estado de pago: ' . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>