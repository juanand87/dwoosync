<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Corrección Simple de Licencia</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    
    $db = Database::getInstance();
    
    // Verificar estado actual
    echo "<h3>Estado actual:</h3>";
    $license = $db->fetch("SELECT * FROM licenses WHERE id = 1");
    echo "Status: '" . $license['status'] . "'<br>";
    echo "Usage Count: " . $license['usage_count'] . "<br>";
    echo "Usage Limit: " . $license['usage_limit'] . "<br><br>";
    
    // Usar query directo para actualizar
    echo "<h3>Actualizando con query directo...</h3>";
    $sql = "UPDATE licenses SET status = 'active' WHERE id = 1";
    $stmt = $db->query($sql);
    $affected = $stmt->rowCount();
    
    echo "Filas afectadas: " . $affected . "<br>";
    
    if ($affected > 0) {
        echo "✅ Licencia actualizada<br>";
        
        // Verificar el cambio
        $updatedLicense = $db->fetch("SELECT * FROM licenses WHERE id = 1");
        echo "<h3>Estado después de la actualización:</h3>";
        echo "Status: '" . $updatedLicense['status'] . "'<br>";
        echo "Usage Count: " . $updatedLicense['usage_count'] . "<br>";
        echo "Usage Limit: " . $updatedLicense['usage_limit'] . "<br>";
        
        if ($updatedLicense['status'] == 'active') {
            echo "<p style='color: green;'>✅ Licencia corregida correctamente</p>";
        } else {
            echo "<p style='color: red;'>❌ Error en la actualización</p>";
        }
    } else {
        echo "❌ No se pudo actualizar la licencia<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>

