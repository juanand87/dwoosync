<?php
/**
 * Crear ciclo de facturación pendiente al hacer clic en botón de pago
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir configuraciones
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Asegurar que la sesión esté iniciada
startSecureSession();

// Debug de sesión
error_log('[BILLING] Session ID: ' . session_id());
error_log('[BILLING] Session data: ' . json_encode($_SESSION));
error_log('[BILLING] Is logged in: ' . (isLoggedIn() ? 'YES' : 'NO'));

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    error_log('[BILLING] Usuario no autenticado - Session ID: ' . session_id());
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado', 'session_id' => session_id()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_type = $_POST['plan_type'] ?? '';
    $subscriber_id = $_SESSION['subscriber_id'] ?? '';
    
    error_log('[BILLING] POST recibido - plan_type: ' . $plan_type . ', subscriber_id de sesión: ' . $subscriber_id);
    error_log('[BILLING] POST data completa: ' . json_encode($_POST));
    
    if (empty($plan_type)) {
        error_log('[BILLING] ERROR: Parámetro faltante - plan_type: ' . $plan_type);
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parámetro requerido: plan_type']);
        exit;
    }
    
    if (empty($subscriber_id)) {
        error_log('[BILLING] ERROR: No hay subscriber_id en la sesión');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }
    
    $pdo = null;
    try {
        $pdo = getDatabase();
        
        // Configurar timeout para evitar cuelgues
        $pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);
        $pdo->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET SESSION wait_timeout=30");
        
        error_log('[BILLING] Iniciando creación de ciclo - Subscriber: ' . $subscriber_id . ', Plan: ' . $plan_type);
        
        // Obtener información del plan (sin transacción)
        $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
        $plan_stmt->execute([$plan_type]);
        $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan_data) {
            throw new Exception('Plan no encontrado: ' . $plan_type);
        }
        
        error_log('[BILLING] Plan encontrado: ' . json_encode($plan_data));
        
        // Obtener license_key del usuario (sin transacción)
        $license_stmt = $pdo->prepare("SELECT license_key FROM licenses WHERE subscriber_id = ?");
        $license_stmt->execute([$subscriber_id]);
        $license_data = $license_stmt->fetch(PDO::FETCH_ASSOC);
        $license_key = $license_data['license_key'] ?? 'PENDING-' . $subscriber_id . '-' . time();
        
        error_log('[BILLING] License key: ' . $license_key);
        
        // Verificar si ya existe un ciclo pendiente para este suscriptor
        $existing_cycle_stmt = $pdo->prepare("
            SELECT id, status, created_at, plan_type, amount
            FROM billing_cycles 
            WHERE subscriber_id = ? AND status = 'pending' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $existing_cycle_stmt->execute([$subscriber_id]);
        $existing_cycle = $existing_cycle_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_cycle) {
            error_log('[BILLING] Ya existe un ciclo pendiente - ID: ' . $existing_cycle['id'] . ', Plan: ' . $existing_cycle['plan_type'] . ', Creado: ' . $existing_cycle['created_at']);
            
            // 1. Si es el mismo tipo de plan, no hacer nada
            if ($existing_cycle['plan_type'] === $plan_type) {
                error_log('[BILLING] Mismo plan pendiente - No se requiere acción');
                echo json_encode([
                    'success' => true,
                    'message' => 'Ya existe una factura pendiente para este plan',
                    'billing_cycle_id' => $existing_cycle['id'],
                    'invoice_number' => 'EXISTING-' . $existing_cycle['id'],
                    'replaced_existing' => false,
                    'no_action_needed' => true
                ]);
                exit;
            }
            
            // 2. Si es otro tipo de plan, actualizar la factura existente
            error_log('[BILLING] Plan diferente pendiente - Actualizando factura existente');
            error_log('[BILLING] Plan actual: ' . $existing_cycle['plan_type'] . ' -> Nuevo plan: ' . $plan_type);
            error_log('[BILLING] Precio actual: ' . $existing_cycle['amount'] . ' -> Nuevo precio: ' . $plan_data['price']);
            
            $update_cycle_stmt = $pdo->prepare("
                UPDATE billing_cycles 
                SET plan_type = ?, amount = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $update_result = $update_cycle_stmt->execute([$plan_type, $plan_data['price'], $existing_cycle['id']]);
            
            error_log('[BILLING] Resultado de actualización: ' . ($update_result ? 'SUCCESS' : 'FAILED'));
            
            if ($update_result) {
                error_log('[BILLING] Factura existente actualizada exitosamente');
                echo json_encode([
                    'success' => true,
                    'message' => 'Factura pendiente actualizada con el nuevo plan',
                    'billing_cycle_id' => $existing_cycle['id'],
                    'invoice_number' => 'UPDATED-' . $existing_cycle['id'],
                    'replaced_existing' => true,
                    'no_action_needed' => false
                ]);
                exit;
            } else {
                error_log('[BILLING] Error actualizando factura existente');
                throw new Exception('Error al actualizar factura existente');
            }
        }
        
        error_log('[BILLING] Creando nuevo ciclo de facturación');
        
        // Crear ciclo de facturación pendiente (sin transacción para evitar deadlocks)
        $cycle_start = date('Y-m-d');
        $cycle_end = date('Y-m-d', strtotime('+30 days'));
        $due_date = date('Y-m-d', strtotime('+33 days'));
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($subscriber_id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(time(), 4, '0', STR_PAD_LEFT);
        
        error_log('[BILLING] Datos del ciclo - Start: ' . $cycle_start . ', End: ' . $cycle_end . ', Due: ' . $due_date . ', Invoice: ' . $invoice_number);
        
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
                payment_reference,
                invoice_number,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $execute_data = [
            $subscriber_id,
            $plan_type,
            $license_key,
            $cycle_start,
            $cycle_end,
            $due_date,
            0, // is_active = 0 (pendiente)
            'pending', // status = pending
            0, // sync_count
            0, // api_calls_count
            0, // products_synced
            $plan_data['price'],
            $plan_type === 'free' ? 'USD' : 'CLP',
            'pending', // payment_method = pending
            'PENDING-' . time(), // payment_reference temporal
            $invoice_number,
            date('Y-m-d H:i:s')
        ];
        
        error_log('[BILLING] Ejecutando INSERT con datos: ' . json_encode($execute_data));
        
        $result = $create_cycle->execute($execute_data);
        
        if (!$result) {
            throw new Exception('Error al ejecutar INSERT: ' . json_encode($create_cycle->errorInfo()));
        }
        
        $billing_cycle_id = $pdo->lastInsertId();
        
        error_log('[BILLING] Ciclo creado con ID: ' . $billing_cycle_id);
        
        // Actualizar sesión
        $_SESSION['billing_cycle_id'] = $billing_cycle_id;
        
        // Cerrar conexión explícitamente
        $pdo = null;
        
        error_log('[BILLING] Ciclo pendiente creado exitosamente - Subscriber: ' . $subscriber_id . ', Plan: ' . $plan_type . ', Cycle ID: ' . $billing_cycle_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Nueva factura pendiente creada',
            'billing_cycle_id' => $billing_cycle_id,
            'invoice_number' => $invoice_number,
            'replaced_existing' => false,
            'no_action_needed' => false
        ]);
        
    } catch (Exception $e) {
        if ($pdo) {
            $pdo = null;
        }
        error_log('[BILLING] Error creando ciclo pendiente: ' . $e->getMessage());
        error_log('[BILLING] Stack trace: ' . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al crear ciclo de facturación: ' . $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
