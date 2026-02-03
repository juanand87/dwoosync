<?php
/**
 * Verificar y arreglar el problema del subscriber_id
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/functions.php';

startSecureSession();

echo "<h2>🔍 Verificación del Subscriber ID</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

$subscriber_id = $_SESSION['subscriber_id'] ?? null;
echo "Subscriber ID en sesión: " . ($subscriber_id ?? 'NULL') . "<br>";

if ($subscriber_id) {
    try {
        $db = getDatabase();
        
        // Verificar si existe el subscriber
        $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
        $stmt->execute([$subscriber_id]);
        $subscriber = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscriber) {
            echo "✅ Subscriber EXISTE en la base de datos<br>";
            echo "- ID: " . $subscriber['id'] . "<br>";
            echo "- Email: " . $subscriber['email'] . "<br>";
            echo "- Nombre: " . $subscriber['first_name'] . " " . $subscriber['last_name'] . "<br>";
            echo "- Status: " . $subscriber['status'] . "<br>";
            echo "- Domain: " . $subscriber['domain'] . "<br>";
            
            // Verificar si ya tiene billing_cycles
            $stmt = $db->prepare("SELECT * FROM billing_cycles WHERE subscriber_id = ? ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$subscriber_id]);
            $cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<br><strong>Billing Cycles existentes:</strong><br>";
            if (empty($cycles)) {
                echo "- No hay ciclos de facturación<br>";
            } else {
                foreach ($cycles as $cycle) {
                    echo "- ID: {$cycle['id']}, Plan: {$cycle['plan_type']}, Status: {$cycle['status']}, Active: {$cycle['is_active']}<br>";
                }
            }
            
            // Verificar licencias
            $stmt = $db->prepare("SELECT * FROM licenses WHERE subscriber_id = ?");
            $stmt->execute([$subscriber_id]);
            $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<br><strong>Licencias existentes:</strong><br>";
            if (empty($licenses)) {
                echo "- No hay licencias<br>";
            } else {
                foreach ($licenses as $license) {
                    echo "- Key: {$license['license_key']}, Status: {$license['status']}, Expires: {$license['expires_at']}<br>";
                }
            }
            
        } else {
            echo "❌ Subscriber NO EXISTE en la base de datos<br>";
            echo "El ID de sesión no corresponde a ningún usuario válido.<br>";
            echo "<br><strong>Solución:</strong> Limpiar sesión y registrar nuevamente<br>";
            echo "<a href='../pages/logout.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Cerrar Sesión y Registrar Nuevamente</a>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error consultando BD: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ No hay subscriber_id en la sesión<br>";
    echo "<a href='../pages/login.php'>Ir a Login</a>";
}

echo "</div>";
?>