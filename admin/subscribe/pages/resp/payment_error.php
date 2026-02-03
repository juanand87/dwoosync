<?php
// Definir constante para acceso a la API
define('API_ACCESS', true);

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Verificar sesión
session_start();
if (!isset($_SESSION['subscriber_id'])) {
    header('Location: ../index.php');
    exit;
}

$subscriber_id = $_SESSION['subscriber_id'];
$error_type = $_GET['error'] ?? 'error_desconocido';

$error_messages = [
    'datos_incompletos' => 'Los datos del pago están incompletos',
    'plan_invalido' => 'El plan seleccionado no es válido',
    'procesamiento_fallido' => 'Error al procesar el pago',
    'error_desconocido' => 'Ha ocurrido un error inesperado'
];

$error_message = $error_messages[$error_type] ?? $error_messages['error_desconocido'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Pago - DiscogsSync</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 600px;
            width: 90%;
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .error-title {
            font-size: 2.5rem;
            color: #dc2626;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .error-message {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .error-details {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .error-details h3 {
            color: #dc2626;
            margin-bottom: 15px;
        }
        
        .error-details p {
            color: #991b1b;
            margin-bottom: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 30px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e9ecef;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(45deg, #dc2626, #b91c1c);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(220, 38, 38, 0.3);
        }
        
        .help-section {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 15px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }
        
        .help-section h4 {
            color: #0ea5e9;
            margin-bottom: 15px;
        }
        
        .help-section ul {
            list-style: none;
            padding: 0;
        }
        
        .help-section li {
            padding: 8px 0;
            color: #0c4a6e;
        }
        
        .help-section li::before {
            content: '💡';
            margin-right: 10px;
        }
        
        .contact-info {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .contact-info h4 {
            color: #92400e;
            margin-bottom: 10px;
        }
        
        .contact-info p {
            color: #92400e;
            margin: 5px 0;
        }
        
        @media (max-width: 768px) {
            .error-container {
                padding: 30px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    
        .spinning-disc {
            animation: spin 3s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .nav-logo h2 {
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #1db954, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(29, 185, 84, 0.3);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">❌</div>
        <h1 class="error-title">Error en el Pago</h1>
        <p class="error-message">
            Lo sentimos, ha ocurrido un problema al procesar tu pago. 
            No se ha cobrado ningún monto a tu cuenta.
        </p>
        
        <div class="error-details">
            <h3>🔍 Detalles del Error</h3>
            <p><strong>Error:</strong> <?php echo $error_message; ?></p>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i'); ?></p>
            <p><strong>ID de Usuario:</strong> <?php echo $subscriber_id; ?></p>
        </div>
        
        <div class="action-buttons">
            <a href="plans.php" class="btn btn-primary">
                🔄 Intentar Nuevamente
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                🏠 Ir al Dashboard
            </a>
            <a href="contact.php" class="btn btn-danger">
                📞 Contactar Soporte
            </a>
        </div>
        
        <div class="help-section">
            <h4>🛠️ Posibles Soluciones</h4>
            <ul>
                <li>Verifica que tu conexión a internet sea estable</li>
                <li>Intenta con un método de pago diferente</li>
                <li>Verifica que tu cuenta de PayPal tenga fondos suficientes</li>
                <li>Limpia la caché de tu navegador y vuelve a intentar</li>
                <li>Si el problema persiste, contacta a nuestro soporte</li>
            </ul>
        </div>
        
        <div class="contact-info">
            <h4>📞 ¿Necesitas Ayuda?</h4>
            <p><strong>Email:</strong> soporte@dwoosync.com</p>
            <p><strong>Horario:</strong> Lunes a Viernes, 9:00 - 18:00</p>
            <p><strong>Respuesta:</strong> Máximo 24 horas</p>
        </div>
    </div>
    
    <script>
        // Auto-redirigir a planes después de 15 segundos
        setTimeout(() => {
            if (confirm('¿Te gustaría volver a la página de planes para intentar nuevamente?')) {
                window.location.href = 'plans.php';
            }
        }, 15000);
    </script>
</body>
</html>

