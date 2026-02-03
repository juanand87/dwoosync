# Configuración del Plugin para la API de Discogs

## 🔧 **Parámetros de Conexión Requeridos**

### **1. URL Base de la API**
```
API_BASE_URL: http://localhost/api_discogs/api/
```

### **2. Parámetros de Autenticación (Obligatorios)**
```php
// Parámetros requeridos en TODAS las peticiones
$params = [
    'license_key' => 'LIC_XXXXXXXXXXXX',  // Clave de licencia del suscriptor
    'domain' => 'tudominio.com',          // Dominio donde se usa el plugin
    'discogs_api_key' => 'TU_DISCOGS_KEY', // Clave de API de Discogs del usuario
    'discogs_api_secret' => 'TU_DISCOGS_SECRET' // Secret de API de Discogs del usuario
];
```

## 📡 **Endpoints Disponibles**

### **1. Búsqueda de Masters**
```php
GET /api/search?endpoint=search
```
**Parámetros:**
- `q` (requerido): Término de búsqueda
- `format` (opcional): Formato (Vinyl, CD, etc.)
- `country` (opcional): País
- `year` (opcional): Año
- `label` (opcional): Sello discográfico
- `genre` (opcional): Género
- `style` (opcional): Estilo

**Ejemplo:**
```php
$url = 'http://localhost/api_discogs/api/index.php?endpoint=search&' . http_build_query([
    'q' => 'Pink Floyd',
    'format' => 'Vinyl',
    'year' => '1973',
    'license_key' => 'LIC_XXXXXXXXXXXX',
    'domain' => 'tudominio.com',
    'discogs_api_key' => 'TU_DISCOGS_KEY',
    'discogs_api_secret' => 'TU_DISCOGS_SECRET'
]);
```

### **2. Versiones de Master**
```php
GET /api/versions?endpoint=versions
```
**Parámetros:**
- `master_id` (requerido): ID del master
- `format` (opcional): Formato
- `country` (opcional): País
- `year` (opcional): Año
- `label` (opcional): Sello

### **3. Detalles de Release**
```php
GET /api/release?endpoint=release
```
**Parámetros:**
- `release_id` (requerido): ID del release

### **4. Detalles de Artista**
```php
GET /api/artist?endpoint=artist
```
**Parámetros:**
- `artist_id` (requerido): ID del artista

### **5. Imagen de Release**
```php
GET /api/image?endpoint=image
```
**Parámetros:**
- `release_id` (requerido): ID del release
- `size` (opcional): Tamaño (small, medium, large) - default: large

### **6. Validar Licencia**
```php
POST /api/license?endpoint=license
```
**Parámetros:**
- `license_key` (requerido): Clave de licencia
- `domain` (opcional): Dominio

### **7. Health Check**
```php
GET /api/health?endpoint=health
```
**Sin parámetros requeridos**

## 🔐 **Sistema de Autenticación**

### **Flujo de Autenticación:**
1. **Validar Licencia**: Verificar que la licencia sea válida y esté activa
2. **Verificar Dominio**: Confirmar que el dominio esté autorizado
3. **Verificar Límites**: Comprobar que no se excedan los límites de uso
4. **Procesar Petición**: Ejecutar la petición a Discogs

### **Respuestas de Error Comunes:**
```json
{
    "success": false,
    "error": "License key requerida"
}

{
    "success": false,
    "error": "Clave de API de Discogs requerida"
}

{
    "success": false,
    "valid": false,
    "error": "Licencia expirada"
}

{
    "success": false,
    "error": "Límite de peticiones excedido"
}
```

## 📊 **Límites por Plan**

### **Plan Gratuito:**
- 100 peticiones/hora
- 1,000 peticiones/día
- 10,000 peticiones/mes

### **Plan Premium:**
- 500 peticiones/hora
- 5,000 peticiones/día
- 50,000 peticiones/mes

### **Plan Enterprise:**
- 2,000 peticiones/hora
- 20,000 peticiones/día
- 200,000 peticiones/mes

## 🛠️ **Configuración del Plugin**

### **Archivo de Configuración del Plugin:**
```php
// config.php del plugin
define('DISCOGS_API_BASE_URL', 'http://localhost/api_discogs/api/');
define('DISCOGS_LICENSE_KEY', 'LIC_XXXXXXXXXXXX');
define('DISCOGS_DOMAIN', 'tudominio.com');
define('DISCOGS_API_KEY', 'TU_DISCOGS_KEY');
define('DISCOGS_API_SECRET', 'TU_DISCOGS_SECRET');
```

### **Ejemplo de Uso en el Plugin:**
```php
class DiscogsImporter {
    private $apiBaseUrl;
    private $licenseKey;
    private $domain;
    private $discogsApiKey;
    private $discogsApiSecret;
    
    public function __construct() {
        $this->apiBaseUrl = get_option('discogs_api_base_url');
        $this->licenseKey = get_option('discogs_license_key');
        $this->domain = get_option('discogs_domain');
        $this->discogsApiKey = get_option('discogs_api_key');
        $this->discogsApiSecret = get_option('discogs_api_secret');
    }
    
    public function search($query, $filters = []) {
        $params = array_merge([
            'q' => $query,
            'license_key' => $this->licenseKey,
            'domain' => $this->domain,
            'discogs_api_key' => $this->discogsApiKey,
            'discogs_api_secret' => $this->discogsApiSecret
        ], $filters);
        
        $url = $this->apiBaseUrl . 'index.php?endpoint=search&' . http_build_query($params);
        
        $response = wp_remote_get($url);
        $body = wp_remote_retrieve_body($response);
        
        return json_decode($body, true);
    }
}
```

## 🔧 **Configuración de WordPress**

### **Página de Configuración del Plugin:**
```php
// En el admin de WordPress
add_settings_field(
    'discogs_api_base_url',
    'URL Base de la API',
    'discogs_api_base_url_callback',
    'discogs_settings'
);

add_settings_field(
    'discogs_license_key',
    'Clave de Licencia',
    'discogs_license_key_callback',
    'discogs_settings'
);

add_settings_field(
    'discogs_domain',
    'Dominio',
    'discogs_domain_callback',
    'discogs_settings'
);

add_settings_field(
    'discogs_api_key',
    'Clave de API de Discogs',
    'discogs_api_key_callback',
    'discogs_settings'
);

add_settings_field(
    'discogs_api_secret',
    'Secret de API de Discogs',
    'discogs_api_secret_callback',
    'discogs_settings'
);
```

## 🚨 **Consideraciones Importantes**

1. **CORS**: La API está configurada para aceptar peticiones desde dominios específicos
2. **Rate Limiting**: Se aplican límites por plan de suscripción
3. **Caché**: Las respuestas se cachean para optimizar rendimiento
4. **Logs**: Todas las peticiones se registran para monitoreo
5. **Seguridad**: Validación de dominios y licencias en cada petición

## 📝 **Ejemplo Completo de Implementación**

```php
// Función para hacer peticiones a la API
function discogs_api_request($endpoint, $params = []) {
    $baseUrl = get_option('discogs_api_base_url', 'http://localhost/api_discogs/api/');
    
    $defaultParams = [
        'license_key' => get_option('discogs_license_key'),
        'domain' => get_option('discogs_domain'),
        'discogs_api_key' => get_option('discogs_api_key'),
        'discogs_api_secret' => get_option('discogs_api_secret')
    ];
    
    $params = array_merge($defaultParams, $params);
    $url = $baseUrl . 'index.php?endpoint=' . $endpoint . '&' . http_build_query($params);
    
    $response = wp_remote_get($url, [
        'timeout' => 30,
        'headers' => [
            'User-Agent' => 'DiscogsImporter/1.0.0'
        ]
    ]);
    
    if (is_wp_error($response)) {
        return ['success' => false, 'error' => $response->get_error_message()];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return $data;
}

// Uso en el plugin
$searchResults = discogs_api_request('search', [
    'q' => 'Pink Floyd',
    'format' => 'Vinyl'
]);
```


