<?php
/**
 * Versión de debug intensivo para process_free_plan.php
 */

// Forzar mostrar todos los errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Buffer de salida para capturar errores
ob_start();

echo "<!DOCTYPE html><html><head><title>Debug Process Free Plan</title></head><body>";
echo "<h2>🔍 Debug Process Free Plan</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>";

try {
    echo "1. ✅ Inicio del archivo<br>";
    flush();
    ob_flush();
    
    echo "2. Cargando config...<br>";
    require_once '../includes/config.php';
    echo "2. ✅ Config cargado<br>";
    flush();
    ob_flush();
    
    echo "3. Cargando functions...<br>";
    require_once '../includes/functions.php';
    echo "3. ✅ Functions cargado<br>";
    flush();
    ob_flush();
    
    echo "4. Iniciando sesión...<br>";
    startSecureSession();
    echo "4. ✅ Sesión iniciada<br>";
    flush();
    ob_flush();
    
    echo "5. Detectando idioma...<br>";
    $currentLang = $_SESSION['selected_language'] ?? 'es';
    $isEnglish = ($currentLang === 'en');
    echo "5. ✅ Idioma: $currentLang<br>";
    flush();
    ob_flush();
    
    echo "6. Verificando login...<br>";
    if (!isLoggedIn()) {
        echo "6. ❌ Usuario NO logueado, redirigiendo<br>";
        echo "<a href='login.php'>Ir a Login</a>";
        echo "</div></body></html>";
        exit;
    }
    echo "6. ✅ Usuario logueado<br>";
    flush();
    ob_flush();
    
    echo "7. Obteniendo datos de sesión...<br>";
    $subscriber_id = $_SESSION['subscriber_id'] ?? null;
    $license_key = $_SESSION['license_key'] ?? null;
    echo "7. ✅ Subscriber ID: " . ($subscriber_id ?? 'NULL') . "<br>";
    echo "7. ✅ License Key: " . ($license_key ?? 'NULL') . "<br>";
    flush();
    ob_flush();
    
    if (!$subscriber_id) {
        echo "8. ❌ No hay subscriber_id, redirigiendo<br>";
        echo "<a href='login.php'>Ir a Login</a>";
        echo "</div></body></html>";
        exit;
    }
    echo "8. ✅ Subscriber ID válido<br>";
    flush();
    ob_flush();
    
    echo "9. Conectando a BD...<br>";
    $db = getDatabase();
    echo "9. ✅ Conexión a BD exitosa<br>";
    flush();
    ob_flush();
    
    echo "10. Iniciando transacción...<br>";
    $db->beginTransaction();
    echo "10. ✅ Transacción iniciada<br>";
    flush();
    ob_flush();
    
    echo "11. Actualizando suscriptor...<br>";
    $stmt = $db->prepare("UPDATE subscribers SET status = 'active', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$subscriber_id]);
    echo "11. ✅ Suscriptor actualizado<br>";
    flush();
    ob_flush();
    
    echo "12. Actualizando licencia (si existe)...<br>";
    if ($license_key) {
        $stmt = $db->prepare("UPDATE licenses SET status = 'active', usage_limit = 10, updated_at = NOW() WHERE license_key = ?");
        $stmt->execute([$license_key]);
        echo "12. ✅ Licencia actualizada<br>";
    } else {
        echo "12. ⚠️ No hay license_key para actualizar<br>";
    }
    flush();
    ob_flush();
    
    echo "13. Calculando fechas...<br>";
    $cycle_start_date = date('Y-m-d H:i:s');
    $cycle_end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
    echo "13. ✅ Fechas: $cycle_start_date a $cycle_end_date<br>";
    flush();
    ob_flush();
    
    echo "14. Desactivando ciclos anteriores...<br>";
    $stmt = $db->prepare("UPDATE billing_cycles SET is_active = 0 WHERE subscriber_id = ?");
    $stmt->execute([$subscriber_id]);
    echo "14. ✅ Ciclos anteriores desactivados<br>";
    flush();
    ob_flush();
    
    echo "15. Generando nueva license_key...<br>";
    $new_license_key = 'FREE-' . strtoupper(substr(md5(uniqid($subscriber_id, true)), 0, 12));
    echo "15. ✅ Nueva license_key: $new_license_key<br>";
    flush();
    ob_flush();
    
    echo "16. Creando ciclo de facturación...<br>";
    $stmt = $db->prepare("
        INSERT INTO billing_cycles 
        (subscriber_id, plan_type, amount, license_key, cycle_start_date, cycle_end_date, due_date, status, is_active, created_at) 
        VALUES (?, 'free', 0.00, ?, ?, ?, ?, 'paid', 1, NOW())
    ");
    $stmt->execute([$subscriber_id, $new_license_key, $cycle_start_date, $cycle_end_date, $cycle_end_date]);
    echo "16. ✅ Ciclo de facturación creado<br>";
    flush();
    ob_flush();
    
    echo "17. Confirmando transacción...<br>";
    $db->commit();
    echo "17. ✅ Transacción confirmada<br>";
    flush();
    ob_flush();
    
    echo "18. ✅ PROCESO COMPLETADO EXITOSAMENTE<br>";
    echo "<br><strong>🎉 Todo funcionó correctamente</strong><br>";
    echo "<a href='dashboard.php?success=account_activated' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Dashboard</a>";
    
} catch (Exception $e) {
    echo "<br>❌ ERROR: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<br>❌ ERROR FATAL: " . $e->getMessage() . "<br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</div></body></html>";
ob_end_flush();
?>