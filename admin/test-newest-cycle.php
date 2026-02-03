<?php
/**
 * Script para probar activación del ciclo más nuevo
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

echo "<h1>Prueba de Ciclo Más Nuevo</h1>";

// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_newest'])) {
    $subscriber_id = $_POST['subscriber_id'] ?? '';
    
    if (!empty($subscriber_id)) {
        echo "<h2>Probando con suscriptor ID: $subscriber_id</h2>";
        
        // Obtener todos los ciclos del suscriptor
        $cycles = $db->query("
            SELECT bc.*, s.first_name, s.last_name, s.email
            FROM billing_cycles bc
            JOIN subscribers s ON bc.subscriber_id = s.id
            WHERE bc.subscriber_id = ?
            ORDER BY bc.created_at DESC
        ", [$subscriber_id])->fetchAll();
        
        echo "<h3>Ciclos del suscriptor:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th>ID</th><th>Plan</th><th>Estado</th><th>Activa</th><th>Creado</th><th>Monto</th>";
        echo "</tr>";
        
        foreach ($cycles as $cycle) {
            $status_color = $cycle['status'] === 'paid' ? '#28a745' : '#ffc107';
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
        
        if (!empty($cycles)) {
            $newest_cycle = $cycles[0]; // El más reciente
            echo "<h3>Probando con el ciclo más nuevo (ID: {$newest_cycle['id']})</h3>";
            
            try {
                // Marcar como 'paid' primero
                echo "<p>1. Marcando como 'paid'...</p>";
                $update_stmt = $db->getConnection()->prepare("
                    UPDATE billing_cycles 
                    SET status = 'paid', updated_at = NOW()
                    WHERE id = ?
                ");
                $result = $update_stmt->execute([$newest_cycle['id']]);
                echo "<p>Resultado: " . ($result ? '✅ Éxito' : '❌ Error') . "</p>";
                
                // Simular la función de activación
                echo "<p>2. Activando cuenta...</p>";
                
                $pdo = $db->getConnection();
                $pdo->beginTransaction();
                
                // Desactivar otros ciclos
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
                $activate_cycle_result = $activate_cycle_stmt->execute([$newest_cycle['id']]);
                echo "<p>Activación de ciclo: " . ($activate_cycle_result ? '✅ Éxito' : '❌ Error') . " (Filas: " . $activate_cycle_stmt->rowCount() . ")</p>";
                
                // Activar la licencia
                $activate_license_stmt = $pdo->prepare("
                    UPDATE licenses 
                    SET status = 'active', updated_at = NOW()
                    WHERE subscriber_id = ?
                ");
                $activate_license_result = $activate_license_stmt->execute([$subscriber_id]);
                echo "<p>Activación de licencia: " . ($activate_license_result ? '✅ Éxito' : '❌ Error') . " (Filas: " . $activate_license_stmt->rowCount() . ")</p>";
                
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
                
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3>✅ ÉXITO</h3>";
                echo "<p>La activación se completó correctamente para el ciclo más nuevo.</p>";
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
}

// Mostrar suscriptores con múltiples ciclos
$subscribers = $db->query("
    SELECT s.id, s.first_name, s.last_name, s.email, COUNT(bc.id) as cycle_count
    FROM subscribers s
    JOIN billing_cycles bc ON s.id = bc.subscriber_id
    GROUP BY s.id, s.first_name, s.last_name, s.email
    HAVING COUNT(bc.id) > 1
    ORDER BY s.created_at DESC
    LIMIT 10
")->fetchAll();

echo "<h2>Suscriptores con Múltiples Ciclos</h2>";

if (empty($subscribers)) {
    echo "<p style='color: orange;'>⚠️ No hay suscriptores con múltiples ciclos</p>";
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
        echo "<button type='submit' name='test_newest' style='background: #007bff; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;'>Probar Más Nuevo</button>";
        echo "</form>";
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='invoices.php' style='color: #007bff;'>📊 Volver a Gestión de Facturas</a></p>";
?>


