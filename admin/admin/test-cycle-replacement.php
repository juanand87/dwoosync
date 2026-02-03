<?php
/**
 * Script para probar el reemplazo de ciclos pendientes
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración y clases
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AdminAuth.php';

// Verificar autenticación de administrador
$auth = new AdminAuth();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();

echo "<h1>Prueba de Reemplazo de Ciclos Pendientes</h1>";

// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_replacement'])) {
    $subscriber_id = $_POST['subscriber_id'] ?? '';
    
    if (!empty($subscriber_id)) {
        echo "<h2>Probando reemplazo con suscriptor ID: $subscriber_id</h2>";
        
        // Obtener todos los ciclos del suscriptor
        $cycles = $db->query("
            SELECT bc.*, s.first_name, s.last_name, s.email
            FROM billing_cycles bc
            JOIN subscribers s ON bc.subscriber_id = s.id
            WHERE bc.subscriber_id = ?
            ORDER BY bc.created_at DESC
        ", [$subscriber_id])->fetchAll();
        
        echo "<h3>Ciclos del suscriptor ANTES del test:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th>ID</th><th>Plan</th><th>Estado</th><th>Activa</th><th>Creado</th><th>Monto</th>";
        echo "</tr>";
        
        foreach ($cycles as $cycle) {
            $status_color = $cycle['status'] === 'paid' ? '#28a745' : ($cycle['status'] === 'cancelled' ? '#dc3545' : '#ffc107');
            $active_text = $cycle['is_active'] == 1 ? '✅ Activa' : '❌ Inactiva';
            $active_color = $cycle['is_active'] == 1 ? '#28a745' : '#dc3545';
            
            echo "<tr>";
            echo "<td>" . $cycle['id'] . "</td>";
            echo "<td>" . strtoupper($cycle['plan_type']) . "</td>";
            echo "<td style='color: $status_color; font-weight: bold;'>" . strtoupper($cycle['status']) . "</td>";
            echo "<td style='color: $active_color; font-weight: bold;'>$active_text</td>";
            echo "<td>" . $cycle['created_at'] . "</td>";
            echo "<td>$" . number_format($cycle['amount'], 0, ',', '.') . " " . $cycle['currency'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Simular la creación de un nuevo ciclo pendiente
        echo "<h3>Simulando creación de nuevo ciclo pendiente...</h3>";
        
        try {
            // 1. Verificar si existe un ciclo pendiente
            $existing_cycle = $db->query("
                SELECT id, status, created_at 
                FROM billing_cycles 
                WHERE subscriber_id = ? AND status = 'pending' 
                ORDER BY created_at DESC 
                LIMIT 1
            ", [$subscriber_id])->fetch();
            
            if ($existing_cycle) {
                echo "<p>✅ Encontrado ciclo pendiente anterior - ID: {$existing_cycle['id']}</p>";
                
                // 2. Cancelar el ciclo anterior
                echo "<p>🔄 Cancelando ciclo anterior...</p>";
                $cancel_result = $db->query("
                    UPDATE billing_cycles 
                    SET status = 'cancelled', updated_at = NOW()
                    WHERE subscriber_id = ? AND status = 'pending'
                ", [$subscriber_id]);
                
                echo "<p>✅ Ciclo anterior cancelado</p>";
            } else {
                echo "<p>ℹ️ No hay ciclo pendiente anterior</p>";
            }
            
            // 3. Crear nuevo ciclo pendiente
            echo "<p>🔄 Creando nuevo ciclo pendiente...</p>";
            
            $cycle_start = date('Y-m-d');
            $cycle_end = date('Y-m-d', strtotime('+30 days'));
            $due_date = date('Y-m-d', strtotime('+33 days'));
            $invoice_number = 'TEST-' . date('Y') . '-' . str_pad($subscriber_id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(time(), 4, '0', STR_PAD_LEFT);
            
            $new_cycle_id = $db->query("
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
            ", [
                $subscriber_id,
                'premium',
                'TEST-' . $subscriber_id . '-' . time(),
                $cycle_start,
                $cycle_end,
                $due_date,
                0, // is_active = 0 (pendiente)
                'pending', // status = pending
                0, // sync_count
                0, // api_calls_count
                0, // products_synced
                10000, // amount
                'CLP',
                'pending', // payment_method = pending
                'TEST-' . time(), // payment_reference temporal
                $invoice_number,
                date('Y-m-d H:i:s')
            ])->lastInsertId();
            
            echo "<p>✅ Nuevo ciclo creado con ID: $new_cycle_id</p>";
            
            // 4. Verificar el resultado
            echo "<h3>Ciclos del suscriptor DESPUÉS del test:</h3>";
            $cycles_after = $db->query("
                SELECT bc.*, s.first_name, s.last_name, s.email
                FROM billing_cycles bc
                JOIN subscribers s ON bc.subscriber_id = s.id
                WHERE bc.subscriber_id = ?
                ORDER BY bc.created_at DESC
            ", [$subscriber_id])->fetchAll();
            
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
            echo "<tr style='background: #f8f9fa;'>";
            echo "<th>ID</th><th>Plan</th><th>Estado</th><th>Activa</th><th>Creado</th><th>Monto</th>";
            echo "</tr>";
            
            foreach ($cycles_after as $cycle) {
                $status_color = $cycle['status'] === 'paid' ? '#28a745' : ($cycle['status'] === 'cancelled' ? '#dc3545' : '#ffc107');
                $active_text = $cycle['is_active'] == 1 ? '✅ Activa' : '❌ Inactiva';
                $active_color = $cycle['is_active'] == 1 ? '#28a745' : '#dc3545';
                
                echo "<tr>";
                echo "<td>" . $cycle['id'] . "</td>";
                echo "<td>" . strtoupper($cycle['plan_type']) . "</td>";
                echo "<td style='color: $status_color; font-weight: bold;'>" . strtoupper($cycle['status']) . "</td>";
                echo "<td style='color: $active_color; font-weight: bold;'>$active_text</td>";
                echo "<td>" . $cycle['created_at'] . "</td>";
                echo "<td>$" . number_format($cycle['amount'], 0, ',', '.') . " " . $cycle['currency'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Verificar que solo hay un ciclo pendiente
            $pending_count = $db->query("
                SELECT COUNT(*) as count 
                FROM billing_cycles 
                WHERE subscriber_id = ? AND status = 'pending'
            ", [$subscriber_id])->fetch()['count'];
            
            if ($pending_count == 1) {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3>✅ ÉXITO</h3>";
                echo "<p>La lógica de reemplazo funciona correctamente. Solo hay $pending_count ciclo(s) pendiente(s).</p>";
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3>❌ ERROR</h3>";
                echo "<p>Hay $pending_count ciclos pendientes. Debería haber solo 1.</p>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>❌ ERROR</h3>";
            echo "<p>Error: " . $e->getMessage() . "</p>";
            echo "</div>";
        }
    }
}

// Mostrar suscriptores para probar
$subscribers = $db->query("
    SELECT s.id, s.first_name, s.last_name, s.email, COUNT(bc.id) as cycle_count
    FROM subscribers s
    JOIN billing_cycles bc ON s.id = bc.subscriber_id
    GROUP BY s.id, s.first_name, s.last_name, s.email
    ORDER BY s.created_at DESC
    LIMIT 10
")->fetchAll();

echo "<h2>Suscriptores para Probar</h2>";

if (empty($subscribers)) {
    echo "<p style='color: orange;'>⚠️ No hay suscriptores</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th>ID</th><th>Cliente</th><th>Ciclos</th><th>Probar</th>";
    echo "</tr>";
    
    foreach ($subscribers as $subscriber) {
        echo "<tr>";
        echo "<td>" . $subscriber['id'] . "</td>";
        echo "<td>" . $subscriber['first_name'] . ' ' . $subscriber['last_name'] . "<br><small>" . $subscriber['email'] . "</small></td>";
        echo "<td>" . $subscriber['cycle_count'] . " ciclos</td>";
        echo "<td>";
        
        echo "<form method='POST' style='display: inline;'>";
        echo "<input type='hidden' name='subscriber_id' value='" . $subscriber['id'] . "'>";
        echo "<button type='submit' name='test_replacement' style='background: #28a745; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;'>Probar Reemplazo</button>";
        echo "</form>";
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='invoices.php' style='color: #007bff;'>📊 Volver a Gestión de Facturas</a></p>";
?>

