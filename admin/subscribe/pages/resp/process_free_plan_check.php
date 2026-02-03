<?php
/**
 * Version de process_free_plan que muestra debug sin redirigir
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/functions.php';

startSecureSession();

echo "<!DOCTYPE html><html><head><title>Process Free Plan Debug</title></head><body>";
echo "<h2>🔍 Process Free Plan - Step by Step</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

$currentLang = $_SESSION['selected_language'] ?? 'es';
$isEnglish = ($currentLang === 'en');

echo "1. ✅ Idioma detectado: $currentLang<br>";

// Verificar login
echo "2. Verificando isLoggedIn()...<br>";
$loggedIn = isLoggedIn();
echo "2. isLoggedIn() = " . ($loggedIn ? 'TRUE' : 'FALSE') . "<br>";

if (!$loggedIn) {
    echo "2. ❌ Usuario NO logueado<br>";
    echo "<a href='login.php?lang=$currentLang&error=not_logged_in'>🔗 Ir a Login</a><br>";
} else {
    echo "2. ✅ Usuario aparece como logueado<br>";
}

// Verificar datos de sesión
echo "3. Verificando datos de sesión...<br>";
$subscriber_id = $_SESSION['subscriber_id'] ?? null;
$license_key = $_SESSION['license_key'] ?? null;

echo "3. subscriber_id: " . ($subscriber_id ?? 'NULL') . "<br>";
echo "3. license_key: " . ($license_key ?? 'NULL') . "<br>";

if (!$subscriber_id) {
    echo "3. ❌ No hay subscriber_id en sesión<br>";
    echo "<strong>PROBLEMA IDENTIFICADO:</strong> La sesión está incompleta<br>";
    
    echo "<h3>📋 Soluciones:</h3>";
    echo "1. <a href='logout.php' style='background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Cerrar Sesión Completa</a><br><br>";
    echo "2. <a href='login.php' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Ir a Login</a><br><br>";
    echo "3. <a href='signup.php?plan=free' style='background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Registrar Usuario Nuevo</a><br><br>";
    
    echo "<h3>🔍 Debug adicional:</h3>";
    echo "Contenido completo de \$_SESSION:<br>";
    echo "<pre>";
    var_dump($_SESSION);
    echo "</pre>";
    
} else {
    echo "3. ✅ subscriber_id válido: $subscriber_id<br>";
    
    // Continuar con el proceso
    echo "4. Verificando que subscriber existe en BD...<br>";
    try {
        $db = getDatabase();
        $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
        $stmt->execute([$subscriber_id]);
        $subscriberData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subscriberData) {
            echo "4. ❌ Subscriber no existe en BD<br>";
            echo "<strong>PROBLEMA:</strong> subscriber_id en sesión pero no en BD<br>";
            echo "<a href='logout.php'>🔗 Cerrar Sesión y Empezar de Nuevo</a><br>";
        } else {
            echo "4. ✅ Subscriber existe: " . $subscriberData['email'] . "<br>";
            echo "5. ✅ TODO CORRECTO - El proceso debería funcionar<br>";
            echo "<a href='process_free_plan.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Procesar Plan Gratuito</a>";
        }
    } catch (Exception $e) {
        echo "4. ❌ Error verificando BD: " . $e->getMessage() . "<br>";
    }
}

echo "</div></body></html>";
?>