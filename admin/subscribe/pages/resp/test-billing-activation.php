<?php
/**
 * Script de prueba para la activación de facturas
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/billing-functions.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$subscriber_id = $_SESSION['subscriber_id'];

echo "<h1>Prueba de Activación de Facturas</h1>";
echo "<p><strong>Subscriber ID:</strong> $subscriber_id</p>";

// Obtener facturas pendientes del usuario actual
$pdo = null;
try {
    $pdo = getDatabase();
    
    $stmt = $pdo->prepare("
        SELECT id, plan_type, invoice_number, amount, currency, created_at
        FROM billing_cycles 
        WHERE subscriber_id = ? AND status = 'pending'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$subscriber_id]);
    $pending_cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Facturas Pendientes del Usuario Actual (" . count($pending_cycles) . ")</h2>";
    
    if (empty($pending_cycles)) {
        echo "<p style='color: orange;'>⚠️ No hay facturas pendientes para este usuario</p>";
        echo "<p><a href='test-billing.php?plan=premium'>Crear una factura de prueba</a></p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th>ID</th><th>Plan</th><th>Factura</th><th>Monto</th><th>Creado</th><th>Acción</th>";
        echo "</tr>";
        
        foreach ($pending_cycles as $cycle) {
            echo "<tr>";
            echo "<td>" . $cycle['id'] . "</td>";
            echo "<td>" . strtoupper($cycle['plan_type']) . "</td>";
            echo "<td>" . $cycle['invoice_number'] . "</td>";
            echo "<td>$" . number_format($cycle['amount'], 0, ',', '.') . " " . $cycle['currency'] . "</td>";
            echo "<td>" . $cycle['created_at'] . "</td>";
            echo "<td>";
            echo "<form method='POST' style='display: inline;'>";
            echo "<input type='hidden' name='billing_cycle_id' value='" . $cycle['id'] . "'>";
            echo "<button type='submit' name='action' value='activate' style='background: #28a745; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;'>Activar</button>";
            echo "</form>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Procesar activación si se envió
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate') {
        $billing_cycle_id = $_POST['billing_cycle_id'] ?? '';
        
        if (!empty($billing_cycle_id)) {
            echo "<h2>Activando Factura ID: $billing_cycle_id</h2>";
            
            $result = activatePaidBillingCycle($billing_cycle_id);
            
            if ($result['success']) {
                echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3>✅ ÉXITO</h3>";
                echo "<p><strong>Mensaje:</strong> " . $result['message'] . "</p>";
                echo "<p><strong>Cliente:</strong> " . $result['subscriber_name'] . " (" . $result['subscriber_email'] . ")</p>";
                echo "</div>";
                
                // Recargar la página para mostrar los cambios
                echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
            } else {
                echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3>❌ ERROR</h3>";
                echo "<p><strong>Error:</strong> " . $result['message'] . "</p>";
                echo "</div>";
            }
        }
    }
    
    // Mostrar estado actual de la licencia
    echo "<h2>Estado Actual de la Licencia</h2>";
    $license_stmt = $pdo->prepare("
        SELECT status, created_at, updated_at
        FROM licenses 
        WHERE subscriber_id = ?
    ");
    $license_stmt->execute([$subscriber_id]);
    $license = $license_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($license) {
        $status_color = $license['status'] === 'active' ? '#28a745' : '#ffc107';
        echo "<p><strong>Status:</strong> <span style='color: $status_color; font-weight: bold;'>" . strtoupper($license['status']) . "</span></p>";
        echo "<p><strong>Creada:</strong> " . $license['created_at'] . "</p>";
        echo "<p><strong>Actualizada:</strong> " . $license['updated_at'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ No se encontró licencia para este usuario</p>";
    }
    
    $pdo = null;
    
} catch (Exception $e) {
    if ($pdo) {
        $pdo = null;
    }
    
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ ERROR</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<h3>Enlaces:</h3>";
echo "<p><a href='admin-billing.php' style='color: #28a745; font-weight: bold;'>📊 Panel de Administración</a></p>";
echo "<p><a href='view-billing-status.php' style='color: #007bff;'>📋 Ver Estado de Facturación</a></p>";
echo "<p><a href='dashboard.php' style='color: #007bff;'>🏠 Ir al Dashboard</a></p>";
?>


