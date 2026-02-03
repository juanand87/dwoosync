<?php
// Script para corregir la sesión del dashboard
echo "=== CORRIGIENDO SESIÓN DEL DASHBOARD ===\n";

try {
    // Iniciar sesión
    session_start();
    
    // Datos del usuario correcto (el que está haciendo el tracking)
    $correct_subscriber_id = 51;
    $correct_license_key = 'DISC-61B239192ED5E349';
    $correct_email = 'juanand87@gmail.com';
    $correct_name = 'Juan Fica';
    $correct_domain = 'localhost';
    $correct_plan = 'free';
    
    // Establecer la sesión correcta
    $_SESSION['subscriber_id'] = $correct_subscriber_id;
    $_SESSION['user_id'] = $correct_subscriber_id;
    $_SESSION['user_email'] = $correct_email;
    $_SESSION['user_name'] = $correct_name;
    $_SESSION['user_domain'] = $correct_domain;
    $_SESSION['user_plan'] = $correct_plan;
    $_SESSION['license_key'] = $correct_license_key;
    $_SESSION['login_time'] = time();
    
    echo "✅ Sesión establecida correctamente:\n";
    echo "  - subscriber_id: " . $_SESSION['subscriber_id'] . "\n";
    echo "  - user_email: " . $_SESSION['user_email'] . "\n";
    echo "  - user_name: " . $_SESSION['user_name'] . "\n";
    echo "  - license_key: " . $_SESSION['license_key'] . "\n";
    
    echo "\n✅ Ahora puedes acceder al dashboard y ver los datos correctos\n";
    echo "<a href='dashboard.php'>Ir al Dashboard</a>\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
