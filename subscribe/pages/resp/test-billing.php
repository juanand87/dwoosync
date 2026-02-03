<?php
/**
 * Página de prueba para crear ciclo de facturación pendiente
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Probar la creación del ciclo directamente (sin cURL)
    echo "<h2>Procesando creación de ciclo...</h2>";
    
    $pdo = null;
    try {
        echo "<p>1. Conectando a la base de datos...</p>";
        $pdo = getDatabase();
        echo "<p>✅ Conexión exitosa</p>";
        
        echo "<p>2. Obteniendo información del plan...</p>";
        $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
        $plan_stmt->execute([$plan_type]);
        $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan_data) {
            throw new Exception('Plan no encontrado: ' . $plan_type);
        }
        echo "<p>✅ Plan encontrado: " . json_encode($plan_data) . "</p>";
        
        echo "<p>3. Obteniendo license_key del usuario...</p>";
        $license_stmt = $pdo->prepare("SELECT license_key FROM licenses WHERE subscriber_id = ?");
        $license_stmt->execute([$subscriber_id]);
        $license_data = $license_stmt->fetch(PDO::FETCH_ASSOC);
        $license_key = $license_data['license_key'] ?? 'PENDING-' . $subscriber_id . '-' . time();
        echo "<p>✅ License key: $license_key</p>";
        
        echo "<p>4. Preparando datos del ciclo...</p>";
        $cycle_start = date('Y-m-d');
        $cycle_end = date('Y-m-d', strtotime('+30 days'));
        $due_date = date('Y-m-d', strtotime('+33 days'));
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($subscriber_id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(time(), 4, '0', STR_PAD_LEFT);
        
        echo "<p>Start: $cycle_start, End: $cycle_end, Due: $due_date, Invoice: $invoice_number</p>";
        
        echo "<p>5. Ejecutando INSERT...</p>";
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
        
        echo "<p>Datos a insertar: " . json_encode($execute_data) . "</p>";
        
        $result = $create_cycle->execute($execute_data);
        
        if (!$result) {
            throw new Exception('Error al ejecutar INSERT: ' . json_encode($create_cycle->errorInfo()));
        }
        
        $billing_cycle_id = $pdo->lastInsertId();
        echo "<p>✅ Ciclo creado con ID: $billing_cycle_id</p>";
        
        // Actualizar sesión
        $_SESSION['billing_cycle_id'] = $billing_cycle_id;
        
        // Cerrar conexión explícitamente
        $pdo = null;
        
        $result = [
            'success' => true,
            'message' => 'Ciclo de facturación pendiente creado',
            'billing_cycle_id' => $billing_cycle_id,
            'invoice_number' => $invoice_number
        ];
        
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>✅ ÉXITO</h3>";
        echo "<p><strong>Billing Cycle ID:</strong> $billing_cycle_id</p>";
        echo "<p><strong>Invoice Number:</strong> $invoice_number</p>";
        echo "<p><strong>Status:</strong> pending</p>";
        echo "</div>";
        
    } catch (Exception $e) {
        if ($pdo) {
            $pdo = null;
        }
        
        $result = [
            'success' => false,
            'message' => 'Error al crear ciclo de facturación: ' . $e->getMessage()
        ];
        
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>❌ ERROR</h3>";
        echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
        echo "<p><strong>Archivo:</strong> " . $e->getFile() . " línea " . $e->getLine() . "</p>";
        echo "<p><strong>Stack trace:</strong></p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
        echo "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba de Ciclo de Facturación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .result {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    
        .spinning-disc {
            animation: spin 3s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .nav-logo h2 {
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #1db954, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(29, 185, 84, 0.3);
        }
    </style>
</head>
<body>
    <h1>Prueba de Ciclo de Facturación Pendiente</h1>
    
    <div class="result">
        <h3>Información del Usuario:</h3>
        <p><strong>Subscriber ID:</strong> <?php echo $subscriber_id; ?></p>
        <p><strong>Plan Type:</strong> <?php echo $plan_type; ?></p>
        <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
    </div>
    
    <form method="POST">
        <button type="submit" class="btn">Crear Ciclo Pendiente</button>
    </form>
    
    <?php if (isset($result)): ?>
    <div class="result <?php echo $result['success'] ? 'success' : 'error'; ?>">
        <h3>Resultado:</h3>
        <p><strong>HTTP Code:</strong> <?php echo $http_code; ?></p>
        <p><strong>Success:</strong> <?php echo $result['success'] ? 'Sí' : 'No'; ?></p>
        <p><strong>Message:</strong> <?php echo htmlspecialchars($result['message']); ?></p>
        
        <?php if (isset($result['billing_cycle_id'])): ?>
        <p><strong>Billing Cycle ID:</strong> <?php echo $result['billing_cycle_id']; ?></p>
        <?php endif; ?>
        
        <?php if (isset($result['invoice_number'])): ?>
        <p><strong>Invoice Number:</strong> <?php echo $result['invoice_number']; ?></p>
        <?php endif; ?>
        
        <h4>Respuesta completa:</h4>
        <pre><?php echo htmlspecialchars($response); ?></pre>
    </div>
    <?php endif; ?>
    
    <div class="result">
        <h3>Verificar en Base de Datos:</h3>
        <p>Puedes verificar si el ciclo se creó correctamente consultando la tabla <code>billing_cycles</code>:</p>
        <pre>SELECT * FROM billing_cycles WHERE subscriber_id = <?php echo $subscriber_id; ?> ORDER BY created_at DESC LIMIT 1;</pre>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="checkout.php?plan=<?php echo $plan_type; ?>" class="btn">Volver al Checkout</a>
        <a href="dashboard.php" class="btn">Ir al Dashboard</a>
    </div>
</body>
</html>
