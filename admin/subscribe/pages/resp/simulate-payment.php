<?php
/**
 * Simular pago para pruebas
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión de forma segura
startSecureSession();

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'simulate_payment') {
    // Log para debug
    error_log('[SIMULATE_PAYMENT] Iniciando pago simulado para subscriber_id: ' . $_SESSION['subscriber_id']);
    
    try {
        $db = getDatabase();
        $db->beginTransaction();
        
        $subscriberId = $_SESSION['subscriber_id'];
        $planType = $_POST['plan_type'] ?? 'free';
        
        // Obtener información del plan
        $planStmt = $db->prepare("SELECT price, plan_name FROM subscription_plans WHERE plan_type = ?");
        $planStmt->execute([$planType]);
        $planData = $planStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$planData) {
            throw new Exception('Plan no encontrado');
        }
        
        // Actualizar plan del suscriptor
        $stmt = $db->prepare("UPDATE subscribers SET plan_type = ?, status = 'active' WHERE id = ?");
        $stmt->execute([$planType, $subscriberId]);
        
        // Actualizar límite de uso de la licencia
        $usageLimit = ($planType === 'free') ? 10 : 9999999;
        $stmt = $db->prepare("UPDATE licenses SET status = 'active', usage_limit = ? WHERE subscriber_id = ?");
        $stmt->execute([$usageLimit, $subscriberId]);
        
        // Crear factura (ciclo de facturación) para simulación
        $cycleStart = date('Y-m-d');
        $cycleEnd = date('Y-m-d', strtotime('+30 days'));
        $dueDate = date('Y-m-d', strtotime($cycleEnd . ' +3 days')); // 3 días después del ciclo
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($subscriberId, 6, '0', STR_PAD_LEFT) . '-' . str_pad(time(), 4, '0', STR_PAD_LEFT);
        
        // Desactivar ciclos anteriores
        $stmt = $db->prepare("UPDATE billing_cycles SET is_active = 0 WHERE subscriber_id = ?");
        $stmt->execute([$subscriberId]);
        
        // Crear nuevo ciclo de facturación
        $stmt = $db->prepare("
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
                invoice_number,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $licenseKey = $_SESSION['license_key'] ?? 'N/A';
        $isPaid = true; // Simulación siempre es pagada
        $status = 'paid';
        $isActive = 1;
        
        $stmt->execute([
            $subscriberId,
            $planType,
            $licenseKey,
            $cycleStart,
            $cycleEnd,
            $dueDate, // due_date = cycle_end_date + 3 días
            $isActive,
            $status,
            0, // sync_count
            0, // api_calls_count
            0, // products_synced
            $planData['price'],
            $invoiceNumber,
            date('Y-m-d H:i:s')
        ]);
        
        $billingCycleId = $db->lastInsertId();
        $_SESSION['billing_cycle_id'] = $billingCycleId;
        
        error_log('[SIMULATE_PAYMENT] Factura creada - ID: ' . $billingCycleId . ', Plan: ' . $planType . ', Amount: ' . $planData['price']);
        
        $db->commit();
        
        // Limpiar estado de cuenta inactiva
        unset($_SESSION['account_status']);
        
        echo json_encode(['success' => true, 'message' => 'Pago simulado exitosamente']);
        
    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>
