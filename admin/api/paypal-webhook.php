<?php
/**
 * Webhook de PayPal para validar pagos y activar planes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir configuraciones
require_once '../config/database.php';
require_once '../config/paypal.php';

// Configuración de PayPal
$paypal_client_id = PAYPAL_CLIENT_ID;
$paypal_client_secret = PAYPAL_CLIENT_SECRET;
$paypal_environment = PAYPAL_ENVIRONMENT;

/**
 * Obtener token de acceso de PayPal
 */
function getPayPalAccessToken($client_id, $client_secret, $environment) {
    $url = $environment === 'sandbox' 
        ? 'https://api.sandbox.paypal.com/v1/oauth2/token'
        : 'https://api.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Accept-Language: en_US'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    return null;
}

/**
 * Validar suscripción con PayPal
 */
function validatePayPalSubscription($subscription_id, $access_token, $environment) {
    $url = $environment === 'sandbox'
        ? 'https://api.sandbox.paypal.com/v1/billing/subscriptions/' . $subscription_id
        : 'https://api.paypal.com/v1/billing/subscriptions/' . $subscription_id;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

/**
 * Procesar pago exitoso
 */
function processSuccessfulPayment($subscription_data, $subscriber_id) {
    try {
        $pdo = getDatabase();
        $pdo->beginTransaction();
        
        // Obtener información del plan premium
        $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE plan_type = 'premium'");
        $plan_stmt->execute();
        $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan_data) {
            throw new Exception('Plan premium no encontrado');
        }
        
        // Actualizar plan del suscriptor
        $update_subscriber = $pdo->prepare("UPDATE subscribers SET plan_type = 'premium', status = 'active' WHERE id = ?");
        $update_subscriber->execute([$subscriber_id]);
        
        // Actualizar plan en billing_cycles
        $update_billing = $pdo->prepare("UPDATE billing_cycles SET plan_type = 'premium' WHERE subscriber_id = ? AND status = 'paid' ORDER BY created_at DESC LIMIT 1");
        $update_billing->execute([$subscriber_id]);
        
        // Actualizar licencia
        $update_license = $pdo->prepare("
            UPDATE licenses 
            SET usage_limit = 9999999, status = 'active' 
            WHERE subscriber_id = ?
        ");
        $update_license->execute([$subscriber_id]);
        
        // Crear ciclo de facturación
        $cycle_start = date('Y-m-d');
        $cycle_end = date('Y-m-d', strtotime('+30 days'));
        $due_date = date('Y-m-d', strtotime('+33 days'));
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($subscriber_id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(time(), 4, '0', STR_PAD_LEFT);
        
        $create_cycle = $pdo->prepare("
            INSERT INTO billing_cycles (
                subscriber_id, 
                plan_type, 
                license_key, 
                cycle_start_date, 
                cycle_end_date, 
                due_date,
                is_active, 
                status, 
                sync_count, 
                api_calls_count, 
                products_synced, 
                amount,
                currency,
                payment_method,
                payment_id,
                invoice_number,
                paid_date,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $license_key = $_SESSION['license_key'] ?? 'N/A';
        
        $create_cycle->execute([
            $subscriber_id,
            'premium',
            $license_key,
            $cycle_start,
            $cycle_end,
            $due_date,
            1, // is_active
            'paid',
            0, // sync_count
            0, // api_calls_count
            0, // products_synced
            $plan_data['price'],
            'USD',
            'paypal',
            $subscription_data['id'],
            $invoice_number,
            date('Y-m-d'),
            date('Y-m-d H:i:s')
        ]);
        
        $billing_cycle_id = $pdo->lastInsertId();
        
        // Actualizar sesión
        $_SESSION['plan_type'] = 'premium';
        $_SESSION['billing_cycle_id'] = $billing_cycle_id;
        
        $pdo->commit();
        
        error_log('[PAYPAL] Pago procesado exitosamente - Subscriber: ' . $subscriber_id . ', Subscription ID: ' . $subscription_data['id']);
        
        return [
            'success' => true,
            'message' => 'Plan premium activado exitosamente',
            'billing_cycle_id' => $billing_cycle_id
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log('[PAYPAL] Error al procesar pago: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al procesar el pago: ' . $e->getMessage()
        ];
    }
}

// Procesar petición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['subscription_id']) || !isset($input['subscriber_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
        exit;
    }
    
    $subscription_id = $input['subscription_id'];
    $subscriber_id = $input['subscriber_id'];
    
    // Obtener token de acceso
    $access_token = getPayPalAccessToken($paypal_client_id, $paypal_client_secret, $paypal_environment);
    
    if (!$access_token) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener token de PayPal']);
        exit;
    }
    
    // Validar suscripción
    $subscription_data = validatePayPalSubscription($subscription_id, $access_token, $paypal_environment);
    
    if (!$subscription_data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Suscripción no válida']);
        exit;
    }
    
    // Verificar estado de la suscripción
    if ($subscription_data['status'] !== 'ACTIVE') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Suscripción no activa']);
        exit;
    }
    
    // Procesar pago exitoso
    $result = processSuccessfulPayment($subscription_data, $subscriber_id);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
