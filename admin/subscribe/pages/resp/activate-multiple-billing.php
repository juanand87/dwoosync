<?php
/**
 * Script para activar múltiples facturas de una vez
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

$results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_multiple') {
    $billing_cycle_ids = $_POST['billing_cycle_ids'] ?? [];
    
    if (empty($billing_cycle_ids)) {
        $errors[] = 'No se seleccionaron facturas para activar';
    } else {
        foreach ($billing_cycle_ids as $billing_cycle_id) {
            $result = activatePaidBillingCycle($billing_cycle_id);
            $results[] = $result;
            
            if (!$result['success']) {
                $errors[] = "Error activando factura $billing_cycle_id: " . $result['message'];
            }
        }
    }
}

// Obtener facturas pendientes
$pending_cycles = getPendingBillingCycles();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activación Masiva - DwooSync</title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 30px 0;
            padding: 30px;
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
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
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
        
        .checkbox {
            width: 20px;
            height: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            margin: 5px;
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
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .form-actions {
            text-align: center;
            margin: 30px 0;
        }
        
        .select-all {
            margin-bottom: 20px;
        }
        
        .results {
            margin-top: 30px;
        }
        
        .result-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 6px;
            background: #f9fafb;
        }
        
        .result-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .result-error {
            background: #fee2e2;
            color: #991b1b;
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
            <h1><i class="fas fa-bolt"></i> Activación Masiva de Facturas</h1>
            <p>Activa múltiples facturas pendientes de una vez</p>
        </div>
    </div>
    
    <div class="container">
        <div class="content">
            <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Errores encontrados:</strong>
                <ul style="margin-top: 10px;">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($results)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Activación completada:</strong> <?php echo count(array_filter($results, function($r) { return $r['success']; })); ?> de <?php echo count($results); ?> facturas activadas correctamente.
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="activate_multiple">
                
                <div class="select-all">
                    <label>
                        <input type="checkbox" id="selectAll" onchange="toggleAll()"> 
                        <strong>Seleccionar todas las facturas pendientes</strong>
                    </label>
                </div>
                
                <?php if (empty($pending_cycles)): ?>
                <div style="text-align: center; padding: 40px; color: #6b7280;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 20px; color: #d1d5db;"></i>
                    <h3>¡Excelente!</h3>
                    <p>No hay facturas pendientes de activación</p>
                </div>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Seleccionar</th>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Plan</th>
                            <th>Factura</th>
                            <th>Monto</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_cycles as $cycle): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="billing_cycle_ids[]" value="<?php echo $cycle['id']; ?>" class="checkbox">
                            </td>
                            <td><?php echo $cycle['id']; ?></td>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($cycle['first_name'] . ' ' . $cycle['last_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($cycle['email']); ?></small>
                                </div>
                            </td>
                            <td><?php echo strtoupper($cycle['plan_type']); ?></td>
                            <td><?php echo htmlspecialchars($cycle['invoice_number']); ?></td>
                            <td>$<?php echo number_format($cycle['amount'], 0, ',', '.'); ?> <?php echo $cycle['currency']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($cycle['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Estás seguro de activar las facturas seleccionadas?')">
                        <i class="fas fa-bolt"></i> Activar Seleccionadas
                    </button>
                    <a href="admin-billing.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
                <?php endif; ?>
            </form>
            
            <?php if (!empty($results)): ?>
            <div class="results">
                <h3>Resultados de la Activación:</h3>
                <?php foreach ($results as $index => $result): ?>
                <div class="result-item <?php echo $result['success'] ? 'result-success' : 'result-error'; ?>">
                    <strong>Factura <?php echo $index + 1; ?>:</strong>
                    <?php if ($result['success']): ?>
                        ✅ <?php echo $result['message']; ?>
                        <br><small>Cliente: <?php echo $result['subscriber_name']; ?> (<?php echo $result['subscriber_email']; ?>)</small>
                    <?php else: ?>
                        ❌ <?php echo $result['message']; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('input[name="billing_cycle_ids[]"]');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
        
        // Actualizar checkbox "Seleccionar todo" cuando se cambien los individuales
        document.querySelectorAll('input[name="billing_cycle_ids[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const selectAll = document.getElementById('selectAll');
                const checkboxes = document.querySelectorAll('input[name="billing_cycle_ids[]"]');
                const checkedCount = document.querySelectorAll('input[name="billing_cycle_ids[]"]:checked').length;
                
                selectAll.checked = checkedCount === checkboxes.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            });
        });
    </script>
</body>
</html>


