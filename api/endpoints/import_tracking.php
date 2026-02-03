<?php
/**
 * API Endpoints para tracking de importaciones
 */

// Endpoint para registrar importación
function trackImport($params) {
    global $db, $logger;
    
    try {
        // Validar parámetros requeridos
        $required = ['subscriber_id', 'license_key', 'import_type'];
        foreach ($required as $field) {
            if (!isset($params[$field]) || empty($params[$field])) {
                return [
                    'success' => false,
                    'error' => "Campo requerido: {$field}"
                ];
            }
        }
        
        // Verificar límites del suscriptor
        $limitCheck = checkImportLimits($params['subscriber_id'], $params['license_key']);
        if (!$limitCheck['can_import']) {
            return [
                'success' => false,
                'error' => 'Límite de importaciones excedido',
                'details' => $limitCheck
            ];
        }
        
        // Preparar datos para insertar
        $importData = [
            'subscriber_id' => $params['subscriber_id'],
            'license_key' => $params['license_key'],
            'import_type' => $params['import_type'],
            'products_imported' => $params['products_imported'] ?? 1,
            'discogs_master_id' => $params['discogs_master_id'] ?? null,
            'discogs_release_id' => $params['discogs_release_id'] ?? null,
            'import_status' => $params['import_status'] ?? 'success',
            'error_message' => $params['error_message'] ?? null,
            'import_data' => json_encode($params['import_data'] ?? [])
        ];
        
        // Insertar registro
        $importId = $db->insert('import_tracking', $importData);
        
        // Obtener estadísticas actualizadas
        $stats = getImportStats($params['subscriber_id']);
        
        $logger->info('Importación registrada', [
            'import_id' => $importId,
            'subscriber_id' => $params['subscriber_id'],
            'products_imported' => $importData['products_imported']
        ]);
        
        return [
            'success' => true,
            'import_id' => $importId,
            'stats' => $stats,
            'message' => 'Importación registrada correctamente'
        ];
        
    } catch (Exception $e) {
        $logger->error('Error al registrar importación', [
            'error' => $e->getMessage(),
            'params' => $params
        ]);
        
        return [
            'success' => false,
            'error' => 'Error interno del servidor'
        ];
    }
}

// Endpoint para obtener estadísticas de importaciones
function getImportStats($subscriberId) {
    global $db;
    
    try {
        // Obtener límite del plan del suscriptor
        $subscriber = $db->fetch("
            SELECT s.*, p.max_imports_per_month 
            FROM subscribers s 
            LEFT JOIN subscription_plans p ON s.plan_type = p.plan_id 
            WHERE s.id = ?
        ", [$subscriberId]);
        
        if (!$subscriber) {
            return [
                'success' => false,
                'error' => 'Suscriptor no encontrado'
            ];
        }
        
        $maxImports = $subscriber['max_imports_per_month'] ?? 200;
        
        // Obtener importaciones del mes actual
        $currentMonth = date('Y-m-01');
        $importsThisMonth = $db->fetch("
            SELECT 
                COUNT(*) as total_imports,
                SUM(products_imported) as total_products,
                SUM(CASE WHEN import_status = 'success' THEN products_imported ELSE 0 END) as successful_products,
                SUM(CASE WHEN import_status = 'failed' THEN 1 ELSE 0 END) as failed_imports
            FROM import_tracking 
            WHERE subscriber_id = ? 
            AND created_at >= ?
        ", [$subscriberId, $currentMonth]);
        
        $totalImports = $importsThisMonth['total_imports'] ?? 0;
        $totalProducts = $importsThisMonth['total_products'] ?? 0;
        $successfulProducts = $importsThisMonth['successful_products'] ?? 0;
        $failedImports = $importsThisMonth['failed_imports'] ?? 0;
        $remainingImports = max(0, $maxImports - $totalImports);
        
        // Obtener historial de últimos 30 días
        $history = $db->fetchAll("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as imports,
                SUM(products_imported) as products
            FROM import_tracking 
            WHERE subscriber_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 30
        ", [$subscriberId]);
        
        return [
            'success' => true,
            'stats' => [
                'max_imports' => $maxImports,
                'used_imports' => $totalImports,
                'remaining_imports' => $remainingImports,
                'total_products' => $totalProducts,
                'successful_products' => $successfulProducts,
                'failed_imports' => $failedImports,
                'success_rate' => $totalImports > 0 ? round(($totalImports - $failedImports) / $totalImports * 100, 1) : 0,
                'history' => $history
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error al obtener estadísticas'
        ];
    }
}

// Función para verificar límites de importación
function checkImportLimits($subscriberId, $licenseKey) {
    global $db;
    
    try {
        // Obtener límite del plan
        $subscriber = $db->fetch("
            SELECT s.*, p.max_imports_per_month 
            FROM subscribers s 
            LEFT JOIN subscription_plans p ON s.plan_type = p.plan_id 
            WHERE s.id = ? AND s.license_key = ?
        ", [$subscriberId, $licenseKey]);
        
        if (!$subscriber) {
            return [
                'can_import' => false,
                'error' => 'Suscriptor o licencia no válida'
            ];
        }
        
        $maxImports = $subscriber['max_imports_per_month'] ?? 200;
        
        // Contar importaciones del mes actual
        $currentMonth = date('Y-m-01');
        $usedImports = $db->fetch("
            SELECT COUNT(*) as count 
            FROM import_tracking 
            WHERE subscriber_id = ? 
            AND created_at >= ?
        ", [$subscriberId, $currentMonth]);
        
        $usedCount = $usedImports['count'] ?? 0;
        $canImport = $usedCount < $maxImports;
        
        return [
            'can_import' => $canImport,
            'max_imports' => $maxImports,
            'used_imports' => $usedCount,
            'remaining_imports' => max(0, $maxImports - $usedCount),
            'limit_reached' => !$canImport
        ];
        
    } catch (Exception $e) {
        return [
            'can_import' => false,
            'error' => 'Error al verificar límites'
        ];
    }
}

// Endpoint para obtener límites de importación
function getImportLimits($subscriberId) {
    return checkImportLimits($subscriberId, $_GET['license_key'] ?? '');
}




