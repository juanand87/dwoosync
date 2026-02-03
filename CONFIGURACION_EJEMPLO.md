# ⚙️ Guía de Configuración - DiscogsSync

## 🚀 Configuración Inicial

### 1. Configuración de Base de Datos

#### Archivo: `config/database.php`
```php
<?php
// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'discogs_api');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');
define('DB_CHARSET', 'utf8mb4');

// Función de conexión
function getDatabase() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error de conexión BD: " . $e->getMessage());
        throw new Exception("Error de conexión a la base de datos");
    }
}
?>
```

### 2. Configuración Principal

#### Archivo: `config/config.php`
```php
<?php
// Configuración principal del sistema

// Base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'discogs_api');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_password');

// URLs del sistema
define('API_BASE_URL', 'https://tudominio.com/api_discogs/api');
define('SITE_BASE_URL', 'https://tudominio.com/api_discogs');
define('WORDPRESS_SITE_URL', 'https://tudominio.com');

// Configuración de sesiones
define('SESSION_LIFETIME', 3600); // 1 hora
define('SESSION_NAME', 'DISC_SYNC_SESSION');

// Configuración de pagos
define('PAYPAL_CLIENT_ID', 'tu_paypal_client_id');
define('PAYPAL_CLIENT_SECRET', 'tu_paypal_secret');
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
define('STRIPE_SECRET_KEY', 'sk_test_...');

// Configuración de email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'tu_email@gmail.com');
define('SMTP_PASS', 'tu_password_app');

// Límites de uso por plan
define('PLAN_LIMITS', [
    'free' => [
        'sync_limit' => 10,
        'api_calls_unlimited' => true
    ],
    'premium' => [
        'sync_limit' => 100,
        'api_calls_unlimited' => true
    ],
    'enterprise' => [
        'sync_limit' => 999999,
        'api_calls_unlimited' => true
    ]
]);

// Configuración de logs
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', __DIR__ . '/../logs/system.log');

// Configuración de cache
define('CACHE_ENABLED', true);
define('CACHE_TTL', 3600); // 1 hora

// Configuración de seguridad
define('ADMIN_KEYS', [
    'ADMIN_KEY_2024',
    'MANUAL_PAYMENT_KEY'
]);

// Configuración de rate limiting
define('RATE_LIMIT_REQUESTS', 100); // requests por minuto
define('RATE_LIMIT_WINDOW', 60); // ventana en segundos
?>
```

### 3. Configuración de WordPress

#### Archivo: `wp-config.php` (agregar al final)
```php
// Configuración DiscogsSync
define('DISC_SYNC_API_URL', 'https://tudominio.com/api_discogs/api');
define('DISC_SYNC_DEBUG', false); // true para desarrollo
define('DISC_SYNC_CACHE_TTL', 3600);
```

## 🔧 Configuración de Servidor

### 1. Apache (.htaccess)

#### Archivo: `api_discogs/.htaccess`
```apache
RewriteEngine On

# Redirigir HTTP a HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Headers de seguridad
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

# CORS para API
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization"

# Cache para assets estáticos
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
</FilesMatch>

# Proteger archivos sensibles
<Files "*.php">
    <RequireAll>
        Require all granted
    </RequireAll>
</Files>

<Files "config.php">
    Require all denied
</Files>

<Files "database.php">
    Require all denied
</Files>
```

### 2. Nginx

#### Archivo: `nginx.conf` (sección relevante)
```nginx
server {
    listen 443 ssl http2;
    server_name tudominio.com;
    
    # SSL Configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
    
    # API endpoints
    location /api_discogs/api/ {
        try_files $uri $uri/ /api_discogs/api/index.php?$query_string;
        
        # CORS headers
        add_header Access-Control-Allow-Origin "*";
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization";
    }
    
    # Static assets
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }
    
    # Protect sensitive files
    location ~ /(config|database)\.php$ {
        deny all;
    }
}
```

## 🔐 Configuración de Seguridad

### 1. Permisos de Archivos

```bash
# Establecer permisos correctos
find /path/to/api_discogs -type f -name "*.php" -exec chmod 644 {} \;
find /path/to/api_discogs -type d -exec chmod 755 {} \;

# Archivos de configuración solo lectura para web server
chmod 600 config/config.php
chmod 600 config/database.php

# Directorio de logs
mkdir -p logs
chmod 755 logs
chown www-data:www-data logs
```

### 2. Configuración de PHP

#### Archivo: `php.ini` (configuraciones relevantes)
```ini
; Seguridad
expose_php = Off
allow_url_fopen = On
allow_url_include = Off

; Límites de memoria y tiempo
memory_limit = 256M
max_execution_time = 30
max_input_time = 30

; Uploads
upload_max_filesize = 10M
post_max_size = 10M

; Sesiones
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

; Logs
log_errors = On
error_log = /path/to/php_errors.log
```

## 🌐 Configuración de Dominios

### 1. DNS Records

```
# Registros DNS necesarios
tudominio.com.          A      192.168.1.100
api.tudominio.com.      A      192.168.1.100
www.tudominio.com.      CNAME  tudominio.com.
```

### 2. Subdominios

```
# Estructura recomendada
tudominio.com           → WordPress principal
api.tudominio.com       → API DiscogsSync
admin.tudominio.com     → Panel de administración
```

## 📧 Configuración de Email

### 1. SMTP Gmail

#### Archivo: `includes/email.php`
```php
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

function sendEmail($to, $subject, $body, $isHTML = true) {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Remitente y destinatario
        $mail->setFrom(SMTP_USER, 'DiscogsSync');
        $mail->addAddress($to);
        
        // Contenido
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error enviando email: " . $e->getMessage());
        return false;
    }
}
?>
```

## 🔄 Configuración de Webhooks

### 1. PayPal Webhook

#### URL del Webhook
```
https://tudominio.com/api_discogs/api/paypal-webhook.php
```

#### Eventos a suscribir
- `PAYMENT.CAPTURE.COMPLETED`
- `PAYMENT.CAPTURE.DENIED`
- `PAYMENT.CAPTURE.REFUNDED`
- `PAYMENT.CAPTURE.PENDING`

### 2. Stripe Webhook

#### URL del Webhook
```
https://tudominio.com/api_discogs/api/stripe-webhook.php
```

#### Eventos a suscribir
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`
- `payment_intent.requires_action`

## 📊 Configuración de Monitoreo

### 1. Logs del Sistema

#### Archivo: `logs/system.log` (ejemplo)
```
[2024-01-15 10:30:15] INFO: Usuario registrado - ID: 123, Email: user@example.com
[2024-01-15 10:30:16] INFO: Licencia creada - Key: DISC-1234567890
[2024-01-15 10:30:17] INFO: Ciclo de suscripción creado - Subscriber: 123
[2024-01-15 10:30:18] INFO: Pago procesado - Payment ID: PAY_123, Status: completed
[2024-01-15 10:30:19] INFO: Suscripción activada - Subscriber: 123
```

### 2. Métricas de Rendimiento

#### Archivo: `monitoring/metrics.php`
```php
<?php
// Métricas del sistema
$metrics = [
    'total_users' => getTotalUsers(),
    'active_subscriptions' => getActiveSubscriptions(),
    'api_calls_today' => getApiCallsToday(),
    'sync_operations_today' => getSyncOperationsToday(),
    'revenue_this_month' => getRevenueThisMonth(),
    'average_response_time' => getAverageResponseTime()
];

// Enviar a servicio de monitoreo
sendMetricsToMonitoring($metrics);
?>
```

## 🧪 Configuración de Testing

### 1. Entorno de Desarrollo

#### Archivo: `config/config.dev.php`
```php
<?php
// Configuración para desarrollo
define('DEBUG_MODE', true);
define('LOG_LEVEL', 'DEBUG');
define('CACHE_ENABLED', false);

// Base de datos de testing
define('DB_NAME', 'discogs_api_test');

// URLs de desarrollo
define('API_BASE_URL', 'http://localhost/api_discogs/api');
define('SITE_BASE_URL', 'http://localhost/api_discogs');

// PayPal sandbox
define('PAYPAL_CLIENT_ID', 'sandbox_client_id');
define('PAYPAL_CLIENT_SECRET', 'sandbox_secret');

// Stripe test mode
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...');
define('STRIPE_SECRET_KEY', 'sk_test_...');
?>
```

### 2. Scripts de Testing

#### Archivo: `tests/run_tests.php`
```php
<?php
// Ejecutar todos los tests
require_once 'test_database.php';
require_once 'test_api_endpoints.php';
require_once 'test_payment_flow.php';
require_once 'test_license_validation.php';

echo "🧪 Ejecutando tests del sistema...\n";

// Tests de base de datos
testDatabaseConnection();
testTableStructure();

// Tests de API
testLicenseValidation();
testUsageTracking();
testDiscogsAPI();

// Tests de pagos
testPaymentConfirmation();
testWebhooks();

echo "✅ Todos los tests completados\n";
?>
```

## 🚀 Configuración de Producción

### 1. Optimizaciones

#### Archivo: `config/production.php`
```php
<?php
// Configuración para producción
define('DEBUG_MODE', false);
define('LOG_LEVEL', 'ERROR');
define('CACHE_ENABLED', true);

// Optimizaciones de BD
define('DB_PERSISTENT', true);
define('DB_OPTIONS', [
    PDO::ATTR_PERSISTENT => true,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
]);

// Cache Redis
define('REDIS_HOST', 'localhost');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', 'tu_redis_password');
?>
```

### 2. Backup Automático

#### Archivo: `scripts/backup.php`
```php
<?php
// Script de backup automático
$backup_file = 'backups/discogs_api_' . date('Y-m-d_H-i-s') . '.sql';

$command = "mysqldump -h " . DB_HOST . " -u " . DB_USER . " -p" . DB_PASS . " " . DB_NAME . " > " . $backup_file;

exec($command, $output, $return_code);

if ($return_code === 0) {
    echo "✅ Backup creado: $backup_file\n";
} else {
    echo "❌ Error creando backup\n";
}
?>
```

---

*Guía de configuración - Última actualización: 2024-01-15*

