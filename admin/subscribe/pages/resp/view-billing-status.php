<?php
/**
 * Script para ver el estado de los ciclos de facturación
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

echo "<h1>Estado de Ciclos de Facturación</h1>";
echo "<p><strong>Subscriber ID:</strong> $subscriber_id</p>";

$pdo = null;
try {
    $pdo = getDatabase();
    echo "<p>✅ Conexión a BD exitosa</p>";
    
    // Obtener todos los ciclos del suscriptor
    $cycles_stmt = $pdo->prepare("
        SELECT id, status, plan_type, invoice_number, amount, currency, 
               cycle_start_date, cycle_end_date, due_date, created_at
        FROM billing_cycles 
        WHERE subscriber_id = ? 
        ORDER BY created_at DESC
    ");
    $cycles_stmt->execute([$subscriber_id]);
    $cycles = $cycles_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Ciclos de Facturación (" . count($cycles) . ")</h2>";
    
    if (count($cycles) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th>ID</th><th>Status</th><th>Plan</th><th>Invoice</th><th>Amount</th><th>Start</th><th>End</th><th>Due</th><th>Creado</th>";
        echo "</tr>";
        
        foreach ($cycles as $cycle) {
            $status_color = '';
            switch ($cycle['status']) {
                case 'pending':
                    $status_color = '#ffc107';
                    break;
                case 'paid':
                    $status_color = '#28a745';
                    break;
                case 'cancelled':
                    $status_color = '#dc3545';
                    break;
                case 'overdue':
                    $status_color = '#fd7e14';
                    break;
                default:
                    $status_color = '#6c757d';
            }
            
            echo "<tr>";
            echo "<td>" . $cycle['id'] . "</td>";
            echo "<td style='color: $status_color; font-weight: bold;'>" . strtoupper($cycle['status']) . "</td>";
            echo "<td>" . $cycle['plan_type'] . "</td>";
            echo "<td>" . $cycle['invoice_number'] . "</td>";
            echo "<td>" . $cycle['amount'] . " " . $cycle['currency'] . "</td>";
            echo "<td>" . $cycle['cycle_start_date'] . "</td>";
            echo "<td>" . $cycle['cycle_end_date'] . "</td>";
            echo "<td>" . $cycle['due_date'] . "</td>";
            echo "<td>" . $cycle['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Resumen por estado
        $status_summary = [];
        foreach ($cycles as $cycle) {
            $status = $cycle['status'];
            if (!isset($status_summary[$status])) {
                $status_summary[$status] = 0;
            }
            $status_summary[$status]++;
        }
        
        echo "<h3>Resumen por Estado:</h3>";
        echo "<ul>";
        foreach ($status_summary as $status => $count) {
            echo "<li><strong>" . strtoupper($status) . ":</strong> $count ciclos</li>";
        }
        echo "</ul>";
        
        // Verificar duplicados pendientes
        $pending_count = $status_summary['pending'] ?? 0;
        if ($pending_count > 1) {
            echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>⚠️ ADVERTENCIA</h3>";
            echo "<p>Se encontraron $pending_count ciclos pendientes. Esto puede causar problemas.</p>";
            echo "<p><a href='cleanup-duplicate-billing.php' style='color: #dc3545; font-weight: bold;'>Limpiar duplicados</a></p>";
            echo "</div>";
        }
        
    } else {
        echo "<p style='color: orange;'>⚠️ No se encontraron ciclos de facturación</p>";
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
echo "<h3>Enlaces de Administración:</h3>";
echo "<p><a href='admin-billing.php' style='color: #28a745; font-weight: bold;'>📊 Panel de Administración de Facturación</a></p>";
echo "<p><a href='activate-multiple-billing.php' style='color: #ffc107; font-weight: bold;'>⚡ Activación Masiva de Facturas</a></p>";
echo "<p><a href='cleanup-duplicate-billing.php' style='color: #dc3545;'>🧹 Limpiar Duplicados</a></p>";

echo "<h3>Enlaces de Usuario:</h3>";
echo "<p><a href='checkout.php?plan=premium' style='color: #007bff;'>🛒 Ir al Checkout</a></p>";
echo "<p><a href='test-billing.php?plan=premium' style='color: #007bff;'>🧪 Probar creación de ciclo</a></p>";
echo "<p><a href='dashboard.php' style='color: #007bff;'>🏠 Ir al Dashboard</a></p>";
?>
