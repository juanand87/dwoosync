<?php
// Procesar formulario de contacto - Versión sin dependencias

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.php');
    exit;
}

// Configuración básica
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validaciones básicas
$errors = [];
if (empty($name)) $errors[] = 'El nombre es obligatorio';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email es obligatorio y debe ser válido';
if (empty($subject)) $errors[] = 'El asunto es obligatorio';
if (empty($message)) $errors[] = 'El mensaje es obligatorio';

$success = false;
$result_message = '';

// Si no hay errores, intentar enviar email usando PHPMailer SMTP
if (empty($errors)) {
    try {
        // Enviar correo usando mail() nativo
        $to = 'support@dwoosync.com';
        $email_subject = 'Contacto desde DWooSync: ' . $subject;
        
        $email_message = "
        Nuevo mensaje de contacto desde DWooSync:
        
        Nombre: $name
        Email: $email
        Asunto: $subject
        
        Mensaje:
        $message
        
        ---
        Enviado desde el formulario de contacto de DWooSync
        ";
        
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        
        $result = mail($to, $email_subject, $email_message, $headers);
        
        if ($result) {
            $success = true;
            $result_message = 'Mensaje enviado correctamente. Te responderemos pronto.';
        } else {
            $result_message = 'No se pudo enviar el mensaje. Por favor, inténtalo de nuevo.';
        }
    } catch (Exception $e) {
        $result_message = 'Error al enviar el mensaje: ' . $e->getMessage();
    }
} else {
    $result_message = 'Por favor, corrige los errores del formulario.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Resultado del Contacto</title>
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
            <h1 class="title">¡Mensaje Enviado!</h1>
            <p class="message"><?php echo htmlspecialchars($result_message); ?></p>
            <a href="contact.php" class="btn">Enviar Otro Mensaje</a>
        <?php else: ?>
            <div class="icon error">✗</div>
            <h1 class="title">Error al Enviar</h1>
            <p class="message"><?php echo htmlspecialchars($result_message); ?></p>
            
            <?php if (!empty($errors)): ?>
                <div class="errors">
                    <strong>Errores:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <a href="contact.php" class="btn">Intentar de Nuevo</a>
        <?php endif; ?>
        
        <a href="../index.php" class="btn">Volver al Inicio</a>
    </div>
</body>
</html>
