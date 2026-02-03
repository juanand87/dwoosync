<?php
/**
 * Versión simplificada de actions.php para debugging
 */

// Configurar error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Incluir archivos necesarios
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/functions.php';
    
    error_log('[Actions Simple] Archivos incluidos correctamente');
    
    // Iniciar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    error_log('[Actions Simple] Sesión iniciada');
    
    // Verificar autenticación básica
    if (!isset($_SESSION['subscriber_id'])) {
        throw new Exception('Usuario no autenticado - subscriber_id no encontrado');
    }
    
    $subscriberId = $_SESSION['subscriber_id'];
    error_log('[Actions Simple] Subscriber ID: ' . $subscriberId);
    
    // Obtener datos POST
    $action = $_POST['action'] ?? '';
    $planType = $_POST['plan_type'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    
    error_log('[Actions Simple] Datos recibidos - Action: ' . $action . ', Plan: ' . $planType . ', Amount: ' . $amount);
    
    if (empty($action)) {
        throw new Exception('No se especificó una acción');
    }
    
    if ($action !== 'create_or_update_billing_cycle') {
        throw new Exception('Acción no válida: ' . $action);
    }
    
    if (empty($planType)) {
        throw new Exception('Tipo de plan requerido');
    }
    
    // Conectar a base de datos
    $db = getDatabase();
    if (!$db) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    error_log('[Actions Simple] Conexión a BD exitosa');
    
    // Buscar facturas pendientes
    $stmt = $db->prepare("
        SELECT id, plan_type, amount, status, license_key, invoice_number, 
               cycle_start_date, cycle_end_date, due_date
        FROM billing_cycles 
        WHERE subscriber_id = ? AND status = 'pending' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$subscriberId]);
    $pendingBilling = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log('[Actions Simple] Consulta ejecutada para subscriber_id: ' . $subscriberId);
    error_log('[Actions Simple] Factura pendiente encontrada: ' . ($pendingBilling ? 'SÍ' : 'NO'));
    if ($pendingBilling) {
        error_log('[Actions Simple] Detalles factura: ID=' . $pendingBilling['id'] . ', Plan=' . $pendingBilling['plan_type'] . ', Amount=' . $pendingBilling['amount']);
    }
    
    if ($pendingBilling) {
        error_log('[Actions Simple] Actualizando factura existente ID: ' . $pendingBilling['id']);
        
        // Generar license_key si no existe
        $licenseKey = $pendingBilling['license_key'];
        if (empty($licenseKey)) {
            $licenseKey = strtoupper(substr($planType, 0, 3)) . '-' . strtoupper(substr(md5(uniqid()), 0, 16));
        }
        
        // Generar invoice_number si no existe
        $invoiceNumber = $pendingBilling['invoice_number'];
        if (empty($invoiceNumber)) {
            $invoiceNumber = generateInvoiceNumber($db);
        }
        
        // Usar fechas existentes o calcular nuevas
        $cycleDates = [
            'start' => $pendingBilling['cycle_start_date'] ?? null,
            'end' => $pendingBilling['cycle_end_date'] ?? null,
            'due' => $pendingBilling['due_date'] ?? null
        ];
        
        // Si no hay fechas existentes, calcular nuevas
        if (empty($cycleDates['start'])) {
            error_log('[Actions Simple] Calculando nuevas fechas del ciclo para plan: ' . $planType);
            $cycleDates = calculateCycleDates($planType);
            error_log('[Actions Simple] Fechas calculadas: ' . print_r($cycleDates, true));
        } else {
            error_log('[Actions Simple] Usando fechas existentes: ' . print_r($cycleDates, true));
        }
        
        // Actualizar factura existente
        error_log('[Actions Simple] Ejecutando UPDATE - Plan: ' . $planType . ', Amount: ' . $amount . ', License: ' . $licenseKey . ', ID: ' . $pendingBilling['id']);
        
        // Actualizar factura existente con fechas
        try {
            $updateStmt = $db->prepare("
                UPDATE billing_cycles 
                SET plan_type = ?, amount = ?, license_key = ?, invoice_number = ?, 
                    cycle_start_date = ?, cycle_end_date = ?, due_date = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $updateStmt->execute([
                $planType, $amount, $licenseKey, $invoiceNumber, 
                $cycleDates['start'], $cycleDates['end'], $cycleDates['due'], 
                $pendingBilling['id']
            ]);
        } catch (Exception $e) {
            error_log('[Actions Simple] Error en UPDATE con fechas: ' . $e->getMessage());
            // Fallback a UPDATE sin fechas
            $updateStmt = $db->prepare("
                UPDATE billing_cycles 
                SET plan_type = ?, amount = ?, license_key = ?, invoice_number = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $updateStmt->execute([
                $planType, $amount, $licenseKey, $invoiceNumber, 
                $pendingBilling['id']
            ]);
        }
        
        error_log('[Actions Simple] Resultado UPDATE: ' . ($result ? 'ÉXITO' : 'FALLO'));
        error_log('[Actions Simple] Filas afectadas: ' . $updateStmt->rowCount());
        
        if (!$result) {
            $errorInfo = $updateStmt->errorInfo();
            error_log('[Actions Simple] Error UPDATE: ' . print_r($errorInfo, true));
            throw new Exception('Error al actualizar la factura: ' . $errorInfo[2]);
        }
        
        $billingCycleId = $pendingBilling['id'];
        $actionResult = 'updated';
        
        // Verificar que la actualización se guardó correctamente
        $verifyStmt = $db->prepare("SELECT plan_type, amount FROM billing_cycles WHERE id = ?");
        $verifyStmt->execute([$billingCycleId]);
        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        error_log('[Actions Simple] Verificación POST-UPDATE - Plan: ' . $verifyResult['plan_type'] . ', Amount: ' . $verifyResult['amount']);
        
    } else {
        error_log('[Actions Simple] Creando nueva factura');
        
        // Generar license_key
        $licenseKey = strtoupper(substr($planType, 0, 3)) . '-' . strtoupper(substr(md5(uniqid()), 0, 16));
        
        // Generar invoice_number
        $invoiceNumber = generateInvoiceNumber($db);
        
        // Calcular fechas del ciclo (para logging)
        error_log('[Actions Simple] Calculando fechas del ciclo para plan: ' . $planType);
        $cycleDates = calculateCycleDates($planType);
        error_log('[Actions Simple] Fechas calculadas: ' . print_r($cycleDates, true));
        
        // Crear nueva factura con fechas
        try {
            $insertStmt = $db->prepare("
                INSERT INTO billing_cycles (
                    subscriber_id, plan_type, amount, status, license_key, invoice_number,
                    cycle_start_date, cycle_end_date, due_date, created_at, updated_at
                ) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $result = $insertStmt->execute([
                $subscriberId, $planType, $amount, $licenseKey, $invoiceNumber,
                $cycleDates['start'], $cycleDates['end'], $cycleDates['due']
            ]);
        } catch (Exception $e) {
            error_log('[Actions Simple] Error en INSERT con fechas: ' . $e->getMessage());
            // Fallback a INSERT sin fechas
            $insertStmt = $db->prepare("
                INSERT INTO billing_cycles (
                    subscriber_id, plan_type, amount, status, license_key, invoice_number,
                    created_at, updated_at
                ) VALUES (?, ?, ?, 'pending', ?, ?, NOW(), NOW())
            ");
            $result = $insertStmt->execute([
                $subscriberId, $planType, $amount, $licenseKey, $invoiceNumber
            ]);
        }
        
        if (!$result) {
            throw new Exception('Error al crear la factura');
        }
        
        $billingCycleId = $db->lastInsertId();
        $actionResult = 'created';
    }
    
    error_log('[Actions Simple] Proceso completado exitosamente - ID: ' . $billingCycleId);
    
    echo json_encode([
        'success' => true,
        'billing_cycle_id' => $billingCycleId,
        'action' => $actionResult,
        'message' => $actionResult === 'created' ? 'Factura creada correctamente' : 'Factura actualizada correctamente'
    ]);
    
} catch (Exception $e) {
    error_log('[Actions Simple] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Genera un número de factura único
 */
function generateInvoiceNumber($db) {
    $prefix = 'INV-';
    $year = date('Y');
    $month = date('m');
    
    // Obtener el último número de factura del mes actual
    $stmt = $db->prepare("
        SELECT invoice_number 
        FROM billing_cycles 
        WHERE invoice_number LIKE ? 
        ORDER BY invoice_number DESC 
        LIMIT 1
    ");
    $likePattern = $prefix . $year . $month . '%';
    $stmt->execute([$likePattern]);
    $lastInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastInvoice) {
        // Extraer el número secuencial del último invoice_number
        $lastNumber = intval(substr($lastInvoice['invoice_number'], -4));
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }
    
    return $prefix . $year . $month . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
}

/**
 * Calcula las fechas del ciclo de facturación
 */
function calculateCycleDates($planType) {
    $now = new DateTime();
    $cycleStart = $now->format('Y-m-d');
    
    if ($planType === 'free') {
        // Plan gratuito: ciclo de 30 días, sin vencimiento
        $cycleEndDate = clone $now;
        $cycleEnd = $cycleEndDate->add(new DateInterval('P30D'))->format('Y-m-d');
        $dueDate = null; // Sin vencimiento para plan gratuito
    } else {
        // Planes de pago: ciclo mensual, vencimiento en 7 días
        $cycleEndDate = clone $now;
        $cycleEnd = $cycleEndDate->add(new DateInterval('P1M'))->format('Y-m-d');
        
        // Fecha de vencimiento: 7 días desde hoy
        $dueDate = clone $now;
        $dueDate = $dueDate->add(new DateInterval('P7D'))->format('Y-m-d');
    }
    
    return [
        'start' => $cycleStart,
        'end' => $cycleEnd,
        'due' => $dueDate
    ];
}
?>
