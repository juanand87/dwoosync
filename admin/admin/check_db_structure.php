<?php
define('API_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();

echo "<h1>Verificación de Estructura de Base de Datos</h1>";

try {
    // Verificar tabla subscription_cycles
    echo "<h2>Tabla subscription_cycles</h2>";
    $cycles = $db->query("SHOW TABLES LIKE 'subscription_cycles'")->fetchAll();
    if (count($cycles) > 0) {
        echo "✅ Tabla existe<br>";
        
        $structure = $db->query("DESCRIBE subscription_cycles")->fetchAll();
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($structure as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Contar registros
        $count = $db->query("SELECT COUNT(*) as total FROM subscription_cycles")->fetch()['total'];
        echo "Registros existentes: $count<br>";
    } else {
        echo "❌ Tabla NO existe<br>";
    }
    
    echo "<br>";
    
    // Verificar tabla invoices
    echo "<h2>Tabla invoices</h2>";
    $invoices = $db->query("SHOW TABLES LIKE 'invoices'")->fetchAll();
    if (count($invoices) > 0) {
        echo "✅ Tabla existe<br>";
        
        $structure = $db->query("DESCRIBE invoices")->fetchAll();
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($structure as $column) {
            echo "<tr>";
            echo "<td>{$column['Field']}</td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>{$column['Default']}</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Contar registros
        $count = $db->query("SELECT COUNT(*) as total FROM invoices")->fetch()['total'];
        echo "Registros existentes: $count<br>";
    } else {
        echo "❌ Tabla NO existe<br>";
    }
    
    echo "<br>";
    
    // Verificar suscriptores
    echo "<h2>Tabla subscribers</h2>";
    $subscribers = $db->query("SELECT id, first_name, last_name, plan_type FROM subscribers LIMIT 5")->fetchAll();
    echo "Suscriptores encontrados: " . count($subscribers) . "<br>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Plan</th></tr>";
    foreach ($subscribers as $sub) {
        echo "<tr>";
        echo "<td>{$sub['id']}</td>";
        echo "<td>{$sub['first_name']} {$sub['last_name']}</td>";
        echo "<td>{$sub['plan_type']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br>";
    
    // Probar inserción simple
    echo "<h2>Prueba de Inserción</h2>";
    try {
        $test_id = $db->insert('subscription_cycles', [
            'subscriber_id' => 88,
            'cycle_start_date' => '2024-01-01',
            'cycle_end_date' => '2024-01-31',
            'sync_count' => 0,
            'api_calls_count' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "✅ Inserción exitosa. ID del ciclo: $test_id<br>";
        
        // Eliminar el registro de prueba
        $db->query("DELETE FROM subscription_cycles WHERE id = $test_id");
        echo "✅ Registro de prueba eliminado<br>";
        
    } catch (Exception $e) {
        echo "❌ Error en inserción: " . $e->getMessage() . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "<br>";
}
?>





