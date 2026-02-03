<?php
/**
 * Cerrar sección de bienvenida
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión de forma segura
startSecureSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_welcome') {
    // Marcar como mostrada en la sesión
    $_SESSION['welcome_shown'] = true;
    echo json_encode(['success' => true, 'message' => 'Sección de bienvenida cerrada']);
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>
