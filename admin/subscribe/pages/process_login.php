<?php
/**
 * Procesar login de suscriptores - Versión simplificada
 */

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Incluir configuración y funciones del sistema
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión segura (igual que el resto del sistema)
startSecureSession();

// Detectar y preservar idioma
$currentLang = $_SESSION['selected_language'] ?? 'en';

// Función para enviar respuesta JSON
function sendJsonResponse($success, $message, $redirect = null) {
    header('Content-Type: application/json');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    if ($redirect) {
        $response['redirect'] = $redirect;
    }
    echo json_encode($response);
    exit;
}

try {
    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Método no permitido');
    }
    
    // Obtener datos del formulario
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirectUrl = $_POST['redirect'] ?? 'dashboard.php'; // URL de redirección personalizada o dashboard por defecto
    
    // Decodificar URL de redirección si viene codificada
    if ($redirectUrl !== 'dashboard.php') {
        $redirectUrl = urldecode($redirectUrl);
    }
    
    // Agregar parámetro de idioma a la URL de redirección
    if (strpos($redirectUrl, '?') !== false) {
        $redirectUrl .= '&lang=' . $currentLang;
    } else {
        $redirectUrl .= '?lang=' . $currentLang;
    }
    
    // Validar datos
    if (empty($email) || empty($password)) {
        sendJsonResponse(false, 'Email y contraseña son requeridos');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Email inválido');
    }
    
    // Usar la función getDatabase() del sistema
    $pdo = getDatabase();
    
    // Buscar usuario
    $stmt = $pdo->prepare('SELECT * FROM subscribers WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendJsonResponse(false, 'No se encontró una cuenta con este correo electrónico. Debes registrarte primero.');
    }
    
    // Verificar si la cuenta está activa - pero permitir acceso para renovar
    if ($user['status'] !== 'active') {
        // Crear sesión temporal para permitir acceso al dashboard para renovar
        $_SESSION['subscriber_id'] = $user['id'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_domain'] = $user['domain'];
        $_SESSION['user_plan'] = $user['plan_type'];
        $_SESSION['login_time'] = time();
        $_SESSION['account_status'] = $user['status']; // Guardar estado para mostrar en dashboard
        
        // Obtener la licencia del suscriptor (incluso si está inactiva)
        $licenseStmt = $pdo->prepare('SELECT license_key FROM licenses WHERE subscriber_id = ? LIMIT 1');
        $licenseStmt->execute([$user['id']]);
        $license = $licenseStmt->fetch();
        if ($license) {
            $_SESSION['license_key'] = $license['license_key'];
        }
        
        // Permitir acceso pero con advertencia
        sendJsonResponse(true, 'Acceso permitido para renovar suscripción', $redirectUrl);
    }
    
    // Verificar contraseña
    if (!password_verify($password, $user['password'])) {
        sendJsonResponse(false, 'Contraseña incorrecta. Verifica tu contraseña e intenta nuevamente.');
    }
    
    // Actualizar último login
    $updateStmt = $pdo->prepare('UPDATE subscribers SET last_login = NOW() WHERE id = ?');
    $updateStmt->execute([$user['id']]);
    
    // Crear sesión
    $_SESSION['subscriber_id'] = $user['id'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_domain'] = $user['domain'];
    $_SESSION['user_plan'] = $user['plan_type'];
    $_SESSION['login_time'] = time();
    
    // Obtener la licencia del suscriptor para el dashboard
    $licenseStmt = $pdo->prepare('SELECT license_key FROM licenses WHERE subscriber_id = ? AND status = "active" LIMIT 1');
    $licenseStmt->execute([$user['id']]);
    $license = $licenseStmt->fetch();
    if ($license) {
        $_SESSION['license_key'] = $license['license_key'];
    }
    
    // Respuesta exitosa
    sendJsonResponse(true, 'Login exitoso', $redirectUrl);
    
} catch (Exception $e) {
    // Log del error
    error_log('Login error: ' . $e->getMessage());
    sendJsonResponse(false, 'Error interno del servidor');
} catch (Error $e) {
    // Log del error fatal
    error_log('Login fatal error: ' . $e->getMessage());
    sendJsonResponse(false, 'Error interno del servidor');
}
?>