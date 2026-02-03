<?php
/**
 * Prueba simple de actualización de billing_cycles
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

if (!isLoggedIn()) {
    die('Usuario no autenticado');
}

$subscriber_id = $_SESSION['subscriber_id'] ?? null;
if (!$subscriber_id) {
    die('No se encontró subscriber_id en la sesión');
}

echo "<h2>Prueba Simple de Actualización</h2>";
echo "<p><strong>Subscriber ID:</strong> $subscriber_id</p>";

try {
    $pdo = getDatabase();
    
    // 1. Crear una factura pendiente de prueba
    echo "<h3>1. Creando factura pendiente de prueba:</h3>";
    
    $test_plan = 'premium';
    $test_amount = 22;
    
    $insert_stmt = $pdo->prepare("
        INSERT INTO billing_cycles (
            subscriber_id, plan_type, amount, status, created_at
        ) VALUES (?, ?, ?, 'pending', NOW())
    ");
    
    $insert_result = $insert_stmt->execute([$subscriber_id, $test_plan, $test_amount]);
    
    if ($insert_result) {
        $billing_cycle_id = $pdo->lastInsertId();
        echo "<p style='color: green;'>✅ Factura creada con ID: $billing_cycle_id</p>";
        
        // 2. Verificar factura creada
        echo "<h3>2. Verificando factura creada:</h3>";
        $verify_stmt = $pdo->prepare("
            SELECT id, plan_type, amount, status, created_at 
            FROM billing_cycles 
            WHERE id = ?
        ");
        $verify_stmt->execute([$billing_cycle_id]);
        $created_cycle = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($created_cycle) {
            echo "<p><strong>Factura creada:</strong></p>";
            echo "<ul>";
            echo "<li>ID: {$created_cycle['id']}</li>";
            echo "<li>Plan: {$created_cycle['plan_type']}</li>";
            echo "<li>Amount: \${$created_cycle['amount']}</li>";
            echo "<li>Status: {$created_cycle['status']}</li>";
            echo "<li>Created: {$created_cycle['created_at']}</li>";
            echo "</ul>";
        }
        
        // 3. Actualizar la factura
        echo "<h3>3. Actualizando factura a enterprise:</h3>";
        
        $new_plan = 'enterprise';
        $new_amount = 45;
        
        $update_stmt = $pdo->prepare("
            UPDATE billing_cycles 
            SET plan_type = ?, amount = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $update_result = $update_stmt->execute([$new_plan, $new_amount, $billing_cycle_id]);
        
        if ($update_result) {
            echo "<p style='color: green;'>✅ Factura actualizada exitosamente</p>";
            
            // 4. Verificar actualización
            echo "<h3>4. Verificando actualización:</h3>";
            $verify_update_stmt = $pdo->prepare("
                SELECT id, plan_type, amount, status, created_at, updated_at 
                FROM billing_cycles 
                WHERE id = ?
            ");
            $verify_update_stmt->execute([$billing_cycle_id]);
            $updated_cycle = $verify_update_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($updated_cycle) {
                echo "<p><strong>Factura después de actualización:</strong></p>";
                echo "<ul>";
                echo "<li>ID: {$updated_cycle['id']}</li>";
                echo "<li>Plan: {$updated_cycle['plan_type']}</li>";
                echo "<li>Amount: \${$updated_cycle['amount']}</li>";
                echo "<li>Status: {$updated_cycle['status']}</li>";
                echo "<li>Created: {$updated_cycle['created_at']}</li>";
                echo "<li>Updated: {$updated_cycle['updated_at']}</li>";
                echo "</ul>";
                
                if ($updated_cycle['plan_type'] === $new_plan && $updated_cycle['amount'] == $new_amount) {
                    echo "<p style='color: green; font-weight: bold;'>✅ ACTUALIZACIÓN EXITOSA - Plan y amount actualizados correctamente</p>";
                } else {
                    echo "<p style='color: red; font-weight: bold;'>❌ ERROR - Plan o amount no se actualizaron correctamente</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ Error al actualizar factura</p>";
            $errorInfo = $update_stmt->errorInfo();
            echo "<p>Error details: " . json_encode($errorInfo) . "</p>";
        }
        
        // 5. Limpiar - eliminar factura de prueba
        echo "<h3>5. Limpiando factura de prueba:</h3>";
        $delete_stmt = $pdo->prepare("DELETE FROM billing_cycles WHERE id = ?");
        $delete_result = $delete_stmt->execute([$billing_cycle_id]);
        
        if ($delete_result) {
            echo "<p style='color: blue;'>✅ Factura de prueba eliminada</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ No se pudo eliminar la factura de prueba</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Error al crear factura de prueba</p>";
        $errorInfo = $insert_stmt->errorInfo();
        echo "<p>Error details: " . json_encode($errorInfo) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
ul { margin: 10px 0; }
li { margin: 5px 0; }

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

