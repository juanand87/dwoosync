<?php
/**
 * Script para verificar y actualizar períodos de gracia
 * Se puede ejecutar manualmente o con cron job
 */

require_once '../config/database.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=discogs_api', 'root', 'mayorista2024');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener suscriptores con ciclos activos que pueden estar en gracia
    $stmt = $pdo->prepare("
        SELECT sc.*, s.status as subscriber_status, s.plan_type
        FROM billing_cycles sc
        JOIN subscribers s ON bc.subscriber_id = s.id
        WHERE bc.is_active = 1
    ");
    $stmt->execute();
    $cycles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $today = new DateTime();
    $updated_count = 0;
    
    foreach ($cycles as $cycle) {
        $cycle_end = new DateTime($cycle['cycle_end_date']);
        $grace_end = clone $cycle_end;
        $grace_end->add(new DateInterval('P3D')); // +3 días
        
        $subscriber_id = $cycle['subscriber_id'];
        $plan_type = $cycle['plan_type'];
        
        if ($today > $grace_end) {
            // Período de gracia expirado - desactivar suscripción
            $pdo->beginTransaction();
            
            // Desactivar ciclo
            $stmt = $pdo->prepare("UPDATE billing_cycles SET is_active = 0 WHERE id = ?");
            $stmt->execute([$cycle['id']]);
            
            // Desactivar suscriptor si no es plan gratuito
            if ($plan_type !== 'free') {
                $stmt = $pdo->prepare("UPDATE subscribers SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$subscriber_id]);
                
                // Desactivar licencia
                $stmt = $pdo->prepare("UPDATE licenses SET status = 'inactive', updated_at = NOW() WHERE subscriber_id = ?");
                $stmt->execute([$subscriber_id]);
            }
            
            $pdo->commit();
            $updated_count++;
            
            error_log("[Grace Period] Suscriptor $subscriber_id desactivado - período de gracia expirado");
            
        } elseif ($today > $cycle_end && $today <= $grace_end) {
            // En período de gracia - mantener activo pero registrar
            error_log("[Grace Period] Suscriptor $subscriber_id en período de gracia");
        }
    }
    
    echo "Proceso completado. $updated_count suscriptores actualizados.\n";
    
} catch (Exception $e) {
    error_log("[Grace Period] Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>

