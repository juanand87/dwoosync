<?php
// Script PHP para eliminar campos redundantes
require_once '../includes/config.php';

try {
    $db = getDatabase();
    
    echo "Eliminando campo subscription_date...\n";
    $db->exec("ALTER TABLE subscribers DROP COLUMN subscription_date");
    echo "✅ Campo subscription_date eliminado\n";
    
    echo "Eliminando campo expiration_date...\n";
    $db->exec("ALTER TABLE subscribers DROP COLUMN expiration_date");
    echo "✅ Campo expiration_date eliminado\n";
    
    echo "\nVerificando estructura de la tabla subscribers:\n";
    $result = $db->query("DESCRIBE subscribers");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
    echo "\n✅ Campos redundantes eliminados exitosamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
