<?php
/**
 * Funciones auxiliares para el sitio de suscripciones
 */

require_once __DIR__ . '/Database.php';

/**
 * Obtener planes de suscripción desde la base de datos
 */
function getSubscriptionPlans($lang = 'es') {
    $isEnglish = ($lang === 'en');
    
    try {
        $db = getDatabase();
        $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC");
        $stmt->execute();
        $plans_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $plans = [];
        foreach ($plans_data as $plan) {
            $plan_id = $plan['plan_type'];
            $plan_name = $plan['plan_name'];
            $plan_price = (float)$plan['price'];
            
            // Traducir nombre si es necesario
            if ($plan_id === 'free') {
                $plan_name = $isEnglish ? 'Free' : 'Gratuito';
            } elseif ($plan_id === 'premium') {
                $plan_name = 'Premium';
            } elseif ($plan_id === 'enterprise') {
                $plan_name = '+Spotify';
            }
            
            // Definir características según el plan
            $features = [];
            switch ($plan_id) {
                case 'free':
                    $features = [
                        $isEnglish ? '10 imports/month' : '10 importaciones/mes',
                        $isEnglish ? 'No support' : 'Sin soporte',
                        $isEnglish ? 'Basic documentation' : 'Documentación básica',
                        $isEnglish ? '1 Domain/Website' : '1 Dominio/Sitio Web',
                        $isEnglish ? 'Updates' : 'Actualizaciones',
                        $isEnglish ? 'Detailed statistics' : 'Estadística detallada',
                        $isEnglish ? 'Spotify Widget' : 'Widget Spotify'
                    ];
                    break;
                case 'premium':
                    $features = [
                        $isEnglish ? 'Unlimited imports' : 'Importaciones ilimitadas',
                        $isEnglish ? 'Priority support' : 'Soporte prioritario',
                        $isEnglish ? '1 Domain/Website' : '1 Dominio/Sitio Web',
                        $isEnglish ? 'Updates' : 'Actualizaciones',
                        $isEnglish ? 'Detailed statistics' : 'Estadística detallada',
                        $isEnglish ? 'Spotify Widget' : 'Widget Spotify'
                    ];
                    break;
                case 'enterprise':
                    $features = [
                        $isEnglish ? 'Unlimited imports' : 'Importaciones ilimitadas',
                        $isEnglish ? 'Spotify integration' : 'Integración con Spotify',
                        $isEnglish ? 'Priority support' : 'Soporte prioritario',
                        $isEnglish ? '1 Domain/Website' : '1 Dominio/Sitio Web',
                        $isEnglish ? 'Updates' : 'Actualizaciones',
                        $isEnglish ? 'Detailed statistics' : 'Estadística detallada',
                        $isEnglish ? 'Spotify Widget' : 'Widget Spotify'
                    ];
                    break;
            }
            
            $plans[] = [
                'id' => $plan_id,
                'name' => $plan_name,
                'price' => $plan_price,
                'featured' => ($plan_id === 'premium'),
                'features' => $features
            ];
        }
        
        return $plans;
        
    } catch (Exception $e) {
        error_log('[SUBSCRIPTION_PLANS] Error obteniendo planes: ' . $e->getMessage());
        
        // Fallback a datos por defecto si hay error
        return [
            [
                'id' => 'free',
                'name' => $isEnglish ? 'Free' : 'Gratuito',
                'price' => 0,
                'featured' => false,
                'features' => [
                    $isEnglish ? '10 imports/month' : '10 importaciones/mes',
                    $isEnglish ? 'No support' : 'Sin soporte',
                    $isEnglish ? 'Basic documentation' : 'Documentación básica',
                    $isEnglish ? '1 Domain/Website' : '1 Dominio/Sitio Web',
                    $isEnglish ? 'Updates' : 'Actualizaciones',
                    $isEnglish ? 'Detailed statistics' : 'Estadística detallada',
                    $isEnglish ? 'Spotify Widget' : 'Widget Spotify'
                ]
            ],
            [
                'id' => 'premium',
                'name' => 'Premium',
                'price' => 22,
                'featured' => true,
                'features' => [
                    $isEnglish ? 'Unlimited imports' : 'Importaciones ilimitadas',
                    $isEnglish ? 'Priority support' : 'Soporte prioritario',
                    $isEnglish ? '1 Domain/Website' : '1 Dominio/Sitio Web',
                    $isEnglish ? 'Updates' : 'Actualizaciones',
                    $isEnglish ? 'Detailed statistics' : 'Estadística detallada',
                    $isEnglish ? 'Spotify Widget' : 'Widget Spotify'
                ]
            ],
            [
                'id' => 'enterprise',
                'name' => '+Spotify',
                'price' => 29,
                'featured' => false,
                'features' => [
                    $isEnglish ? 'Unlimited imports' : 'Importaciones ilimitadas',
                    $isEnglish ? 'Spotify integration' : 'Integración con Spotify',
                    $isEnglish ? 'Priority support' : 'Soporte prioritario',
                    $isEnglish ? '1 Domain/Website' : '1 Dominio/Sitio Web',
                    $isEnglish ? 'Updates' : 'Actualizaciones',
                    $isEnglish ? 'Detailed statistics' : 'Estadística detallada',
                    $isEnglish ? 'Spotify Widget' : 'Widget Spotify'
                ]
            ]
        ];
    }
}

/**
 * Conectar a la base de datos
 */
function getDatabase() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 30,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=30, interactive_timeout=30"
            ]);
        } catch (PDOException $e) {
            error_log('[DATABASE] Error de conexión: ' . $e->getMessage());
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }
    
    return $db;
}

/**
 * Generar clave de licencia con formato estandarizado
 * Por defecto genera para plan free, usar generateLicenseKey($planType) en actions.php para otros planes
 */
function generateLicenseKey($planType = 'free') {
    // Generar license_key según el formato estandarizado
    if ($planType === 'free') {
        // Plan Free: FREE + 14 caracteres
        return 'FREE' . strtoupper(substr(md5(uniqid(rand(), true) . time()), 0, 14));
    } else {
        // Plan Premium/Enterprise: DW + 16 caracteres
        return 'DW' . strtoupper(substr(md5(uniqid(rand(), true) . time()), 0, 16));
    }
}

/**
 * Validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitizar datos de entrada
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Generar hash de contraseña
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verificar contraseña
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Enviar email (usando SMTP con fallback)
 */
function sendEmail($to, $subject, $message, $isHTML = true) {
    // Incluir configuración de email SMTP
    require_once __DIR__ . '/email.php';
    
    // Usar la función SMTP con fallback automático
    return sendEmailSMTP($to, $subject, $message, $isHTML);
}

/**
 * Redirigir con mensaje
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
    header("Location: $url");
    exit;
}

/**
 * Mostrar mensaje
 */
function showMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'] ?? 'info';
        
        echo "<div class='alert alert-{$type}'>{$message}</div>";
        
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

/**
 * Verificar si el usuario está logueado
 */
function isLoggedIn() {
    return isset($_SESSION['subscriber_id']) && !empty($_SESSION['subscriber_id']);
}

/**
 * Obtener usuario actual
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = getDatabase();
    $stmt = $db->prepare("SELECT * FROM subscribers WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    return $stmt->fetch();
}

/**
 * Requerir login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirectWithMessage('login.php', 'Debes iniciar sesión para acceder a esta página', 'error');
    }
}

/**
 * Formatear fecha
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Formatear precio
 */
function formatPrice($price, $currency = '$') {
    return $currency . number_format($price, 2);
}

/**
 * Función para mostrar el botón flotante de WhatsApp
 */
function renderWhatsAppButton($currentLang = 'es') {
    $isEnglish = ($currentLang === 'en');
    $tooltip = $isEnglish ? 'Contact us via WhatsApp!' : '¡Contáctanos por WhatsApp!';
    $ariaLabel = $isEnglish ? 'Contact via WhatsApp' : 'Contactar por WhatsApp';
    
    echo '<!-- Botón flotante de WhatsApp -->
    <a href="#" 
       class="whatsapp-float" 
       id="whatsappBtn"
       data-tooltip="' . htmlspecialchars($tooltip) . '"
       aria-label="' . htmlspecialchars($ariaLabel) . '">
        <i class="fab fa-whatsapp"></i>
    </a>';
}

/**
 * Función para mostrar los estilos CSS del botón de WhatsApp
 */
function renderWhatsAppStyles() {
    echo '<style>
        /* Botón flotante de WhatsApp */
        .whatsapp-float {
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 40px;
            right: 40px;
            background-color: #25d366;
            color: white;
            border-radius: 50px;
            text-align: center;
            font-size: 30px;
            box-shadow: 0 4px 16px rgba(37, 211, 102, 0.3);
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            animation: whatsappPulse 2s infinite;
        }
        
        .whatsapp-float:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(37, 211, 102, 0.5);
            background-color: #128c7e;
            color: white;
            text-decoration: none;
        }
        
        .whatsapp-float:active {
            transform: scale(0.95);
        }
        
        @keyframes whatsappPulse {
            0% { box-shadow: 0 4px 16px rgba(37, 211, 102, 0.3); }
            50% { box-shadow: 0 4px 20px rgba(37, 211, 102, 0.6); }
            100% { box-shadow: 0 4px 16px rgba(37, 211, 102, 0.3); }
        }
        
        /* Tooltip para el botón de WhatsApp */
        .whatsapp-float::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 70px;
            right: 0;
            background: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            font-family: "Inter", sans-serif;
        }
        
        .whatsapp-float:hover::before {
            opacity: 1;
            visibility: visible;
        }
        
        /* Responsive para móviles */
        @media (max-width: 768px) {
            .whatsapp-float {
                width: 55px;
                height: 55px;
                bottom: 20px;
                right: 20px;
                font-size: 26px;
            }
            
            .whatsapp-float::before {
                bottom: 65px;
                font-size: 12px;
                padding: 6px 10px;
            }
        }
    </style>';
}

/**
 * Función para mostrar el JavaScript del botón de WhatsApp
 */
function renderWhatsAppScript($currentLang = 'es') {
    $isEnglish = ($currentLang === 'en');
    $message = $isEnglish 
        ? "Hi! I'm interested in learning more about DWooSync. Could you help me?" 
        : "¡Hola! Me interesa conocer más sobre DWooSync. ¿Podrían ayudarme?";
    
    echo '<script>
        // Funcionalidad del botón de WhatsApp
        document.addEventListener("DOMContentLoaded", function() {
            const whatsappBtn = document.getElementById("whatsappBtn");
            
            if (whatsappBtn) {
                whatsappBtn.addEventListener("click", function(e) {
                    e.preventDefault();
                    
                    // Número de WhatsApp (formato internacional sin + ni espacios)
                    const phoneNumber = "56955858896";
                    
                    // Mensaje predefinido
                    const message = "' . addslashes($message) . '";
                    
                    // Codificar el mensaje para URL
                    const encodedMessage = encodeURIComponent(message);
                    
                    // Crear la URL de WhatsApp
                    const whatsappURL = `https://wa.me/${phoneNumber}?text=${encodedMessage}`;
                    
                    // Abrir WhatsApp en una nueva ventana
                    window.open(whatsappURL, "_blank");
                });
            }
        });
    </script>';
}
?>
