<?php
define('API_ACCESS', true);
require_once 'config/database.php';

try {
    $stmt = $pdo->query("DESCRIBE billing_cycles");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== ESTRUCTURA DE LA TABLA billing_cycles ===\n\n";
    
    foreach ($columns as $column) {
        echo "Campo: " . $column['Field'] . "\n";
        echo "  Tipo: " . $column['Type'] . "\n";
        echo "  Null: " . $column['Null'] . "\n";
        echo "  Key: " . $column['Key'] . "\n";
        echo "  Default: " . ($column['Default'] ?? 'NULL') . "\n";
        echo "  Extra: " . $column['Extra'] . "\n";
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

