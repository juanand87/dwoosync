<?php
define('API_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json');

try {
    $subscriber_id = (int)($_GET['subscriber_id'] ?? 0);
    
    if (!$subscriber_id) {
        echo json_encode(['success' => false, 'message' => 'ID de suscriptor requerido']);
        exit;
    }
    
    $db = Database::getInstance();
    
    // Obtener el último ciclo del suscriptor
    $last_cycle = $db->query("
        SELECT * FROM billing_cycles 
        WHERE subscriber_id = $subscriber_id 
        ORDER BY cycle_end_date DESC 
        LIMIT 1
    ")->fetch();
    
    if ($last_cycle) {
        echo json_encode([
            'success' => true,
            'last_cycle' => [
                'id' => $last_cycle['id'],
                'cycle_start_date' => $last_cycle['cycle_start_date'],
                'cycle_end_date' => $last_cycle['cycle_end_date'],
                'status' => $last_cycle['status'],
                'amount' => $last_cycle['amount']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'last_cycle' => null,
            'message' => 'No se encontraron ciclos para este suscriptor'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>





