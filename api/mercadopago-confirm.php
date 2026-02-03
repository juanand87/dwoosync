<?php
/**
 * Endpoint para confirmar pagos de MercadoPago
 * Este es el URL que debes configurar en MercadoPago como "Pago aprobado"
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir configuraciones
require_once '../config/database.php';

// Función para procesar pago exitoso
function processMercadoPagoPayment($preapproval_id, $subscriber_id, $plan_type = 'premium') {
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
        
        // Obtener license_key de la sesión o generar uno
        session_start();
        $license_key = $_SESSION['license_key'] ?? 'MP-' . $subscriber_id . '-' . time();
        
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
            'billing_cycle_id' => $billing_cycle_id,
            'invoice_number' => $invoice_number
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
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Obtener parámetros de la URL
    $preapproval_id = $_GET['preapproval_id'] ?? '';
    $external_reference = $_GET['external_reference'] ?? '';
    $status = $_GET['status'] ?? '';
    
    // Log de la petición
    error_log('[MERCADOPAGO] Confirmación recibida - Preapproval: ' . $preapproval_id . ', External Ref: ' . $external_reference . ', Status: ' . $status);
    
    if (empty($preapproval_id) || empty($external_reference)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Parámetros requeridos: preapproval_id y external_reference'
        ]);
        exit;
    }
    
    // Procesar pago exitoso
    $result = processMercadoPagoPayment($preapproval_id, $external_reference, 'premium');
    
    if ($result['success']) {
        // Redirigir al dashboard con parámetros de éxito
        $dashboard_url = '../subscribe/pages/dashboard.php?payment_success=true&method=mercadopago&invoice=' . $result['invoice_number'];
        header('Location: ' . $dashboard_url);
        exit;
    } else {
        // Redirigir al checkout con error
        $checkout_url = '../subscribe/pages/checkout.php?plan=premium&error=payment_failed&message=' . urlencode($result['message']);
        header('Location: ' . $checkout_url);
        exit;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar webhook de MercadoPago
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['preapproval_id']) || !isset($input['external_reference'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
        exit;
    }
    
    $preapproval_id = $input['preapproval_id'];
    $external_reference = $input['external_reference'];
    
    $result = processMercadoPagoPayment($preapproval_id, $external_reference, 'premium');
    
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


