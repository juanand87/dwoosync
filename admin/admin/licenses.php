<?php
/**
 * Página de gestión de licencias
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración y clases
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/LicenseManager.php';
require_once __DIR__ . '/../classes/CacheManager.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/AdminAuth.php';

// Verificar autenticación de administrador
$auth = new AdminAuth();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();

$db = Database::getInstance();
$licenseManager = new LicenseManager();

// Procesar acciones
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("DEBUG - Procesando POST con acción: $action");
    switch ($action) {
        case 'suspend_license':
            $licenseKey = $_POST['license_key'] ?? '';
            if ($licenseKey) {
                $result = $db->query("UPDATE licenses SET status = 'inactive' WHERE license_key = :key", ['key' => $licenseKey]);
                if ($result->rowCount() > 0) {
                    $message = 'Licencia suspendida exitosamente';
                } else {
                    $error = 'Licencia no encontrada';
                }
            }
            break;
            
        case 'activate_license':
            $licenseKey = $_POST['license_key'] ?? '';
            if ($licenseKey) {
                $result = $db->query("UPDATE licenses SET status = 'active' WHERE license_key = :key", ['key' => $licenseKey]);
                if ($result->rowCount() > 0) {
                    $message = 'Licencia activada exitosamente';
                } else {
                    $error = 'Licencia no encontrada';
                }
            }
            break;
            
        case 'revoke_license':
            $licenseKey = $_POST['license_key'] ?? '';
            if ($licenseKey) {
                $result = $db->query("UPDATE licenses SET status = 'revoked' WHERE license_key = :key", ['key' => $licenseKey]);
                if ($result->rowCount() > 0) {
                    $message = 'Licencia revocada exitosamente';
                } else {
                    $error = 'Licencia no encontrada';
                }
            }
            break;
            
        case 'create_subscription_cycle':
            $subscriber_id = (int)($_POST['subscriber_id'] ?? 0);
            $cycle_plan_type = $_POST['cycle_plan_type'] ?? 'free';
            $cycle_start_date = $_POST['cycle_start_date'] ?? '';
            $cycle_end_date = $_POST['cycle_end_date'] ?? '';
            $invoice_due_date = $_POST['invoice_due_date'] ?? '';
            
            // Debug: Mostrar datos recibidos
            error_log("DEBUG - Datos recibidos: subscriber_id=$subscriber_id, start=$cycle_start_date, end=$cycle_end_date, due=$invoice_due_date");
            error_log("DEBUG - POST completo: " . print_r($_POST, true));
            
            if ($subscriber_id && $cycle_start_date && $cycle_end_date && $invoice_due_date) {
                try {
                    // Validar fechas
                    $start = new DateTime($cycle_start_date);
                    $end = new DateTime($cycle_end_date);
                    $due = new DateTime($invoice_due_date);
                    
                    if ($end <= $start) {
                        $error = 'La fecha de fin debe ser posterior a la fecha de inicio';
                        break;
                    }
                    
                    // Obtener información del suscriptor
                    $subscriber = $db->query("SELECT * FROM subscribers WHERE id = $subscriber_id")->fetch();
                    
                    if ($subscriber) {
                        // Verificar si ya existe un ciclo pendiente para este suscriptor
                        $existing_cycle = $db->query("
                            SELECT id, status, created_at 
                            FROM billing_cycles 
                            WHERE subscriber_id = ? AND status = 'pending' 
                            ORDER BY created_at DESC 
                            LIMIT 1
                        ", [$subscriber_id])->fetch();
                        
                        if ($existing_cycle) {
                            error_log("DEBUG - Ya existe un ciclo pendiente - ID: {$existing_cycle['id']}, cancelando...");
                            
                            // Cancelar el ciclo anterior pendiente
                            $cancel_result = $db->query("
                                UPDATE billing_cycles 
                                SET status = 'cancelled', updated_at = NOW()
                                WHERE subscriber_id = ? AND status = 'pending'
                            ", [$subscriber_id]);
                            
                            if ($cancel_result) {
                                error_log("DEBUG - Ciclo anterior cancelado exitosamente");
                            } else {
                                error_log("DEBUG - Error cancelando ciclo anterior");
                            }
                        }
                        
                        // Obtener precio del plan seleccionado en el modal
                        $plan = $db->query("SELECT price FROM subscription_plans WHERE plan_type = '$cycle_plan_type'")->fetch();
                        $plan_price = $plan ? $plan['price'] : 0;
                        
                        // Crear número de factura
                        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($subscriber_id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(time(), 4, '0', STR_PAD_LEFT);
                        error_log("DEBUG - Creando ciclo de facturación con número: $invoice_number");
                        
                        // Obtener license_key del suscriptor
                        $license = $db->query("SELECT license_key FROM licenses WHERE subscriber_id = $subscriber_id LIMIT 1")->fetch();
                        $license_key = $license ? $license['license_key'] : 'TEMP-' . $subscriber_id;
                        
                        // Crear ciclo de facturación (incluye toda la información de factura)
                        $billing_cycle_id = $db->insert('billing_cycles', [
                            'subscriber_id' => $subscriber_id,
                            'plan_type' => $cycle_plan_type,
                            'license_key' => $license_key,
                            'cycle_start_date' => $cycle_start_date,
                            'cycle_end_date' => $cycle_end_date,
                            'is_active' => false, // Cambiado a false para que sea pendiente
                            'invoice_number' => $invoice_number,
                            'amount' => $plan_price,
                            'currency' => 'USD',
                            'status' => 'pending',
                            'due_date' => $invoice_due_date,
                            'sync_count' => 0,
                            'api_calls_count' => 0,
                            'products_synced' => 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        error_log("DEBUG - Ciclo de facturación creado con ID: $billing_cycle_id");
                        
                        $plan_names = ['free' => 'Gratuito', 'premium' => 'Premium', 'enterprise' => 'Enterprise'];
                        $plan_name = $plan_names[$cycle_plan_type] ?? ucfirst($cycle_plan_type);
                        
                        $replacement_message = $existing_cycle ? 
                            " (ciclo anterior cancelado y reemplazado)" : 
                            "";
                        
                        $message = "Ciclo de facturación creado exitosamente para {$subscriber['first_name']} {$subscriber['last_name']} con plan {$plan_name} (Del {$cycle_start_date} al {$cycle_end_date}){$replacement_message}";
                    } else {
                        $error = 'Suscriptor no encontrado';
                    }
                } catch (Exception $e) {
                    $error = 'Error al crear ciclo: ' . $e->getMessage();
                }
            } else {
                $error = 'Faltan datos requeridos para crear el ciclo';
            }
            break;
            
        case 'reset_free_licenses':
            try {
                // Obtener licencias gratuitas vencidas
                $free_licenses = $db->query("
                    SELECT l.id, l.license_key, l.expires_at, l.subscriber_id, s.first_name, s.last_name, s.email
                    FROM licenses l
                    JOIN subscribers s ON l.subscriber_id = s.id
                    WHERE s.plan_type = 'free' 
                    AND l.expires_at <= NOW()
                    ORDER BY l.expires_at ASC
                ")->fetchAll();
                
                $reset_count = 0;
                $new_expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                foreach ($free_licenses as $license) {
                    // Reiniciar contador de licencia y extender fecha de vencimiento
                    $license_result = $db->query("
                        UPDATE licenses 
                        SET usage_count = 0, expires_at = ?, updated_at = NOW()
                        WHERE id = ?
                    ", [$new_expiry, $license['id']]);
                    
                    if ($license_result) {
                        // También reiniciar sync_count y actualizar fechas del ciclo de facturación activo
                        $cycle_result = $db->query("
                            UPDATE billing_cycles 
                            SET sync_count = 0, cycle_end_date = ?, due_date = ?, updated_at = NOW()
                            WHERE subscriber_id = ? AND is_active = 1
                        ", [$new_expiry, $new_expiry, $license['subscriber_id']]);
                        
                        $reset_count++;
                        error_log("DEBUG - Licencia gratuita reiniciada: {$license['license_key']} para {$license['email']} (usage_count, sync_count, cycle_end_date y due_date actualizados)");
                    }
                }
                
                if ($reset_count > 0) {
                    $message = "Se reiniciaron $reset_count licencias gratuitas vencidas. Se reiniciaron los contadores de uso y sincronización. Nuevas fechas de vencimiento: " . date('d/m/Y', strtotime($new_expiry));
                } else {
                    $message = "No hay licencias gratuitas vencidas para reiniciar";
                }
                
            } catch (Exception $e) {
                $error = 'Error al reiniciar licencias gratuitas: ' . $e->getMessage();
            }
            break;
    }
}

// Obtener filtros
$status = $_GET['status'] ?? 'all';
$planType = $_GET['plan_type'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir consulta
$whereConditions = [];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = "l.status = :status";
    $params['status'] = $status;
}

if ($planType !== 'all') {
    $whereConditions[] = "s.plan_type = :plan_type";
    $params['plan_type'] = $planType;
}

if (!empty($search)) {
    $whereConditions[] = "(s.email LIKE :search OR s.first_name LIKE :search OR s.last_name LIKE :search OR l.license_key LIKE :search)";
    $params['search'] = "%{$search}%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Obtener licencias con información detallada
$licenses = $db->fetchAll("
    SELECT 
        l.*,
        s.email, s.first_name, s.last_name, s.plan_type, s.status as subscriber_status,
        s.company, s.city, s.country,
        COUNT(al.id) as total_requests,
        MAX(al.created_at) as last_api_request,
        CASE 
            WHEN l.expires_at IS NULL THEN 'permanent'
            WHEN l.expires_at > NOW() THEN 'active'
            ELSE 'expired'
        END as license_status_detailed,
        CASE 
            WHEN l.usage_limit IS NULL THEN 'unlimited'
            WHEN l.usage_count >= l.usage_limit THEN 'limit_reached'
            WHEN l.usage_count >= (l.usage_limit * 0.8) THEN 'near_limit'
            ELSE 'normal'
        END as usage_status
    FROM licenses l
    JOIN subscribers s ON l.subscriber_id = s.id
    LEFT JOIN api_logs al ON s.id = al.subscriber_id
    {$whereClause}
    GROUP BY l.id
    ORDER BY l.created_at DESC
    LIMIT :limit OFFSET :offset
", array_merge($params, ['limit' => $limit, 'offset' => $offset]));

// Obtener total para paginación
$totalQuery = "
    SELECT COUNT(*) as count
    FROM licenses l
    JOIN subscribers s ON l.subscriber_id = s.id
    {$whereClause}
";
$total = $db->fetch($totalQuery, $params)['count'];
$totalPages = ceil($total / $limit);

// Obtener estadísticas detalladas
$stats = [
    'total_licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses")['count'],
    'active_licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses WHERE status = 'active'")['count'],
    'inactive_licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses WHERE status = 'inactive'")['count'],
    'expired_licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses WHERE status = 'expired'")['count'],
    'revoked_licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses WHERE status = 'revoked'")['count'],
    'permanent_licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses WHERE expires_at IS NULL")['count'],
    'near_limit_licenses' => $db->fetch("
        SELECT COUNT(*) as count FROM licenses 
        WHERE usage_count >= (usage_limit * 0.8) AND usage_limit IS NOT NULL
    ")['count'],
    'limit_reached_licenses' => $db->fetch("
        SELECT COUNT(*) as count FROM licenses 
        WHERE usage_count >= usage_limit AND usage_limit IS NOT NULL
    ")['count'],
    'unused_licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses WHERE usage_count = 0")['count'],
    'expired_free_licenses' => $db->fetch("
        SELECT COUNT(*) as count FROM licenses l
        JOIN subscribers s ON l.subscriber_id = s.id
        WHERE s.plan_type = 'free' AND l.expires_at <= NOW()
    ")['count']
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Licencias - Discogs API</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin-nav.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007cba; margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 0.9em; }
        .filters { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filter-group { display: inline-block; margin-right: 20px; margin-bottom: 10px; }
        .filter-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .filter-group select, .filter-group input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007cba; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #3b82f6; color: white; }
        .btn-sm { padding: 4px 8px; font-size: 0.8em; }
        .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h3 { margin-top: 0; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .status-active { color: green; font-weight: bold; }
        .status-inactive { color: red; font-weight: bold; }
        .status-expired { color: orange; font-weight: bold; }
        .status-revoked { color: darkred; font-weight: bold; }
        
        /* Estilos para licencias */
        .license-key { font-size: 0.9em; line-height: 1.4; }
        .user-info { font-size: 0.9em; line-height: 1.4; }
        .license-status { font-size: 0.9em; line-height: 1.4; }
        .usage-info { font-size: 0.9em; line-height: 1.4; }
        
        /* Estados de licencias */
        .license-status-active { color: #28a745; font-weight: bold; }
        .license-status-expired { color: #dc3545; font-weight: bold; background: #ffe6e6; padding: 2px 6px; border-radius: 3px; }
        .license-status-permanent { color: #007cba; font-weight: bold; background: #e6f3ff; padding: 2px 6px; border-radius: 3px; }
        
        /* Estados de suscriptores */
        .subscriber-status-active { color: #28a745; font-weight: bold; }
        .subscriber-status-suspended { color: #dc3545; font-weight: bold; }
        .subscriber-status-expired { color: #ffc107; font-weight: bold; }
        .subscriber-status-inactive { color: #6c757d; font-weight: bold; }
        
        /* Barras de uso */
        .usage-bar { width: 100px; background: #f0f0f0; height: 8px; border-radius: 4px; margin: 5px 0; overflow: hidden; }
        .usage-fill { height: 100%; border-radius: 4px; transition: width 0.3s ease; }
        .usage-normal { background: #28a745; }
        .usage-warning { background: #ffc107; }
        .usage-danger { background: #dc3545; }
        
        /* Estados de uso */
        .usage-status-normal { color: #28a745; }
        .usage-status-near_limit { color: #ffc107; font-weight: bold; }
        .usage-status-limit_reached { color: #dc3545; font-weight: bold; }
        .usage-status-unlimited { color: #007cba; }
        
        /* Colores de fondo para filas */
        tr.license-active { background-color: #e6ffe6; }
        tr.license-expired { background-color: #ffe6e6; }
        tr.license-permanent { background-color: #e6f3ff; }
        tr.usage-limit_reached { background-color: #fff5f5; }
        tr.usage-near_limit { background-color: #fffbf0; }
        
        /* Elementos especiales */
        .no-usage { color: #6c757d; font-style: italic; }
        .permanent-license { color: #007cba; font-weight: bold; }
        
        .pagination { text-align: center; margin-top: 20px; }
        .pagination a, .pagination span { display: inline-block; padding: 8px 12px; margin: 0 4px; border: 1px solid #ddd; text-decoration: none; color: #007cba; }
        .pagination .current { background: #007cba; color: white; }
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .back-btn { background: #6c757d; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin-bottom: 20px; display: inline-block; }
        .back-btn:hover { background: #5a6268; }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1>Gestión de Licencias</h1>
            </div>
            <div>
                <span>Bienvenido, <?php echo htmlspecialchars($currentUser['username']); ?></span>
                <a href="logout.php" class="btn btn-danger btn-sm" style="margin-left: 10px;">Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <?php include 'includes/admin-nav.php'; ?>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['total_licenses']); ?></div>
            <div class="stat-label">Total Licencias</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['active_licenses']); ?></div>
            <div class="stat-label">Activas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['permanent_licenses']); ?></div>
            <div class="stat-label">Permanentes</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['expired_licenses']); ?></div>
            <div class="stat-label">Expiradas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['near_limit_licenses']); ?></div>
            <div class="stat-label">Cerca del Límite</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['limit_reached_licenses']); ?></div>
            <div class="stat-label">Límite Alcanzado</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['unused_licenses']); ?></div>
            <div class="stat-label">Sin Uso</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['revoked_licenses']); ?></div>
            <div class="stat-label">Revocadas</div>
        </div>
        <div class="stat-card" style="<?php echo $stats['expired_free_licenses'] > 0 ? 'background: #fff3cd; border: 2px solid #ffc107;' : ''; ?>">
            <div class="stat-number" style="<?php echo $stats['expired_free_licenses'] > 0 ? 'color: #856404;' : ''; ?>"><?php echo number_format($stats['expired_free_licenses']); ?></div>
            <div class="stat-label" style="<?php echo $stats['expired_free_licenses'] > 0 ? 'color: #856404;' : ''; ?>">Gratuitas Vencidas</div>
        </div>
    </div>

    <div class="filters">
        <h3>Filtros</h3>
        <form method="GET">
            <div class="filter-group">
                <label for="status">Estado:</label>
                <select name="status" id="status">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Todos</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activas</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivas</option>
                    <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>Expiradas</option>
                    <option value="revoked" <?php echo $status === 'revoked' ? 'selected' : ''; ?>>Revocadas</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="plan_type">Plan:</label>
                <select name="plan_type" id="plan_type">
                    <option value="all" <?php echo $planType === 'all' ? 'selected' : ''; ?>>Todos</option>
                    <option value="free" <?php echo $planType === 'free' ? 'selected' : ''; ?>>Gratuito</option>
                    <option value="premium" <?php echo $planType === 'premium' ? 'selected' : ''; ?>>Premium</option>
                    <option value="enterprise" <?php echo $planType === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="search">Buscar:</label>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Email, nombre o licencia">
            </div>
            <div class="filter-group">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="licenses.php" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>
        
        <!-- Botón para reiniciar licencias gratuitas -->
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h4>Gestión de Licencias Gratuitas</h4>
            <p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">
                Reinicia el contador de uso de las licencias gratuitas vencidas y extiende su vencimiento por 30 días más.
            </p>
            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres reiniciar todas las licencias gratuitas vencidas? Esta acción reiniciará el contador de uso y extenderá el vencimiento por 30 días.')">
                <input type="hidden" name="action" value="reset_free_licenses">
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-sync-alt"></i> Reiniciar Licencias Gratuitas Vencidas
                </button>
            </form>
        </div>
    </div>

    <div class="section">
        <h3>Licencias (<?php echo number_format($total); ?> encontradas)</h3>
        <table>
            <thead>
                <tr>
                    <th>Licencia</th>
                    <th>Usuario</th>
                    <th>Plan</th>
                    <th>Estado</th>
                    <th>Uso</th>
                    <th>Último Uso</th>
                    <th>Expira</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($licenses as $license): ?>
                <tr class="license-<?php echo $license['license_status_detailed']; ?> usage-<?php echo $license['usage_status']; ?>">
                    <td>
                        <div class="license-key">
                            <strong><?php echo htmlspecialchars($license['license_key']); ?></strong><br>
                            <small>Dominio: <?php echo htmlspecialchars($license['domain']); ?></small><br>
                            <small>Creada: <?php echo date('d/m/Y', strtotime($license['created_at'])); ?></small>
                        </div>
                    </td>
                    <td>
                        <div class="user-info">
                            <strong><?php echo htmlspecialchars($license['first_name'] . ' ' . $license['last_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($license['email']); ?></small><br>
                            <small><?php echo htmlspecialchars($license['company']); ?></small><br>
                            <span class="subscriber-status-<?php echo $license['subscriber_status']; ?>">
                                <?php echo ucfirst($license['subscriber_status']); ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $license['plan_type']; ?>">
                            <?php echo ucfirst($license['plan_type']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="license-status">
                            <span class="license-status-<?php echo $license['license_status_detailed']; ?>">
                                <?php 
                                switch($license['license_status_detailed']) {
                                    case 'active': echo '✅ Activa'; break;
                                    case 'expired': echo '❌ Expirada'; break;
                                    case 'permanent': echo '♾️ Permanente'; break;
                                    default: echo '❓ Desconocido';
                                }
                                ?>
                            </span><br>
                            <span class="status-<?php echo $license['status']; ?>">
                                <?php echo ucfirst($license['status']); ?>
                            </span>
                        </div>
                    </td>
                    <td>
                        <div class="usage-info">
                            <strong><?php echo number_format($license['usage_count']); ?></strong> / 
                            <strong><?php echo $license['usage_limit'] ? number_format($license['usage_limit']) : '∞'; ?></strong>
                            <div class="usage-bar">
                                <?php 
                                $usagePercent = $license['usage_limit'] ? min(100, ($license['usage_count'] / $license['usage_limit']) * 100) : 0;
                                $barColor = 'normal';
                                if ($license['usage_status'] === 'limit_reached') $barColor = 'danger';
                                elseif ($license['usage_status'] === 'near_limit') $barColor = 'warning';
                                ?>
                                <div class="usage-fill usage-<?php echo $barColor; ?>" style="width: <?php echo $usagePercent; ?>%;"></div>
                            </div>
                            <small class="usage-status-<?php echo $license['usage_status']; ?>">
                                <?php 
                                switch($license['usage_status']) {
                                    case 'unlimited': echo 'Sin límite'; break;
                                    case 'normal': echo 'Normal'; break;
                                    case 'near_limit': echo '⚠️ Cerca del límite'; break;
                                    case 'limit_reached': echo '🚫 Límite alcanzado'; break;
                                }
                                ?>
                            </small>
                        </div>
                    </td>
                    <td>
                        <?php if ($license['last_api_request']): ?>
                            <strong><?php echo date('d/m/Y', strtotime($license['last_api_request'])); ?></strong><br>
                            <small><?php echo date('H:i', strtotime($license['last_api_request'])); ?></small>
                        <?php else: ?>
                            <span class="no-usage">Nunca usado</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($license['expires_at']): ?>
                            <?php 
                            $expires_at = new DateTime($license['expires_at']);
                            $now = new DateTime();
                            $is_expired = $expires_at < $now;
                            $date_color = $is_expired ? 'color: #dc3545; font-weight: bold;' : '';
                            ?>
                            <strong style="<?php echo $date_color; ?>"><?php echo date('d/m/Y', strtotime($license['expires_at'])); ?></strong><br>
                            <small style="<?php echo $date_color; ?>"><?php echo date('H:i', strtotime($license['expires_at'])); ?></small>
                        <?php else: ?>
                            <span class="permanent-license">Permanente</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($license['status'] === 'active'): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Suspender esta licencia?')">
                                <input type="hidden" name="license_key" value="<?php echo htmlspecialchars($license['license_key']); ?>">
                                <button type="submit" name="action" value="suspend_license" class="btn btn-warning btn-sm">Suspender</button>
                            </form>
                        <?php elseif ($license['status'] === 'inactive'): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Activar esta licencia?')">
                                <input type="hidden" name="license_key" value="<?php echo htmlspecialchars($license['license_key']); ?>">
                                <button type="submit" name="action" value="activate_license" class="btn btn-success btn-sm">Activar</button>
                            </form>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Revocar esta licencia? Esta acción no se puede deshacer.')">
                            <input type="hidden" name="license_key" value="<?php echo htmlspecialchars($license['license_key']); ?>">
                            <button type="submit" name="action" value="revoke_license" class="btn btn-danger btn-sm">Revocar</button>
                        </form>
                        
                        <button onclick="openCycleModal(<?php echo $license['subscriber_id']; ?>, '<?php echo htmlspecialchars($license['first_name'] . ' ' . $license['last_name']); ?>', '<?php echo $license['plan_type']; ?>')" class="btn btn-info btn-sm">
                            <i class="fas fa-plus-circle"></i> Crear Ciclo
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Anterior</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <style>
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
        .badge-free { background: #6c757d; color: white; }
        .badge-premium { background: #007cba; color: white; }
        .badge-enterprise { background: #28a745; color: white; }
        
        /* Estilos para el modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: none;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            background: #3b82f6;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .form-group {
            margin-bottom: 1rem;
            padding: 0 1.5rem;
        }
        
        .form-group:first-of-type {
            padding-top: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .modal-actions {
            padding: 1rem 1.5rem 1.5rem;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
    </style>

    <!-- Modal para crear ciclo de facturación -->
    <div id="cycleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Crear Ciclo de Suscripción</h3>
                <button class="close" onclick="closeCycleModal()">&times;</button>
            </div>
            <form id="cycleForm" method="POST" onsubmit="console.log('Formulario enviado:', new FormData(this)); console.log('Datos del formulario:', {
                action: this.action.value,
                subscriber_id: this.subscriber_id.value,
                cycle_start_date: this.cycle_start_date.value,
                cycle_end_date: this.cycle_end_date.value,
                invoice_due_date: this.invoice_due_date.value
            }); return true;">
                <input type="hidden" name="action" value="create_subscription_cycle">
                <input type="hidden" name="subscriber_id" id="modal_subscriber_id">
                
                <div class="form-group">
                    <label for="modal_user_name">Usuario:</label>
                    <input type="text" id="modal_user_name" readonly style="background: #f9fafb;">
                </div>
                
                <div class="form-group">
                    <label for="cycle_plan_type">Plan del Ciclo:</label>
                    <select id="cycle_plan_type" name="cycle_plan_type" class="form-control" onchange="updatePlanInfo(this.value)">
                        <option value="">Cargando planes...</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Resumen del Plan:</label>
                    <div id="cycle_plan_info" style="background: #f0f9ff; padding: 12px; border-radius: 6px; border: 1px solid #bfdbfe;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong id="plan_name">Plan Gratuito</strong>
                                <div style="font-size: 0.9em; color: #6b7280; margin-top: 4px;">
                                    <span id="plan_type_display">Gratuito</span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.5em; font-weight: bold; color: #059669;" id="plan_price">$0.00</div>
                                <div style="font-size: 0.8em; color: #6b7280;">USD</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="cycle_start_date">Fecha de Inicio:</label>
                    <input type="date" name="cycle_start_date" id="cycle_start_date" required>
                </div>
                
                <div class="form-group">
                    <label for="cycle_end_date">Fecha de Fin:</label>
                    <input type="date" name="cycle_end_date" id="cycle_end_date" required>
                </div>
                
                <div class="form-group">
                    <label for="invoice_due_date">Fecha de Vencimiento de Factura:</label>
                    <input type="date" name="invoice_due_date" id="invoice_due_date" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeCycleModal()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Ciclo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCycleModal(subscriberId, userName, planType) {
            console.log('Abriendo modal para suscriptor:', subscriberId, 'usuario:', userName, 'plan:', planType);
            
            document.getElementById('modal_subscriber_id').value = subscriberId;
            document.getElementById('modal_user_name').value = userName;
            
            // Cargar planes y luego configurar el modal
            loadSubscriptionPlans().then(plans => {
                // Actualizar el dropdown con los planes de la base de datos
                updatePlanDropdown(plans);
                
                // Seleccionar el plan actual del suscriptor por defecto
                document.getElementById('cycle_plan_type').value = planType;
                
                // Mostrar información del plan
                updatePlanInfo(planType);
            });
            
            // Obtener el último ciclo del suscriptor para calcular fechas
            fetch('get_last_cycle.php?subscriber_id=' + subscriberId)
                .then(response => response.json())
                .then(data => {
                    let startDate, endDate, dueDate;
                    
                    if (data.success && data.last_cycle) {
                        // Usar la fecha de fin del último ciclo como inicio del nuevo
                        const lastCycleEnd = new Date(data.last_cycle.cycle_end_date);
                        startDate = lastCycleEnd.toISOString().split('T')[0];
                        
                        // Calcular fecha de fin (30 días después)
                        const newEndDate = new Date(lastCycleEnd.getTime() + (30 * 24 * 60 * 60 * 1000));
                        endDate = newEndDate.toISOString().split('T')[0];
                        
                        // Fecha de vencimiento (7 días después del inicio)
                        const newDueDate = new Date(lastCycleEnd.getTime() + (7 * 24 * 60 * 60 * 1000));
                        dueDate = newDueDate.toISOString().split('T')[0];
                        
                        console.log('Último ciclo encontrado:', data.last_cycle);
                    } else {
                        // Si no hay último ciclo, usar fechas por defecto
                        const today = new Date();
                        startDate = today.toISOString().split('T')[0];
                        endDate = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0];
                        dueDate = new Date(today.getTime() + (7 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0];
                        
                        console.log('No se encontró último ciclo, usando fechas por defecto');
                    }
                    
                    // Establecer las fechas calculadas
                    document.getElementById('cycle_start_date').value = startDate;
                    document.getElementById('cycle_end_date').value = endDate;
                    document.getElementById('invoice_due_date').value = dueDate;
                    
                    document.getElementById('cycleModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error al obtener último ciclo:', error);
                    
                    // En caso de error, usar fechas por defecto
                    const today = new Date();
                    const startDate = today.toISOString().split('T')[0];
                    const endDate = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0];
                    const dueDate = new Date(today.getTime() + (7 * 24 * 60 * 60 * 1000)).toISOString().split('T')[0];
                    
                    document.getElementById('cycle_start_date').value = startDate;
                    document.getElementById('cycle_end_date').value = endDate;
                    document.getElementById('invoice_due_date').value = dueDate;
                    
                    document.getElementById('cycleModal').style.display = 'block';
                });
        }
        
        // Variable global para almacenar los planes
        let subscriptionPlans = {};
        
        // Cargar planes desde la base de datos
        function loadSubscriptionPlans() {
            return fetch('get_subscription_plans.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        subscriptionPlans = data.plans;
                        return data.plans;
                    } else {
                        console.error('Error cargando planes:', data.error);
                        return {};
                    }
                })
                .catch(error => {
                    console.error('Error de conexión:', error);
                    return {};
                });
        }
        
        function updatePlanDropdown(plans) {
            const dropdown = document.getElementById('cycle_plan_type');
            dropdown.innerHTML = ''; // Limpiar opciones existentes
            
            // Agregar opciones basadas en los planes de la base de datos
            Object.values(plans).forEach(plan => {
                const option = document.createElement('option');
                option.value = plan.plan_type;
                option.textContent = `${plan.plan_name} - $${plan.price}`;
                dropdown.appendChild(option);
            });
        }
        
        function updatePlanInfo(planType) {
            const plan = subscriptionPlans[planType] || {
                plan_name: 'Plan Desconocido',
                plan_type: planType,
                price: '0.00'
            };
            
            document.getElementById('plan_name').textContent = plan.plan_name;
            document.getElementById('plan_type_display').textContent = plan.plan_type.charAt(0).toUpperCase() + plan.plan_type.slice(1);
            document.getElementById('plan_price').textContent = '$' + plan.price;
        }
        
        function closeCycleModal() {
            console.log('Cerrando modal');
            document.getElementById('cycleModal').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('cycleModal');
            if (event.target === modal) {
                closeCycleModal();
            }
        }
        
        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCycleModal();
            }
        });
    </script>
</body>
</html>

