<?php
/**
 * Script de prueba para verificar actualización de facturas pendientes
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    die('Usuario no autenticado');
}

$subscriber_id = $_SESSION['subscriber_id'] ?? null;
if (!$subscriber_id) {
    die('No se encontró subscriber_id en la sesión');
}

echo "<h2>Prueba de Actualización de Facturas Pendientes</h2>";
echo "<p><strong>Subscriber ID:</strong> $subscriber_id</p>";

try {
    $pdo = getDatabase();
    
    // 1. Verificar facturas pendientes actuales
    echo "<h3>1. Facturas Pendientes Actuales:</h3>";
    $stmt = $pdo->prepare("
        SELECT id, plan_type, amount, status, created_at, updated_at 
        FROM billing_cycles 
        WHERE subscriber_id = ? AND status = 'pending' 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$subscriber_id]);
    $pending_cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pending_cycles)) {
        echo "<p style='color: orange;'>No hay facturas pendientes</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Plan</th><th>Amount</th><th>Status</th><th>Creado</th><th>Actualizado</th></tr>";
        foreach ($pending_cycles as $cycle) {
            echo "<tr>";
            echo "<td>{$cycle['id']}</td>";
            echo "<td>{$cycle['plan_type']}</td>";
            echo "<td>\${$cycle['amount']}</td>";
            echo "<td>{$cycle['status']}</td>";
            echo "<td>{$cycle['created_at']}</td>";
            echo "<td>{$cycle['updated_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Simular actualización de plan
    echo "<h3>2. Simulando Actualización de Plan:</h3>";
    
    $test_plan = 'enterprise'; // Cambiar a enterprise
    $test_amount = 45;
    
    echo "<p><strong>Plan a cambiar:</strong> $test_plan (\$$test_amount)</p>";
    
    // Buscar factura pendiente existente
    $existing_stmt = $pdo->prepare("
        SELECT id, plan_type, amount 
        FROM billing_cycles 
        WHERE subscriber_id = ? AND status = 'pending' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $existing_stmt->execute([$subscriber_id]);
    $existing_cycle = $existing_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_cycle) {
        echo "<p><strong>Factura existente encontrada:</strong> ID {$existing_cycle['id']}, Plan: {$existing_cycle['plan_type']}, Amount: \${$existing_cycle['amount']}</p>";
        
        if ($existing_cycle['plan_type'] === $test_plan) {
            echo "<p style='color: blue;'>✅ Mismo plan - No se requiere actualización</p>";
        } else {
            echo "<p style='color: orange;'>🔄 Plan diferente - Actualizando factura...</p>";
            
            // Actualizar factura
            $update_stmt = $pdo->prepare("
                UPDATE billing_cycles 
                SET plan_type = ?, amount = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $update_result = $update_stmt->execute([$test_plan, $test_amount, $existing_cycle['id']]);
            
            if ($update_result) {
                echo "<p style='color: green;'>✅ Factura actualizada exitosamente</p>";
                
                // Verificar actualización
                $verify_stmt = $pdo->prepare("
                    SELECT id, plan_type, amount, updated_at 
                    FROM billing_cycles 
                    WHERE id = ?
                ");
                $verify_stmt->execute([$existing_cycle['id']]);
                $updated_cycle = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                
                echo "<p><strong>Factura después de actualización:</strong></p>";
                echo "<ul>";
                echo "<li>ID: {$updated_cycle['id']}</li>";
                echo "<li>Plan: {$updated_cycle['plan_type']}</li>";
                echo "<li>Amount: \${$updated_cycle['amount']}</li>";
                echo "<li>Updated: {$updated_cycle['updated_at']}</li>";
                echo "</ul>";
            } else {
                echo "<p style='color: red;'>❌ Error al actualizar factura</p>";
                $errorInfo = $update_stmt->errorInfo();
                echo "<p>Error details: " . json_encode($errorInfo) . "</p>";
            }
        }
    } else {
        echo "<p style='color: blue;'>No hay facturas pendientes para actualizar</p>";
    }
    
    // 3. Mostrar estado final
    echo "<h3>3. Estado Final:</h3>";
    $final_stmt = $pdo->prepare("
        SELECT id, plan_type, amount, status, created_at, updated_at 
        FROM billing_cycles 
        WHERE subscriber_id = ? AND status = 'pending' 
        ORDER BY created_at DESC
    ");
    $final_stmt->execute([$subscriber_id]);
    $final_cycles = $final_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($final_cycles)) {
        echo "<p style='color: orange;'>No hay facturas pendientes</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Plan</th><th>Amount</th><th>Status</th><th>Creado</th><th>Actualizado</th></tr>";
        foreach ($final_cycles as $cycle) {
            echo "<tr>";
            echo "<td>{$cycle['id']}</td>";
            echo "<td>{$cycle['plan_type']}</td>";
            echo "<td>\${$cycle['amount']}</td>";
            echo "<td>{$cycle['status']}</td>";
            echo "<td>{$cycle['created_at']}</td>";
            echo "<td>{$cycle['updated_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Probar la función create-pending-billing.php directamente
    echo "<h3>4. Probando create-pending-billing.php:</h3>";
    
    // Simular POST request
    $_POST['plan_type'] = 'premium';
    $_POST['payment_method'] = 'test';
    $_POST['payment_reference'] = 'test_' . time();
    
    echo "<p><strong>Simulando POST:</strong></p>";
    echo "<ul>";
    echo "<li>plan_type: " . $_POST['plan_type'] . "</li>";
    echo "<li>subscriber_id: " . $subscriber_id . " (de sesión)</li>";
    echo "<li>payment_method: " . $_POST['payment_method'] . "</li>";
    echo "<li>payment_reference: " . $_POST['payment_reference'] . "</li>";
    echo "</ul>";
    
    // Capturar output de create-pending-billing.php
    ob_start();
    include 'create-pending-billing.php';
    $output = ob_get_clean();
    
    echo "<p><strong>Respuesta de create-pending-billing.php:</strong></p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }

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