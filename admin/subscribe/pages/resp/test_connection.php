<?php
// Test de conexión a la base de datos
require_once '../includes/config.php';

try {
    $db = getDatabase();
    echo "✅ Conexión exitosa a la base de datos\n";
    
    // Verificar estructura actual
    $result = $db->query("DESCRIBE subscribers");
    echo "\nEstructura actual de la tabla subscribers:\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "\n";
}
?>
