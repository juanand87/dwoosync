<?php
// Definir constante para acceso a la API
define('API_ACCESS', true);

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar sesión
session_start();
if (!isset($_SESSION['subscriber_id'])) {
    header('Location: ../index.php');
    exit;
}

$subscriber_id = $_SESSION['subscriber_id'];

// Verificar que se recibieron los datos del pago
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['plan_type']) || !isset($_POST['plan_price'])) {
    header('Location: plans.php?error=datos_incompletos');
    exit;
}

$plan_type = $_POST['plan_type'];
$plan_price = $_POST['plan_price'];

// Validar el plan
$valid_plans = ['premium' => 29, 'enterprise' => 99];
if (!isset($valid_plans[$plan_type]) || $valid_plans[$plan_type] != $plan_price) {
    header('Location: plans.php?error=plan_invalido');
    exit;
}

try {
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // 1. Actualizar el plan del suscriptor
    $update_subscriber = $pdo->prepare("
        UPDATE subscribers 
        SET plan_type = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $update_subscriber->execute([$plan_type, $subscriber_id]);
    
    // 2. Desactivar el ciclo actual si existe
    $deactivate_cycle = $pdo->prepare("
        UPDATE billing_cycles 
        SET is_active = 0, updated_at = NOW() 
        WHERE subscriber_id = ? AND is_active = 1
    ");
    $deactivate_cycle->execute([$subscriber_id]);
    
    // 3. Crear nuevo ciclo de facturación
    $cycle_start_date = date('Y-m-d H:i:s');
    $cycle_end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $create_cycle = $pdo->prepare("
        INSERT INTO billing_cycles (
            subscriber_id, 
            license_key, 
            plan_type, 
            cycle_start_date, 
            cycle_end_date, 
            is_active, 
            created_at, 
            updated_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
    ");
    
    // Obtener la licencia del suscriptor
    $license_query = $pdo->prepare("SELECT license_key FROM subscribers WHERE id = ?");
    $license_query->execute([$subscriber_id]);
    $subscriber_data = $license_query->fetch(PDO::FETCH_ASSOC);
    $license_key = $subscriber_data['license_key'] ?? null;
    
    $create_cycle->execute([
        $subscriber_id,
        $license_key,
        $plan_type,
        $cycle_start_date,
        $cycle_end_date
    ]);
    
    // 4. Registrar el pago (tabla de pagos)
    $payment_id = 'PAY_' . time() . '_' . $subscriber_id;
    $insert_payment = $pdo->prepare("
        INSERT INTO payments (
            payment_id,
            subscriber_id,
            amount,
            currency,
            payment_method,
            status,
            payment_date
        ) VALUES (?, ?, ?, 'USD', 'paypal', 'completed', NOW())
    ");
    $insert_payment->execute([
        $payment_id,
        $subscriber_id,
        $plan_price
    ]);
    
    // 5. Actualizar la fecha de expiración de la licencia
    if ($license_key) {
        $update_license = $pdo->prepare("
            UPDATE licenses 
            SET expires_at = ?, updated_at = NOW() 
            WHERE license_key = ?
        ");
        $update_license->execute([$cycle_end_date, $license_key]);
    }
    
    // Confirmar transacción
    $pdo->commit();
    
    // Actualizar sesión
    $_SESSION['plan_type'] = $plan_type;
    
    // Redirigir a página de confirmación
    header('Location: payment_success.php?plan=' . $plan_type . '&payment_id=' . $payment_id);
    exit;
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $pdo->rollBack();
    
    // Log del error (en un entorno real, usar un sistema de logging)
    error_log("Error en procesamiento de pago: " . $e->getMessage());
    
    // Redirigir con error
    header('Location: payment_error.php?error=procesamiento_fallido');
    exit;
}
?>
