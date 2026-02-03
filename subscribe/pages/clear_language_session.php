<?php
/**
 * Script para limpiar la sesión de idioma y resetear a inglés por defecto
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Limpiar configuración de idioma
if (isset($_SESSION['selected_language'])) {
    unset($_SESSION['selected_language']);
}

// Redirigir de vuelta a la página principal
header('Location: ../index.php');
exit;
?>