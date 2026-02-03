<?php
/**
 * AJAX Actions para el sistema de suscripciones
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Iniciar sesión
startSecureSession();

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

// Verificar que subscriber_id esté en sesión
if (!isset($_SESSION['subscriber_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de suscriptor no encontrado en sesión']);
    exit;
}

// Obtener la acción
$action = $_POST['action'] ?? '';

// Configurar headers para JSON
header('Content-Type: application/json');

try {
    error_log('[Actions] Acción recibida: ' . $action);
    error_log('[Actions] POST data: ' . print_r($_POST, true));
    error_log('[Actions] Sesión: subscriber_id=' . ($_SESSION['subscriber_id'] ?? 'NO SET'));
    
    if (empty($action)) {
        throw new Exception('No se especificó una acción');
    }
    
    if ($action === 'create_or_update_billing_cycle') {
        handleCreateOrUpdateBillingCycle();
    } else {
        throw new Exception('Acción no válida: ' . $action);
    }
} catch (Exception $e) {
    error_log('[Actions] Error: ' . $e->getMessage());
    error_log('[Actions] Stack trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Generar una license_key única
 */
function generateLicenseKey($planType) {
    // Generar license_key según el formato estandarizado
    if ($planType === 'free') {
        // Plan Free: FREE + 14 caracteres
        $licenseKey = 'FREE' . strtoupper(substr(md5(uniqid(rand(), true) . time()), 0, 14));
    } else {
        // Plan Premium/Enterprise: DW + 16 caracteres
        $licenseKey = 'DW' . strtoupper(substr(md5(uniqid(rand(), true) . time()), 0, 16));
    }
    
    return $licenseKey;
}

/**
 * Crear o actualizar ciclo de facturación
 */
function handleCreateOrUpdateBillingCycle() {
    $planType = $_POST['plan_type'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    
    error_log("[Billing] Iniciando proceso. Plan: $planType, Amount: $amount");
    
    if (empty($planType)) {
        throw new Exception('Tipo de plan requerido');
    }
    
    if (!isset($_SESSION['subscriber_id'])) {
        throw new Exception('ID de suscriptor no encontrado en sesión');
    }
    
    $subscriberId = $_SESSION['subscriber_id'];
    error_log("[Billing] Subscriber ID: $subscriberId");
    
    try {
        $db = getDatabase();
        if (!$db) {
            throw new Exception('No se pudo conectar a la base de datos');
        }
        
        // Buscar facturas pendientes del suscriptor
        $stmt = $db->prepare("
            SELECT id, plan_type, amount, status, license_key, invoice_number, 
                   cycle_start_date, cycle_end_date, due_date, created_at 
            FROM billing_cycles 
            WHERE subscriber_id = ? AND status = 'pending' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$subscriberId]);
        $pendingBilling = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("[Billing] Facturas pendientes encontradas: " . ($pendingBilling ? "SÍ (ID: {$pendingBilling['id']})" : "NO"));
        
        if ($pendingBilling) {
            // Si la factura no tiene license_key, generar uno
            $licenseKey = $pendingBilling['license_key'];
            if (empty($licenseKey)) {
                $licenseKey = generateLicenseKey($planType);
            }
            
            // Si la factura no tiene invoice_number, generar uno
            $invoiceNumber = $pendingBilling['invoice_number'];
            if (empty($invoiceNumber)) {
                $invoiceNumber = generateInvoiceNumber($db);
            }
            
            // Calcular fechas del ciclo
            $cycleDates = calculateCycleDates($planType);
            
            $updateStmt = $db->prepare("
                UPDATE billing_cycles 
                SET plan_type = ?, amount = ?, license_key = ?, invoice_number = ?, 
                    cycle_start_date = ?, cycle_end_date = ?, due_date = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([
                $planType, $amount, $licenseKey, $invoiceNumber,
                $cycleDates['start'], $cycleDates['end'], $cycleDates['due'],
                $pendingBilling['id']
            ]);
            
            error_log("[Billing] Factura pendiente actualizada: ID {$pendingBilling['id']}, Plan: $planType, Amount: $amount, License: $licenseKey, Invoice: $invoiceNumber");
            
            $billingCycleId = $pendingBilling['id'];
            $action = 'updated';
        } else {
            // Generar license_key único
            $licenseKey = generateLicenseKey($planType);
            
            // Generar invoice_number único
            $invoiceNumber = generateInvoiceNumber($db);
            
            // Calcular fechas del ciclo
            $cycleDates = calculateCycleDates($planType);
            
            // Crear nueva factura
            $insertStmt = $db->prepare("
                INSERT INTO billing_cycles (
                    subscriber_id, plan_type, amount, status, license_key, invoice_number,
                    cycle_start_date, cycle_end_date, due_date, created_at, updated_at
                ) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $insertStmt->execute([
                $subscriberId, $planType, $amount, $licenseKey, $invoiceNumber,
                $cycleDates['start'], $cycleDates['end'], $cycleDates['due']
            ]);
            
            $billingCycleId = $db->lastInsertId();
            $action = 'created';
            
            error_log("[Billing] Nueva factura creada: ID $billingCycleId, Plan: $planType, Amount: $amount, License: $licenseKey, Invoice: $invoiceNumber");
        }
        
        echo json_encode([
            'success' => true,
            'billing_cycle_id' => $billingCycleId,
            'action' => $action,
            'message' => $action === 'created' ? 'Factura creada correctamente' : 'Factura actualizada correctamente'
        ]);
        
    } catch (Exception $e) {
        error_log("[Billing] Error en handleCreateOrUpdateBillingCycle: " . $e->getMessage());
        throw new Exception('Error al procesar la factura: ' . $e->getMessage());
    }
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
