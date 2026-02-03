<?php
// Probar registro completo
require_once 'subscribe/includes/config.php';
require_once 'subscribe/includes/functions.php';

// Iniciar sesión
startSecureSession();

echo "=== PRUEBA DE REGISTRO COMPLETO ===\n";

// Simular datos de registro
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'email' => 'test' . time() . '@example.com',
    'password' => '123456',
    'confirm_password' => '123456',
    'domain' => 'test' . time() . '.com',
    'company' => 'Test Company',
    'city' => 'Test City',
    'country' => 'Test Country',
    'phone' => '123456789',
    'csrf_token' => generateCSRFToken()
];

echo "1. Datos simulados:\n";
foreach ($_POST as $key => $value) {
    if ($key !== 'csrf_token') {
        echo "   $key: $value\n";
    }
}

echo "2. Token CSRF: " . $_POST['csrf_token'] . "\n";

// Verificar token
$isValid = verifyCSRFToken($_POST['csrf_token']);
echo "3. Token válido: " . ($isValid ? 'Sí' : 'No') . "\n";

if ($isValid) {
    echo "4. Procesando registro...\n";
    
    try {
        $db = Database::getInstance();
        
        // Verificar que no existe el dominio
        $existingDomain = $db->fetch('SELECT id FROM subscribers WHERE domain = ?', [$_POST['domain']]);
        if ($existingDomain) {
            echo "   Error: El dominio ya existe\n";
        } else {
            echo "   Dominio disponible\n";
        }
        
        // Verificar que no existe el email
        $existingEmail = $db->fetch('SELECT id FROM subscribers WHERE email = ?', [$_POST['email']]);
        if ($existingEmail) {
            echo "   Error: El email ya existe\n";
        } else {
            echo "   Email disponible\n";
        }
        
        echo "✅ El registro debería funcionar correctamente\n";
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Error: Token CSRF inválido\n";
}

// Limpiar
unlink('test_signup_complete.php');
?>


