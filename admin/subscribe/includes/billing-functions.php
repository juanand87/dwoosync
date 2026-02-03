<?php
/**
 * Funciones para manejo de facturación
 */

/**
 * Activar un ciclo de facturación pagado
 * @param int $billing_cycle_id ID del ciclo de facturación
 * @return array Resultado de la operación
 */
function activatePaidBillingCycle($billing_cycle_id) {
    $pdo = null;
    try {
        $pdo = getDatabase();
        $pdo->beginTransaction();
        
        // Obtener información del ciclo de facturación
        $cycle_stmt = $pdo->prepare("
            SELECT bc.*, s.email, s.first_name, s.last_name 
            FROM billing_cycles bc
            JOIN subscribers s ON bc.subscriber_id = s.id
            WHERE bc.id = ? AND (bc.status = 'pending' OR bc.status = 'paid')
        ");
        $cycle_stmt->execute([$billing_cycle_id]);
        $cycle = $cycle_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cycle) {
            throw new Exception('Ciclo de facturación no encontrado o ya procesado');
        }
        
        // Si ya está activo, no hacer nada
        if ($cycle['is_active'] == 1) {
            return [
                'success' => true,
                'message' => 'El ciclo de facturación ya está activo',
                'billing_cycle_id' => $billing_cycle_id,
                'subscriber_id' => $cycle['subscriber_id'],
                'subscriber_email' => $cycle['email'],
                'subscriber_name' => $cycle['first_name'] . ' ' . $cycle['last_name']
            ];
        }
        
        $subscriber_id = $cycle['subscriber_id'];
        
        // Desactivar otros ciclos activos del mismo suscriptor
        $deactivate_stmt = $pdo->prepare("
            UPDATE billing_cycles 
            SET is_active = 0 
            WHERE subscriber_id = ? AND is_active = 1
        ");
        $deactivate_stmt->execute([$subscriber_id]);
        
        // Activar el ciclo actual
        $activate_cycle_stmt = $pdo->prepare("
            UPDATE billing_cycles 
            SET is_active = 1, status = 'paid', updated_at = NOW()
            WHERE id = ?
        ");
        $activate_cycle_stmt->execute([$billing_cycle_id]);
        
        // Activar la licencia del suscriptor y actualizar fecha de vencimiento
        $activate_license_stmt = $pdo->prepare("
            UPDATE licenses 
            SET status = 'active', expires_at = ?, updated_at = NOW()
            WHERE subscriber_id = ?
        ");
        $activate_license_stmt->execute([$cycle['cycle_end_date'], $subscriber_id]);
        
        // Actualizar estado y plan del suscriptor
        $activate_subscriber_stmt = $pdo->prepare("
            UPDATE subscribers 
            SET status = 'active', plan_type = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $activate_subscriber_stmt->execute([$cycle['plan_type'], $subscriber_id]);
        
        $pdo->commit();
        
        // Log de la operación
        error_log("[BILLING_ACTIVATION] Ciclo $billing_cycle_id activado para suscriptor $subscriber_id ({$cycle['email']})");
        
        return [
            'success' => true,
            'message' => 'Ciclo de facturación activado correctamente',
            'billing_cycle_id' => $billing_cycle_id,
            'subscriber_id' => $subscriber_id,
            'subscriber_email' => $cycle['email'],
            'subscriber_name' => $cycle['first_name'] . ' ' . $cycle['last_name']
        ];
        
    } catch (Exception $e) {
        if ($pdo) {
            $pdo->rollback();
        }
        
        error_log("[BILLING_ACTIVATION_ERROR] Error activando ciclo $billing_cycle_id: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Error activando ciclo de facturación: ' . $e->getMessage()
        ];
    } finally {
        if ($pdo) {
            $pdo = null;
        }
    }
}

/**
 * Obtener facturas pendientes de activación
 * @return array Lista de facturas pendientes
 */
function getPendingBillingCycles() {
    $pdo = null;
    try {
        $pdo = getDatabase();
        
        $stmt = $pdo->prepare("
            SELECT 
                bc.id,
                bc.subscriber_id,
                bc.plan_type,
                bc.invoice_number,
                bc.amount,
                bc.currency,
                bc.created_at,
                s.email,
                s.first_name,
                s.last_name,
                s.company
            FROM billing_cycles bc
            JOIN subscribers s ON bc.subscriber_id = s.id
            WHERE bc.status = 'pending'
            ORDER BY bc.created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("[BILLING_ERROR] Error obteniendo facturas pendientes: " . $e->getMessage());
        return [];
    } finally {
        if ($pdo) {
            $pdo = null;
        }
    }
}

/**
 * Obtener estadísticas de facturación
 * @return array Estadísticas
 */
function getBillingStats() {
    $pdo = null;
    try {
        $pdo = getDatabase();
        
        $stats = [];
        
        // Total de facturas por estado
        $status_stmt = $pdo->query("
            SELECT status, COUNT(*) as count 
            FROM billing_cycles 
            GROUP BY status
        ");
        $status_data = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($status_data as $row) {
            $stats[$row['status']] = $row['count'];
        }
        
        // Facturas activas
        $active_stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM billing_cycles 
            WHERE is_active = 1
        ");
        $stats['active'] = $active_stmt->fetchColumn();
        
        // Ingresos totales
        $revenue_stmt = $pdo->query("
            SELECT SUM(amount) as total 
            FROM billing_cycles 
            WHERE status = 'paid'
        ");
        $stats['total_revenue'] = $revenue_stmt->fetchColumn() ?: 0;
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("[BILLING_ERROR] Error obteniendo estadísticas: " . $e->getMessage());
        return [];
    } finally {
        if ($pdo) {
            $pdo = null;
        }
    }
}
?>
