<?php
/**
 * API REST para Discogs Importer
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración y clases
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/LicenseManager.php';
require_once __DIR__ . '/../classes/CacheManager.php';
require_once __DIR__ . '/../classes/DiscogsAPI.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/endpoints/import_tracking.php';

// Inicializar logger
$logger = new Logger();

// Obtener método y endpoint
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Si accedemos directamente al archivo index.php, usar parámetro 'endpoint'
if (strpos($path, '/api/index.php') !== false) {
    $endpoint = $_GET['endpoint'] ?? '';
} else {
    // Si usamos rewrite, extraer del path
    $path = str_replace('/api_discogs/api/', '', $path);
    $pathParts = explode('/', trim($path, '/'));
    $endpoint = $pathParts[0] ?? '';
}

// Obtener parámetros
$params = array_merge($_GET, $_POST);
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $params = array_merge($params, $input);
}

// Headers de respuesta
header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar modo de mantenimiento
    if (isMaintenanceMode()) {
        sendResponse(503, [
            'success' => false,
            'error' => 'Servicio en mantenimiento',
            'message' => 'El servicio está temporalmente no disponible'
        ]);
    }

    // Debug: Log endpoint y parámetros
    error_log("API Debug - Endpoint: $endpoint, Method: $method, Params: " . json_encode($params));
    
    // Enrutar peticiones
    $response = routeRequest($method, $endpoint, $params);

    // Enviar respuesta
    sendResponse(200, $response);

} catch (Exception $e) {
    $logger->error('Error en API: ' . $e->getMessage(), [
        'method' => $method,
        'path' => $path,
        'params' => $params
    ]);

    sendResponse(500, [
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => 'Ha ocurrido un error inesperado'
    ]);
}

/**
 * Enrutar peticiones
 */
function routeRequest($method, $endpoint, $params) {
    switch ($endpoint) {
        case 'search':
            return handleSearch($method, $params);
        
        case 'versions':
            return handleVersions($method, $params);
        
        case 'release':
            return handleRelease($method, $params);
        
        case 'artist':
            return handleArtist($method, $params);
        
        case 'image':
            return handleImage($method, $params);
        
        case 'license':
            return handleLicense($method, $params);
        
        case 'validate_license':
            error_log("API Debug - Llamando handleValidateLicense");
            return handleValidateLicense($method, $params);
        
        case 'get_subscriber_id':
            return handleGetSubscriberId($method, $params);
        
        case 'get_usage_stats':
            return handleGetUsageStats($method, $params);
        
        case 'subscription':
            return handleSubscription($method, $params);
        
        case 'stats':
            return handleStats($method, $params);
        
        case 'import_track':
            return trackImport($params);
        
        case 'import_stats':
            return getImportStats($params['subscriber_id'] ?? 0);
        
        case 'import_limits':
            return getImportLimits($params['subscriber_id'] ?? 0);
        
        case 'health':
            return handleHealth($method, $params);
        
        case 'test-discogs-connection':
            return handleTestDiscogsConnection($method, $params);
        
        case 'test-discogs-oauth-connection':
            return handleTestDiscogsOAuthConnection($method, $params);
        
        case 'increment_sync_count':
            return handleIncrementSyncCount($method, $params);
        
        default:
            error_log("API Debug - Endpoint no encontrado: '$endpoint'");
            return [
                'success' => false,
                'error' => 'Endpoint no encontrado',
                'message' => 'El endpoint solicitado no existe'
            ];
    }
}

/**
 * Manejar búsquedas
 */
function handleSearch($method, $params) {
    if ($method !== 'GET') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    // Validar licencia
    $auth = validateAuth($params);
    if (!$auth['valid']) {
        return $auth;
    }

    $required = ['q'];
    foreach ($required as $field) {
        if (empty($params[$field])) {
            return ['success' => false, 'error' => "Campo requerido: {$field}"];
        }
    }

    // Obtener claves de Discogs del usuario
    $discogsApiKey = $params['discogs_api_key'] ?? '';
    $discogsApiSecret = $params['discogs_api_secret'] ?? '';
    
    if (empty($discogsApiKey)) {
        return ['success' => false, 'error' => 'Clave de API de Discogs requerida'];
    }
    
    $discogs = new DiscogsAPI($discogsApiKey, $discogsApiSecret);
    $filters = array_intersect_key($params, array_flip(['format', 'country', 'year', 'label', 'genre', 'style']));
    
    try {
        $result = $discogs->searchMasters($params['q'], $filters);
        
        if ($result['success']) {
            logApiRequest($auth['subscriber_id'], $auth['license_key'], 'search', 'GET', $result);
            
            // Incluir warning si está en período de gracia
            if (isset($auth['warning'])) {
                $result['warning'] = $auth['warning'];
            }
            
            return $result;
        }

        // Log del error específico
        error_log('[Search Debug] Error en searchMasters: ' . json_encode($result));
        return $result;
    } catch (Exception $e) {
        error_log('Error en searchMasters: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()];
    }
}

/**
 * Manejar versiones de master
 */
function handleVersions($method, $params) {
    if ($method !== 'GET') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    $auth = validateAuth($params);
    if (!$auth['valid']) {
        return $auth;
    }

    if (empty($params['master_id'])) {
        return ['success' => false, 'error' => 'Campo requerido: master_id'];
    }

    // Obtener claves de Discogs del usuario
    $discogsApiKey = $params['discogs_api_key'] ?? '';
    $discogsApiSecret = $params['discogs_api_secret'] ?? '';
    
    if (empty($discogsApiKey)) {
        return ['success' => false, 'error' => 'Clave de API de Discogs requerida'];
    }
    
    $discogs = new DiscogsAPI($discogsApiKey, $discogsApiSecret);
    $filters = array_intersect_key($params, array_flip(['format', 'country', 'year', 'label']));
    
    $result = $discogs->getMasterVersions($params['master_id'], $filters);
    
    if ($result['success']) {
        $processed = $discogs->processMasterVersions($result['data']);
        logApiRequest($auth['subscriber_id'], $auth['license_key'], 'versions', 'GET', $processed);
        return $processed;
    }

    return $result;
}

/**
 * Manejar detalles de release
 */
function handleRelease($method, $params) {
    if ($method !== 'GET') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    // Validar licencia (el endpoint release se usa tanto para búsquedas como para importaciones)
    // Para importaciones, no actualizamos api_calls_count
    $updateUsage = !isset($params['from_import']) || $params['from_import'] !== 'true';
    $auth = validateAuth($params, $updateUsage);
    if (!$auth['valid']) {
        return $auth;
    }

    if (empty($params['release_id'])) {
        return ['success' => false, 'error' => 'Campo requerido: release_id'];
    }

    // Obtener claves de Discogs del usuario
    $discogsApiKey = $params['discogs_api_key'] ?? '';
    $discogsApiSecret = $params['discogs_api_secret'] ?? '';
    
    if (empty($discogsApiKey)) {
        return ['success' => false, 'error' => 'Clave de API de Discogs requerida'];
    }
    
    if (empty($discogsApiSecret)) {
        return ['success' => false, 'error' => 'Secreto de API de Discogs requerido'];
    }
    
    $discogs = new DiscogsAPI($discogsApiKey, $discogsApiSecret);
    $result = $discogs->getRelease($params['release_id']);
    
    if ($result['success']) {
        logApiRequest($auth['subscriber_id'], $auth['license_key'], 'release', 'GET', $result['data']);
    }

    return $result;
}

/**
 * Manejar detalles de artista
 */
function handleArtist($method, $params) {
    if ($method !== 'GET') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    $auth = validateAuth($params);
    if (!$auth['valid']) {
        return $auth;
    }

    if (empty($params['artist_id'])) {
        return ['success' => false, 'error' => 'Campo requerido: artist_id'];
    }

    // Obtener claves de Discogs del usuario
    $discogsApiKey = $params['discogs_api_key'] ?? '';
    $discogsApiSecret = $params['discogs_api_secret'] ?? '';
    
    if (empty($discogsApiKey)) {
        return ['success' => false, 'error' => 'Clave de API de Discogs requerida'];
    }
    
    $discogs = new DiscogsAPI($discogsApiKey, $discogsApiSecret);
    $result = $discogs->getArtistDetails($params['artist_id']);
    
    if ($result['success']) {
        logApiRequest($auth['subscriber_id'], $auth['license_key'], 'artist', 'GET', $result['data']);
    }

    return $result;
}

/**
 * Manejar imágenes
 */
function handleImage($method, $params) {
    if ($method !== 'GET') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    $auth = validateAuth($params);
    if (!$auth['valid']) {
        return $auth;
    }

    if (empty($params['release_id'])) {
        return ['success' => false, 'error' => 'Campo requerido: release_id'];
    }

    // Obtener claves de Discogs del usuario
    $discogsApiKey = $params['discogs_api_key'] ?? '';
    $discogsApiSecret = $params['discogs_api_secret'] ?? '';
    
    if (empty($discogsApiKey)) {
        return ['success' => false, 'error' => 'Clave de API de Discogs requerida'];
    }
    
    $discogs = new DiscogsAPI($discogsApiKey, $discogsApiSecret);
    $size = $params['size'] ?? 'large';
    $result = $discogs->getReleaseImage($params['release_id'], $size);
    
    if ($result['success']) {
        logApiRequest($auth['subscriber_id'], $auth['license_key'], 'image', 'GET', $result);
    }

    return $result;
}

/**
 * Manejar licencias
 */
function handleLicense($method, $params) {
    $licenseManager = new LicenseManager();

    switch ($method) {
        case 'GET':
            if (empty($params['license_key'])) {
                return ['success' => false, 'error' => 'Campo requerido: license_key'];
            }
            return $licenseManager->validateLicense($params['license_key'], $params['domain'] ?? '');

        case 'POST':
            // Crear nueva licencia
            $required = ['email', 'domain', 'first_name', 'last_name'];
            foreach ($required as $field) {
                if (empty($params[$field])) {
                    return ['success' => false, 'error' => "Campo requerido: {$field}"];
                }
            }
            return $licenseManager->createSubscription($params);

        default:
            return ['success' => false, 'error' => 'Método no permitido'];
    }
}

/**
 * Manejar suscripciones
 */
function handleSubscription($method, $params) {
    $licenseManager = new LicenseManager();

    switch ($method) {
        case 'GET':
            if (empty($params['subscription_code'])) {
                return ['success' => false, 'error' => 'Campo requerido: subscription_code'];
            }
            $info = $licenseManager->getSubscriptionInfo($params['subscription_code']);
            return $info ? ['success' => true, 'data' => $info] : ['success' => false, 'error' => 'Suscripción no encontrada'];

        case 'PUT':
            if (empty($params['subscription_code'])) {
                return ['success' => false, 'error' => 'Campo requerido: subscription_code'];
            }
            $expirationDate = $params['expiration_date'] ?? date('Y-m-d H:i:s', strtotime('+1 month'));
            $success = $licenseManager->renewSubscription($params['subscription_code'], $expirationDate);
            return ['success' => $success, 'message' => $success ? 'Suscripción renovada' : 'Error renovando suscripción'];

        default:
            return ['success' => false, 'error' => 'Método no permitido'];
    }
}

/**
 * Manejar estadísticas
 */
function handleStats($method, $params) {
    if ($method !== 'GET') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    $auth = validateAuth($params);
    if (!$auth['valid']) {
        return $auth;
    }

    $db = Database::getInstance();
    $logger = new Logger();

    $stats = [
        'api_stats' => $logger->getLogStats(7),
        'database_stats' => $db->getDatabaseStats(),
        'cache_stats' => (new CacheManager())->getStats()
    ];

    return ['success' => true, 'data' => $stats];
}

/**
 * Manejar health check
 */
function handleHealth($method, $params) {
    if ($method !== 'GET') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    $db = Database::getInstance();
    
    try {
        // Verificar conexión a base de datos
        $db->query("SELECT 1");
        $dbStatus = 'ok';
    } catch (Exception $e) {
        $dbStatus = 'error';
    }

    return [
        'success' => true,
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => API_VERSION,
        'database' => $dbStatus,
        'maintenance_mode' => isMaintenanceMode()
    ];
}

/**
 * Manejar validación de licencia
 */
function handleValidateLicense($method, $params) {
    if ($method !== 'POST') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    $licenseKey = $params['license_key'] ?? '';
    $domain = $params['domain'] ?? '';

    if (empty($licenseKey)) {
        return [
            'success' => false,
            'valid' => false,
            'error' => 'LICENSE_KEY_REQUIRED',
            'message' => 'License key requerida'
        ];
    }

    try {
        // Extraer solo el dominio base (sin protocolo y sin subdirectorios)
        $baseDomain = parse_url($domain, PHP_URL_HOST) ?: $domain;
        
        $licenseManager = new LicenseManager();
        $validation = $licenseManager->validateLicense($licenseKey, $baseDomain);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'valid' => false,
                'error' => $validation['error'],
                'message' => $validation['message'],
                'registered_domain' => $validation['registered_domain'] ?? null
            ];
        }

        // Verificar límites de uso
        $license = $validation['license'];
        $limits = $licenseManager->checkUsageLimits($license['subscriber_id'], $license['plan_type']);

        if (!$limits['allowed']) {
            return [
                'success' => false,
                'valid' => false,
                'error' => 'USAGE_LIMIT_EXCEEDED',
                'message' => $limits['error']
            ];
        }

        $response = [
            'success' => true,
            'valid' => true,
            'license' => $license,
            'subscriber_id' => $license['subscriber_id'],
            'license_key' => $licenseKey,
            'plan_type' => $license['plan_type'],
            'expires_at' => $license['expires_at'],
            'status' => $license['status'],
            'usage_limit' => $license['usage_limit']
        ];
        
        // Incluir warning si está en período de gracia
        if (isset($validation['warning'])) {
            $response['warning'] = $validation['warning'];
        }
        
        return $response;

    } catch (Exception $e) {
        return [
            'success' => false,
            'valid' => false,
            'error' => 'INTERNAL_ERROR',
            'message' => 'Error interno del servidor: ' . $e->getMessage()
        ];
    }
}

/**
 * Validar autenticación con seguridad reforzada
 */
function validateAuth($params, $updateUsage = true) {
    $licenseKey = $params['license_key'] ?? '';
    $domain = $params['domain'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $endpoint = $params['endpoint'] ?? 'unknown';

    // Log de intento de acceso
    $logger = new Logger();
    $logger->logSecurityEvent('auth_attempt', [
        'ip' => $ip,
        'user_agent' => $userAgent,
        'license_key' => substr($licenseKey, 0, 8) . '...',
        'domain' => $domain,
        'endpoint' => $endpoint,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    if (empty($licenseKey)) {
        $logger->logSecurityEvent('auth_failed', [
            'reason' => 'missing_license_key',
            'ip' => $ip,
            'domain' => $domain
        ]);
        return ['success' => false, 'valid' => false, 'error' => 'License key requerida'];
    }

    // Verificar rate limiting por IP
    if (isRateLimited($ip)) {
        $logger->logSecurityEvent('rate_limit_exceeded', [
            'ip' => $ip,
            'license_key' => substr($licenseKey, 0, 8) . '...',
            'domain' => $domain
        ]);
        return ['success' => false, 'valid' => false, 'error' => 'Demasiadas peticiones. Intenta más tarde.'];
    }

    // Extraer solo el dominio base (sin protocolo y sin subdirectorios)
    $baseDomain = parse_url($domain, PHP_URL_HOST) ?: $domain;
    
    $licenseManager = new LicenseManager();
    $validation = $licenseManager->validateLicense($licenseKey, $baseDomain);

    if (!$validation['valid']) {
        $logger->logSecurityEvent('auth_failed', [
            'reason' => 'invalid_license',
            'ip' => $ip,
            'license_key' => substr($licenseKey, 0, 8) . '...',
            'domain' => $domain,
            'error' => $validation['error']
        ]);
        return [
            'success' => false, 
            'valid' => false, 
            'error' => $validation['error'],
            'message' => $validation['message'],
            'registered_domain' => $validation['registered_domain'] ?? null
        ];
    }

    // Verificar límites de uso
    $license = $validation['license'];
    $limits = $licenseManager->checkUsageLimits($license['subscriber_id'], $license['plan_type']);

    if (!$limits['allowed']) {
        $logger->logSecurityEvent('usage_limit_exceeded', [
            'ip' => $ip,
            'subscriber_id' => $license['subscriber_id'],
            'license_key' => substr($licenseKey, 0, 8) . '...',
            'domain' => $domain,
            'error' => $limits['error']
        ]);
        return ['success' => false, 'valid' => false, 'error' => $limits['error']];
    }

    // Actualizar último uso de la licencia solo si se solicita
    if ($updateUsage) {
        $licenseManager->updateLastUsedAsync($licenseKey);
    }

    // Log de acceso exitoso
    $logger->logSecurityEvent('auth_success', [
        'ip' => $ip,
        'subscriber_id' => $license['subscriber_id'],
        'license_key' => substr($licenseKey, 0, 8) . '...',
        'domain' => $domain,
        'endpoint' => $endpoint
    ]);

    $response = [
        'success' => true,
        'valid' => true,
        'subscriber_id' => $license['subscriber_id'],
        'license_key' => $licenseKey,
        'plan_type' => $license['plan_type']
    ];
    
    // Incluir warning si está en período de gracia
    if (isset($validation['warning'])) {
        $response['warning'] = $validation['warning'];
    }
    
    return $response;
}

/**
 * Registrar petición de API
 */
function logApiRequest($subscriberId, $licenseKey, $endpoint, $method, $response) {
    $logger = new Logger();
    $startTime = microtime(true);
    $responseTime = (microtime(true) - $startTime) * 1000; // en milisegundos

    $logger->logApiRequest(
        $subscriberId,
        $licenseKey,
        $endpoint,
        $method,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $_REQUEST,
        $response,
        $responseTime,
        200,
        null
    );
}

/**
 * Verificar modo de mantenimiento
 */
function isMaintenanceMode() {
    try {
        $db = Database::getInstance();
        $result = $db->fetch("SELECT config_value FROM system_config WHERE config_key = 'maintenance_mode'");
        return $result && $result['config_value'] === '1';
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verificar rate limiting por IP
 */
function isRateLimited($ip) {
    try {
        $db = Database::getInstance();
        $oneMinuteAgo = date('Y-m-d H:i:s', strtotime('-1 minute'));
        
        // Contar peticiones en el último minuto
        $sql = "SELECT COUNT(*) as count FROM api_logs 
                WHERE ip_address = :ip 
                AND created_at >= :one_minute_ago";
        
        $result = $db->fetch($sql, [
            'ip' => $ip,
            'one_minute_ago' => $oneMinuteAgo
        ]);
        
        // Límite: 1000 peticiones por minuto por IP (aumentado para desarrollo)
        return $result['count'] >= 1000;
        
    } catch (Exception $e) {
        error_log("Error verificando rate limit: " . $e->getMessage());
        return false; // En caso de error, permitir la petición
    }
}

/**
 * Manejar prueba de conexión con Discogs API
 */
function handleTestDiscogsConnection($method, $params) {
    if ($method !== 'POST') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }
    
    $api_key = $params['api_key'] ?? '';
    
    if (empty($api_key)) {
        return [
            'success' => false,
            'error' => 'API Key requerida',
            'message' => 'Debe proporcionar una API Key de Discogs'
        ];
    }
    
    try {
        // URL de la API de Discogs para hacer una llamada de prueba
        $discogs_url = 'https://api.discogs.com/database/search?q=test&type=release&per_page=1';
        
        // Headers para la llamada a Discogs
        $headers = array(
            'User-Agent' => 'DiscogsSync/1.0 +https://www.dwoosync.com',
            'Authorization' => 'Discogs token=' . $api_key
        );
        
        // Hacer la llamada a Discogs
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $discogs_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            return [
                'success' => false,
                'error' => 'Error de conexión',
                'message' => 'Error cURL: ' . $curl_error
            ];
        }
        
        if ($http_code === 401) {
            return [
                'success' => false,
                'error' => 'API Key inválida',
                'message' => 'La API Key proporcionada no es válida o no está autorizada'
            ];
        }
        
        if ($http_code === 403) {
            return [
                'success' => false,
                'error' => 'API Key sin permisos',
                'message' => 'La API Key no tiene permisos suficientes para acceder a la API de Discogs'
            ];
        }
        
        if ($http_code !== 200) {
            return [
                'success' => false,
                'error' => 'Error HTTP',
                'message' => 'Error HTTP: ' . $http_code
            ];
        }
        
        $data = json_decode($response, true);
        
        if (!$data) {
            return [
                'success' => false,
                'error' => 'Respuesta inválida',
                'message' => 'La respuesta de Discogs API no es válida'
            ];
        }
        
        // Si llegamos aquí, la conexión fue exitosa
        return [
            'success' => true,
            'message' => 'Conexión con Discogs API exitosa',
            'data' => [
                'results_count' => count($data['results'] ?? []),
                'http_code' => $http_code
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error interno',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Manejar prueba de conexión OAuth con Discogs API
 */
function handleTestDiscogsOAuthConnection($method, $params) {
    if ($method !== 'POST') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }
    
    $api_key = $params['api_key'] ?? '';
    $api_secret = $params['api_secret'] ?? '';
    
    if (empty($api_key) || empty($api_secret)) {
        return [
            'success' => false,
            'error' => 'Credenciales requeridas',
            'message' => 'Debe proporcionar tanto la API Key como el Secret de Discogs'
        ];
    }
    
    try {
        // Validar que las credenciales tengan el formato correcto
        if (strlen($api_key) < 10 || strlen($api_secret) < 10) {
            return [
                'success' => false,
                'error' => 'Credenciales inválidas',
                'message' => 'Las credenciales proporcionadas no tienen el formato correcto'
            ];
        }
        
        // Hacer una búsqueda real usando la misma lógica que el plugin
        $discogs_url = 'https://api.discogs.com/database/search?q="test"&type=release&per_page=1';
        
        $headers = [
            'User-Agent: DiscogsAPI/1.0 +https://discogs.com',
            'Authorization: Discogs key=' . $api_key . ', secret=' . $api_secret
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $discogs_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'Error de conexión',
                'message' => 'Error cURL: ' . $error
            ];
        }
        
        if ($httpCode === 401) {
            return [
                'success' => false,
                'error' => 'Credenciales inválidas',
                'message' => 'Las credenciales de Discogs no son válidas'
            ];
        }
        
        if ($httpCode === 403) {
            return [
                'success' => false,
                'error' => 'Credenciales sin permisos',
                'message' => 'Las credenciales no tienen permisos suficientes para acceder a la API de Discogs'
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'Error HTTP',
                'message' => 'Error HTTP: ' . $httpCode
            ];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Respuesta inválida',
                'message' => 'La respuesta de Discogs API no es válida'
            ];
        }
        
        // Si llegamos aquí, las credenciales funcionan correctamente
        return [
            'success' => true,
            'message' => 'Conexión con Discogs API exitosa - Credenciales válidas',
            'data' => [
                'api_key_length' => strlen($api_key),
                'api_secret_length' => strlen($api_secret),
                'http_code' => $httpCode,
                'results_count' => count($data['results'] ?? []),
                'auth_method' => 'Discogs API Key + Secret'
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error interno',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Manejar incremento de sync_count
 */
function handleIncrementSyncCount($method, $params) {
    if ($method !== 'POST') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    // Validar autenticación sin actualizar uso (solo validar licencia)
    $auth = validateAuth($params, false);
    if (!$auth['valid']) {
        return $auth;
    }

    try {
        $db = Database::getInstance();
        
        // Obtener límite de uso de la licencia
        $license = $db->fetch("
            SELECT usage_limit FROM licenses 
            WHERE subscriber_id = :subscriber_id
        ", [
            'subscriber_id' => $auth['subscriber_id']
        ]);
        
        if (!$license) {
            return ['success' => false, 'error' => 'No se encontró licencia para este suscriptor'];
        }
        
        $usage_limit = $license['usage_limit'];
        
        // Buscar último billing cycle del suscriptor
        $lastBillingCycle = $db->query("
            SELECT id, sync_count, cycle_end_date, status, is_active
            FROM billing_cycles 
            WHERE subscriber_id = :subscriber_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ", [
            'subscriber_id' => $auth['subscriber_id']
        ])->fetch();
        
        if (!$lastBillingCycle) {
            return ['success' => false, 'error' => 'No se encontró billing cycle para este suscriptor'];
        }
        
        // VALIDAR LÍMITE: Verificar si se puede realizar la importación
        $current_sync_count = $lastBillingCycle['sync_count'];
        
        error_log("[API] Validación de límite - sync_count: $current_sync_count, usage_limit: $usage_limit");
        
        // Solo verificar límites si no es ilimitado (-1)
        if ($usage_limit > 0 && $current_sync_count >= $usage_limit) {
            error_log("[API] LÍMITE EXCEDIDO - Bloqueando importación para subscriber_id: " . $auth['subscriber_id']);
            return [
                'success' => false, 
                'error' => 'USAGE_LIMIT_EXCEEDED',
                'message' => 'Has alcanzado el límite de importaciones para tu plan actual (' . $usage_limit . ' por mes). <a href="https://www.dwoosync.com" target="_blank" style="color: #0073aa; text-decoration: underline;">Mejora tu plan aquí</a> para obtener más importaciones.',
                'limit' => $usage_limit,
                'current' => $current_sync_count
            ];
        }
        
        error_log("[API] Límite OK - Continuando importación para subscriber_id: " . $auth['subscriber_id']);
        
        // Incrementar sync_count
        $oldSyncCount = $lastBillingCycle['sync_count'];
        $newSyncCount = $oldSyncCount + 1;
        
        // Usar PDO directamente para asegurar que funcione
        $pdo = $db->getConnection();
        
        // Actualizar sync_count en billing_cycles
        $stmt = $pdo->prepare("UPDATE billing_cycles SET sync_count = :sync_count WHERE id = :billing_cycle_id");
        $result = $stmt->execute([
            'sync_count' => $newSyncCount,
            'billing_cycle_id' => $lastBillingCycle['id']
        ]);
        
        $rowsAffected = $stmt->rowCount();
        
        if (!$result || $rowsAffected === 0) {
            return ['success' => false, 'error' => 'No se pudo actualizar el contador de sincronizaciones'];
        }
        
        // Actualizar usage_count en licenses para el import
        $licenseUpdate = $pdo->prepare("UPDATE licenses SET usage_count = usage_count + 1 WHERE subscriber_id = :subscriber_id");
        $licenseResult = $licenseUpdate->execute([
            'subscriber_id' => $auth['subscriber_id']
        ]);
        
        $licenseRowsAffected = $licenseUpdate->rowCount();
        
        if (!$licenseResult || $licenseRowsAffected === 0) {
            // Log el error pero no fallar la operación completa
            error_log("Error actualizando usage_count en licenses para subscriber_id: " . $auth['subscriber_id']);
        }
        
        // Verificar la actualización
        $updatedCycle = $db->fetch("
            SELECT sync_count FROM billing_cycles WHERE id = :id
        ", [
            'id' => $lastBillingCycle['id']
        ]);
        
        // Verificar usage_count actualizado en licenses
        $updatedLicense = $db->fetch("
            SELECT usage_count FROM licenses WHERE subscriber_id = :subscriber_id
        ", [
            'subscriber_id' => $auth['subscriber_id']
        ]);
        
        // Log de la petición
        logApiRequest($auth['subscriber_id'], $auth['license_key'], 'increment_sync_count', 'POST', [
            'old_sync_count' => $oldSyncCount,
            'new_sync_count' => $newSyncCount,
            'verified_sync_count' => $updatedCycle['sync_count'],
            'license_usage_count' => $updatedLicense['usage_count'] ?? 'N/A'
        ]);

        return [
            'success' => true,
            'message' => 'Sync count y usage count incrementados exitosamente',
            'data' => [
                'billing_cycle_id' => $lastBillingCycle['id'],
                'old_sync_count' => $oldSyncCount,
                'new_sync_count' => $newSyncCount,
                'verified_sync_count' => $updatedCycle['sync_count'],
                'license_usage_count' => $updatedLicense['usage_count'] ?? 'N/A',
                'subscriber_id' => $auth['subscriber_id']
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error interno del servidor',
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Manejar obtención de subscriber_id
 */
function handleGetSubscriberId($method, $params) {
    if ($method !== 'POST') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    $license_key = $params['license_key'] ?? '';
    
    if (empty($license_key)) {
        return ['success' => false, 'error' => 'License key requerida'];
    }

    try {
        $db = Database::getInstance();
        
        // Obtener subscriber_id directamente desde la base de datos
        $sql = "SELECT subscriber_id FROM licenses WHERE license_key = :license_key AND status = 'active'";
        $license = $db->fetch($sql, ['license_key' => $license_key]);
        
        if (!$license) {
            return ['success' => false, 'error' => 'Licencia no encontrada'];
        }
        
        return [
            'success' => true,
            'subscriber_id' => $license['subscriber_id']
        ];
        
    } catch (Exception $e) {
        error_log("Error obteniendo subscriber_id: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error interno del servidor'];
    }
}

/**
 * Manejar petición de estadísticas de uso
 */
function handleGetUsageStats($method, $params) {
    if ($method !== 'POST') {
        return ['success' => false, 'error' => 'Método no permitido'];
    }

    $subscriber_id = $params['subscriber_id'] ?? '';
    $license_key = $params['license_key'] ?? '';

    if (empty($subscriber_id) || empty($license_key)) {
        return ['success' => false, 'error' => 'subscriber_id y license_key requeridos'];
    }

    try {
        $db = Database::getInstance();
        
        // Obtener el último billing cycle del suscriptor
        $billing_cycle = $db->fetch("
            SELECT sync_count, api_calls_count, cycle_start_date, cycle_end_date, is_active
            FROM billing_cycles 
            WHERE subscriber_id = :subscriber_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ", [
            'subscriber_id' => $subscriber_id
        ]);
        
        if (!$billing_cycle) {
            return [
                'success' => true,
                'sync_count' => 0,
                'api_calls_count' => 0,
                'products_synced' => 0
            ];
        }
        
        return [
            'success' => true,
            'sync_count' => $billing_cycle['sync_count'],
            'api_calls_count' => $billing_cycle['api_calls_count'],
            'products_synced' => $billing_cycle['sync_count'] // products_synced es lo mismo que sync_count
        ];
        
    } catch (Exception $e) {
        error_log("Error obteniendo estadísticas de uso: " . $e->getMessage());
        return ['success' => false, 'error' => 'Error interno del servidor'];
    }
}

/**
 * Enviar respuesta HTTP
 */
function sendResponse($statusCode, $data) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
