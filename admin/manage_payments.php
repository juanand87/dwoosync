<?php
/**
 * Página de gestión de pagos/facturas de suscriptores
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

// Verificar autenticación de administrador
$auth = new AdminAuth();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

// Obtener ID del suscriptor
$subscriber_id = (int)($_GET['subscriber_id'] ?? 0);

if (!$subscriber_id) {
    header('Location: subscribers.php');
    exit;
}

// Obtener información del suscriptor
$subscriber = $db->query("SELECT * FROM subscribers WHERE id = $subscriber_id")->fetch();
if (!$subscriber) {
    header('Location: subscribers.php');
    exit;
}

// Obtener información de la licencia
$license = $db->query("SELECT * FROM licenses WHERE subscriber_id = $subscriber_id ORDER BY created_at DESC LIMIT 1")->fetch();

// Verificar si la columna status existe en la tabla licenses
$has_status_column = $db->query("SHOW COLUMNS FROM licenses LIKE 'status'")->fetch();

// Procesar acciones
$action = $_POST['action'] ?? '';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        switch ($action) {
            case 'update_invoice_status':
                $invoice_id = (int)($_POST['invoice_id'] ?? 0);
                $new_status = $_POST['status'] ?? '';
                $payment_method = trim($_POST['payment_method'] ?? '');
                $payment_reference = trim($_POST['payment_reference'] ?? '');
                $new_amount = isset($_POST['amount']) ? (float)$_POST['amount'] : null;
                
                if ($invoice_id && in_array($new_status, ['pending', 'paid', 'cancelled', 'refunded'])) {
                    $update_data = [
                        'status' => $new_status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    // Actualizar monto si se proporciona
                    if ($new_amount !== null && $new_amount >= 0) {
                        $update_data['amount'] = $new_amount;
                    }
                    
                    if ($new_status === 'paid') {
                        $update_data['paid_date'] = date('Y-m-d');
                        if ($payment_method) $update_data['payment_method'] = $payment_method;
                        if ($payment_reference) $update_data['payment_reference'] = $payment_reference;
                    } else {
                        $update_data['paid_date'] = null;
                        $update_data['payment_method'] = null;
                        $update_data['payment_reference'] = null;
                    }
                    
                    // Usar consulta preparada directamente
                    $setClause = [];
                    foreach ($update_data as $key => $value) {
                        $setClause[] = "{$key} = :{$key}";
                    }
                    $setClause = implode(', ', $setClause);
                    
                    $sql = "UPDATE billing_cycles SET {$setClause} WHERE id = :invoice_id";
                    $update_data['invoice_id'] = $invoice_id;
                    
                    $stmt = $db->query($sql, $update_data);
                    
                    // Actualizar estado del suscriptor basado en ciclos de facturación
                    $active_cycles = $db->query("
                        SELECT COUNT(*) as count 
                        FROM billing_cycles 
                        WHERE subscriber_id = $subscriber_id 
                        AND status = 'paid' 
                        AND cycle_end_date >= CURDATE()
                    ")->fetch()['count'];
                    
                    $new_subscriber_status = $active_cycles > 0 ? 'active' : 'pending';
                    $db->update('subscribers', ['status' => $new_subscriber_status], "id = $subscriber_id");
                    
                    // Actualizar estado de las licencias basado en facturas
                    $license_status = $new_subscriber_status === 'active' ? 'active' : 'inactive';
                    
                    // Verificar si la columna status existe en la tabla licenses
                    $columns = $db->query("SHOW COLUMNS FROM licenses LIKE 'status'")->fetch();
                    if ($columns) {
                        $db->update('licenses', ['status' => $license_status], "subscriber_id = $subscriber_id");
                    } else {
                        // Si no existe la columna, agregarla con el estado inactive incluido
                        $db->query("ALTER TABLE licenses ADD COLUMN status ENUM('active', 'pending', 'inactive', 'suspended', 'expired') DEFAULT 'pending' AFTER license_key");
                        $db->update('licenses', ['status' => $license_status], "subscriber_id = $subscriber_id");
                    }
                    
                    $message = "Estado de factura actualizado correctamente. Suscripción: " . ucfirst($new_subscriber_status) . ", Licencia: " . ucfirst($license_status);
                } else {
                    $error = "Datos inválidos";
                }
                break;
                
            case 'update_invoice_amount':
                $invoice_id = (int)($_POST['invoice_id'] ?? 0);
                $new_amount = (float)($_POST['amount'] ?? 0);
                
                if ($invoice_id && $new_amount >= 0) {
                    // Usar consulta preparada directamente
                    $sql = "UPDATE billing_cycles SET amount = :amount, updated_at = :updated_at WHERE id = :invoice_id";
                    $stmt = $db->query($sql, [
                        'amount' => $new_amount,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'invoice_id' => $invoice_id
                    ]);
                    
                    $message = "Monto de factura actualizado correctamente a $" . number_format($new_amount, 2);
                } else {
                    $error = "Monto inválido";
                }
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener facturas del suscriptor
$billing_cycles = $db->query("
    SELECT * FROM billing_cycles
    WHERE subscriber_id = $subscriber_id 
    ORDER BY created_at DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Pagos - <?php echo htmlspecialchars($subscriber['first_name'] . ' ' . $subscriber['last_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table-container {
            overflow-x: auto;
            margin-top: 1rem;
        }
        
        .invoices-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .invoices-table th,
        .invoices-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .invoices-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        
        .invoices-table tr:hover {
            background: #f9fafb;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Modal styles */
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
            margin: 5% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #1f2937;
        }
        
        .close {
            color: #6b7280;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }
        
        .close:hover {
            color: #374151;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .invoice-card.paid {
            border-left-color: #10b981;
        }
        
        .invoice-card.pending {
            border-left-color: #f59e0b;
        }
        
        .invoice-card.overdue {
            border-left-color: #ef4444;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
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
        
        .status-active {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
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
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="admin-nav">
                <h1><i class="fas fa-credit-card"></i> Gestionar Pagos</h1>
                <div class="admin-nav-links">
                    <a href="subscribers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Suscriptores
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </header>

        <div class="admin-content">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="subscriber-info">
                <h2>
                    <i class="fas fa-user"></i> 
                    <?php echo htmlspecialchars($subscriber['first_name'] . ' ' . $subscriber['last_name']); ?>
                </h2>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($subscriber['email']); ?></p>
                <p><strong>Dominio:</strong> <?php echo htmlspecialchars($subscriber['domain']); ?></p>
                <p><strong>Plan:</strong> <span class="badge badge-<?php echo $subscriber['plan_type']; ?>"><?php echo ucfirst($subscriber['plan_type']); ?></span></p>
                <p><strong>Estado Suscripción:</strong> <span class="status-<?php echo $subscriber['status']; ?>"><?php echo ucfirst($subscriber['status']); ?></span></p>
                <?php if ($license): ?>
                    <?php if ($has_status_column): ?>
                        <p><strong>Estado Licencia:</strong> <span class="status-<?php echo $license['status']; ?>"><?php echo ucfirst($license['status']); ?></span></p>
                    <?php else: ?>
                        <p><strong>Estado Licencia:</strong> <span class="status-pending">Pendiente (columna status no existe)</span></p>
                    <?php endif; ?>
                    <p><strong>Clave de Licencia:</strong> <code><?php echo htmlspecialchars($license['license_key']); ?></code></p>
                <?php endif; ?>
            </div>

            <div class="invoices-section">
                <h3><i class="fas fa-file-invoice"></i> Facturas del Suscriptor</h3>
                
                <?php if (empty($billing_cycles)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-info-circle"></i> No hay facturas registradas para este suscriptor.
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="invoices-table">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Monto</th>
                                    <th>Vencimiento</th>
                                    <th>Período</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($billing_cycles as $invoice): ?>
                                    <?php
                                    $is_overdue = false;
                                    if ($invoice['status'] === 'pending') {
                                        $due_date = new DateTime($invoice['due_date']);
                                        $today = new DateTime();
                                        $is_overdue = $today > $due_date;
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($invoice['amount'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                                            <?php if ($is_overdue): ?>
                                                <br><small style="color: #dc2626;">Vencida</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($invoice['cycle_start_date'] && $invoice['cycle_end_date']): ?>
                                                <?php echo date('d/m/Y', strtotime($invoice['cycle_start_date'])); ?> - 
                                                <?php echo date('d/m/Y', strtotime($invoice['cycle_end_date'])); ?>
                                            <?php else: ?>
                                                <span style="color: #6b7280;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $invoice['status']; ?>">
                                                <?php
                                                switch ($invoice['status']) {
                                                    case 'paid':
                                                        echo '✅ Pagada';
                                                        break;
                                                    case 'pending':
                                                        echo $is_overdue ? '⚠️ Vencida' : '⏳ Pendiente';
                                                        break;
                                                    case 'cancelled':
                                                        echo '❌ Cancelada';
                                                        break;
                                                    case 'refunded':
                                                        echo '🔄 Reembolsada';
                                                        break;
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($invoice)); ?>)" 
                                                    class="btn btn-primary btn-sm">
                                                <i class="fas fa-edit"></i> Editar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal para editar factura -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Editar Factura</h3>
                <button class="close" onclick="closeEditModal()">&times;</button>
            </div>
            
            <form id="editInvoiceForm" method="POST">
                <input type="hidden" name="action" value="update_invoice_status">
                <input type="hidden" name="invoice_id" id="edit_invoice_id">
                
                <div class="form-group">
                    <label for="edit_invoice_number">Número de Factura:</label>
                    <input type="text" id="edit_invoice_number" readonly style="background: #f9fafb;">
                </div>
                
                <div class="form-group">
                    <label for="edit_amount">Monto:</label>
                    <input type="number" name="amount" id="edit_amount" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Estado:</label>
                    <select name="status" id="edit_status" required>
                        <option value="pending">Pendiente</option>
                        <option value="paid">Pagada</option>
                        <option value="cancelled">Cancelada</option>
                        <option value="refunded">Reembolsada</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_payment_method">Método de Pago:</label>
                    <input type="text" name="payment_method" id="edit_payment_method" 
                           placeholder="Ej: PayPal, Stripe, Transferencia">
                </div>
                
                <div class="form-group">
                    <label for="edit_payment_reference">Referencia de Pago:</label>
                    <input type="text" name="payment_reference" id="edit_payment_reference" 
                           placeholder="Ej: PP123456789, ST_abc123">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(invoice) {
            // Llenar el formulario con los datos de la factura
            document.getElementById('edit_invoice_id').value = invoice.id;
            document.getElementById('edit_invoice_number').value = invoice.invoice_number;
            document.getElementById('edit_amount').value = parseFloat(invoice.amount).toFixed(2);
            document.getElementById('edit_status').value = invoice.status;
            document.getElementById('edit_payment_method').value = invoice.payment_method || '';
            document.getElementById('edit_payment_reference').value = invoice.payment_reference || '';
            
            // Mostrar el modal
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }
        
        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
