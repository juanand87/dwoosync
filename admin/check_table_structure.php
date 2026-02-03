<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificación de Estructura de Tabla</h1>";

try {
    define('API_ACCESS', true);
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/classes/Database.php';
    
    $db = Database::getInstance();
    
    // Verificar estructura de la tabla licenses
    echo "<h3>Estructura de la tabla 'licenses':</h3>";
    $columns = $db->fetchAll("DESCRIBE licenses");
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
    }
    
    echo "<br><h3>Datos actuales en la tabla 'licenses':</h3>";
    $licenses = $db->fetchAll("SELECT * FROM licenses");
    foreach ($licenses as $license) {
        echo "<pre>" . print_r($license, true) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

