<?php
/**
 * Webhook de MercadoPago para validar pagos y activar planes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir configuraciones
require_once '../config/database.php';

/**
 * Procesar pago exitoso de MercadoPago
 */
function processMercadoPagoPayment($preapproval_id, $subscriber_id, $plan_type) {
    try {
        $pdo = getDatabase();
        $pdo->beginTransaction();
        
        // Obtener información del plan
        $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
        $plan_stmt->execute([$plan_type]);
        $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan_data) {
            throw new Exception('Plan no encontrado');
        }
        
        // Actualizar plan del suscriptor
        $update_subscriber = $pdo->prepare("UPDATE subscribers SET plan_type = ?, status = 'active' WHERE id = ?");
        $update_subscriber->execute([$plan_type, $subscriber_id]);
        
        // Actualizar plan en billing_cycles
        $update_billing = $pdo->prepare("UPDATE billing_cycles SET plan_type = ? WHERE subscriber_id = ? AND status = 'paid' ORDER BY created_at DESC LIMIT 1");
        $update_billing->execute([$plan_type, $subscriber_id]);
        
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
            $plan_type,
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
            'CLP', // MercadoPago usa pesos chilenos
            'mercadopago',
            $preapproval_id,
            $invoice_number,
            date('Y-m-d'),
            date('Y-m-d H:i:s')
        ]);
        
        $billing_cycle_id = $pdo->lastInsertId();
        
        // Actualizar sesión
        $_SESSION['plan_type'] = $plan_type;
        $_SESSION['billing_cycle_id'] = $billing_cycle_id;
        
        $pdo->commit();
        
        error_log('[MERCADOPAGO] Pago procesado exitosamente - Subscriber: ' . $subscriber_id . ', Preapproval ID: ' . $preapproval_id);
        
        return [
            'success' => true,
            'message' => 'Plan ' . $plan_type . ' activado exitosamente',
            'billing_cycle_id' => $billing_cycle_id
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log('[MERCADOPAGO] Error al procesar pago: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error al procesar el pago: ' . $e->getMessage()
        ];
    }
}

// Procesar petición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['preapproval_id']) || !isset($input['subscriber_id']) || !isset($input['plan_type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
        exit;
    }
    
    $preapproval_id = $input['preapproval_id'];
    $subscriber_id = $input['subscriber_id'];
    $plan_type = $input['plan_type'];
    
    // Procesar pago exitoso
    $result = processMercadoPagoPayment($preapproval_id, $subscriber_id, $plan_type);
    
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



