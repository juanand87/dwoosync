<?php
/**
 * Test básico para identificar el problema
 */

// Test 1: PHP básico
echo "✅ PHP funciona<br>";

// Test 2: Mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "✅ Error reporting habilitado<br>";

// Test 3: Verificar directorio actual
echo "✅ Directorio actual: " . __DIR__ . "<br>";

// Test 4: Verificar si existe el archivo de config
$configPath = __DIR__ . '/../includes/config.php';
echo "✅ Ruta config: $configPath<br>";

if (file_exists($configPath)) {
    echo "✅ config.php existe<br>";
} else {
    echo "❌ config.php NO existe<br>";
    exit;
}

// Test 5: Intentar cargar config
echo "Intentando cargar config...<br>";
try {
    require_once $configPath;
    echo "✅ config.php cargado<br>";
} catch (Exception $e) {
    echo "❌ Error cargando config: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "❌ Error fatal cargando config: " . $e->getMessage() . "<br>";
    exit;
}

// Test 6: Verificar functions
$functionsPath = __DIR__ . '/../includes/functions.php';
echo "✅ Ruta functions: $functionsPath<br>";

if (file_exists($functionsPath)) {
    echo "✅ functions.php existe<br>";
} else {
    echo "❌ functions.php NO existe<br>";
    exit;
}

// Test 7: Intentar cargar functions
echo "Intentando cargar functions...<br>";
try {
    require_once $functionsPath;
    echo "✅ functions.php cargado<br>";
} catch (Exception $e) {
    echo "❌ Error cargando functions: " . $e->getMessage() . "<br>";
    exit;
} catch (Error $e) {
    echo "❌ Error fatal cargando functions: " . $e->getMessage() . "<br>";
    exit;
}

// Test 8: Verificar si startSecureSession existe
if (function_exists('startSecureSession')) {
    echo "✅ startSecureSession disponible<br>";
} else {
    echo "❌ startSecureSession NO disponible<br>";
}

// Test 9: Verificar si getDatabase existe
if (function_exists('getDatabase')) {
    echo "✅ getDatabase disponible<br>";
} else {
    echo "❌ getDatabase NO disponible<br>";
}

echo "<br><strong>🎯 Si llegas hasta aquí, los archivos básicos funcionan</strong><br>";
echo "<a href='process_free_plan.php'>Probar process_free_plan.php</a>";
?>