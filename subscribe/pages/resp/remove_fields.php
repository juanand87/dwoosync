<?php
require_once '../includes/config.php';

$db = getDatabase();

// Eliminar subscription_date
$db->exec("ALTER TABLE subscribers DROP COLUMN subscription_date");

// Eliminar expiration_date  
$db->exec("ALTER TABLE subscribers DROP COLUMN expiration_date");

echo "Campos eliminados exitosamente";
?>
