<?php
/**
 * Script para probar la actualización de fecha de vencimiento de licencias
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

echo "<h1>Prueba de Actualización de Fecha de Vencimiento de Licencias</h1>";

// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_expiration'])) {
    $subscriber_id = $_POST['subscriber_id'] ?? '';
    
    if (!empty($subscriber_id)) {
        echo "<h2>Probando actualización de vencimiento para suscriptor ID: $subscriber_id</h2>";
        
        // Obtener información del suscriptor y su licencia
        $subscriber_info = $db->query("
            SELECT s.*, l.license_key, l.expires_at as license_expires_at, l.status as license_status
            FROM subscribers s
            LEFT JOIN licenses l ON s.id = l.subscriber_id
            WHERE s.id = ?
        ", [$subscriber_id])->fetch();
        
        if (!$subscriber_info) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>❌ ERROR</h3>";
            echo "<p>Suscriptor no encontrado</p>";
            echo "</div>";
            return;
        }
        
        echo "<h3>Información del Suscriptor:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th>Campo</th><th>Valor</th>";
        echo "</tr>";
        echo "<tr><td>ID</td><td>" . $subscriber_info['id'] . "</td></tr>";
        echo "<tr><td>Nombre</td><td>" . $subscriber_info['first_name'] . ' ' . $subscriber_info['last_name'] . "</td></tr>";
        echo "<tr><td>Email</td><td>" . $subscriber_info['email'] . "</td></tr>";
        echo "<tr><td>Licencia</td><td>" . ($subscriber_info['license_key'] ?? 'No encontrada') . "</td></tr>";
        echo "<tr><td>Estado Licencia</td><td>" . ($subscriber_info['license_status'] ?? 'N/A') . "</td></tr>";
        echo "<tr><td>Vencimiento Actual</td><td>" . ($subscriber_info['license_expires_at'] ?? 'N/A') . "</td></tr>";
        echo "</table>";
        
        // Obtener ciclos de facturación del suscriptor
        $cycles = $db->query("
            SELECT bc.*, s.first_name, s.last_name, s.email
            FROM billing_cycles bc
            JOIN subscribers s ON bc.subscriber_id = s.id
            WHERE bc.subscriber_id = ?
            ORDER BY bc.created_at DESC
        ", [$subscriber_id])->fetchAll();
        
        echo "<h3>Ciclos de Facturación:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th>ID</th><th>Plan</th><th>Estado</th><th>Activa</th><th>Inicio</th><th>Fin</th><th>Monto</th><th>Acción</th>";
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
            echo "<td>" . $cycle['cycle_start_date'] . "</td>";
            echo "<td>" . $cycle['cycle_end_date'] . "</td>";
            echo "<td>$" . number_format($cycle['amount'], 0, ',', '.') . " " . $cycle['currency'] . "</td>";
            echo "<td>";
            
            if ($cycle['status'] === 'paid' && $cycle['is_active'] == 0) {
                echo "<form method='POST' style='display: inline;'>";
                echo "<input type='hidden' name='subscriber_id' value='$subscriber_id'>";
                echo "<input type='hidden' name='cycle_id' value='{$cycle['id']}'>";
                echo "<button type='submit' name='test_activation' style='background: #28a745; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;'>Activar</button>";
                echo "</form>";
            } else {
                echo "-";
            }
            
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_activation'])) {
    $subscriber_id = $_POST['subscriber_id'] ?? '';
    $cycle_id = $_POST['cycle_id'] ?? '';
    
    if (!empty($subscriber_id) && !empty($cycle_id)) {
        echo "<h2>Probando activación de ciclo ID: $cycle_id</h2>";
        
        try {
            // Obtener información del ciclo
            $cycle = $db->query("
                SELECT bc.*, s.email, s.first_name, s.last_name 
                FROM billing_cycles bc
                JOIN subscribers s ON bc.subscriber_id = s.id
                WHERE bc.id = ? AND bc.status = 'paid'
            ", [$cycle_id])->fetch();
            
            if (!$cycle) {
                throw new Exception('Ciclo no encontrado o no está pagado');
            }
            
            echo "<h3>Información del Ciclo a Activar:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
            echo "<tr style='background: #f8f9fa;'>";
            echo "<th>Campo</th><th>Valor</th>";
            echo "</tr>";
            echo "<tr><td>ID</td><td>" . $cycle['id'] . "</td></tr>";
            echo "<tr><td>Plan</td><td>" . strtoupper($cycle['plan_type']) . "</td></tr>";
            echo "<tr><td>Estado</td><td>" . strtoupper($cycle['status']) . "</td></tr>";
            echo "<tr><td>Fecha Inicio</td><td>" . $cycle['cycle_start_date'] . "</td></tr>";
            echo "<tr><td>Fecha Fin</td><td>" . $cycle['cycle_end_date'] . "</td></tr>";
            echo "<tr><td>Monto</td><td>$" . number_format($cycle['amount'], 0, ',', '.') . " " . $cycle['currency'] . "</td></tr>";
            echo "</table>";
            
            // Simular la activación
            $pdo = $db->getConnection();
            $pdo->beginTransaction();
            
            // Desactivar otros ciclos activos
            $deactivate_stmt = $pdo->prepare("
                UPDATE billing_cycles 
                SET is_active = 0 
                WHERE subscriber_id = ? AND is_active = 1
            ");
            $deactivate_result = $deactivate_stmt->execute([$subscriber_id]);
            echo "<p>Desactivación de otros ciclos: " . ($deactivate_result ? '✅ Éxito' : '❌ Error') . "</p>";
            
            // Activar el ciclo actual
            $activate_cycle_stmt = $pdo->prepare("
                UPDATE billing_cycles 
                SET is_active = 1, status = 'paid', updated_at = NOW()
                WHERE id = ?
            ");
            $activate_cycle_result = $activate_cycle_stmt->execute([$cycle_id]);
            echo "<p>Activación de ciclo: " . ($activate_cycle_result ? '✅ Éxito' : '❌ Error') . " (Filas: " . $activate_cycle_stmt->rowCount() . ")</p>";
            
            // Activar la licencia y actualizar fecha de vencimiento
            $activate_license_stmt = $pdo->prepare("
                UPDATE licenses 
                SET status = 'active', expires_at = ?, updated_at = NOW()
                WHERE subscriber_id = ?
            ");
            $activate_license_result = $activate_license_stmt->execute([$cycle['cycle_end_date'], $subscriber_id]);
            echo "<p>Activación de licencia con nueva fecha de vencimiento: " . ($activate_license_result ? '✅ Éxito' : '❌ Error') . " (Filas: " . $activate_license_stmt->rowCount() . ")</p>";
            echo "<p>Nueva fecha de vencimiento: " . $cycle['cycle_end_date'] . "</p>";
            
            // Activar el suscriptor
            $activate_subscriber_stmt = $pdo->prepare("
                UPDATE subscribers 
                SET status = 'active', updated_at = NOW()
                WHERE id = ?
            ");
            $activate_subscriber_result = $activate_subscriber_stmt->execute([$subscriber_id]);
            echo "<p>Activación de suscriptor: " . ($activate_subscriber_result ? '✅ Éxito' : '❌ Error') . " (Filas: " . $activate_subscriber_stmt->rowCount() . ")</p>";
            
            $pdo->commit();
            echo "<p>✅ Transacción confirmada</p>";
            
            // Verificar el resultado
            $updated_license = $db->query("
                SELECT license_key, expires_at, status
                FROM licenses
                WHERE subscriber_id = ?
            ", [$subscriber_id])->fetch();
            
            echo "<h3>Licencia Actualizada:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
            echo "<tr style='background: #f8f9fa;'>";
            echo "<th>Campo</th><th>Valor</th>";
            echo "</tr>";
            echo "<tr><td>Licencia</td><td>" . $updated_license['license_key'] . "</td></tr>";
            echo "<tr><td>Estado</td><td>" . $updated_license['status'] . "</td></tr>";
            echo "<tr><td>Nueva Fecha de Vencimiento</td><td>" . $updated_license['expires_at'] . "</td></tr>";
            echo "</table>";
            
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>✅ ÉXITO</h3>";
            echo "<p>La licencia se actualizó correctamente con la nueva fecha de vencimiento: " . $updated_license['expires_at'] . "</p>";
            echo "</div>";
            
        } catch (Exception $e) {
            $pdo->rollback();
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>❌ ERROR</h3>";
            echo "<p>Error: " . $e->getMessage() . "</p>";
            echo "</div>";
        }
    }
}

// Mostrar suscriptores para probar
$subscribers = $db->query("
    SELECT s.id, s.first_name, s.last_name, s.email, l.expires_at
    FROM subscribers s
    LEFT JOIN licenses l ON s.id = l.subscriber_id
    ORDER BY s.created_at DESC
    LIMIT 10
")->fetchAll();

echo "<h2>Suscriptores para Probar</h2>";

if (empty($subscribers)) {
    echo "<p style='color: orange;'>⚠️ No hay suscriptores</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th>ID</th><th>Cliente</th><th>Vencimiento Actual</th><th>Probar</th>";
    echo "</tr>";
    
    foreach ($subscribers as $subscriber) {
        echo "<tr>";
        echo "<td>" . $subscriber['id'] . "</td>";
        echo "<td>" . $subscriber['first_name'] . ' ' . $subscriber['last_name'] . "<br><small>" . $subscriber['email'] . "</small></td>";
        echo "<td>" . ($subscriber['expires_at'] ?? 'N/A') . "</td>";
        echo "<td>";
        
        echo "<form method='POST' style='display: inline;'>";
        echo "<input type='hidden' name='subscriber_id' value='" . $subscriber['id'] . "'>";
        echo "<button type='submit' name='test_expiration' style='background: #007bff; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;'>Probar</button>";
        echo "</form>";
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='invoices.php' style='color: #007bff;'>📊 Volver a Gestión de Facturas</a></p>";
?>

