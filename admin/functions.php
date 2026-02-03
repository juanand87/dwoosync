<?php
/**
 * Funciones auxiliares para el panel de administración
 */

/**
 * Obtener límite de uso por tipo de plan
 */
function getUsageLimitByPlan($planType) {
    $limits = [
        'free' => 10,        // 10 importaciones al mes
        'premium' => -1,     // Ilimitadas
        'enterprise' => -1   // Ilimitadas
    ];
    
    return $limits[$planType] ?? 10;
}

/**
 * Actualizar límite de uso de una licencia basado en el plan
 */
function updateLicenseUsageLimit($subscriberId, $planType) {
    $db = Database::getInstance();
    
    $usageLimit = getUsageLimitByPlan($planType);
    
    // Debug: Log de la actualización
    error_log("Actualizando límite de uso - Subscriber ID: $subscriberId, Plan: $planType, Nuevo límite: $usageLimit");
    
    try {
        // Verificar si existe la licencia antes de actualizar
        $licenseExists = $db->query("SELECT id FROM licenses WHERE subscriber_id = :subscriber_id", [
            'subscriber_id' => $subscriberId
        ])->fetch();
        
        if (!$licenseExists) {
            error_log("No se encontró licencia para el suscriptor $subscriberId");
            return false;
        }
        
        $stmt = $db->query("
            UPDATE licenses 
            SET usage_limit = :usage_limit, updated_at = NOW()
            WHERE subscriber_id = :subscriber_id
        ", [
            'usage_limit' => $usageLimit,
            'subscriber_id' => $subscriberId
        ]);
        
        $rowsAffected = $stmt->rowCount();
        error_log("Filas afectadas en la actualización: $rowsAffected");
        
        return $rowsAffected > 0;
    } catch (Exception $e) {
        error_log("Error actualizando límite de uso: " . $e->getMessage());
        return false;
    }
}
?>
