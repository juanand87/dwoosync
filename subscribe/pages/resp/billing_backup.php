<?php
/**
 * Página de facturación del usuario
 */

// Definir constante para acceso a la API
define('API_ACCESS', true);

// Incluir configuración
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Verificar sesión
startSecureSession();
requireLogin();

$subscriber_id = $_SESSION['user_id'];
$license_key = $_SESSION['license_key'] ?? '';

// Obtener información del suscriptor
$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$subscriber = $pdo->prepare("SELECT * FROM subscribers WHERE id = ?");
$subscriber->execute([$subscriber_id]);
$subscriber_data = $subscriber->fetch(PDO::FETCH_ASSOC);

// Obtener información de la licencia
$license = $pdo->prepare("SELECT * FROM licenses WHERE subscriber_id = ? AND license_key = ?");
$license->execute([$subscriber_id, $license_key]);
$license_data = $license->fetch(PDO::FETCH_ASSOC);

// Obtener facturas del suscriptor
$invoices = $pdo->prepare("
    SELECT i.*, sc.cycle_start_date, sc.cycle_end_date
    FROM invoices i
    LEFT JOIN subscription_cycles sc ON i.cycle_id = sc.id
    WHERE i.subscriber_id = ? 
    ORDER BY i.created_at DESC 
    LIMIT 20
");
$invoices->execute([$subscriber_id]);
$invoices_data = $invoices->fetchAll(PDO::FETCH_ASSOC);



// Calcular estadísticas de facturación
$total_paid = $pdo->prepare("
    SELECT SUM(amount) as total FROM payments 
    WHERE subscriber_id = ? AND status = 'completed'
");
$total_paid->execute([$subscriber_id]);
$total_paid_data = $total_paid->fetch(PDO::FETCH_ASSOC);

$pending_payments = $pdo->prepare("
    SELECT COUNT(*) as count FROM payments 
    WHERE subscriber_id = ? AND status = 'pending'
");
$pending_payments->execute([$subscriber_id]);
$pending_data = $pending_payments->fetch(PDO::FETCH_ASSOC);

// Función para formatear moneda
function formatCurrency($amount, $currency = 'USD') {
    return '$' . number_format($amount, 2);
}

// Función para obtener estado del pago
function getPaymentStatus($status) {
    switch ($status) {
        case 'completed':
            return ['text' => 'Completado', 'class' => 'success', 'icon' => 'check-circle'];
        case 'pending':
            return ['text' => 'Pendiente', 'class' => 'warning', 'icon' => 'clock'];
        case 'failed':
            return ['text' => 'Fallido', 'class' => 'error', 'icon' => 'times-circle'];
        case 'cancelled':
            return ['text' => 'Cancelado', 'class' => 'secondary', 'icon' => 'ban'];
        default:
            return ['text' => ucfirst($status), 'class' => 'secondary', 'icon' => 'question'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facturación - DiscogsSync</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: #333;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-logout {
            background: linear-gradient(45deg, #dc2626, #b91c1c);
            color: white !important;
        }
        
        .btn-logout:hover {
            background: linear-gradient(45deg, #b91c1c, #991b1b);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .billing-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .billing-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            margin-bottom: 10px;
        }
        
        .billing-subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .billing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .billing-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            border-left: 4px solid #667eea;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .plan-info {
            background: #f0f4ff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .plan-badge {
            background: linear-gradient(45deg, #10b981, #059669);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 1.1rem;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .plan-badge.free {
            background: linear-gradient(45deg, #6b7280, #4b5563);
        }
        
        .plan-badge.premium {
            background: linear-gradient(45deg, #f59e0b, #d97706);
        }
        
        .plan-badge.enterprise {
            background: linear-gradient(45deg, #8b5cf6, #7c3aed);
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #374151;
        }
        
        .info-value {
            color: #6b7280;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .table th {
            background: #f8f9fa;
            color: #374151;
            font-weight: 600;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            color: #4b5563;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-badge.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-badge.secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-cancelled {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .status-refunded {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(45deg, #10b981, #059669);
        }
        
        .btn-warning {
            background: linear-gradient(45deg, #f59e0b, #d97706);
        }
        
        .btn-secondary {
            background: linear-gradient(45deg, #6b7280, #4b5563);
        }
        
        .warning {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #f59e0b;
        }
        
        .success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #d1d5db;
        }
        
        @media (max-width: 768px) {
            .billing-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table-container {
                font-size: 0.9rem;
            }
        }
    
        .spinning-disc {
            animation: spin 3s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .nav-logo h2 {
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #1db954, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(29, 185, 84, 0.3);
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <h2><i class="fas fa-compact-disc spinning-disc"></i> DWooSync</h2>
                </div>
                <div class="nav-menu">
                    <a href="dashboard.php" class="nav-link">🏠 Inicio</a>
                    <a href="profile.php" class="nav-link">👤 Perfil</a>
                    <a href="billing.php" class="nav-link btn-primary">💳 Facturación</a>
                    <a href="plugin-config.php" class="nav-link">⚙️ Configurar Plugin</a>
                    <a href="logout.php" class="nav-link btn-logout">🚪 Cerrar Sesión</a>
                </div>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="billing-header">
            <h1 class="billing-title">💳 Facturación</h1>
            <p class="billing-subtitle">Gestiona tu suscripción, pagos y facturación</p>
        </div>

        <?php if (($subscriber_data['plan_type'] ?? 'free') === 'free'): ?>
            <!-- Mensaje para Plan Free -->
            <div class="billing-card" style="text-align: center; margin-bottom: 40px;">
                <div style="font-size: 4rem; margin-bottom: 20px;">🎵</div>
                <h2 style="color: #1f2937; margin-bottom: 15px; font-size: 2rem;">Plan Gratuito</h2>
                <p style="color: #6b7280; font-size: 1.1rem; margin-bottom: 30px; line-height: 1.6;">
                    Actualmente tienes el plan gratuito de DiscogsSync.<br>
                    Para acceder a estadísticas detalladas, más importaciones y funciones avanzadas,<br>
                    <strong>mejora tu plan</strong> haciendo clic en el botón de abajo.
                </p>
                <button onclick="openPlansModal()" class="btn btn-warning" style="font-size: 1.2rem; padding: 15px 40px;">
                    <i class="fas fa-arrow-up"></i> Mejorar Plan
                </button>
            </div>
        <?php else: ?>
            <!-- Estadísticas de Facturación (solo para planes pagos) -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?php echo formatCurrency($total_paid_data['total'] ?? 0); ?></div>
                    <div class="stat-label">Total Pagado</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($payments_data); ?></div>
                    <div class="stat-label">Pagos Realizados</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $pending_data['count'] ?? 0; ?></div>
                    <div class="stat-label">Pagos Pendientes</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($history_data); ?></div>
                    <div class="stat-label">Ciclos de Suscripción</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="billing-grid">
            <!-- Información del Plan -->
            <div class="billing-card">
                <h2 class="card-title">
                    <i class="fas fa-crown"></i> Plan Actual
                </h2>
                
                <div class="plan-info">
                    <div class="plan-badge <?php echo $subscriber_data['plan_type'] ?? 'free'; ?>">
                        <?php 
                        $plan_type = $subscriber_data['plan_type'] ?? 'free';
                        echo $plan_type === 'enterprise' ? '+Spotify' : ucfirst($plan_type);
                        ?>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Estado de la Cuenta:</span>
                        <span class="info-value" style="color: <?php echo ($subscriber_data['status'] ?? 'inactive') === 'active' ? '#10b981' : '#dc2626'; ?>">
                            <?php echo ucfirst($subscriber_data['status'] ?? 'inactive'); ?>
                        </span>
                    </div>
                    
                    <?php if ($cycle_data): ?>
                        <div class="info-item">
                            <span class="info-label">Ciclo Actual:</span>
                            <span class="info-value">
                                <?php echo date('d/m/Y', strtotime($cycle_data['cycle_start_date'])); ?> - 
                                <?php echo date('d/m/Y', strtotime($cycle_data['cycle_end_date'])); ?>
                            </span>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">Próxima Renovación:</span>
                            <span class="info-value">
                                <?php echo date('d/m/Y', strtotime($cycle_data['cycle_end_date'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <span class="info-label">Dominio:</span>
                        <span class="info-value"><?php echo htmlspecialchars($subscriber_data['domain'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <?php if (($subscriber_data['plan_type'] ?? 'free') === 'free'): ?>
                    <button onclick="openPlansModal()" class="btn btn-warning">
                        <i class="fas fa-arrow-up"></i> Mejorar Plan
                    </button>
                <?php else: ?>
                    <button onclick="openPlansModal()" class="btn btn-secondary">
                        <i class="fas fa-exchange-alt"></i> Cambiar Plan
                    </button>
                <?php endif; ?>
            </div>

            <!-- Información de la Licencia -->
            <div class="billing-card">
                <h2 class="card-title">
                    <i class="fas fa-key"></i> Información de Licencia
                </h2>
                
                <?php if ($license_data): ?>
                    <div class="info-item">
                        <span class="info-label">Clave de Licencia:</span>
                        <span class="info-value" style="font-family: 'Courier New', monospace; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($license_key); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Estado de la Licencia:</span>
                        <span class="info-value" style="color: <?php echo $license_data['status'] === 'active' ? '#10b981' : '#dc2626'; ?>">
                            <?php echo ucfirst($license_data['status']); ?>
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Límite de Uso:</span>
                        <span class="info-value">
                            <?php echo number_format($license_data['usage_limit']); ?> operaciones
                        </span>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Uso Actual:</span>
                        <span class="info-value">
                            <?php echo number_format($license_data['usage_count']); ?> operaciones
                        </span>
                    </div>
                    
                    <?php if ($license_data['expires_at']): ?>
                        <div class="info-item">
                            <span class="info-label">Expira:</span>
                            <span class="info-value">
                                <?php echo date('d/m/Y H:i', strtotime($license_data['expires_at'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>No se encontró información de licencia</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (($subscriber_data['plan_type'] ?? 'free') !== 'free'): ?>
            <!-- Historial de Facturas (solo para planes pagos) -->
            <div class="billing-card">
                <h2 class="card-title">
                    <i class="fas fa-file-invoice"></i> Historial de Facturas
                </h2>
                
                <?php if (!empty($invoices_data)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Número de Factura</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Fecha de Vencimiento</th>
                                    <th>Fecha de Pago</th>
                                    <th>Método de Pago</th>
                                    <th>Período</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices_data as $invoice): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo '$' . number_format($invoice['amount'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $status = $invoice['status'];
                                            $status_class = '';
                                            $status_icon = '';
                                            
                                            switch ($status) {
                                                case 'paid':
                                                    $status_class = 'status-paid';
                                                    $status_icon = '✅';
                                                    break;
                                                case 'pending':
                                                    $due_date = new DateTime($invoice['due_date']);
                                                    $today = new DateTime();
                                                    if ($today > $due_date) {
                                                        $status_class = 'status-overdue';
                                                        $status_icon = '⚠️';
                                                    } else {
                                                        $status_class = 'status-pending';
                                                        $status_icon = '⏳';
                                                    }
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'status-cancelled';
                                                    $status_icon = '❌';
                                                    break;
                                                case 'refunded':
                                                    $status_class = 'status-refunded';
                                                    $status_icon = '🔄';
                                                    break;
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $status_class; ?>">
                                                <?php echo $status_icon; ?> <?php echo strtoupper($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                                            <?php if ($status === 'pending'): ?>
                                                <?php
                                                $due_date = new DateTime($invoice['due_date']);
                                                $today = new DateTime();
                                                if ($today > $due_date) {
                                                    $days_overdue = $today->diff($due_date)->days;
                                                    echo '<br><small style="color: #dc2626;">Vencida hace ' . $days_overdue . ' días</small>';
                                                } else {
                                                    $days_remaining = $today->diff($due_date)->days;
                                                    echo '<br><small style="color: #059669;">Vence en ' . $days_remaining . ' días</small>';
                                                }
                                                ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($invoice['paid_date']) {
                                                echo date('d/m/Y', strtotime($invoice['paid_date']));
                                            } else {
                                                echo '<span style="color: #6b7280;">-</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($invoice['payment_method']) {
                                                echo ucfirst($invoice['payment_method']);
                                            } else {
                                                echo '<span style="color: #6b7280;">-</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($invoice['cycle_start_date'] && $invoice['cycle_end_date']): ?>
                                                <?php echo date('d/m/Y', strtotime($invoice['cycle_start_date'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($invoice['cycle_end_date'])); ?>
                                            <?php else: ?>
                                                <span style="color: #6b7280;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <p>No hay facturas registradas</p>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <!-- Acciones -->
        <div style="text-align: center; margin-top: 30px;">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
            <a href="profile.php" class="btn">
                <i class="fas fa-user"></i> Ver Perfil
            </a>
        </div>
    </div>

    <!-- Modal de Planes -->
    <div id="plansModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-crown"></i> Selecciona tu Plan</h2>
                <button class="modal-close" onclick="closePlansModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="plans-grid">
                    <!-- Plan Free -->
                    <div class="plan-card" onclick="selectPlan('free')">
                        <div class="plan-header">
                            <h3>Free</h3>
                            <div class="plan-price">€0<span>/mes</span></div>
                        </div>
                        <div class="plan-features">
                            <ul>
                                <li><i class="fas fa-check"></i> Importaciones ilimitadas</li>
                                <li><i class="fas fa-check"></i> Soporte por email</li>
                                <li><i class="fas fa-check"></i> Actualizaciones básicas</li>
                            </ul>
                        </div>
                        <div class="plan-button">
                            <span class="current-plan">Plan Actual</span>
                        </div>
                    </div>

                    <!-- Plan Premium -->
                    <div class="plan-card premium" onclick="selectPlan('premium')">
                        <div class="plan-badge">Más Popular</div>
                        <div class="plan-header">
                            <h3>Premium</h3>
                            <div class="plan-price">€22<span>/mes</span></div>
                        </div>
                        <div class="plan-features">
                            <ul>
                                <li><i class="fas fa-check"></i> Importaciones ilimitadas</li>
                                <li><i class="fas fa-check"></i> Soporte prioritario</li>
                                <li><i class="fas fa-check"></i> Todas las actualizaciones</li>
                                <li><i class="fas fa-check"></i> Estadísticas avanzadas</li>
                                <li><i class="fas fa-times" style="color: #dc2626;"></i> Widget Spotify</li>
                            </ul>
                        </div>
                        <div class="plan-button">
                            <button class="btn-select">Seleccionar</button>
                        </div>
                    </div>

                    <!-- Plan +Spotify -->
                    <div class="plan-card enterprise" onclick="selectPlan('enterprise')">
                        <div class="plan-header">
                            <h3>+Spotify</h3>
                            <div class="plan-price">€29<span>/mes</span></div>
                        </div>
                        <div class="plan-features">
                            <ul>
                                <li><i class="fas fa-check"></i> Importaciones ilimitadas</li>
                                <li><i class="fas fa-check"></i> Integración con Spotify</li>
                                <li><i class="fas fa-check"></i> Soporte prioritario</li>
                                <li><i class="fas fa-check"></i> Todas las actualizaciones</li>
                                <li><i class="fas fa-check"></i> Estadísticas avanzadas</li>
                            </ul>
                        </div>
                        <div class="plan-button">
                            <button class="btn-select">Seleccionar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Estilos del Modal */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 25px 30px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.2);
        }

        .modal-body {
            padding: 30px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .plan-card {
            background: #f8f9fa;
            border: 2px solid #e5e7eb;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .plan-card.premium {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
        }

        .plan-card.enterprise {
            border-color: #8b5cf6;
            background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
        }

        .plan-badge {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(45deg, #f59e0b, #d97706);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .plan-header h3 {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 20px;
        }

        .plan-price span {
            font-size: 1rem;
            color: #6b7280;
            font-weight: normal;
        }

        .plan-features ul {
            list-style: none;
            padding: 0;
            margin: 0 0 25px 0;
        }

        .plan-features li {
            padding: 8px 0;
            color: #4b5563;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .plan-features i {
            color: #10b981;
            font-size: 0.9rem;
        }

        .plan-button {
            margin-top: auto;
        }

        .btn-select {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-select:hover {
            background: linear-gradient(45deg, #5a67d8, #6b46c1);
            transform: translateY(-2px);
        }

        .current-plan {
            background: #6b7280;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
            width: 100%;
        }

        @media (max-width: 768px) {
            .modal-content {
                margin: 2% auto;
                width: 95%;
            }
            
            .plans-grid {
                grid-template-columns: 1fr;
            }
        }
    
        .spinning-disc {
            animation: spin 3s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .nav-logo h2 {
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #1db954, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(29, 185, 84, 0.3);
        }
    </style>

    <script>
        function openPlansModal() {
            document.getElementById('plansModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closePlansModal() {
            document.getElementById('plansModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function selectPlan(planId) {
            // Cerrar el modal
            closePlansModal();
            
            // Redirigir al checkout con el plan seleccionado
            window.location.href = `checkout.php?plan=${planId}&renewal=true`;
        }

        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('plansModal');
            if (event.target === modal) {
                closePlansModal();
            }
        }

        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePlansModal();
            }
        });
    </script>
</body>
</html>
