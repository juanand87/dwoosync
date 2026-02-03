<?php
/**
 * Prueba simple de conexión y estructura de billing_cycles
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
$plan_type = $_GET['plan'] ?? 'premium';

echo "<h1>Prueba Simple de Billing Cycles</h1>";
echo "<p><strong>Subscriber ID:</strong> $subscriber_id</p>";
echo "<p><strong>Plan Type:</strong> $plan_type</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

try {
    $pdo = getDatabase();
    echo "<p>✅ <strong>Conexión a BD exitosa</strong></p>";
    
    // Verificar estructura de la tabla
    $stmt = $pdo->query("DESCRIBE billing_cycles");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Estructura de la tabla billing_cycles:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "<td>" . $col['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar si existe un plan
    $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
    $plan_stmt->execute([$plan_type]);
    $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($plan_data) {
        echo "<p>✅ <strong>Plan encontrado:</strong> " . json_encode($plan_data) . "</p>";
    } else {
        echo "<p>❌ <strong>Plan no encontrado:</strong> $plan_type</p>";
    }
    
    // Verificar licencia del usuario
    $license_stmt = $pdo->prepare("SELECT license_key FROM licenses WHERE subscriber_id = ?");
    $license_stmt->execute([$subscriber_id]);
    $license_data = $license_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($license_data) {
        echo "<p>✅ <strong>License key encontrada:</strong> " . $license_data['license_key'] . "</p>";
    } else {
        echo "<p>⚠️ <strong>No se encontró license key para subscriber $subscriber_id</strong></p>";
    }
    
    // Intentar un INSERT simple
    echo "<h3>Probando INSERT simple...</h3>";
    
    $cycle_start = date('Y-m-d');
    $cycle_end = date('Y-m-d', strtotime('+30 days'));
    $due_date = date('Y-m-d', strtotime('+33 days'));
    $invoice_number = 'TEST-' . time();
    $license_key = $license_data['license_key'] ?? 'TEST-' . $subscriber_id . '-' . time();
    
    $test_insert = $pdo->prepare("
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
    
    $test_data = [
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
        $plan_data['price'] ?? 0,
        $plan_type === 'free' ? 'USD' : 'CLP',
        'pending', // payment_method = pending
        'TEST-' . time(), // payment_reference temporal
        $invoice_number,
        date('Y-m-d H:i:s')
    ];
    
    echo "<p><strong>Datos a insertar:</strong></p>";
    echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";
    
    $result = $test_insert->execute($test_data);
    
    if ($result) {
        $billing_cycle_id = $pdo->lastInsertId();
        echo "<p>✅ <strong>INSERT exitoso! ID del ciclo:</strong> $billing_cycle_id</p>";
        
        // Verificar que se insertó correctamente
        $verify_stmt = $pdo->prepare("SELECT * FROM billing_cycles WHERE id = ?");
        $verify_stmt->execute([$billing_cycle_id]);
        $inserted_data = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Datos insertados:</strong></p>";
        echo "<pre>" . json_encode($inserted_data, JSON_PRETTY_PRINT) . "</pre>";
        
    } else {
        echo "<p>❌ <strong>Error en INSERT:</strong> " . json_encode($test_insert->errorInfo()) . "</p>";
    }
    
    $pdo = null;
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Stack trace:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<p><a href='test-billing.php?plan=$plan_type'>Volver a test-billing.php</a></p>";
echo "<p><a href='dashboard.php'>Ir al Dashboard</a></p>";
?>


