<?php
/**
 * Versión simplificada de process_free_plan.php para debug
 */

// Mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "INICIO DEL ARCHIVO<br>";

// Solo cargar config primero
try {
    echo "Cargando config...<br>";
    require_once '../includes/config.php';
    echo "Config cargado OK<br>";
} catch (Exception $e) {
    echo "ERROR en config: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "ERROR FATAL en config: " . $e->getMessage() . "<br>";
    exit;
}

// Cargar functions
try {
    echo "Cargando functions...<br>";
    require_once '../includes/functions.php';
    echo "Functions cargado OK<br>";
} catch (Exception $e) {
    echo "ERROR en functions: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "ERROR FATAL en functions: " . $e->getMessage() . "<br>";
    exit;
}

// Intentar iniciar sesión
try {
    echo "Iniciando sesión...<br>";
    startSecureSession();
    echo "Sesión iniciada OK<br>";
} catch (Exception $e) {
    echo "ERROR en sesión: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "ERROR FATAL en sesión: " . $e->getMessage() . "<br>";
    exit;
}

// Mostrar datos de sesión
echo "Datos de sesión:<br>";
echo "- selected_language: " . ($_SESSION['selected_language'] ?? 'NO SET') . "<br>";
echo "- subscriber_id: " . ($_SESSION['subscriber_id'] ?? 'NO SET') . "<br>";
echo "- license_key: " . ($_SESSION['license_key'] ?? 'NO SET') . "<br>";

// Verificar si está logueado
if (function_exists('isLoggedIn')) {
    $loggedIn = isLoggedIn();
    echo "- isLoggedIn: " . ($loggedIn ? 'SÍ' : 'NO') . "<br>";
} else {
    echo "- isLoggedIn: FUNCIÓN NO EXISTE<br>";
}

echo "<br>🎯 PROCESO BÁSICO COMPLETADO<br>";
echo "<a href='../pages/dashboard.php'>Ir al Dashboard</a>";
?>