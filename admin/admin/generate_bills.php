<?php
/**
 * Generar ciclos de facturación para suscriptores próximos a vencer
 */

define('API_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = Database::getInstance();
$message = '';
$error = '';
$generated_cycles = 0;
$errors = [];

// Procesar generación de ciclos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_cycles') {
        try {
            $db->beginTransaction();
            
            // Obtener ciclos seleccionados
            $selected_cycles = $_POST['cycles'] ?? [];
            
            if (empty($selected_cycles)) {
                throw new Exception('No se seleccionaron ciclos para procesar');
            }
            
            foreach ($selected_cycles as $cycle_id) {
                try {
                    // Obtener información del ciclo actual
                    $current_cycle = $db->query("
                        SELECT bc.*, s.first_name, s.last_name, s.email, s.plan_type
                        FROM billing_cycles bc
                        JOIN subscribers s ON bc.subscriber_id = s.id
                        WHERE bc.id = $cycle_id
                    ")->fetch();
                    
                    if ($current_cycle) {
                        // Verificar si ya existe un nuevo ciclo para este suscriptor
                        $next_cycle = $db->query("
                            SELECT * FROM billing_cycles 
                            WHERE subscriber_id = {$current_cycle['subscriber_id']} 
                            AND cycle_start_date = '{$current_cycle['cycle_end_date']}'
                            LIMIT 1
                        ")->fetch();
                        
                        if (!$next_cycle) {
                            // Obtener precio del plan desde la base de datos
                            $plan = $db->query("SELECT price FROM subscription_plans WHERE plan_type = '{$current_cycle['plan_type']}'")->fetch();
                            $planPrice = $plan ? $plan['price'] : 0.00;
                            
                            // Obtener license_key
                            $license = $db->query("SELECT license_key FROM licenses WHERE subscriber_id = {$current_cycle['subscriber_id']} LIMIT 1")->fetch();
                            $license_key = $license ? $license['license_key'] : 'TEMP-' . $current_cycle['subscriber_id'];
                            
                            // Crear número de factura
                            $invoice_number = 'INV-' . date('Y') . '-' . str_pad($current_cycle['subscriber_id'], 6, '0', STR_PAD_LEFT) . '-' . str_pad(time() + rand(1, 999), 4, '0', STR_PAD_LEFT);
                            
                            // Determinar estado según el tipo de plan
                            $is_active = ($current_cycle['plan_type'] === 'free') ? true : false;
                            $status = ($current_cycle['plan_type'] === 'free') ? 'paid' : 'pending';
                            
                            // Crear nuevo ciclo de facturación (incluye factura)
                            $new_billing_cycle_id = $db->insert('billing_cycles', [
                                'subscriber_id' => $current_cycle['subscriber_id'],
                                'plan_type' => $current_cycle['plan_type'],
                                'license_key' => $license_key,
                                'cycle_start_date' => $current_cycle['cycle_end_date'],
                                'cycle_end_date' => date('Y-m-d', strtotime($current_cycle['cycle_end_date'] . ' +30 days')),
                                'is_active' => $is_active,
                                'invoice_number' => $invoice_number,
                                'amount' => $planPrice,
                                'currency' => 'USD',
                                'status' => $status,
                                'due_date' => date('Y-m-d', strtotime($current_cycle['cycle_end_date'] . ' +3 days')),
                                'sync_count' => 0,
                                'api_calls_count' => 0,
                                'products_synced' => 0,
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                            
                            $generated_cycles++;
                        } else {
                            $errors[] = "{$current_cycle['first_name']} {$current_cycle['last_name']} ya tiene un nuevo ciclo creado";
                        }
                    } else {
                        $errors[] = "Ciclo ID $cycle_id no encontrado";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error con ciclo ID $cycle_id: " . $e->getMessage();
                }
            }
            
            $db->commit();
            
            if ($generated_cycles > 0) {
                $message = "Se generaron $generated_cycles ciclos de facturación exitosamente";
            } else {
                $error = "No se generaron ciclos. Verifique los errores.";
            }
            
        } catch (Exception $e) {
            $db->rollback();
            $error = "Error al generar ciclos: " . $e->getMessage();
        }
    }
}

// Obtener ciclos próximos a vencer (7 días)
$cycles_to_expire = $db->query("
    SELECT bc.*, s.first_name, s.last_name, s.email, s.plan_type,
           DATEDIFF(bc.cycle_end_date, CURDATE()) as days_until_expiry
    FROM billing_cycles bc
    JOIN subscribers s ON bc.subscriber_id = s.id
    WHERE bc.cycle_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND NOT EXISTS (
        SELECT 1 FROM billing_cycles bc2 
        WHERE bc2.subscriber_id = bc.subscriber_id 
        AND bc2.cycle_start_date = bc.cycle_end_date
    )
    ORDER BY bc.cycle_end_date ASC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Ciclos de Facturación - Admin</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
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
        .checkbox {
            width: 20px;
            height: 20px;
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
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        .days-warning {
            color: #dc2626;
            font-weight: 600;
        }
        .form-actions {
            padding: 1.5rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .select-all-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-plus"></i> Generar Ciclos de Facturación</h1>
            <p>Ciclos que vencen en los próximos 7 días</p>
        </div>

        <?php include 'includes/admin-nav.php'; ?>

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

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> Errores encontrados:
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <form method="POST" id="cyclesForm">
                <input type="hidden" name="action" value="generate_cycles">
                
                <table>
                    <thead>
                        <tr>
                            <th>
                                <div class="select-all-container">
                                    <input type="checkbox" id="selectAll" class="checkbox">
                                    <label for="selectAll">Seleccionar</label>
                                </div>
                            </th>
                            <th>Suscriptor</th>
                            <th>Plan</th>
                            <th>Ciclo Actual</th>
                            <th>Nuevo Ciclo</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Días Restantes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cycles_to_expire)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 3rem; color: #6b7280;">
                                    <i class="fas fa-calendar-check"></i> No hay ciclos próximos a vencer
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cycles_to_expire as $cycle): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="cycles[]" value="<?php echo $cycle['id']; ?>" class="checkbox cycle-checkbox">
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($cycle['first_name'] . ' ' . $cycle['last_name']); ?></strong><br>
                                            <small style="color: #6b7280;"><?php echo htmlspecialchars($cycle['email']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $cycle['plan_type']; ?>">
                                            <?php echo ucfirst($cycle['plan_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div>
                                            <strong>Del:</strong> <?php echo date('d/m/Y', strtotime($cycle['cycle_start_date'])); ?><br>
                                            <strong>Al:</strong> <?php echo date('d/m/Y', strtotime($cycle['cycle_end_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="background: #f0fdf4; padding: 0.5rem; border-radius: 4px; border: 1px solid #bbf7d0;">
                                            <strong>Del:</strong> <?php echo date('d/m/Y', strtotime($cycle['cycle_end_date'])); ?><br>
                                            <strong>Al:</strong> <?php echo date('d/m/Y', strtotime($cycle['cycle_end_date'] . ' +30 days')); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($cycle['amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $cycle['status']; ?>">
                                            <?php echo ucfirst($cycle['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($cycle['days_until_expiry'] <= 3): ?>
                                            <span class="days-warning">
                                                <i class="fas fa-exclamation-triangle"></i> <?php echo $cycle['days_until_expiry']; ?> días
                                            </span>
                                        <?php else: ?>
                                            <span><?php echo $cycle['days_until_expiry']; ?> días</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (!empty($cycles_to_expire)): ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success" id="generateBtn" disabled>
                            <i class="fas fa-plus-circle"></i> Generar Ciclos Seleccionados
                        </button>
                        <span id="selectedCount">0 ciclos seleccionados</span>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script>
        // Seleccionar todos
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.cycle-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });

        // Actualizar contador de seleccionados
        document.querySelectorAll('.cycle-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedCount();
                updateSelectAllState();
            });
        });

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.cycle-checkbox:checked').length;
            document.getElementById('selectedCount').textContent = selected + ' ciclos seleccionados';
            document.getElementById('generateBtn').disabled = selected === 0;
        }

        function updateSelectAllState() {
            const checkboxes = document.querySelectorAll('.cycle-checkbox');
            const selectAll = document.getElementById('selectAll');
            const checked = document.querySelectorAll('.cycle-checkbox:checked').length;
            
            if (checked === 0) {
                selectAll.indeterminate = false;
                selectAll.checked = false;
            } else if (checked === checkboxes.length) {
                selectAll.indeterminate = false;
                selectAll.checked = true;
            } else {
                selectAll.indeterminate = true;
            }
        }

        // Confirmar generación
        document.getElementById('cyclesForm').addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.cycle-checkbox:checked').length;
            if (selected === 0) {
                e.preventDefault();
                alert('Por favor selecciona al menos un ciclo para generar');
                return;
            }
            
            if (!confirm(`¿Generar ${selected} ciclos de facturación?`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>