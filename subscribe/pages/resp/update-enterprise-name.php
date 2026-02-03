<?php
require_once '../includes/config.php';

try {
    $db = getDatabase();
    
    // Verificar el nombre actual del plan enterprise
    $stmt = $db->prepare("SELECT plan_name FROM subscription_plans WHERE plan_type = 'enterprise'");
    $stmt->execute();
    $currentName = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Estado Actual del Plan Enterprise:</h2>";
    echo "<p>Nombre actual: " . ($currentName['plan_name'] ?? 'No encontrado') . "</p>";
    
    // Actualizar el nombre a '+Spotify'
    $updateStmt = $db->prepare("UPDATE subscription_plans SET plan_name = '+Spotify' WHERE plan_type = 'enterprise'");
    $result = $updateStmt->execute();
    
    if ($result) {
        echo "<p style='color: green;'>✅ Nombre actualizado exitosamente a '+Spotify'</p>";
        
        // Verificar el cambio
        $verifyStmt = $db->prepare("SELECT plan_name FROM subscription_plans WHERE plan_type = 'enterprise'");
        $verifyStmt->execute();
        $newName = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Nuevo nombre: " . $newName['plan_name'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Error al actualizar el nombre</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

