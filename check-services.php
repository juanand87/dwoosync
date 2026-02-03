<?php
/**
 * Verificar estado de servicios Apache y MySQL
 */

echo "<h1>Estado de Servicios</h1>";

// Función para ejecutar comandos
function runCommand($command, $description) {
    echo "<h3>$description</h3>";
    echo "<p>Comando: <code>$command</code></p>";
    
    $output = [];
    $return_code = 0;
    
    exec($command . " 2>&1", $output, $return_code);
    
    echo "<p><strong>Resultado (código $return_code):</strong></p>";
    echo "<pre>" . implode("\n", $output) . "</pre>";
    
    return $return_code === 0;
}

echo "<h2>1. Estado de Apache</h2>";
runCommand("tasklist /FI \"IMAGENAME eq httpd.exe\" /FO TABLE", "Procesos de Apache");

echo "<h2>2. Estado de MySQL</h2>";
runCommand("tasklist /FI \"IMAGENAME eq mysqld.exe\" /FO TABLE", "Procesos de MySQL");

echo "<h2>3. Conexiones de red</h2>";
runCommand("netstat -an | findstr :80", "Puerto 80 (Apache)");
runCommand("netstat -an | findstr :3306", "Puerto 3306 (MySQL)");

echo "<h2>4. Servicios de Windows</h2>";
runCommand("sc query Apache2.4", "Servicio Apache2.4");
runCommand("sc query MySQL", "Servicio MySQL");

echo "<h2>5. Prueba de conexión a base de datos</h2>";
try {
    require_once 'subscribe/includes/config.php';
    require_once 'subscribe/includes/functions.php';
    
    $pdo = getDatabase();
    echo "<p>✅ <strong>Conexión a BD exitosa</strong></p>";
    
    // Probar una consulta simple
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM subscribers");
    $result = $stmt->fetch();
    echo "<p>✅ <strong>Consulta exitosa. Total suscriptores:</strong> " . $result['total'] . "</p>";
    
    $pdo = null;
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Error de conexión:</strong> " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='subscribe/pages/test-direct-billing.php?plan=premium'>Probar creación directa de ciclo</a></p>";
echo "<p><a href='restart-apache-safe.php'>Reiniciar Apache</a></p>";
?>


