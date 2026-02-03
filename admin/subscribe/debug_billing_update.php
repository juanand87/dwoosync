<?php
/**
 * Script de diagnóstico para verificar actualización de billing_cycles
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<h1>Diagnóstico de Actualización de Billing Cycles</h1>";

try {
    // Verificar autenticación
    if (!isset($_SESSION['subscriber_id'])) {
        echo "<p style='color: red;'>❌ Usuario no autenticado</p>";
        exit;
    }
    
    $subscriberId = $_SESSION['subscriber_id'];
    echo "<p>✅ Subscriber ID: <strong>$subscriberId</strong></p>";
    
    // Conectar a base de datos
    $db = getDatabase();
    if (!$db) {
        echo "<p style='color: red;'>❌ Error de conexión a BD</p>";
        exit;
    }
    
    echo "<p>✅ Conexión a BD exitosa</p>";
    
    // Mostrar todas las facturas del suscriptor
    echo "<h2>Facturas del Suscriptor</h2>";
    $stmt = $db->prepare("
        SELECT id, plan_type, amount, status, license_key, created_at, updated_at 
        FROM billing_cycles 
        WHERE subscriber_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$subscriberId]);
    $allBilling = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($allBilling)) {
        echo "<p style='color: orange;'>⚠️ No hay facturas para este suscriptor</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Plan</th><th>Amount</th><th>Status</th><th>License Key</th><th>Created</th><th>Updated</th></tr>";
        foreach ($allBilling as $billing) {
            $color = $billing['status'] === 'pending' ? 'orange' : 'green';
            echo "<tr style='background-color: $color;'>";
            echo "<td>{$billing['id']}</td>";
            echo "<td>{$billing['plan_type']}</td>";
            echo "<td>{$billing['amount']}</td>";
            echo "<td>{$billing['status']}</td>";
            echo "<td>" . ($billing['license_key'] ?: 'NULL') . "</td>";
            echo "<td>{$billing['created_at']}</td>";
            echo "<td>{$billing['updated_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Buscar facturas pendientes específicamente
    echo "<h2>Facturas Pendientes</h2>";
    $stmt = $db->prepare("
        SELECT id, plan_type, amount, status, license_key, created_at, updated_at 
        FROM billing_cycles 
        WHERE subscriber_id = ? AND status = 'pending' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$subscriberId]);
    $pendingBilling = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pendingBilling) {
        echo "<p style='color: orange;'>⚠️ Factura pendiente encontrada:</p>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$pendingBilling['id']}</li>";
        echo "<li><strong>Plan:</strong> {$pendingBilling['plan_type']}</li>";
        echo "<li><strong>Amount:</strong> {$pendingBilling['amount']}</li>";
        echo "<li><strong>License Key:</strong> " . ($pendingBilling['license_key'] ?: 'NULL') . "</li>";
        echo "<li><strong>Updated:</strong> {$pendingBilling['updated_at']}</li>";
        echo "</ul>";
        
        // Simular actualización
        echo "<h2>Simulación de Actualización</h2>";
        $newPlan = 'premium';
        $newAmount = 22;
        
        echo "<p>Intentando actualizar a:</p>";
        echo "<ul>";
        echo "<li><strong>Nuevo Plan:</strong> $newPlan</li>";
        echo "<li><strong>Nuevo Amount:</strong> $newAmount</li>";
        echo "</ul>";
        
        $updateStmt = $db->prepare("
            UPDATE billing_cycles 
            SET plan_type = ?, amount = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $updateStmt->execute([$newPlan, $newAmount, $pendingBilling['id']]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ UPDATE ejecutado exitosamente</p>";
            echo "<p>Filas afectadas: <strong>" . $updateStmt->rowCount() . "</strong></p>";
            
            // Verificar actualización
            $verifyStmt = $db->prepare("SELECT plan_type, amount, updated_at FROM billing_cycles WHERE id = ?");
            $verifyStmt->execute([$pendingBilling['id']]);
            $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);
            
            echo "<p>Verificación POST-UPDATE:</p>";
            echo "<ul>";
            echo "<li><strong>Plan:</strong> {$verifyResult['plan_type']}</li>";
            echo "<li><strong>Amount:</strong> {$verifyResult['amount']}</li>";
            echo "<li><strong>Updated:</strong> {$verifyResult['updated_at']}</li>";
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>❌ Error en UPDATE</p>";
            $errorInfo = $updateStmt->errorInfo();
            echo "<p>Error: " . print_r($errorInfo, true) . "</p>";
        }
        
    } else {
        echo "<p style='color: green;'>✅ No hay facturas pendientes</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
</style>



