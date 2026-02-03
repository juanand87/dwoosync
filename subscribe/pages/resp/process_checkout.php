<?php
/**
 * Procesar checkout - Crear/actualizar billing_cycle y redirigir a payment
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Detectar idioma
$currentLang = $_SESSION['selected_language'] ?? 'es';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php?lang=' . $currentLang);
    exit;
}

// Obtener datos del formulario
$plan_type = $_POST['plan'] ?? 'premium';
$billing_cycle_id = $_POST['billing_cycle_id'] ?? null;
$subscriber_id = $_SESSION['subscriber_id'] ?? null;

if (!$subscriber_id) {
    header('Location: login.php?lang=' . $currentLang . '&error=session_expired');
    exit;
}

// Guardar el plan en sesión
$_SESSION['user_plan'] = $plan_type;

error_log("PROCESS_CHECKOUT: === INICIO ===");
error_log("PROCESS_CHECKOUT: subscriber_id = " . $subscriber_id);
error_log("PROCESS_CHECKOUT: plan_type = " . $plan_type);
error_log("PROCESS_CHECKOUT: billing_cycle_id recibido = " . ($billing_cycle_id ?? 'NULL'));

try {
    $db = getDatabase();
    
    // Verificar si ya existe un ciclo pendiente
    $stmt = $db->prepare("
        SELECT id, plan_type, amount
        FROM billing_cycles 
        WHERE subscriber_id = ? 
        AND status = 'pending' 
        AND is_active = 0
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$subscriber_id]);
    $existingCycle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener la license_key del usuario
    $stmt = $db->prepare("SELECT license_key FROM licenses WHERE subscriber_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$subscriber_id]);
    $licenseData = $stmt->fetch(PDO::FETCH_ASSOC);
    $license_key = $licenseData['license_key'] ?? null;
    
    // Obtener el precio del plan
    $stmt = $db->prepare("SELECT price FROM subscription_plans WHERE plan_type = ?");
    $stmt->execute([$plan_type]);
    $planData = $stmt->fetch(PDO::FETCH_ASSOC);
    $amount = $planData['price'] ?? 0;
    
    // Calcular fechas
    $cycle_start_date = date('Y-m-d');
    $cycle_end_date = date('Y-m-d', strtotime('+30 days'));
    $due_date = $cycle_end_date;
    
    if ($existingCycle) {
        // ACTUALIZAR factura pendiente existente
        $billing_cycle_id = $existingCycle['id'];
        
        // Generar nuevo invoice_number
        $invoice_number = 'INV' . date('Ymd') . '_' . strtoupper(substr(md5(uniqid($subscriber_id, true)), 0, 8));
        
        error_log("PROCESS_CHECKOUT: Actualizando factura pendiente ID=" . $billing_cycle_id . " de plan '" . $existingCycle['plan_type'] . "' ($" . $existingCycle['amount'] . ") a '" . $plan_type . "' ($" . $amount . ")");
        
        $stmt = $db->prepare("
            UPDATE billing_cycles 
            SET plan_type = ?,
                license_key = ?,
                amount = ?,
                cycle_start_date = ?,
                cycle_end_date = ?,
                due_date = ?,
                invoice_number = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $plan_type,
            $license_key,
            $amount,
            $cycle_start_date,
            $cycle_end_date,
            $due_date,
            $invoice_number,
            $billing_cycle_id
        ]);
        
        error_log("PROCESS_CHECKOUT: ✓ Factura actualizada exitosamente");
        
    } else {
        // CREAR nueva factura pendiente
        error_log("PROCESS_CHECKOUT: Creando nueva factura pendiente para plan: " . $plan_type);
        
        // Generar invoice_number único
        $invoice_number = 'INV' . date('Ymd') . '_' . strtoupper(substr(md5(uniqid($subscriber_id, true)), 0, 8));
        
        $stmt = $db->prepare("
            INSERT INTO billing_cycles (
                subscriber_id, 
                license_key, 
                plan_type, 
                cycle_start_date, 
                cycle_end_date, 
                due_date,
                paid_date,
                invoice_number,
                amount,
                status,
                is_active, 
                created_at, 
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, 'pending', 0, NOW(), NOW())
        ");
        
        $stmt->execute([
            $subscriber_id,
            $license_key,
            $plan_type,
            $cycle_start_date,
            $cycle_end_date,
            $due_date,
            $invoice_number,
            $amount
        ]);
        
        $billing_cycle_id = $db->lastInsertId();
        
        error_log("PROCESS_CHECKOUT: ✓ Nueva factura creada con ID: " . $billing_cycle_id);
    }
    
    // Guardar en sesión
    $_SESSION['billing_cycle_id'] = $billing_cycle_id;
    
    // Redirigir a payment.php con el billing_cycle_id
    header('Location: payment.php?plan=' . $plan_type . '&billing_cycle_id=' . $billing_cycle_id . '&lang=' . $currentLang);
    exit;
    
} catch (Exception $e) {
    error_log("PROCESS_CHECKOUT: ✗ ERROR: " . $e->getMessage());
    error_log("PROCESS_CHECKOUT: ✗ Stack trace: " . $e->getTraceAsString());
    
    // Redirigir con error
    header('Location: checkout.php?plan=' . $plan_type . '&lang=' . $currentLang . '&error=billing_cycle_error');
    exit;
}
?>



