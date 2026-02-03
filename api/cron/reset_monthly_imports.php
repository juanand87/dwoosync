<?php
/**
 * Script para resetear contadores de importaciones mensualmente
 * Ejecutar con cron job: 0 0 1 * * php /path/to/reset_monthly_imports.php
 */

// Permitir ejecución desde línea de comandos
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_key'])) {
    die('Acceso no permitido');
}

// Verificar clave de cron (opcional, para seguridad)
$cronKey = $_GET['cron_key'] ?? '';
if (!empty($cronKey) && $cronKey !== 'reset_imports_2024') {
    die('Clave de cron inválida');
}

// Incluir configuración
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Logger.php';

$db = new Database();
$logger = new Logger();

try {
    $logger->info('Iniciando reset mensual de importaciones');
    
    // Obtener todos los suscriptores activos
    $subscribers = $db->fetchAll("
        SELECT s.id, s.email, s.plan_type, p.max_imports_per_month
        FROM subscribers s
        LEFT JOIN subscription_plans p ON s.plan_type = p.plan_id
        WHERE s.status = 'active'
    ");
    
    $resetCount = 0;
    $notificationsSent = 0;
    
    foreach ($subscribers as $subscriber) {
        // Obtener estadísticas del mes anterior
        $lastMonth = date('Y-m-01', strtotime('-1 month'));
        $thisMonth = date('Y-m-01');
        
        $stats = $db->fetch("
            SELECT 
                COUNT(*) as total_imports,
                SUM(products_imported) as total_products
            FROM import_tracking 
            WHERE subscriber_id = ? 
            AND created_at >= ? 
            AND created_at < ?
        ", [$subscriber['id'], $lastMonth, $thisMonth]);
        
        $totalImports = $stats['total_imports'] ?? 0;
        $totalProducts = $stats['total_products'] ?? 0;
        
        // Crear registro de reset
        $resetData = [
            'subscriber_id' => $subscriber['id'],
            'license_key' => 'SYSTEM_RESET',
            'import_type' => 'system_reset',
            'products_imported' => 0,
            'import_status' => 'success',
            'import_data' => json_encode([
                'reset_type' => 'monthly',
                'previous_month_imports' => $totalImports,
                'previous_month_products' => $totalProducts,
                'reset_date' => date('Y-m-d H:i:s')
            ])
        ];
        
        $db->insert('import_tracking', $resetData);
        
        // Enviar notificación por email (opcional)
        if ($totalImports > 0) {
            sendResetNotification($subscriber, $totalImports, $totalProducts);
            $notificationsSent++;
        }
        
        $resetCount++;
        
        $logger->info('Reset completado para suscriptor', [
            'subscriber_id' => $subscriber['id'],
            'email' => $subscriber['email'],
            'previous_imports' => $totalImports,
            'previous_products' => $totalProducts
        ]);
    }
    
    $logger->info('Reset mensual completado', [
        'subscribers_processed' => $resetCount,
        'notifications_sent' => $notificationsSent
    ]);
    
    echo "Reset mensual completado:\n";
    echo "- Suscriptores procesados: {$resetCount}\n";
    echo "- Notificaciones enviadas: {$notificationsSent}\n";
    echo "- Fecha: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    $logger->error('Error en reset mensual', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo "Error en reset mensual: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Enviar notificación de reset por email
 */
function sendResetNotification($subscriber, $totalImports, $totalProducts) {
    // En un entorno real, aquí se enviaría un email
    // Por ahora solo loggeamos la notificación
    
    $logger = new Logger();
    $logger->info('Notificación de reset enviada', [
        'subscriber_id' => $subscriber['id'],
        'email' => $subscriber['email'],
        'total_imports' => $totalImports,
        'total_products' => $totalProducts
    ]);
    
    // Aquí iría el código para enviar email real:
    /*
    $subject = "Reset Mensual de Importaciones - DiscogsSync";
    $message = "
    Hola {$subscriber['first_name']},
    
    Tu contador de importaciones ha sido reseteado para el nuevo mes.
    
    Resumen del mes anterior:
    - Importaciones realizadas: {$totalImports}
    - Productos importados: {$totalProducts}
    
    Tu nuevo límite para este mes es: {$subscriber['max_imports_per_month']} importaciones.
    
    ¡Gracias por usar DiscogsSync!
    ";
    
    mail($subscriber['email'], $subject, $message);
    */
}






