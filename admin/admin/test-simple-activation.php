<?php
/**
 * Script simple para probar la activación
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

echo "<h1>Prueba Simple de Activación</h1>";

// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test'])) {
    $invoice_id = $_POST['invoice_id'] ?? '';
    
    if (!empty($invoice_id)) {
        echo "<h2>Probando factura ID: $invoice_id</h2>";
        
        try {
            // Primero marcar como 'paid'
            echo "<p>1. Marcando como 'paid'...</p>";
            $update_stmt = $db->getConnection()->prepare("
                UPDATE billing_cycles 
                SET status = 'paid', updated_at = NOW()
                WHERE id = ?
            ");
            $result = $update_stmt->execute([$invoice_id]);
            echo "<p>Resultado: " . ($result ? '✅ Éxito' : '❌ Error') . "</p>";
            
            // Ahora activar
            echo "<p>2. Activando cuenta...</p>";
            
            // Función de activación para admin
            function activateBillingCycleAdmin($billing_cycle_id, $pdo) {
                try {
                    $pdo->beginTransaction();
                    
                    // Obtener información del ciclo de facturación
                    $cycle_stmt = $pdo->prepare("
                        SELECT bc.*, s.email, s.first_name, s.last_name 
                        FROM billing_cycles bc
                        JOIN subscribers s ON bc.subscriber_id = s.id
                        WHERE bc.id = ? AND (bc.status = 'pending' OR bc.status = 'paid')
                    ");
                    $cycle_stmt->execute([$billing_cycle_id]);
                    $cycle = $cycle_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$cycle) {
                        throw new Exception('Ciclo de facturación no encontrado o ya procesado');
                    }
                    
                    // Si ya está activo, no hacer nada
                    if ($cycle['is_active'] == 1) {
                        return [
                            'success' => true,
                            'message' => 'El ciclo de facturación ya está activo',
                            'billing_cycle_id' => $billing_cycle_id,
                            'subscriber_id' => $cycle['subscriber_id'],
                            'subscriber_email' => $cycle['email'],
                            'subscriber_name' => $cycle['first_name'] . ' ' . $cycle['last_name']
                        ];
                    }
                    
                    $subscriber_id = $cycle['subscriber_id'];
                    
                    // Desactivar otros ciclos activos del mismo suscriptor
                    $deactivate_stmt = $pdo->prepare("
                        UPDATE billing_cycles 
                        SET is_active = 0 
                        WHERE subscriber_id = ? AND is_active = 1
                    ");
                    $deactivate_stmt->execute([$subscriber_id]);
                    
                    // Activar el ciclo actual
                    $activate_cycle_stmt = $pdo->prepare("
                        UPDATE billing_cycles 
                        SET is_active = 1, status = 'paid', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $activate_cycle_stmt->execute([$billing_cycle_id]);
                    
                    // Activar la licencia del suscriptor
                    $activate_license_stmt = $pdo->prepare("
                        UPDATE licenses 
                        SET status = 'active', updated_at = NOW()
                        WHERE subscriber_id = ?
                    ");
                    $activate_license_stmt->execute([$subscriber_id]);
                    
                    // Actualizar estado del suscriptor
                    $activate_subscriber_stmt = $pdo->prepare("
                        UPDATE subscribers 
                        SET status = 'active', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $activate_subscriber_stmt->execute([$subscriber_id]);
                    
                    $pdo->commit();
                    
                    return [
                        'success' => true,
                        'message' => 'Ciclo de facturación activado correctamente',
                        'billing_cycle_id' => $billing_cycle_id,
                        'subscriber_id' => $subscriber_id,
                        'subscriber_email' => $cycle['email'],
                        'subscriber_name' => $cycle['first_name'] . ' ' . $cycle['last_name']
                    ];
                    
                } catch (Exception $e) {
                    $pdo->rollback();
                    return [
                        'success' => false,
                        'message' => 'Error activando ciclo de facturación: ' . $e->getMessage()
                    ];
                }
            }
            
            $activation_result = activateBillingCycleAdmin($invoice_id, $db->getConnection());
            
            echo "<h3>Resultado:</h3>";
            echo "<pre>" . print_r($activation_result, true) . "</pre>";
            
            if ($activation_result['success']) {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3>✅ ÉXITO</h3>";
                echo "<p>La activación se completó correctamente.</p>";
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3>❌ ERROR</h3>";
                echo "<p>Error: " . $activation_result['message'] . "</p>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>❌ EXCEPCIÓN</h3>";
            echo "<p>Error: " . $e->getMessage() . "</p>";
            echo "<p>Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "</p>";
            echo "</div>";
        }
    }
}

// Mostrar facturas
$invoices = $db->query("
    SELECT bc.*, s.first_name, s.last_name, s.email
    FROM billing_cycles bc
    JOIN subscribers s ON bc.subscriber_id = s.id
    WHERE bc.status IN ('pending', 'paid')
    ORDER BY bc.created_at DESC
    LIMIT 5
")->fetchAll();

echo "<h2>Facturas Disponibles</h2>";

if (empty($invoices)) {
    echo "<p style='color: orange;'>⚠️ No hay facturas para probar</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th>ID</th><th>Cliente</th><th>Estado</th><th>Activa</th><th>Probar</th>";
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
        echo "<td>";
        
        echo "<form method='POST' style='display: inline;'>";
        echo "<input type='hidden' name='invoice_id' value='" . $invoice['id'] . "'>";
        echo "<button type='submit' name='test' style='background: #007bff; color: white; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;'>Probar</button>";
        echo "</form>";
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='invoices.php' style='color: #007bff;'>📊 Volver a Gestión de Facturas</a></p>";
?>
