<?php
/**
 * Script para limpiar ciclos de facturación duplicados
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

echo "<h1>Limpieza de Ciclos de Facturación Duplicados</h1>";
echo "<p><strong>Subscriber ID:</strong> $subscriber_id</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Procesando limpieza...</h2>";
    
    $pdo = null;
    try {
        $pdo = getDatabase();
        echo "<p>✅ Conexión a BD exitosa</p>";
        
        // Buscar ciclos pendientes duplicados
        $duplicates_stmt = $pdo->prepare("
            SELECT id, status, created_at, plan_type, invoice_number
            FROM billing_cycles 
            WHERE subscriber_id = ? AND status = 'pending' 
            ORDER BY created_at ASC
        ");
        $duplicates_stmt->execute([$subscriber_id]);
        $duplicates = $duplicates_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p><strong>Ciclos pendientes encontrados:</strong> " . count($duplicates) . "</p>";
        
        if (count($duplicates) > 1) {
            echo "<h3>Eliminando duplicados (manteniendo el más reciente)...</h3>";
            
            // Mantener solo el más reciente
            $keep_id = $duplicates[count($duplicates) - 1]['id'];
            $delete_ids = array_slice(array_column($duplicates, 'id'), 0, -1);
            
            echo "<p><strong>Manteniendo ciclo ID:</strong> $keep_id</p>";
            echo "<p><strong>Eliminando ciclos ID:</strong> " . implode(', ', $delete_ids) . "</p>";
            
            // Eliminar duplicados
            $delete_stmt = $pdo->prepare("DELETE FROM billing_cycles WHERE id = ?");
            $deleted_count = 0;
            
            foreach ($delete_ids as $delete_id) {
                if ($delete_stmt->execute([$delete_id])) {
                    $deleted_count++;
                    echo "<p>✅ Eliminado ciclo ID: $delete_id</p>";
                } else {
                    echo "<p>❌ Error eliminando ciclo ID: $delete_id</p>";
                }
            }
            
            echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>✅ LIMPIEZA COMPLETADA</h3>";
            echo "<p><strong>Ciclos eliminados:</strong> $deleted_count</p>";
            echo "<p><strong>Ciclo mantenido:</strong> $keep_id</p>";
            echo "</div>";
            
        } else if (count($duplicates) == 1) {
            echo "<p style='color: green;'>✅ Solo existe un ciclo pendiente, no hay duplicados</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ No se encontraron ciclos pendientes</p>";
        }
        
        // Mostrar ciclos actuales
        echo "<h3>Ciclos actuales:</h3>";
        $current_stmt = $pdo->prepare("
            SELECT id, status, created_at, plan_type, invoice_number
            FROM billing_cycles 
            WHERE subscriber_id = ? 
            ORDER BY created_at DESC
        ");
        $current_stmt->execute([$subscriber_id]);
        $current_cycles = $current_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Status</th><th>Plan</th><th>Invoice</th><th>Creado</th></tr>";
        foreach ($current_cycles as $cycle) {
            $status_color = $cycle['status'] === 'pending' ? '#ffc107' : '#28a745';
            echo "<tr>";
            echo "<td>" . $cycle['id'] . "</td>";
            echo "<td style='color: $status_color; font-weight: bold;'>" . $cycle['status'] . "</td>";
            echo "<td>" . $cycle['plan_type'] . "</td>";
            echo "<td>" . $cycle['invoice_number'] . "</td>";
            echo "<td>" . $cycle['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
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
}

echo "<hr>";
echo "<form method='POST'>";
echo "<button type='submit' style='background: #dc3545; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>Limpiar Duplicados</button>";
echo "</form>";

echo "<hr>";
echo "<h3>Enlaces:</h3>";
echo "<p><a href='checkout.php?plan=premium' style='color: #007bff;'>Ir al Checkout</a></p>";
echo "<p><a href='test-billing.php?plan=premium' style='color: #007bff;'>Probar creación de ciclo</a></p>";
echo "<p><a href='dashboard.php' style='color: #007bff;'>Ir al Dashboard</a></p>";
?>


