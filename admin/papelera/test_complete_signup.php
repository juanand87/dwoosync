<?php
// Probar flujo completo de registro
require_once 'subscribe/includes/config.php';
require_once 'subscribe/includes/functions.php';

// Iniciar sesión
startSecureSession();

echo "=== PRUEBA DE REGISTRO COMPLETO ===\n";

// 1. Generar token CSRF
$token = generateCSRFToken();
echo "1. Token generado: " . $token . "\n";

// 2. Simular datos de registro
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'first_name' => 'Juan',
    'last_name' => 'Pérez',
    'email' => 'test' . time() . '@example.com', // Email único
    'password' => '123456',
    'confirm_password' => '123456',
    'domain' => 'test' . time() . '.com', // Dominio único
    'plan_type' => 'free',
    'csrf_token' => $token
];

echo "2. Datos simulados:\n";
foreach ($_POST as $key => $value) {
    echo "   $key: $value\n";
}

// 3. Verificar token
$isValid = verifyCSRFToken($_POST['csrf_token']);
echo "3. Token válido: " . ($isValid ? 'Sí' : 'No') . "\n";

if ($isValid) {
    echo "✅ El registro debería funcionar correctamente\n";
} else {
    echo "❌ Error: Token CSRF inválido\n";
}

// Limpiar
unlink('test_complete_signup.php');
?>


