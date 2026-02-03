<?php
// Probar generación de token CSRF
require_once 'subscribe/includes/config.php';
require_once 'subscribe/includes/functions.php';

// Iniciar sesión
startSecureSession();

echo "Sesión iniciada: " . (session_status() === PHP_SESSION_ACTIVE ? 'Sí' : 'No') . "\n";
echo "ID de sesión: " . session_id() . "\n";

// Generar token
$token = generateCSRFToken();
echo "Token generado: " . $token . "\n";

// Verificar token
$isValid = verifyCSRFToken($token);
echo "Token válido: " . ($isValid ? 'Sí' : 'No') . "\n";

// Mostrar datos de sesión
echo "Datos de sesión:\n";
print_r($_SESSION);
?>


