<?php
/**
 * Versión de debug para crear ciclo de facturación pendiente
 * Devuelve HTML en lugar de JSON para facilitar debugging
 */

// Habilitar logging de errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Debug: Crear Ciclo de Facturación</h2>";

// Mostrar información de la petición
echo "<h3>Información de la Petición:</h3>";
echo "<p><strong>Method:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";
echo "<p><strong>Content Type:</strong> " . ($_SERVER['CONTENT_TYPE'] ?? 'No definido') . "</p>";

// Mostrar datos POST
echo "<h3>Datos POST:</h3>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

// Incluir configuraciones
require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h3>Después de incluir config.php:</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "</p>";

// Mostrar datos de sesión
echo "<h3>Datos de Sesión:</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

// Verificar isLoggedIn()
echo "<h3>Función isLoggedIn():</h3>";
$isLoggedIn = isLoggedIn();
echo "<p><strong>Resultado:</strong> " . ($isLoggedIn ? 'TRUE' : 'FALSE') . "</p>";

if (!$isLoggedIn) {
    echo "<p style='color: red; font-weight: bold;'>❌ USUARIO NO AUTENTICADO - ABORTANDO</p>";
    echo "<p>Posibles causas:</p>";
    echo "<ul>";
    echo "<li>La sesión no se inició correctamente</li>";
    echo "<li>Las cookies no se están enviando</li>";
    echo "<li>El archivo config.php no está iniciando la sesión</li>";
    echo "<li>La función isLoggedIn() está mal implementada</li>";
    echo "</ul>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_type = $_POST['plan_type'] ?? '';
    $subscriber_id = $_POST['subscriber_id'] ?? '';
    
    echo "<h3>Procesando Creación de Ciclo:</h3>";
    echo "<p><strong>Plan Type:</strong> " . htmlspecialchars($plan_type) . "</p>";
    echo "<p><strong>Subscriber ID:</strong> " . htmlspecialchars($subscriber_id) . "</p>";
    
    if (empty($plan_type) || empty($subscriber_id)) {
        echo "<p style='color: red;'>❌ Parámetros requeridos faltantes</p>";
        exit;
    }
    
    try {
        $pdo = getDatabase();
        echo "<p style='color: green;'>✅ Conexión a BD exitosa</p>";
        
        // Obtener información del plan (sin transacción)
        $plan_stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
        $plan_stmt->execute([$plan_type]);
        $plan_data = $plan_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plan_data) {
            throw new Exception('Plan no encontrado: ' . $plan_type);
        }
        
        echo "<p style='color: green;'>✅ Plan encontrado: " . $plan_data['plan_name'] . "</p>";
        
        // Obtener license_key del usuario (sin transacción)
        $license_stmt = $pdo->prepare("SELECT license_key FROM licenses WHERE subscriber_id = ?");
        $license_stmt->execute([$subscriber_id]);
        $license_data = $license_stmt->fetch(PDO::FETCH_ASSOC);
        $license_key = $license_data['license_key'] ?? 'PENDING-' . $subscriber_id . '-' . time();
        
        echo "<p style='color: green;'>✅ License key: " . $license_key . "</p>";
        
        // Verificar si ya existe un ciclo pendiente para este suscriptor
        $existing_cycle_stmt = $pdo->prepare("
            SELECT id, status, created_at 
            FROM billing_cycles 
            WHERE subscriber_id = ? AND status = 'pending' 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $existing_cycle_stmt->execute([$subscriber_id]);
        $existing_cycle = $existing_cycle_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_cycle) {
            echo "<p style='color: orange; font-weight: bold;'>⚠️ YA EXISTE UN CICLO PENDIENTE</p>";
            echo "<p><strong>Ciclo ID:</strong> " . $existing_cycle['id'] . "</p>";
            echo "<p><strong>Status:</strong> " . $existing_cycle['status'] . "</p>";
            echo "<p><strong>Creado:</strong> " . $existing_cycle['created_at'] . "</p>";
            
            // Actualizar sesión con el ciclo existente
            $_SESSION['billing_cycle_id'] = $existing_cycle['id'];
            echo "<p style='color: green;'>✅ Sesión actualizada con ciclo existente</p>";
            
            echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3>ℹ️ INFORMACIÓN</h3>";
            echo "<p>Ya existe un ciclo de facturación pendiente para este suscriptor.</p>";
            echo "<p>No se creará un nuevo ciclo para evitar duplicados.</p>";
            echo "</div>";
            exit;
        }
        
        echo "<p style='color: green;'>✅ No existe ciclo pendiente, creando uno nuevo</p>";
        
        // Crear ciclo de facturación pendiente (sin transacción para evitar deadlocks)
        $cycle_start = date('Y-m-d');
        $cycle_end = date('Y-m-d', strtotime('+30 days'));
        $due_date = date('Y-m-d', strtotime('+33 days'));
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($subscriber_id, 6, '0', STR_PAD_LEFT) . '-' . str_pad(time(), 4, '0', STR_PAD_LEFT);
        
        echo "<p><strong>Datos del ciclo:</strong></p>";
        echo "<ul>";
        echo "<li>Start: " . $cycle_start . "</li>";
        echo "<li>End: " . $cycle_end . "</li>";
        echo "<li>Due: " . $due_date . "</li>";
        echo "<li>Invoice: " . $invoice_number . "</li>";
        echo "</ul>";
        
        $create_cycle = $pdo->prepare("
            INSERT INTO billing_cycles (
                subscriber_id, 
                plan_type, 
                license_key, 
                cycle_start_date, 
                cycle_end_date, 
                due_date,
                is_active, 
                status, 
                sync_count, 
                api_calls_count, 
                products_synced, 
                amount,
                currency,
                payment_method,
                payment_reference,
                invoice_number,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $execute_data = [
            $subscriber_id,
            $plan_type,
            $license_key,
            $cycle_start,
            $cycle_end,
            $due_date,
            0, // is_active = 0 (pendiente)
            'pending', // status = pending
            0, // sync_count
            0, // api_calls_count
            0, // products_synced
            $plan_data['price'],
            $plan_type === 'free' ? 'USD' : 'CLP',
            'pending', // payment_method = pending
            'PENDING-' . time(), // payment_reference temporal
            $invoice_number,
            date('Y-m-d H:i:s')
        ];
        
        echo "<p><strong>Ejecutando INSERT con datos:</strong></p>";
        echo "<pre>" . print_r($execute_data, true) . "</pre>";
        
        $result = $create_cycle->execute($execute_data);
        
        if (!$result) {
            throw new Exception('Error al ejecutar INSERT: ' . json_encode($create_cycle->errorInfo()));
        }
        
        $billing_cycle_id = $pdo->lastInsertId();
        echo "<p style='color: green;'>✅ Ciclo creado con ID: " . $billing_cycle_id . "</p>";
        
        // Actualizar sesión
        $_SESSION['billing_cycle_id'] = $billing_cycle_id;
        echo "<p style='color: green;'>✅ Sesión actualizada</p>";
        
        // Cerrar conexión explícitamente
        $pdo = null;
        echo "<p style='color: green; font-weight: bold;'>✅ CICLO CREADO EXITOSAMENTE</p>";
        
        echo "<h3>Resultado Final:</h3>";
        echo "<p><strong>Billing Cycle ID:</strong> " . $billing_cycle_id . "</p>";
        echo "<p><strong>Invoice Number:</strong> " . $invoice_number . "</p>";
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo = null;
        }
        echo "<p style='color: red; font-weight: bold;'>❌ ERROR: " . $e->getMessage() . "</p>";
        echo "<p><strong>Stack trace:</strong></p>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "<p style='color: orange;'>⚠️ No es una petición POST</p>";
}
?>
