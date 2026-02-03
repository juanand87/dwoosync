<?php
// Simular envío de formulario de registro
require_once 'subscribe/includes/config.php';
require_once 'subscribe/includes/functions.php';

// Iniciar sesión
startSecureSession();

// Generar token CSRF
$token = generateCSRFToken();
echo "Token generado: " . $token . "\n";

// Simular datos POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'first_name' => 'Juan',
    'last_name' => 'Pérez',
    'email' => 'test@example.com',
    'password' => '123456',
    'confirm_password' => '123456',
    'domain' => 'test.com',
    'plan_type' => 'free',
    'csrf_token' => $token
];

echo "Datos POST simulados:\n";
print_r($_POST);

// Verificar token
$isValid = verifyCSRFToken($_POST['csrf_token']);
echo "Token válido: " . ($isValid ? 'Sí' : 'No') . "\n";

// Limpiar
unlink('test_csrf.php');
unlink('test_signup_form.php');
?>


