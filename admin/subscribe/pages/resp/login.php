<?php
/**
 * Página de login
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión
startSecureSession();

// Detectar idioma del navegador
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'es', 0, 2);
$currentLang = $_GET['lang'] ?? ($_SESSION['selected_language'] ?? $browserLang);
$isEnglish = ($currentLang === 'en');

// Guardar idioma seleccionado en sesión
if (isset($_GET['lang'])) {
    $_SESSION['selected_language'] = $_GET['lang'];
}

// Función para traducir texto
function t($spanish, $english) {
    global $isEnglish;
    return $isEnglish ? $english : $spanish;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('Login - DiscogsSync', 'Login - DiscogsSync'); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/pages.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #059669;
        }
        
        .alert {
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }
        
        .link {
            text-align: center;
            margin-top: 1rem;
        }
        
        .link a {
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
        }
        
        .link a:hover {
            text-decoration: underline;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
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
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            letter-spacing: 2px;
            background: linear-gradient(135deg, #1db954, #10b981, #059669, #047857);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 30px rgba(29, 185, 84, 0.4);
            font-size: 1.8rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                        <h2><i class="fas fa-compact-disc spinning-disc"></i> DWooSync</h2>
                </div>
                <div class="nav-menu">
                    <a href="../index.php" class="nav-link">🏠 <?php echo t('Inicio', 'Home'); ?></a>
                    <a href="login.php" class="nav-link btn-primary">🔐 <?php echo t('Login', 'Login'); ?></a>
                    <div class="language-dropdown" style="position: relative; margin-left: 10px;">
                        <button class="nav-link" style="background: #1db954; color: white; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                            <?php if ($isEnglish): ?>
                                <span style="font-size: 1.2em;">🇬🇧</span>
                            <?php else: ?>
                                <span style="font-size: 1.2em;">🇪🇸</span>
                            <?php endif; ?>
                            <span><?php echo $isEnglish ? 'EN' : 'ES'; ?></span>
                            <i class="fas fa-chevron-down" style="font-size: 0.8em;"></i>
                        </button>
                        <div class="language-menu" style="display: none; position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 1000; min-width: 140px;">
                            <a href="?lang=es" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; border-bottom: 1px solid #f3f4f6; <?php echo !$isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                                <span style="font-size: 1.1em; margin-right: 8px;">🇪🇸</span> Español
                            </a>
                            <a href="?lang=en" style="display: block; padding: 10px 15px; color: #374151; text-decoration: none; <?php echo $isEnglish ? 'background: #f0fdf4; font-weight: bold;' : ''; ?>">
                                <span style="font-size: 1.1em; margin-right: 8px;">🇬🇧</span> English
                    </a>
                </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <main style="padding-top: 100px; min-height: 100vh; background: #f8fafc; display: flex; align-items: center; justify-content: center;">
        <div class="card" style="max-width: 400px; width: 100%; margin: 2rem;">
                <div style="text-align: center; margin-bottom: 2rem;">
                <h1 style="color: #1f2937; margin-bottom: 0.5rem;">🔐 <?php echo t('Login', 'Login'); ?></h1>
                <p style="color: #6b7280;"><?php echo t('Panel de Suscriptores DiscogsSync', 'DiscogsSync Subscribers Panel'); ?></p>
                </div>
                
        <div id="error" class="alert alert-error" style="display: none;"></div>
        <div id="success" class="alert alert-success" style="display: none;"></div>
        
        <form id="loginForm">
                    <div class="form-group">
                <label for="email"><?php echo t('Email:', 'Email:'); ?></label>
                <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                <label for="password"><?php echo t('Contraseña:', 'Password:'); ?></label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn"><?php echo t('Iniciar Sesión', 'Login'); ?></button>
        </form>
        
        <div class="link">
            <a href="signup.php"><?php echo t('¿No tienes cuenta? Regístrate aquí', 'Don\'t have an account? Sign up here'); ?></a>
                        </div>
                    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('error');
            const successDiv = document.getElementById('success');
            
            // Ocultar mensajes anteriores
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            if (!email || !password) {
                errorDiv.textContent = '<?php echo t('Email y contraseña son requeridos', 'Email and password are required'); ?>';
                errorDiv.style.display = 'block';
                return;
            }
            
            // Mostrar loading
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = '<?php echo t('Verificando...', 'Verifying...'); ?>';
            submitBtn.disabled = true;
            
            // Enviar datos al servidor
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            
            fetch('process_login.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = data.message + '! Redirigiendo...';
                    successDiv.style.display = 'block';
                    
                    // Redirigir
                    setTimeout(function() {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    errorDiv.textContent = data.message;
                    errorDiv.style.display = 'block';
                    
                    // Si el mensaje indica que debe registrarse, destacar el enlace
                    if (data.message.includes('Debes registrarte') || data.message.includes('No se encontró una cuenta')) {
                        const signupLink = document.querySelector('.link a');
                        signupLink.style.color = '#dc2626';
                        signupLink.style.fontWeight = 'bold';
                        signupLink.style.textDecoration = 'underline';
                        signupLink.style.fontSize = '1.1rem';
                        
                        // Agregar efecto de parpadeo
                        signupLink.style.animation = 'pulse 1s infinite';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                errorDiv.textContent = '<?php echo t('Error de conexión. Intenta nuevamente.', 'Connection error. Try again.'); ?>';
                errorDiv.style.display = 'block';
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Funcionalidad del dropdown de idioma
        document.addEventListener('DOMContentLoaded', function() {
            const languageDropdown = document.querySelector('.language-dropdown');
            const languageButton = languageDropdown.querySelector('button');
            const languageMenu = languageDropdown.querySelector('.language-menu');
            
            languageButton.addEventListener('click', function(e) {
                e.stopPropagation();
                languageMenu.style.display = languageMenu.style.display === 'none' ? 'block' : 'none';
            });
            
            // Cerrar dropdown al hacer clic fuera
            document.addEventListener('click', function() {
                languageMenu.style.display = 'none';
            });
            
            // Prevenir cierre al hacer clic dentro del dropdown
            languageMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>
