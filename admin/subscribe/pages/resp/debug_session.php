<?php
/**
 * Debug completo de la sesión
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/functions.php';

startSecureSession();

echo "<h2>🔍 Debug Completo de Sesión</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

echo "<h3>1. Contenido completo de \$_SESSION:</h3>";
echo "<pre>";
var_dump($_SESSION);
echo "</pre>";

echo "<h3>2. Verificaciones específicas:</h3>";
echo "- subscriber_id: " . ($_SESSION['subscriber_id'] ?? 'NO SET') . "<br>";
echo "- user_id: " . ($_SESSION['user_id'] ?? 'NO SET') . "<br>";
echo "- email: " . ($_SESSION['email'] ?? 'NO SET') . "<br>";
echo "- license_key: " . ($_SESSION['license_key'] ?? 'NO SET') . "<br>";
echo "- selected_language: " . ($_SESSION['selected_language'] ?? 'NO SET') . "<br>";

echo "<h3>3. Función isLoggedIn():</h3>";
if (function_exists('isLoggedIn')) {
    $loggedIn = isLoggedIn();
    echo "- isLoggedIn(): " . ($loggedIn ? 'TRUE' : 'FALSE') . "<br>";
    
    // Vamos a ver el código de isLoggedIn
    echo "<br><h3>4. Investigar por qué isLoggedIn() dice TRUE pero no hay subscriber_id:</h3>";
    
    // Las verificaciones típicas
    echo "- isset(\$_SESSION['user_id']): " . (isset($_SESSION['user_id']) ? 'TRUE' : 'FALSE') . "<br>";
    echo "- isset(\$_SESSION['subscriber_id']): " . (isset($_SESSION['subscriber_id']) ? 'TRUE' : 'FALSE') . "<br>";
    echo "- isset(\$_SESSION['email']): " . (isset($_SESSION['email']) ? 'TRUE' : 'FALSE') . "<br>";
    
} else {
    echo "- isLoggedIn(): FUNCIÓN NO EXISTE<br>";
}

echo "<h3>5. Posibles soluciones:</h3>";
echo "1. <a href='../pages/logout.php' style='background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Cerrar Sesión Completa</a><br><br>";
echo "2. <a href='../pages/login.php' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Ir a Login</a><br><br>";
echo "3. <a href='../pages/signup.php' style='background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Registrar Nuevo Usuario</a>";

echo "</div>";
?>