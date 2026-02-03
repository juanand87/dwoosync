<?php
/**
 * Panel de administración de facturación
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/billing-functions.php';

// Verificar que el usuario esté logueado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Obtener facturas pendientes
$pending_cycles = getPendingBillingCycles();
$stats = getBillingStats();

// Procesar activación si se envió
$activation_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate') {
    $billing_cycle_id = $_POST['billing_cycle_id'] ?? '';
    
    if (!empty($billing_cycle_id)) {
        $activation_result = activatePaidBillingCycle($billing_cycle_id);
        
        // Recargar datos después de la activación
        if ($activation_result['success']) {
            $pending_cycles = getPendingBillingCycles();
            $stats = getBillingStats();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Facturación - DwooSync</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1f2937;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #6b7280;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1f2937;
        }
        
        .stat-card.pending .number {
            color: #f59e0b;
        }
        
        .stat-card.paid .number {
            color: #10b981;
        }
        
        .stat-card.active .number {
            color: #3b82f6;
        }
        
        .stat-card.revenue .number {
            color: #8b5cf6;
        }
        
        .content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .content-header {
            padding: 25px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .content-header h2 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        
        .content-body {
            padding: 25px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        .table tbody tr:hover {
            background: #f9fafb;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-warning:hover {
            background: #d97706;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-active {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #d1d5db;
        }
        
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
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }
        
        .modal h3 {
            margin-bottom: 20px;
            color: #1f2937;
        }
        
        .modal p {
            margin-bottom: 30px;
            color: #6b7280;
        }
        
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
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
    <div class="header">
        <div class="container">
            <h1><i class="fas fa-file-invoice"></i> Administración de Facturación</h1>
            <p>Gestiona las facturas pendientes y activa las cuentas de los usuarios</p>
        </div>
    </div>
    
    <div class="container">
        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <h3>Pendientes</h3>
                <div class="number"><?php echo $stats['pending'] ?? 0; ?></div>
            </div>
            <div class="stat-card paid">
                <h3>Pagadas</h3>
                <div class="number"><?php echo $stats['paid'] ?? 0; ?></div>
            </div>
            <div class="stat-card active">
                <h3>Activas</h3>
                <div class="number"><?php echo $stats['active'] ?? 0; ?></div>
            </div>
            <div class="stat-card revenue">
                <h3>Ingresos</h3>
                <div class="number">$<?php echo number_format($stats['total_revenue'] ?? 0, 0, ',', '.'); ?></div>
            </div>
        </div>
        
        <!-- Resultado de activación -->
        <?php if ($activation_result): ?>
        <div class="alert <?php echo $activation_result['success'] ? 'alert-success' : 'alert-error'; ?>">
            <i class="fas fa-<?php echo $activation_result['success'] ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $activation_result['message']; ?>
            <?php if ($activation_result['success']): ?>
            <br><small>Cliente: <?php echo $activation_result['subscriber_name']; ?> (<?php echo $activation_result['subscriber_email']; ?>)</small>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Lista de facturas pendientes -->
        <div class="content">
            <div class="content-header">
                <h2><i class="fas fa-clock"></i> Facturas Pendientes de Activación</h2>
                <p>Estas facturas están esperando ser activadas manualmente</p>
            </div>
            <div class="content-body">
                <?php if (empty($pending_cycles)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>¡Excelente!</h3>
                    <p>No hay facturas pendientes de activación</p>
                </div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Plan</th>
                            <th>Factura</th>
                            <th>Monto</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_cycles as $cycle): ?>
                        <tr>
                            <td><?php echo $cycle['id']; ?></td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($cycle['first_name'] . ' ' . $cycle['last_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($cycle['email']); ?></small>
                                    <?php if ($cycle['company']): ?>
                                    <br><small><i class="fas fa-building"></i> <?php echo htmlspecialchars($cycle['company']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-pending"><?php echo strtoupper($cycle['plan_type']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($cycle['invoice_number']); ?></td>
                            <td>$<?php echo number_format($cycle['amount'], 0, ',', '.'); ?> <?php echo $cycle['currency']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($cycle['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-success" onclick="confirmActivation(<?php echo $cycle['id']; ?>, '<?php echo htmlspecialchars($cycle['first_name'] . ' ' . $cycle['last_name']); ?>')">
                                    <i class="fas fa-check"></i> Activar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Enlaces -->
        <div style="text-align: center; margin: 30px 0;">
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
            <a href="view-billing-status.php" class="btn btn-warning">
                <i class="fas fa-chart-bar"></i> Ver Estado de Facturación
            </a>
        </div>
    </div>
    
    <!-- Modal de confirmación -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Activación</h3>
            <p>¿Estás seguro de que quieres activar la factura del cliente <strong id="clientName"></strong>?</p>
            <p><small>Esta acción activará la cuenta del usuario y no se puede deshacer.</small></p>
            <div class="modal-buttons">
                <button class="btn btn-primary" onclick="closeModal()">Cancelar</button>
                <button class="btn btn-success" id="confirmBtn">Sí, Activar</button>
            </div>
        </div>
    </div>

    <script>
        let currentBillingCycleId = null;
        
        function confirmActivation(billingCycleId, clientName) {
            currentBillingCycleId = billingCycleId;
            document.getElementById('clientName').textContent = clientName;
            document.getElementById('confirmModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
            currentBillingCycleId = null;
        }
        
        function activateBillingCycle() {
            if (!currentBillingCycleId) return;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'action';
            actionField.value = 'activate';
            form.appendChild(actionField);
            
            const idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'billing_cycle_id';
            idField.value = currentBillingCycleId;
            form.appendChild(idField);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Event listeners
        document.getElementById('confirmBtn').addEventListener('click', activateBillingCycle);
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>


