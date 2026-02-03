<?php
/**
 * Obtener datos de un suscriptor para edición
 */

define('API_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/AdminAuth.php';

// Verificar autenticación de administrador
$auth = new AdminAuth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    
    $subscriberId = (int)($_GET['id'] ?? 0);
    
    if (!$subscriberId) {
        throw new Exception('ID de suscriptor requerido');
    }
    
    $subscriber = $db->fetch("SELECT * FROM subscribers WHERE id = :id", ['id' => $subscriberId]);
    
    if (!$subscriber) {
        throw new Exception('Suscriptor no encontrado');
    }
    
    echo json_encode([
        'success' => true,
        'subscriber' => $subscriber
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>





