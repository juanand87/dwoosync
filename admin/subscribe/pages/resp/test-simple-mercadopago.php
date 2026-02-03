<?php
/**
 * Prueba simple para MercadoPago sin iframe
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$subscriber_id = $_SESSION['subscriber_id'];
$plan_type = 'premium';

echo "<h1>Prueba Simple de MercadoPago</h1>";
echo "<p><strong>Subscriber ID:</strong> $subscriber_id</p>";
echo "<p><strong>Plan Type:</strong> $plan_type</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Creando ciclo de facturación pendiente...</h2>";
    
    $pdo = null;
    try {
        $pdo = getDatabase();
        echo "<p>✅ Conexión a BD exitosa</p>";
        
        // Obtener información del plan
        $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
        $plan_stmt->execute([$plan_type]);
        $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan_data) {
            throw new Exception('Plan no encontrado: ' . $plan_type);
        }
        echo "<p>✅ Plan encontrado: " . $plan_data['plan_name'] . "</p>";
        
        // Obtener license_key del usuario
        $license_stmt = $pdo->prepare("SELECT license_key FROM licenses WHERE subscriber_id = ?");
        $license_stmt->execute([$subscriber_id]);
        $license_data = $license_stmt->fetch(PDO::FETCH_ASSOC);
        $license_key = $license_data['license_key'] ?? 'PENDING-' . $subscriber_id . '-' . time();
        echo "<p>✅ License key: $license_key</p>";
        
        // Crear ciclo de facturación pendiente
        $cycle_start = date('Y-m-d');
        $cycle_end = date('Y-m-d', strtotime('+30 days'));
        $due_date = date('Y-m-d', strtotime('+33 days'));
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($subscriber_id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(time(), 4, '0', STR_PAD_LEFT);
        
        echo "<p><strong>Datos del ciclo:</strong></p>";
        echo "<ul>";
        echo "<li>Start: $cycle_start</li>";
        echo "<li>End: $cycle_end</li>";
        echo "<li>Due: $due_date</li>";
        echo "<li>Invoice: $invoice_number</li>";
        echo "</ul>";
        
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
            'CLP', // MercadoPago usa CLP
            'pending', // payment_method = pending
            'PENDING-' . time(), // payment_reference temporal
            $invoice_number,
            date('Y-m-d H:i:s')
        ];
        
        $result = $create_cycle->execute($execute_data);
        
        if (!$result) {
            throw new Exception('Error al ejecutar INSERT: ' . json_encode($create_cycle->errorInfo()));
        }
        
        $billing_cycle_id = $pdo->lastInsertId();
        echo "<p>✅ Ciclo creado con ID: $billing_cycle_id</p>";
        
        // Actualizar sesión
        $_SESSION['billing_cycle_id'] = $billing_cycle_id;
        
        // Cerrar conexión
        $pdo = null;
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ ÉXITO - Ciclo de MercadoPago Creado</h3>";
        echo "<p><strong>Billing Cycle ID:</strong> $billing_cycle_id</p>";
        echo "<p><strong>Invoice Number:</strong> $invoice_number</p>";
        echo "<p><strong>Status:</strong> pending</p>";
        echo "<p><strong>Currency:</strong> CLP</p>";
        echo "<p><strong>Amount:</strong> $" . $plan_data['price'] . " CLP</p>";
        echo "</div>";
        
        echo "<h3>Ahora puedes probar el botón de MercadoPago:</h3>";
        echo "<p><a href='checkout.php?plan=premium' style='background: #3483FA; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Ir al Checkout</a></p>";
        
    } catch (Exception $e) {
        if ($pdo) {
            $pdo = null;
        }
        
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>❌ ERROR</h3>";
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Archivo:</strong> " . $e->getFile() . " línea " . $e->getLine() . "</p>";
        echo "<p><strong>Stack trace:</strong></p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
        echo "</div>";
    }
}

echo "<hr>";
echo "<form method='POST'>";
echo "<button type='submit' style='background: #3483FA; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>Crear Ciclo Pendiente para MercadoPago</button>";
echo "</form>";

echo "<hr>";
echo "<h3>Enlaces de prueba:</h3>";
echo "<p><a href='checkout.php?plan=premium' style='color: #007bff;'>Ir al Checkout (Plan Premium)</a></p>";
echo "<p><a href='test-js-billing.php' style='color: #007bff;'>Probar función JavaScript</a></p>";
echo "<p><a href='dashboard.php' style='color: #007bff;'>Ir al Dashboard</a></p>";
?>


