<?php
/**
 * Webhook de Stripe para confirmaciones de pago
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    // Obtener datos del webhook de Stripe
    $input = file_get_contents('php://input');
    $webhook_data = json_decode($input, true);
    
    if (!$webhook_data) {
        throw new Exception("Datos del webhook inválidos");
    }
    
    // Log del webhook para debugging
    error_log("Stripe Webhook recibido: " . json_encode($webhook_data));
    
    // Verificar que es un evento de pago
    if (!isset($webhook_data['type'])) {
        throw new Exception("Tipo de evento no especificado");
    }
    
    $event_type = $webhook_data['type'];
    $data = $webhook_data['data']['object'] ?? [];
    
    // Procesar según el tipo de evento
    switch ($event_type) {
        case 'payment_intent.succeeded':
            $payment_id = $data['id'] ?? null;
            $amount = ($data['amount'] ?? 0) / 100; // Stripe usa centavos
            $currency = $data['currency'] ?? 'usd';
            $status = 'completed';
            break;
            
        case 'payment_intent.payment_failed':
        case 'payment_intent.canceled':
            $payment_id = $data['id'] ?? null;
            $amount = ($data['amount'] ?? 0) / 100;
            $currency = $data['currency'] ?? 'usd';
            $status = 'failed';
            break;
            
        case 'payment_intent.requires_action':
            $payment_id = $data['id'] ?? null;
            $amount = ($data['amount'] ?? 0) / 100;
            $currency = $data['currency'] ?? 'usd';
            $status = 'pending';
            break;
            
        default:
            // Evento no relevante, responder OK
            echo json_encode(['success' => true, 'message' => 'Evento no procesado']);
            exit;
    }
    
    if (!$payment_id) {
        throw new Exception("ID de pago no encontrado en el webhook");
    }
    
    // Buscar el pago en nuestra base de datos
    $db = getDatabase();
    $stmt = $db->prepare("
        SELECT p.*, s.id as subscriber_id, s.plan_type 
        FROM payments p 
        JOIN subscribers s ON p.subscriber_id = s.id 
        WHERE p.payment_id = ? OR p.transaction_id = ?
    ");
    $stmt->execute([$payment_id, $payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        throw new Exception("Pago no encontrado en la base de datos: $payment_id");
    }
    
    // Preparar datos para el endpoint de confirmación
    $confirmation_data = [
        'payment_id' => $payment['payment_id'],
        'status' => $status,
        'subscriber_id' => $payment['subscriber_id'],
        'payment_method' => 'stripe',
        'amount' => $amount,
        'currency' => strtoupper($currency),
        'transaction_id' => $payment_id,
        'notes' => "Webhook Stripe: $event_type"
    ];
    
    // Llamar al endpoint de confirmación
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/api_discogs/api/payment-confirmation.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($confirmation_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($confirmation_data))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Error al procesar confirmación: HTTP $http_code");
    }
    
    $result = json_decode($response, true);
    if (!$result['success']) {
        throw new Exception("Error en confirmación: " . $result['error']);
    }
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Webhook procesado correctamente',
        'data' => $result['data']
    ]);
    
} catch (Exception $e) {
    error_log("Error en Stripe webhook: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

