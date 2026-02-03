<?php
// Procesar formulario de contacto - Versión sin dependencias

// Iniciar sesión para detectar idioma
session_start();

// Detectar idioma - Inglés por defecto
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
$currentLang = $_GET['lang'] ?? ($_SESSION['selected_language'] ?? 'en');
$isEnglish = ($currentLang === 'en');

// Función para traducir texto
function t($spanish, $english) {
    global $isEnglish;
    return $isEnglish ? $english : $spanish;
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $redirectUrl = 'contact.php' . ($currentLang === 'en' ? '?lang=en' : '');
    header('Location: ' . $redirectUrl);
    exit;
}

// Configuración básica
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validaciones básicas
$errors = [];
if (empty($name)) $errors[] = t('El nombre es obligatorio', 'Name is required');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('El email es obligatorio y debe ser válido', 'Email is required and must be valid');
if (empty($subject)) $errors[] = t('El asunto es obligatorio', 'Subject is required');
if (empty($message)) $errors[] = t('El mensaje es obligatorio', 'Message is required');

$success = false;
$result_message = '';

// Si no hay errores, intentar enviar email usando PHPMailer SMTP
if (empty($errors)) {
    try {
        // Enviar correo usando mail() nativo
        $to = 'support@dwoosync.com';
        $email_subject = t('Contacto desde DWooSync: ', 'Contact from DWooSync: ') . $subject;
        
        $email_message = t(
            "Nuevo mensaje de contacto desde DWooSync:
        
        Nombre: $name
        Email: $email
        Asunto: $subject
        
        Mensaje:
        $message
        
        ---
        Enviado desde el formulario de contacto de DWooSync",
            
            "New contact message from DWooSync:
        
        Name: $name
        Email: $email
        Subject: $subject
        
        Message:
        $message
        
        ---
        Sent from DWooSync contact form"
        );
        
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        $result = mail($to, $email_subject, $email_message, $headers);
        
        if ($result) {
            $success = true;
            $result_message = t('Mensaje enviado correctamente. Te responderemos pronto.', 'Message sent successfully. We will respond to you soon.');
        } else {
            $result_message = t('No se pudo enviar el mensaje. Por favor, inténtalo de nuevo.', 'Could not send the message. Please try again.');
        }
    } catch (Exception $e) {
        $result_message = t('Error al enviar el mensaje: ', 'Error sending message: ') . $e->getMessage();
    }
} else {
    $result_message = t('Por favor, corrige los errores del formulario.', 'Please correct the form errors.');
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo t('Resultado del Contacto - DWooSync', 'Contact Result - DWooSync'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .icon { font-size: 60px; margin-bottom: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .title { font-size: 24px; margin-bottom: 20px; color: #333; }
        .message { font-size: 16px; margin-bottom: 30px; color: #666; line-height: 1.5; }
        .btn { display: inline-block; padding: 12px 24px; margin: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .btn:hover { background: #0056b3; }
        .errors { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: left; }
        .errors ul { margin: 0; padding-left: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="icon success">✓</div>
            <h1 class="title"><?php echo t('¡Mensaje Enviado!', 'Message Sent!'); ?></h1>
            <p class="message"><?php echo htmlspecialchars($result_message); ?></p>
            <a href="contact.php?lang=<?php echo $currentLang; ?>" class="btn"><?php echo t('Enviar Otro Mensaje', 'Send Another Message'); ?></a>
        <?php else: ?>
            <div class="icon error">✗</div>
            <h1 class="title"><?php echo t('Error al Enviar', 'Send Error'); ?></h1>
            <p class="message"><?php echo htmlspecialchars($result_message); ?></p>
            
            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <strong><?php echo t('Errores:', 'Errors:'); ?></strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <a href="contact.php?lang=<?php echo $currentLang; ?>" class="btn"><?php echo t('Intentar de Nuevo', 'Try Again'); ?></a>
        <?php endif; ?>
        
        <a href="../index.php?lang=<?php echo $currentLang; ?>" class="btn"><?php echo t('Volver al Inicio', 'Back to Home'); ?></a>
    </div>
</body>
</html>
