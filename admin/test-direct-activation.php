<?php
/**
 * Script de prueba directa para activación de facturas
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración y clases
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AdminAuth.php';
require_once __DIR__ . '/../subscribe/includes/billing-functions.php';

// Verificar autenticación de administrador
$auth = new AdminAuth();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();

echo "<h1>Prueba Directa de Activación</h1>";

// Procesar activación directa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_activation'])) {
    $invoice_id = $_POST['invoice_id'] ?? '';
    
    if (!empty($invoice_id)) {
        echo "<h2>Probando activación directa para factura ID: $invoice_id</h2>";
        
        // Obtener información de la factura antes
        $before_stmt = $db->query("
            SELECT bc.*, l.status as license_status, s.status as subscriber_status
            FROM billing_cycles bc
            LEFT JOIN licenses l ON bc.subscriber_id = l.subscriber_id
            LEFT JOIN subscribers s ON bc.subscriber_id = s.id
            WHERE bc.id = ?
        ", [$invoice_id])->fetch();
        
        if ($before_stmt) {
            echo "<h3>Estado ANTES de la activación:</h3>";
            echo "<ul>";
            echo "<li><strong>Billing Cycle Status:</strong> " . $before_stmt['status'] . "</li>";
            echo "<li><strong>Billing Cycle is_active:</strong> " . ($before_stmt['is_active'] ? 'Sí' : 'No') . "</li>";
            echo "<li><strong>License Status:</strong> " . ($before_stmt['license_status'] ?? 'No encontrada') . "</li>";
            echo "<li><strong>Subscriber Status:</strong> " . ($before_stmt['subscriber_status'] ?? 'No encontrado') . "</li>";
            echo "</ul>";
            
            // Cambiar estado a 'paid' primero
            echo "<p>🔄 Cambiando estado a 'paid'...</p>";
            $update_stmt = $db->getConnection()->prepare("
                UPDATE billing_cycles 
                SET status = 'paid', updated_at = NOW()
                WHERE id = ?
            ");
            $update_result = $update_stmt->execute([$invoice_id]);
            echo "<p>Resultado: " . ($update_result ? '✅ Éxito' : '❌ Error') . "</p>";
            
            // Ahora activar
            echo "<p>🔄 Llamando a activatePaidBillingCycle($invoice_id)...</p>";
            $activation_result = activatePaidBillingCycle($invoice_id);
            
            echo "<h3>Resultado de la activación:</h3>";
            echo "<pre>" . print_r($activation_result, true) . "</pre>";
            
            // Obtener información después
            $after_stmt = $db->query("
                SELECT bc.*, l.status as license_status, s.status as subscriber_status
                FROM billing_cycles bc
                LEFT JOIN licenses l ON bc.subscriber_id = l.subscriber_id
                LEFT JOIN subscribers s ON bc.subscriber_id = s.id
                WHERE bc.id = ?
            ", [$invoice_id])->fetch();
            
            if ($after_stmt) {
                echo "<h3>Estado DESPUÉS de la activación:</h3>";
                echo "<ul>";
                echo "<li><strong>Billing Cycle Status:</strong> " . $after_stmt['status'] . "</li>";
                echo "<li><strong>Billing Cycle is_active:</strong> " . ($after_stmt['is_active'] ? 'Sí' : 'No') . "</li>";
                echo "<li><strong>License Status:</strong> " . ($after_stmt['license_status'] ?? 'No encontrada') . "</li>";
                echo "<li><strong>Subscriber Status:</strong> " . ($after_stmt['subscriber_status'] ?? 'No encontrado') . "</li>";
                echo "</ul>";
                
                // Verificar cambios
                $changes = [];
                if ($before_stmt['status'] !== $after_stmt['status']) {
                    $changes[] = "Status: {$before_stmt['status']} → {$after_stmt['status']}";
                }
                if ($before_stmt['is_active'] != $after_stmt['is_active']) {
                    $changes[] = "is_active: " . ($before_stmt['is_active'] ? 'Sí' : 'No') . " → " . ($after_stmt['is_active'] ? 'Sí' : 'No');
                }
                if ($before_stmt['license_status'] !== $after_stmt['license_status']) {
                    $changes[] = "License: " . ($before_stmt['license_status'] ?? 'N/A') . " → " . ($after_stmt['license_status'] ?? 'N/A');
                }
                if ($before_stmt['subscriber_status'] !== $after_stmt['subscriber_status']) {
                    $changes[] = "Subscriber: " . ($before_stmt['subscriber_status'] ?? 'N/A') . " → " . ($after_stmt['subscriber_status'] ?? 'N/A');
                }
                
                if (!empty($changes)) {
                    echo "<h3>✅ Cambios realizados:</h3>";
                    echo "<ul>";
                    foreach ($changes as $change) {
                        echo "<li>$change</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<h3>⚠️ No se detectaron cambios</h3>";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ Factura no encontrada</p>";
        }
    }
}

// Mostrar facturas disponibles
$invoices = $db->query("
    SELECT bc.*, s.first_name, s.last_name, s.email
    FROM billing_cycles bc
    JOIN subscribers s ON bc.subscriber_id = s.id
    WHERE bc.status IN ('pending', 'paid')
    ORDER BY bc.created_at DESC
    LIMIT 10
")->fetchAll();

echo "<h2>Facturas Disponibles para Probar</h2>";

if (empty($invoices)) {
    echo "<p style='color: orange;'>⚠️ No hay facturas para probar</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th>ID</th><th>Cliente</th><th>Estado</th><th>Activa</th><th>Monto</th><th>Probar</th>";
    echo "</tr>";
    
    foreach ($invoices as $invoice) {
        $status_color = $invoice['status'] === 'paid' ? '#28a745' : '#ffc107';
        $active_text = $invoice['is_active'] == 1 ? '✅ Activa' : '❌ Inactiva';
        $active_color = $invoice['is_active'] == 1 ? '#28a745' : '#dc3545';
        
        echo "<tr>";
        echo "<td>" . $invoice['id'] . "</td>";
        echo "<td>" . $invoice['first_name'] . ' ' . $invoice['last_name'] . "<br><small>" . $invoice['email'] . "</small></td>";
        echo "<td style='color: $status_color; font-weight: bold;'>" . strtoupper($invoice['status']) . "</td>";
        echo "<td style='color: $active_color; font-weight: bold;'>$active_text</td>";
        echo "<td>$" . number_format($invoice['amount'], 0, ',', '.') . " " . $invoice['currency'] . "</td>";
        echo "<td>";
        
        echo "<form method='POST' style='display: inline;'>";
        echo "<input type='hidden' name='invoice_id' value='" . $invoice['id'] . "'>";
        echo "<button type='submit' name='test_activation' style='background: #007bff; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;'>Probar Activación</button>";
        echo "</form>";
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h3>Enlaces:</h3>";
echo "<p><a href='invoices.php' style='color: #007bff;'>📊 Gestión de Facturas</a></p>";
?>


