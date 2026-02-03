<?php
/**
 * Página de gestión de facturas
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración y clases
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AdminAuth.php';
require_once __DIR__ . '/../subscribe/includes/billing-functions.php';

// Verificar autenticación de administrador
$auth = new AdminAuth();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();

$db = Database::getInstance();

/**
 * Activar un ciclo de facturación pagado (versión para admin)
 */
function activateBillingCycleAdmin($billing_cycle_id, $pdo) {
    try {
        error_log("[ACTIVATE_BILLING] Iniciando activación para ciclo $billing_cycle_id");
        
        $pdo->beginTransaction();
        
        // Obtener información del ciclo de facturación específico que se está marcando como pagado
        $cycle_stmt = $pdo->prepare("
            SELECT bc.*, s.email, s.first_name, s.last_name 
            FROM billing_cycles bc
            JOIN subscribers s ON bc.subscriber_id = s.id
            WHERE bc.id = ? AND bc.status = 'paid'
        ");
        $cycle_stmt->execute([$billing_cycle_id]);
        $cycle = $cycle_stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("[ACTIVATE_BILLING] Ciclo encontrado: " . json_encode($cycle));
        
        if (!$cycle) {
            throw new Exception('Ciclo de facturación no encontrado o ya procesado');
        }
        
        // Si ya está activo, no hacer nada
        if ($cycle['is_active'] == 1) {
            error_log("[ACTIVATE_BILLING] Ciclo ya está activo, retornando éxito");
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
        error_log("[ACTIVATE_BILLING] Subscriber ID: $subscriber_id");
        
        // Desactivar otros ciclos activos del mismo suscriptor
        error_log("[ACTIVATE_BILLING] Desactivando otros ciclos del suscriptor $subscriber_id");
        $deactivate_stmt = $pdo->prepare("
            UPDATE billing_cycles 
            SET is_active = 0 
            WHERE subscriber_id = ? AND is_active = 1
        ");
        $deactivate_result = $deactivate_stmt->execute([$subscriber_id]);
        error_log("[ACTIVATE_BILLING] Desactivación de otros ciclos: " . ($deactivate_result ? 'Éxito' : 'Error'));
        
        // Activar el ciclo actual
        error_log("[ACTIVATE_BILLING] Activando ciclo $billing_cycle_id");
        $activate_cycle_stmt = $pdo->prepare("
            UPDATE billing_cycles 
            SET is_active = 1, status = 'paid', updated_at = NOW()
            WHERE id = ?
        ");
        $activate_cycle_result = $activate_cycle_stmt->execute([$billing_cycle_id]);
        error_log("[ACTIVATE_BILLING] Activación de ciclo: " . ($activate_cycle_result ? 'Éxito' : 'Error') . ", Filas afectadas: " . $activate_cycle_stmt->rowCount());
        
        // Activar la licencia del suscriptor y actualizar fecha de vencimiento
        error_log("[ACTIVATE_BILLING] Activando licencia del suscriptor $subscriber_id con nueva fecha de vencimiento");
        $activate_license_stmt = $pdo->prepare("
            UPDATE licenses 
            SET status = 'active', expires_at = ?, updated_at = NOW()
            WHERE subscriber_id = ?
        ");
        $activate_license_result = $activate_license_stmt->execute([$cycle['cycle_end_date'], $subscriber_id]);
        error_log("[ACTIVATE_BILLING] Activación de licencia: " . ($activate_license_result ? 'Éxito' : 'Error') . ", Filas afectadas: " . $activate_license_stmt->rowCount());
        error_log("[ACTIVATE_BILLING] Nueva fecha de vencimiento de licencia: " . $cycle['cycle_end_date']);
        
        // Actualizar estado y plan del suscriptor
        error_log("[ACTIVATE_BILLING] Activando suscriptor $subscriber_id con plan {$cycle['plan_type']}");
        $activate_subscriber_stmt = $pdo->prepare("
            UPDATE subscribers 
            SET status = 'active', plan_type = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $activate_subscriber_result = $activate_subscriber_stmt->execute([$cycle['plan_type'], $subscriber_id]);
        error_log("[ACTIVATE_BILLING] Activación de suscriptor: " . ($activate_subscriber_result ? 'Éxito' : 'Error') . ", Filas afectadas: " . $activate_subscriber_stmt->rowCount());
        
        $pdo->commit();
        error_log("[ACTIVATE_BILLING] Transacción confirmada exitosamente");
        
        // Log de la operación
        error_log("[BILLING_ACTIVATION_ADMIN] Ciclo $billing_cycle_id activado para suscriptor $subscriber_id ({$cycle['email']})");
        
        return [
            'success' => true,
            'message' => 'Ciclo de facturación activado correctamente',
            'billing_cycle_id' => $billing_cycle_id,
            'subscriber_id' => $subscriber_id,
            'subscriber_email' => $cycle['email'],
            'subscriber_name' => $cycle['first_name'] . ' ' . $cycle['last_name']
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        
        error_log("[BILLING_ACTIVATION_ADMIN_ERROR] Error activando ciclo $billing_cycle_id: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Error activando ciclo de facturación: ' . $e->getMessage()
        ];
    }
}

// Procesar activación manual de factura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'activate_invoice') {
    try {
        $invoice_id = (int)($_POST['invoice_id'] ?? 0);
        
        if ($invoice_id <= 0) {
            throw new Exception('ID de factura inválido');
        }
        
        $activation_result = activateBillingCycleAdmin($invoice_id, $db->getConnection());
        
        // Limpiar cualquier output previo
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($activation_result);
        exit;
        
    } catch (Exception $e) {
        // Limpiar cualquier output previo
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Procesar actualización de factura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_invoice') {
    try {
        $invoice_id = (int)($_POST['invoice_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $cycle_start_date = $_POST['cycle_start_date'] ?? '';
        $cycle_end_date = $_POST['cycle_end_date'] ?? '';
        $due_date = $_POST['due_date'] ?? '';
        $paid_date = $_POST['paid_date'] ?? '';
        $sync_count = (int)($_POST['sync_count'] ?? 0);
        $api_calls_count = (int)($_POST['api_calls_count'] ?? 0);
        
        // Validar datos requeridos
        if ($invoice_id <= 0) {
            throw new Exception('ID de factura inválido');
        }
        
        if (empty($status)) {
            throw new Exception('El estado es requerido');
        }
        
        // Obtener el estado anterior de la factura para comparar
        $old_invoice_stmt = $db->getConnection()->prepare("
            SELECT status, subscriber_id FROM billing_cycles WHERE id = :invoice_id
        ");
        $old_invoice_stmt->execute(['invoice_id' => $invoice_id]);
        $old_invoice = $old_invoice_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$old_invoice) {
            throw new Exception('Factura no encontrada');
        }
        
        $old_status = $old_invoice['status'];
        $subscriber_id = $old_invoice['subscriber_id'];
        
        // Actualizar la factura
        $stmt = $db->getConnection()->prepare("
            UPDATE billing_cycles 
            SET 
                amount = :amount,
                status = :status,
                cycle_start_date = :cycle_start_date,
                cycle_end_date = :cycle_end_date,
                due_date = :due_date,
                paid_date = :paid_date,
                sync_count = :sync_count,
                api_calls_count = :api_calls_count,
                updated_at = NOW()
            WHERE id = :invoice_id
        ");
        
        $result = $stmt->execute([
            'amount' => $amount,
            'status' => $status,
            'cycle_start_date' => $cycle_start_date ?: null,
            'cycle_end_date' => $cycle_end_date ?: null,
            'due_date' => $due_date ?: null,
            'paid_date' => $paid_date ?: null,
            'sync_count' => $sync_count,
            'api_calls_count' => $api_calls_count,
            'invoice_id' => $invoice_id
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            $activation_message = '';
            
            error_log("[ADMIN_INVOICES] Factura $invoice_id actualizada. Estado anterior: $old_status, Estado nuevo: $status");
            
            // Si el estado cambió a 'paid' y antes no era 'paid', activar la cuenta
            if ($status === 'paid' && $old_status !== 'paid') {
                error_log("[ADMIN_INVOICES] Detectado cambio a 'paid' para factura $invoice_id, iniciando activación...");
                error_log("[ADMIN_INVOICES] Estado anterior: $old_status, Estado nuevo: $status");
                
                // Activar la cuenta directamente con la conexión de admin
                error_log("[ADMIN_INVOICES] Llamando a activateBillingCycleAdmin($invoice_id)...");
                $activation_result = activateBillingCycleAdmin($invoice_id, $db->getConnection());
                error_log("[ADMIN_INVOICES] Resultado de activación: " . json_encode($activation_result));
                
                if ($activation_result['success']) {
                    $activation_message = ' y cuenta activada';
                    error_log("[ADMIN_INVOICES] ✅ Cuenta activada exitosamente para factura $invoice_id: " . $activation_result['message']);
                } else {
                    // Log el error pero no fallar la actualización de la factura
                    error_log("[ADMIN_INVOICES] ❌ Error activando cuenta después de marcar como pagada: " . $activation_result['message']);
                    $activation_message = ' (error al activar cuenta: ' . $activation_result['message'] . ')';
                }
            } else {
                error_log("[ADMIN_INVOICES] No se requiere activación - Estado anterior: $old_status, Estado nuevo: $status");
                $activation_message = '';
            }
            
            // Limpiar cualquier output previo
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Obtener información del suscriptor y plan para el mensaje
            $subscriber_info = $db->fetch("
                SELECT s.first_name, s.last_name, bc.plan_type 
                FROM subscribers s 
                JOIN billing_cycles bc ON s.id = bc.subscriber_id 
                WHERE bc.id = :invoice_id
            ", ['invoice_id' => $invoice_id]);
            
            $plan_name = '';
            if ($subscriber_info) {
                switch($subscriber_info['plan_type']) {
                    case 'free': $plan_name = 'Gratuito'; break;
                    case 'premium': $plan_name = 'Premium'; break;
                    case 'enterprise': $plan_name = '+Spotify'; break;
                    default: $plan_name = ucfirst($subscriber_info['plan_type']);
                }
                $subscriber_name = $subscriber_info['first_name'] . ' ' . $subscriber_info['last_name'];
            } else {
                $plan_name = 'desconocido';
                $subscriber_name = 'desconocido';
            }
            
            // Construir mensaje base
            $base_message = 'Factura actualizada exitosamente';
            if ($activation_message) {
                $base_message .= $activation_message;
            }
            $base_message .= '. Se ha asignado el plan ' . $plan_name . ' al suscriptor ' . $subscriber_name;
            
            // Respuesta JSON para AJAX
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => $base_message
            ]);
            exit;
        } else {
            throw new Exception('No se pudo actualizar la factura');
        }
        
    } catch (Exception $e) {
        // Limpiar cualquier output previo
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// Obtener parámetros de filtro
$subscriber_id = $_GET['subscriber_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Construir consulta con filtros
$where_conditions = [];
$params = [];

if (!empty($subscriber_id)) {
    $where_conditions[] = "bc.subscriber_id = :subscriber_id";
    $params['subscriber_id'] = $subscriber_id;
}

if (!empty($status_filter)) {
    $where_conditions[] = "bc.status = :status";
    $params['status'] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.email LIKE :search OR bc.invoice_number LIKE :search)";
    $params['search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Obtener facturas
$invoices = $db->query("
    SELECT bc.*, s.first_name, s.last_name, s.email, s.plan_type as subscriber_plan
    FROM billing_cycles bc
    JOIN subscribers s ON bc.subscriber_id = s.id
    $where_clause
    ORDER BY bc.created_at DESC
", $params)->fetchAll();

// Obtener estadísticas
$stats = $db->query("
    SELECT 
        COUNT(*) as total_invoices,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_invoices,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_invoices,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_invoices,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_revenue
    FROM billing_cycles
")->fetch();

// Obtener suscriptor específico si se filtra por ID
$subscriber_info = null;
if (!empty($subscriber_id)) {
    $subscriber_info = $db->query("
        SELECT first_name, last_name, email, plan_type 
        FROM subscribers 
        WHERE id = :subscriber_id
    ", ['subscriber_id' => $subscriber_id])->fetch();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Facturas - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin-nav.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8fafc;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #059669;
        }
        .stat-label {
            color: #6b7280;
            margin-top: 0.5rem;
        }
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        .form-control {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-overdue {
            background: #fecaca;
            color: #dc2626;
        }
        .amount {
            font-weight: 600;
            color: #059669;
        }
        .subscriber-info {
            background: #f0f9ff;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-file-invoice"></i> Gestión de Facturas</h1>
            <p>Administra todas las facturas del sistema</p>
        </div>

        <?php include 'includes/admin-nav.php'; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['total_invoices']; ?></div>
                <div class="stat-label">Total Facturas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['paid_invoices']; ?></div>
                <div class="stat-label">Pagadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending_invoices']; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['cancelled_invoices']; ?></div>
                <div class="stat-label">Canceladas</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Ingresos Totales</div>
            </div>
        </div>

        <!-- Información del suscriptor (si se filtra por uno específico) -->
        <?php if ($subscriber_info): ?>
        <div class="subscriber-info">
            <h3><i class="fas fa-user"></i> Facturas de: <?php echo htmlspecialchars($subscriber_info['first_name'] . ' ' . $subscriber_info['last_name']); ?></h3>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($subscriber_info['email']); ?> | 
               <strong>Plan:</strong> <?php echo ucfirst($subscriber_info['plan_type']); ?></p>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filters">
            <form method="GET" class="filter-row">
                <div class="form-group">
                    <label for="subscriber_id">Suscriptor</label>
                    <input type="text" id="subscriber_id" name="subscriber_id" class="form-control" 
                           value="<?php echo htmlspecialchars($subscriber_id); ?>" 
                           placeholder="ID del suscriptor">
                </div>
                <div class="form-group">
                    <label for="status">Estado</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Pagada</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                        <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Vencida</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="search">Buscar</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Nombre, email o número de factura">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="invoices.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Tabla de facturas -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Número de Factura</th>
                        <th>Suscriptor</th>
                        <th>Plan</th>
                        <th>Período</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th>Activa</th>
                        <th>Fecha de Vencimiento</th>
                        <th>Fecha de Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="10" class="no-data">
                                <i class="fas fa-file-invoice"></i> No se encontraron facturas
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></strong><br>
                                        <small style="color: #6b7280;"><?php echo htmlspecialchars($invoice['email']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $invoice['plan_type']; ?>">
                                        <?php echo ucfirst($invoice['plan_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <strong>Del:</strong> <?php echo date('d/m/Y', strtotime($invoice['cycle_start_date'])); ?><br>
                                        <strong>Al:</strong> <?php echo date('d/m/Y', strtotime($invoice['cycle_end_date'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="amount">$<?php echo number_format($invoice['amount'], 2); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $invoice['status']; ?>">
                                        <?php echo ucfirst($invoice['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($invoice['is_active'] == 1): ?>
                                        <span class="status-badge status-active">
                                            <i class="fas fa-check-circle"></i> Activa
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">
                                            <i class="fas fa-clock"></i> Inactiva
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                                </td>
                                <td>
                                    <?php echo $invoice['paid_date'] ? date('d/m/Y', strtotime($invoice['paid_date'])) : '-'; ?>
                                </td>
                                <td>
                                    <button onclick="openInvoiceModal(<?php echo htmlspecialchars(json_encode($invoice)); ?>)" 
                                            class="btn btn-primary btn-sm">Gestionar</button>
                                    
                                    <?php if ($invoice['status'] === 'paid' && $invoice['is_active'] != 1): ?>
                                    <button onclick="activateInvoice(<?php echo $invoice['id']; ?>)" 
                                            class="btn btn-success btn-sm" style="margin-left: 5px;">
                                        <i class="fas fa-bolt"></i> Activar
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para editar factura -->
    <div id="invoiceModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Editar Factura</h2>
                <span class="close" onclick="closeInvoiceModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="invoiceForm">
                    <input type="hidden" id="invoice_id" name="invoice_id">
                    <input type="hidden" id="subscriber_id" name="subscriber_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="invoice_number">Número de Factura</label>
                            <input type="text" id="invoice_number" name="invoice_number" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label for="amount">Monto</label>
                            <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Estado</label>
                            <select id="status" name="status" class="form-control">
                                <option value="pending">Pendiente</option>
                                <option value="paid">Pagada</option>
                                <option value="cancelled">Cancelada</option>
                                <option value="overdue">Vencida</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Estado de Activación</label>
                            <div id="activation_status" style="padding: 8px; border-radius: 4px; font-weight: bold;">
                                <!-- Se llenará dinámicamente -->
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="plan_type">Plan</label>
                            <input type="text" id="plan_type" name="plan_type" class="form-control" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="cycle_start_date">Fecha de Inicio</label>
                            <input type="date" id="cycle_start_date" name="cycle_start_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="cycle_end_date">Fecha de Fin</label>
                            <input type="date" id="cycle_end_date" name="cycle_end_date" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="due_date">Fecha de Vencimiento</label>
                            <input type="date" id="due_date" name="due_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="paid_date">Fecha de Pago</label>
                            <input type="date" id="paid_date" name="paid_date" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sync_count">Sincronizaciones</label>
                            <input type="number" id="sync_count" name="sync_count" class="form-control" min="0">
                        </div>
                        <div class="form-group">
                            <label for="api_calls_count">Llamadas API</label>
                            <input type="number" id="api_calls_count" name="api_calls_count" class="form-control" min="0">
                        </div>
                    </div>
                    
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeInvoiceModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveInvoice()">Guardar Cambios</button>
            </div>
        </div>
    </div>

    <style>
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #374151;
        }
        
        .close {
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            color: #6b7280;
        }
        
        .close:hover {
            color: #374151;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
    </style>

    <script>
        function openInvoiceModal(invoice) {
            // Llenar el formulario con los datos de la factura
            document.getElementById('invoice_id').value = invoice.id;
            document.getElementById('subscriber_id').value = invoice.subscriber_id;
            document.getElementById('invoice_number').value = invoice.invoice_number || '';
            document.getElementById('amount').value = invoice.amount || 0;
            document.getElementById('status').value = invoice.status || 'pending';
            document.getElementById('plan_type').value = invoice.plan_type || '';
            document.getElementById('cycle_start_date').value = invoice.cycle_start_date || '';
            document.getElementById('cycle_end_date').value = invoice.cycle_end_date || '';
            document.getElementById('due_date').value = invoice.due_date || '';
            document.getElementById('paid_date').value = invoice.paid_date || '';
            document.getElementById('sync_count').value = invoice.sync_count || 0;
            document.getElementById('api_calls_count').value = invoice.api_calls_count || 0;
            
            // Mostrar estado de activación
            const activationStatus = document.getElementById('activation_status');
            if (invoice.is_active == 1) {
                activationStatus.innerHTML = '<span style="color: #28a745;"><i class="fas fa-check-circle"></i> ✅ ACTIVA</span>';
                activationStatus.style.backgroundColor = '#d4edda';
                activationStatus.style.border = '1px solid #c3e6cb';
            } else {
                activationStatus.innerHTML = '<span style="color: #dc3545;"><i class="fas fa-clock"></i> ❌ INACTIVA</span>';
                activationStatus.style.backgroundColor = '#f8d7da';
                activationStatus.style.border = '1px solid #f5c6cb';
            }
            
            // Mostrar el modal
            document.getElementById('invoiceModal').style.display = 'flex';
        }
        
        function closeInvoiceModal() {
            document.getElementById('invoiceModal').style.display = 'none';
        }
        
        function saveInvoice() {
            const form = document.getElementById('invoiceForm');
            const formData = new FormData(form);
            
            // Agregar action
            formData.append('action', 'update_invoice');
            
            // Mostrar loading
            const saveBtn = document.querySelector('.modal-footer .btn-primary');
            const originalText = saveBtn.textContent;
            saveBtn.textContent = 'Guardando...';
            saveBtn.disabled = true;
            
            // Usar XMLHttpRequest en lugar de fetch
            const xhr = new XMLHttpRequest();
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    saveBtn.textContent = originalText;
                    saveBtn.disabled = false;
                    
                    console.log('Response status:', xhr.status);
                    console.log('Response text:', xhr.responseText);
                    
                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            console.log('Parsed data:', data);
                            
                            if (data.success) {
                                alert(data.message);
                                closeInvoiceModal();
                                location.reload(); // Recargar la página para mostrar los cambios
                            } else {
                                alert('Error al actualizar la factura: ' + (data.message || 'Error desconocido'));
                            }
                        } catch (parseError) {
                            console.error('JSON parse error:', parseError);
                            console.error('Response text that failed to parse:', xhr.responseText);
                            alert('Error al procesar la respuesta del servidor. Ver consola para detalles.');
                        }
                    } else {
                        alert('Error de conexión al actualizar la factura. Status: ' + xhr.status);
                    }
                }
            };
            
            xhr.open('POST', 'invoices.php', true);
            xhr.send(formData);
        }
        
        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('invoiceModal');
            if (event.target === modal) {
                closeInvoiceModal();
            }
        }
        
        // Función para activar una factura
        function activateInvoice(invoiceId) {
            if (!confirm('¿Estás seguro de que quieres activar esta factura? Esto activará la cuenta del usuario.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'activate_invoice');
            formData.append('invoice_id', invoiceId);
            
            fetch('invoices.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Factura activada exitosamente: ' + data.message);
                    location.reload(); // Recargar la página para mostrar los cambios
                } else {
                    alert('Error al activar la factura: ' + (data.message || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión al activar la factura');
            });
        }
    </script>
</body>
</html>
