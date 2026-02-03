<?php
// Test simple de AJAX en pages/
error_log("[AJAX_TEST_PAGES] Test endpoint called");

// Incluir configuración
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo json_encode([
        'success' => true,
        'message' => 'AJAX test endpoint working in pages directory!',
        'timestamp' => date('Y-m-d H:i:s'),
        'post_data' => $_POST,
        'session_active' => isLoggedIn(),
        'subscriber_id' => $_SESSION['subscriber_id'] ?? 'null'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Only POST method allowed'
    ]);
}
?>