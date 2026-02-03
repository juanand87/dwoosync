<?php
/**
 * Página de edición de suscriptor
 */

define('API_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AdminAuth.php';
require_once __DIR__ . '/functions.php';

// Verificar autenticación de administrador
$auth = new AdminAuth();
if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$message = '';
$error = '';
$subscriber = null;

// Obtener ID del suscriptor
$subscriberId = (int)($_GET['id'] ?? 0);

if (!$subscriberId) {
    header('Location: subscribers.php');
    exit;
}

// Obtener datos del suscriptor
$subscriber = $db->fetch("SELECT * FROM subscribers WHERE id = :id", ['id' => $subscriberId]);

if (!$subscriber) {
    header('Location: subscribers.php');
    exit;
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_subscriber') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $domain = trim($_POST['domain'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $planType = $_POST['plan_type'] ?? 'free';
    $status = $_POST['status'] ?? 'active';
    
                // Debug: Mostrar datos recibidos
                $debugMessage = "DEBUG: Datos recibidos - Plan: '$planType', Status: '$status', Subscriber ID: $subscriberId";
                error_log($debugMessage);
                echo "<!-- $debugMessage -->";
    
    if ($firstName && $lastName && $email && $domain) {
        try {
            $stmt = $db->query("
                UPDATE subscribers SET
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    domain = :domain,
                    company = :company,
                    city = :city,
                    country = :country,
                    phone = :phone,
                    plan_type = :plan_type,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id
            ", [
                'id' => $subscriberId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'domain' => $domain,
                'company' => $company,
                'city' => $city,
                'country' => $country,
                'phone' => $phone,
                'plan_type' => $planType,
                'status' => $status
            ]);
            
            if ($stmt->rowCount() > 0) {
                // Actualizar límite de uso de la licencia basado en el nuevo plan
                $limits = [
                    'free' => 10,        // 10 importaciones al mes
                    'premium' => -1,     // Ilimitadas
                    'enterprise' => -1   // Ilimitadas
                ];
                
                $usageLimit = $limits[$planType] ?? 10;
                $licenseUpdated = false;
                
                // Debug: Mostrar información de la actualización
                $debugInfo = "DEBUG: Plan seleccionado: '$planType', Límite asignado: $usageLimit, Subscriber ID: $subscriberId";
                error_log($debugInfo);
                echo "<!-- $debugInfo -->";
                
                // Debug: Verificar licencias antes de actualizar
                $allLicenses = $db->query("SELECT id, subscriber_id, usage_limit, status FROM licenses WHERE subscriber_id = $subscriberId ORDER BY created_at DESC")->fetchAll();
                $debugMessage = '<br><strong>Licencias encontradas para el suscriptor ' . $subscriberId . ':</strong><br>';
                if (empty($allLicenses)) {
                    $debugMessage .= '❌ No se encontraron licencias<br>';
                } else {
                    foreach ($allLicenses as $i => $license) {
                        $debugMessage .= 'Licencia ' . ($i + 1) . ': ID=' . $license['id'] . ', Usage Limit=' . $license['usage_limit'] . ', Status=' . $license['status'] . '<br>';
                    }
                }
                
                // Actualizar licencia usando consulta directa
                try {
                    if (!empty($allLicenses)) {
                        $licenseId = $allLicenses[0]['id'];
                        $debugMessage .= '<br>Actualizando licencia ID: ' . $licenseId . '<br>';
                        
                        $sql = "UPDATE licenses SET usage_limit = $usageLimit WHERE id = $licenseId";
                        $debugMessage .= 'SQL ejecutado: ' . htmlspecialchars($sql) . '<br>';
                        
                        $result = $db->query($sql);
                        $rowsAffected = $result->rowCount();
                        $debugMessage .= 'Filas afectadas: ' . $rowsAffected . '<br>';
                        
                        $licenseUpdated = $rowsAffected > 0;
                        
                        // Verificar la actualización
                        $verifyLicense = $db->query("SELECT usage_limit FROM licenses WHERE id = $licenseId")->fetch();
                        $debugMessage .= 'Límite verificado después de actualizar: ' . $verifyLicense['usage_limit'] . '<br>';
                    } else {
                        $debugMessage .= '<br>❌ No hay licencias para actualizar<br>';
                        $licenseUpdated = false;
                    }
                } catch (Exception $e) {
                    $licenseUpdated = false;
                    $debugMessage .= '<br>❌ Error actualizando licencia: ' . htmlspecialchars($e->getMessage()) . '<br>';
                }
                
                $message = 'Suscriptor actualizado exitosamente';
                if ($licenseUpdated) {
                    $message .= ' y límite de uso de licencia actualizado a ' . ($usageLimit == -1 ? 'ilimitado' : $usageLimit);
                } else {
                    $message .= ' pero NO se pudo actualizar el límite de uso de la licencia';
                }
                
                // Agregar información de debug al mensaje
                $message .= '<br><br><strong>Debug Info:</strong><br>';
                $message .= 'Plan seleccionado: ' . htmlspecialchars($planType) . '<br>';
                $message .= 'Límite asignado: ' . $usageLimit . '<br>';
                $message .= 'Subscriber ID: ' . $subscriberId . '<br>';
                $message .= 'Licencia actualizada: ' . ($licenseUpdated ? 'SÍ' : 'NO') . '<br>';
                $message .= $debugMessage;
                
                // Recargar datos actualizados
                $subscriber = $db->fetch("SELECT * FROM subscribers WHERE id = :id", ['id' => $subscriberId]);
            } else {
                $error = 'No se encontró el suscriptor o no hubo cambios';
                $error .= '<br><br><strong>Debug Info:</strong><br>';
                $error .= 'Plan seleccionado: ' . htmlspecialchars($planType) . '<br>';
                $error .= 'Subscriber ID: ' . $subscriberId . '<br>';
                $error .= 'Filas afectadas en subscribers: 0<br>';
            }
        } catch (Exception $e) {
            $error = 'Error actualizando suscriptor: ' . $e->getMessage();
            $error .= '<br><br><strong>Debug Info:</strong><br>';
            $error .= 'Plan seleccionado: ' . htmlspecialchars($planType) . '<br>';
            $error .= 'Subscriber ID: ' . $subscriberId . '<br>';
            $error .= 'Error: ' . htmlspecialchars($e->getMessage()) . '<br>';
        }
    } else {
        $error = 'Faltan campos requeridos';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Suscriptor - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin-nav.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.2);
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn-primary {
            background: #007cba;
            color: white;
        }
        .btn-primary:hover {
            background: #005a87;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            color: #007cba;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1><i class="fas fa-user-edit"></i> Editar Suscriptor</h1>
                <a href="subscribers.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Suscriptores
                </a>
            </div>
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

        <div class="form-container">
            <form method="POST">
                <input type="hidden" name="action" value="update_subscriber">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Nombre:</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($subscriber['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Apellido:</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($subscriber['last_name']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($subscriber['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="domain">Dominio:</label>
                    <input type="text" id="domain" name="domain" value="<?php echo htmlspecialchars($subscriber['domain']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="company">Empresa:</label>
                    <input type="text" id="company" name="company" value="<?php echo htmlspecialchars($subscriber['company']); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="city">Ciudad:</label>
                        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($subscriber['city']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="country">País:</label>
                        <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($subscriber['country']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Teléfono:</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($subscriber['phone']); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="plan_type">Plan:</label>
                        <select id="plan_type" name="plan_type">
                            <option value="free" <?php echo $subscriber['plan_type'] === 'free' ? 'selected' : ''; ?>>Gratuito</option>
                            <option value="premium" <?php echo $subscriber['plan_type'] === 'premium' ? 'selected' : ''; ?>>Premium</option>
                            <option value="enterprise" <?php echo $subscriber['plan_type'] === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Estado:</label>
                        <select id="status" name="status">
                            <option value="active" <?php echo $subscriber['status'] === 'active' ? 'selected' : ''; ?>>Activo</option>
                            <option value="suspended" <?php echo $subscriber['status'] === 'suspended' ? 'selected' : ''; ?>>Suspendido</option>
                            <option value="expired" <?php echo $subscriber['status'] === 'expired' ? 'selected' : ''; ?>>Expirado</option>
                            <option value="inactive" <?php echo $subscriber['status'] === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>
                
                <div style="text-align: right; margin-top: 30px;">
                    <a href="subscribers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Suscriptores
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
