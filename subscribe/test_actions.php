<?php
/**
 * Test simple para actions.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Iniciar sesión
startSecureSession();

header('Content-Type: application/json');

try {
    error_log('[Test] Iniciando test de actions.php');
    
    // Verificar autenticación
    if (!isLoggedIn()) {
        throw new Exception('Usuario no autenticado');
    }
    
    if (!isset($_SESSION['subscriber_id'])) {
        throw new Exception('ID de suscriptor no encontrado en sesión');
    }
    
    $subscriberId = $_SESSION['subscriber_id'];
    error_log('[Test] Subscriber ID: ' . $subscriberId);
    
    // Verificar conexión a base de datos
    $db = getDatabase();
    if (!$db) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    error_log('[Test] Conexión a BD exitosa');
    
    // Test simple de consulta
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM billing_cycles WHERE subscriber_id = ?");
    $stmt->execute([$subscriberId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log('[Test] Facturas existentes: ' . $result['count']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Test exitoso',
        'subscriber_id' => $subscriberId,
        'existing_billing_cycles' => $result['count']
    ]);
    
} catch (Exception $e) {
    error_log('[Test] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>



