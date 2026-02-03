<?php
/**
 * Endpoint para confirmaciones manuales de pago
 * Transferencias bancarias, cheques, efectivo, etc.
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
    // Obtener datos del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    // Validar datos requeridos
    $required_fields = ['payment_id', 'subscriber_id', 'admin_key'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    // Verificar clave de administrador (por seguridad)
    $admin_key = $data['admin_key'];
    $valid_admin_keys = ['ADMIN_KEY_2024', 'MANUAL_PAYMENT_KEY']; // Cambiar por claves reales
    
    if (!in_array($admin_key, $valid_admin_keys)) {
        throw new Exception("Clave de administrador inválida");
    }
    
    $payment_id = $data['payment_id'];
    $subscriber_id = $data['subscriber_id'];
    $status = $data['status'] ?? 'completed';
    $payment_method = $data['payment_method'] ?? 'manual';
    $amount = $data['amount'] ?? null;
    $currency = $data['currency'] ?? 'USD';
    $transaction_id = $data['transaction_id'] ?? null;
    $notes = $data['notes'] ?? 'Pago manual confirmado';
    $confirmed_by = $data['confirmed_by'] ?? 'admin';
    
    // Validar status
    $valid_statuses = ['completed', 'pending', 'failed', 'cancelled', 'refunded'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception("Status de pago inválido: $status");
    }
    
    // Preparar datos para el endpoint de confirmación
    $confirmation_data = [
        'payment_id' => $payment_id,
        'status' => $status,
        'subscriber_id' => $subscriber_id,
        'payment_method' => $payment_method,
        'amount' => $amount,
        'currency' => $currency,
        'transaction_id' => $transaction_id,
        'notes' => "$notes (Confirmado por: $confirmed_by)"
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
    
    // Log de confirmación manual
    error_log("Pago manual confirmado - Subscriber ID: $subscriber_id, Payment ID: $payment_id, Status: $status, By: $confirmed_by");
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Pago manual procesado correctamente',
        'data' => $result['data']
    ]);
    
} catch (Exception $e) {
    error_log("Error en manual-payment: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

