<?php
/**
 * Endpoint para recibir confirmaciones de pago de cualquier método
 * Compatible con PayPal, Stripe, transferencias bancarias, etc.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    
    // Si no es JSON, intentar con $_POST
    if (!$data) {
        $data = $_POST;
    }
    
    // Validar datos requeridos
    $required_fields = ['payment_id', 'status', 'subscriber_id'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    $payment_id = $data['payment_id'];
    $status = $data['status'];
    $subscriber_id = $data['subscriber_id'];
    $payment_method = $data['payment_method'] ?? 'unknown';
    $amount = $data['amount'] ?? null;
    $currency = $data['currency'] ?? 'USD';
    $transaction_id = $data['transaction_id'] ?? null;
    $notes = $data['notes'] ?? '';
    
    // Validar status
    $valid_statuses = ['completed', 'pending', 'failed', 'cancelled', 'refunded'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception("Status de pago inválido: $status");
    }
    
    // Conectar a la base de datos
    $db = getDatabase();
    $db->beginTransaction();
    
    // Verificar que el suscriptor existe
    $stmt = $db->prepare("SELECT id, plan_type, status FROM subscribers WHERE id = ?");
    $stmt->execute([$subscriber_id]);
    $subscriber = $stmt->fetch();
    
    if (!$subscriber) {
        throw new Exception("Suscriptor no encontrado: $subscriber_id");
    }
    
    // Actualizar el pago
    $stmt = $db->prepare("
        UPDATE payments 
        SET status = ?, 
            payment_method = ?, 
            amount = ?, 
            currency = ?, 
            transaction_id = ?, 
            notes = ?,
            updated_at = NOW()
        WHERE payment_id = ? AND subscriber_id = ?
    ");
    $stmt->execute([
        $status,
        $payment_method,
        $amount,
        $currency,
        $transaction_id,
        $notes,
        $payment_id,
        $subscriber_id
    ]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Pago no encontrado: $payment_id");
    }
    
    // Si el pago se completó, activar la suscripción
    if ($status === 'completed') {
        // Activar suscriptor
        $stmt = $db->prepare("UPDATE subscribers SET status = 'active' WHERE id = ?");
        $stmt->execute([$subscriber_id]);
        
        // Activar licencia
        $stmt = $db->prepare("UPDATE licenses SET status = 'active' WHERE subscriber_id = ?");
        $stmt->execute([$subscriber_id]);
        
        // Crear ciclo de facturación si no existe
        $stmt = $db->prepare("
            SELECT COUNT(*) as count 
            FROM billing_cycles 
            WHERE subscriber_id = ? AND is_active = 1
        ");
        $stmt->execute([$subscriber_id]);
        $cycle_exists = $stmt->fetch()['count'] > 0;
        
        if (!$cycle_exists) {
            // Obtener license_key
            $stmt = $db->prepare("SELECT license_key FROM licenses WHERE subscriber_id = ?");
            $stmt->execute([$subscriber_id]);
            $license = $stmt->fetch();
            
            if ($license) {
                $stmt = $db->prepare("
                    INSERT INTO billing_cycles (
                        subscriber_id, 
                        license_key, 
                        cycle_start_date, 
                        cycle_end_date, 
                        is_active, 
                        sync_count, 
                        api_calls_count, 
                        products_synced, 
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $cycleStart = date('Y-m-d');
                $cycleEnd = date('Y-m-d', strtotime('+30 days'));
                
                $stmt->execute([
                    $subscriber_id,
                    $license['license_key'],
                    $cycleStart,
                    $cycleEnd,
                    1, // is_active = 1
                    0, // sync_count
                    0, // api_calls_count
                    0, // products_synced
                    date('Y-m-d H:i:s')
                ]);
            }
        }
        
        // Log de activación
        error_log("Suscripción activada - Subscriber ID: $subscriber_id, Payment ID: $payment_id");
    }
    
    // Si el pago falló o fue cancelado, mantener inactivo
    if (in_array($status, ['failed', 'cancelled', 'refunded'])) {
        $stmt = $db->prepare("UPDATE subscribers SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$subscriber_id]);
        
        $stmt = $db->prepare("UPDATE licenses SET status = 'inactive' WHERE subscriber_id = ?");
        $stmt->execute([$subscriber_id]);
        
        // Log de desactivación
        error_log("Suscripción desactivada - Subscriber ID: $subscriber_id, Payment ID: $payment_id, Status: $status");
    }
    
    $db->commit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Confirmación de pago procesada correctamente',
        'data' => [
            'subscriber_id' => $subscriber_id,
            'payment_id' => $payment_id,
            'status' => $status,
            'subscription_active' => $status === 'completed'
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    
    error_log("Error en payment-confirmation: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

