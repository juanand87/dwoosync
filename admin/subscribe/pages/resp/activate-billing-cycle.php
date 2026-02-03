<?php
/**
 * Endpoint para activar un ciclo de facturación pagado
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/billing-functions.php';

// Verificar que el usuario esté logueado (puedes agregar verificación de admin aquí)
startSecureSession();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billing_cycle_id = $_POST['billing_cycle_id'] ?? '';
    
    if (empty($billing_cycle_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de ciclo de facturación requerido']);
        exit;
    }
    
    if (!is_numeric($billing_cycle_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de ciclo de facturación inválido']);
        exit;
    }
    
    $result = activatePaidBillingCycle($billing_cycle_id);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(500);
    }
    
    echo json_encode($result);
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>


