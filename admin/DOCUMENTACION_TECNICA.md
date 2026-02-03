# 📚 Documentación Técnica - DiscogsSync Plugin & API

## 🎯 Resumen Ejecutivo

**DiscogsSync** es un sistema completo que consta de:
- **Plugin de WordPress** para importar datos de Discogs a WooCommerce
- **API Intermediaria** para gestión de licencias y suscripciones
- **Sistema de Suscripciones** con tracking de uso mensual
- **Dashboard de Usuario** para gestión de cuentas

---

## 🏗️ Arquitectura del Sistema

### Componentes Principales

```
┌─────────────────────────────────────────────────────────────┐
│                    WORDPRESS PLUGIN                         │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐  │
│  │   Admin Panel   │  │  Import Engine  │  │  WooCommerce│  │
│  │   (Settings)    │  │  (Discogs API)  │  │ Integration │  │
│  └─────────────────┘  └─────────────────┘  └─────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    API INTERMEDIARIA                        │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐  │
│  │ License Manager │  │ Usage Tracker   │  │ Subscription│  │
│  │   (Validation)  │  │ (API Calls)     │  │   Manager   │  │
│  └─────────────────┘  └─────────────────┘  └─────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    DISCogs API                              │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐  │
│  │   Search API    │  │  Release API    │  │  Image API  │  │
│  │  (Masters)      │  │  (Details)      │  │ (Covers)    │  │
│  └─────────────────┘  └─────────────────┘  └─────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 Estructura de Archivos

### WordPress Plugin
```
wp-content/plugins/Discogs-Importer/
├── woocommerce-discogs-importer.php          # Plugin principal
├── admin/
│   └── class-wdi-admin.php                   # Panel de administración
├── wordpress_integration/
│   └── class-wdi-api-client.php              # Cliente API
├── includes/
│   ├── class-wdi-usage-tracker.php           # Tracking de uso
│   └── class-wdi-subscription-renewal.php    # Renovaciones
└── assets/
    ├── css/
    └── js/
```

### API Intermediaria
```
api_discogs/
├── api/
│   ├── index.php                             # Router principal
│   ├── payment-confirmation.php              # Confirmación de pagos
│   ├── paypal-webhook.php                    # Webhook PayPal
│   ├── stripe-webhook.php                    # Webhook Stripe
│   └── manual-payment.php                    # Pagos manuales
├── classes/
│   ├── DiscogsAPI.php                        # Cliente Discogs
│   └── Database.php                          # Conexión BD
├── config/
│   ├── config.php                            # Configuración
│   └── database.php                          # Conexión BD
├── subscribe/
│   ├── pages/
│   │   ├── signup.php                        # Registro
│   │   ├── login.php                         # Login
│   │   ├── dashboard.php                     # Dashboard
│   │   ├── checkout.php                      # Checkout
│   │   ├── payment_success.php               # Éxito pago
│   │   └── payment_pending.php               # Pago pendiente
│   └── includes/
│       └── functions.php                     # Funciones helper
└── assets/
    ├── css/
    └── js/
```

---

## 🗄️ Base de Datos

### Tablas Principales

#### 1. `subscribers`
```sql
CREATE TABLE subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    domain VARCHAR(255) UNIQUE NOT NULL,
    company VARCHAR(255) NOT NULL,
    city VARCHAR(100),
    country VARCHAR(100),
    phone VARCHAR(20),
    plan_type ENUM('free', 'premium', 'enterprise') DEFAULT 'free',
    status ENUM('active', 'inactive') DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 2. `licenses`
```sql
CREATE TABLE licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT NOT NULL,
    subscription_code VARCHAR(50) UNIQUE NOT NULL,
    license_key VARCHAR(100) UNIQUE NOT NULL,
    domain VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'inactive',
    usage_count INT DEFAULT 0,
    usage_limit INT DEFAULT 10,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);
```

#### 3. `subscription_cycles`
```sql
CREATE TABLE subscription_cycles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT NOT NULL,
    license_key VARCHAR(100) NOT NULL,
    cycle_start_date DATE NOT NULL,
    cycle_end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    sync_count INT DEFAULT 0,
    api_calls_count INT DEFAULT 0,
    products_synced INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);
```

#### 4. `payments`
```sql
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id VARCHAR(100) UNIQUE NOT NULL,
    subscriber_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(50) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);
```

#### 5. `sync_operations`
```sql
CREATE TABLE sync_operations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT NOT NULL,
    product_id INT NOT NULL,
    sync_type ENUM('manual', 'automatic') DEFAULT 'manual',
    fields_updated TEXT,
    sync_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);
```

#### 6. `api_calls_log`
```sql
CREATE TABLE api_calls_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscriber_id INT NOT NULL,
    product_id INT NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    call_type ENUM('search', 'release', 'master', 'artist', 'image') NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    response_time INT,
    error_message TEXT,
    call_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);
```

---

## 🔧 Funcionalidades del Plugin

### 1. Panel de Administración

#### Configuración de Licencia
- **Campo de Licencia**: Input para clave de licencia
- **Botón "Probar Conexión"**: Valida licencia en tiempo real
- **Campo de Renovación**: Muestra fecha de expiración con estado visual
- **Validación AJAX**: Sin recargar página

#### Configuración de Discogs API
- **API Key**: Clave de consumidor Discogs
- **API Secret**: Secreto de consumidor Discogs
- **Botón "Probar Conexión"**: Valida credenciales OAuth
- **Validación OAuth**: Prueba real de conexión a Discogs

### 2. Motor de Importación

#### Proceso de Búsqueda
```php
// 1. Usuario busca disco
$api_client = new WDI_API_Client($subscriber_id);
$results = $api_client->search_masters($query, $product_id);

// 2. Tracking automático
$usage_tracker = new WDI_Usage_Tracker($subscriber_id, $license_key);
$usage_tracker->increment_api_calls($product_id, 'search', 'masters');
```

#### Proceso de Importación
```php
// 1. Obtener detalles del release
$release_data = $api_client->get_version_details($release_id, $product_id);

// 2. Actualizar producto WooCommerce
$product->set_name($release_data['title']);
$product->set_description($release_data['description']);
$product->set_regular_price($release_data['price']);

// 3. Tracking de sincronización
$usage_tracker->increment_sync_count($product_id, 'manual', $fields_updated);
```

### 3. Integración WooCommerce

#### Pestañas de Producto
- **"Discogs Data"**: Muestra información importada
- **"Spotify Player"**: Reproductor integrado
- **"Sync Status"**: Estado de sincronización

#### Metadatos
- `_discogs_release_id`: ID del release en Discogs
- `_discogs_master_id`: ID del master en Discogs
- `_discogs_artist`: Artista principal
- `_discogs_label`: Sello discográfico
- `_discogs_year`: Año de lanzamiento

---

## 🌐 API Intermediaria

### Endpoints Principales

#### 1. Validación de Licencia
```
POST /api/license-validate
{
    "license_key": "DISC-1234567890",
    "domain": "example.com"
}

Response:
{
    "success": true,
    "data": {
        "valid": true,
        "subscriber_id": 123,
        "plan_type": "premium",
        "usage_limit": -1,
        "usage_count": 45
    }
}
```

#### 2. Tracking de Uso
```
POST /api/track-usage
{
    "subscriber_id": 123,
    "license_key": "DISC-1234567890",
    "usage_type": "api_call",
    "product_id": 456,
    "endpoint": "search/masters"
}

Response:
{
    "success": true,
    "data": {
        "tracked": true,
        "current_usage": 46
    }
}
```

#### 3. Búsqueda en Discogs
```
POST /api/discogs-search
{
    "query": "Pink Floyd Dark Side",
    "type": "master",
    "per_page": 10
}

Response:
{
    "success": true,
    "data": {
        "results": [...],
        "pagination": {...}
    }
}
```

### Clases Principales

#### `DiscogsAPI`
```php
class DiscogsAPI {
    private $consumer_key;
    private $consumer_secret;
    
    public function makeRequest($endpoint, $params = []);
    public function searchMasters($query, $per_page = 10);
    public function getMasterVersions($master_id);
    public function getReleaseDetails($release_id);
    public function getArtistDetails($artist_id);
    public function getImageUrl($image_url);
}
```

#### `WDI_Usage_Tracker`
```php
class WDI_Usage_Tracker {
    private $subscriber_id;
    private $license_key;
    
    public function increment_sync_count($product_id, $sync_type, $fields_updated);
    public function increment_api_calls($product_id, $call_type, $endpoint);
    public function get_current_usage();
    public function get_cycle_data();
}
```

---

## 💳 Sistema de Suscripciones

### Planes Disponibles

#### Plan Free
- **Precio**: €0/mes
- **Límites**: 10 sincronizaciones/mes
- **Características**: Básicas
- **Activación**: Inmediata

#### Plan Premium
- **Precio**: €22/mes
- **Límites**: 100 sincronizaciones/mes
- **Características**: Avanzadas + soporte prioritario
- **Activación**: Tras confirmar pago

#### Plan Enterprise
- **Precio**: €69/mes
- **Límites**: Ilimitadas
- **Características**: Todas + soporte dedicado
- **Activación**: Tras confirmar pago

### Flujo de Suscripción

```
1. Usuario se registra → signup.php
2. Selecciona plan → checkout.php
3. Procesa pago → payment-confirmation.php
4. Activa suscripción → dashboard.php
5. Crea ciclo mensual → subscription_cycles
```

### Tracking de Uso

#### Contadores por Ciclo
- **sync_count**: Sincronizaciones realizadas
- **api_calls_count**: Llamadas a API (sin límite)
- **products_synced**: Productos sincronizados

#### Renovación de Ciclos
- **Ciclo de 30 días** desde fecha de pago
- **Renovación automática** al confirmar pago
- **Historial preservado** de ciclos anteriores

---

## 🔐 Sistema de Pagos

### Métodos Soportados

#### 1. PayPal
- **Webhook**: `/api/paypal-webhook.php`
- **Eventos**: `PAYMENT.CAPTURE.COMPLETED`
- **Activación**: Automática

#### 2. Stripe
- **Webhook**: `/api/stripe-webhook.php`
- **Eventos**: `payment_intent.succeeded`
- **Activación**: Automática

#### 3. Pago Manual
- **Endpoint**: `/api/manual-payment.php`
- **Uso**: Transferencias, cheques, efectivo
- **Activación**: Manual por admin

### Estados de Pago

| Estado | Descripción | Acción |
|--------|-------------|--------|
| `pending` | Pago pendiente | Mantener inactivo |
| `completed` | Pago confirmado | Activar suscripción |
| `failed` | Pago fallido | Mantener inactivo |
| `cancelled` | Pago cancelado | Mantener inactivo |
| `refunded` | Pago reembolsado | Desactivar suscripción |

---

## 📊 Dashboard de Usuario

### Información Mostrada

#### Datos del Usuario
- **Nombre completo**
- **Email**
- **Dominio**
- **Plan actual**
- **Estado de suscripción**

#### Uso del Ciclo Actual
- **Sincronizaciones**: X/100 (con barra de progreso)
- **Llamadas API**: X realizadas (sin límite)
- **Productos sincronizados**: X

#### Historial de Ciclos
- **Ciclos anteriores** con fechas
- **Estadísticas** por ciclo
- **Gráficos** de uso

#### Información de Licencia
- **Clave completa** con botón copiar
- **Fecha de renovación** con estado visual
- **Enlaces** de renovación si expirada

### Funcionalidades

#### Botón "Mejorar Plan"
- **Solo para usuarios Free**
- **Redirige** a página de planes
- **Integración** con checkout

#### Notificaciones
- **Avisos** de límites alcanzados
- **Alertas** de renovación
- **Estados** de pago pendiente

---

## 🛠️ Instalación y Configuración

### Requisitos del Sistema

#### WordPress
- **Versión**: 5.0 o superior
- **WooCommerce**: 3.0 o superior
- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior

#### Servidor
- **Apache/Nginx** con mod_rewrite
- **cURL** habilitado
- **OpenSSL** para HTTPS
- **Memoria**: Mínimo 256MB

### Instalación del Plugin

1. **Subir archivos** a `/wp-content/plugins/Discogs-Importer/`
2. **Activar plugin** en WordPress
3. **Configurar licencia** en ajustes
4. **Configurar credenciales** Discogs API
5. **Probar conexiones** con botones de validación

### Configuración de la API

1. **Configurar base de datos** en `config/database.php`
2. **Configurar constantes** en `config/config.php`
3. **Configurar webhooks** en PayPal/Stripe
4. **Probar endpoints** con script de prueba

### Configuración de Webhooks

#### PayPal
```
URL: https://tudominio.com/api_discogs/api/paypal-webhook.php
Eventos: PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED
```

#### Stripe
```
URL: https://tudominio.com/api_discogs/api/stripe-webhook.php
Eventos: payment_intent.succeeded, payment_intent.payment_failed
```

---

## 🔍 Testing y Debugging

### Scripts de Prueba

#### `test_payment_endpoints.php`
- **Prueba** todos los endpoints de pago
- **Simula** webhooks de PayPal/Stripe
- **Valida** respuestas y códigos HTTP

#### `debug_tracking.php`
- **Verifica** tracking de uso
- **Comprueba** contadores de ciclos
- **Valida** datos de suscripción

### Logs del Sistema

#### Logs de WordPress
```php
error_log("DiscogsSync: " . $message);
```

#### Logs de API
```php
error_log("API: " . $endpoint . " - " . $message);
```

#### Logs de Pagos
```php
error_log("Payment: " . $payment_id . " - " . $status);
```

---

## 🚀 Optimizaciones y Mejoras

### Rendimiento

#### Caching
- **Cache** de respuestas Discogs API
- **Cache** de datos de productos
- **Cache** de validaciones de licencia

#### Optimizaciones de BD
- **Índices** en campos de búsqueda
- **Particionado** de tablas de logs
- **Limpieza** automática de datos antiguos

### Seguridad

#### Validaciones
- **Sanitización** de inputs
- **Validación** de tipos de datos
- **Escape** de outputs HTML

#### Autenticación
- **Tokens** de API con expiración
- **Rate limiting** por IP
- **Validación** de dominios

### Escalabilidad

#### Arquitectura
- **Microservicios** para funciones específicas
- **Queue system** para procesamiento asíncrono
- **Load balancing** para alta disponibilidad

#### Base de Datos
- **Replicación** para lectura
- **Sharding** por región
- **Backup** automático

---

## 📈 Métricas y Monitoreo

### KPIs Principales

#### Uso del Sistema
- **Sincronizaciones** por día/mes
- **Llamadas API** por usuario
- **Productos** sincronizados
- **Tiempo de respuesta** de API

#### Negocio
- **Conversiones** de registro a pago
- **Retención** de usuarios
- **Churn rate** por plan
- **Revenue** por mes

### Alertas

#### Técnicas
- **Errores** de API > 5%
- **Tiempo de respuesta** > 5s
- **Memoria** > 80%
- **Espacio en disco** > 90%

#### Negocio
- **Pagos fallidos** > 10%
- **Usuarios inactivos** > 30 días
- **Límites alcanzados** > 80%
- **Renovaciones** próximas a vencer

---

## 🔧 Mantenimiento

### Tareas Regulares

#### Diarias
- **Monitoreo** de logs de error
- **Verificación** de webhooks
- **Backup** de base de datos

#### Semanales
- **Limpieza** de logs antiguos
- **Análisis** de métricas
- **Actualización** de dependencias

#### Mensuales
- **Revisión** de rendimiento
- **Optimización** de consultas
- **Actualización** de documentación

### Actualizaciones

#### Plugin WordPress
- **Versionado** semántico
- **Changelog** detallado
- **Migración** automática de datos
- **Rollback** en caso de errores

#### API Intermediaria
- **Versionado** de endpoints
- **Deprecación** gradual
- **Compatibilidad** hacia atrás
- **Testing** exhaustivo

---

## 📞 Soporte y Contacto

### Documentación
- **README** del proyecto
- **API Documentation** (Swagger)
- **Video tutorials** de instalación
- **FAQ** de problemas comunes

### Soporte Técnico
- **Email**: soporte@discogssync.com
- **Ticket system** integrado
- **Chat en vivo** para usuarios premium
- **Documentación** de troubleshooting

### Comunidad
- **GitHub** para reportes de bugs
- **Foro** de usuarios
- **Discord** para desarrolladores
- **Blog** con actualizaciones

---

## 📝 Changelog

### Versión 1.0.0 (2024-01-15)
- ✅ Plugin WordPress básico
- ✅ Integración con Discogs API
- ✅ Sistema de licencias
- ✅ Dashboard de usuario
- ✅ Sistema de suscripciones
- ✅ Tracking de uso mensual
- ✅ Integración con WooCommerce
- ✅ Sistema de pagos (PayPal/Stripe)
- ✅ Webhooks automáticos
- ✅ Documentación completa

---

## 🎯 Roadmap Futuro

### Versión 1.1.0
- 🔄 **Cache** de respuestas API
- 🔄 **Rate limiting** mejorado
- 🔄 **Bulk import** de productos
- 🔄 **Scheduled sync** automático

### Versión 1.2.0
- 🔄 **Multi-site** support
- 🔄 **White-label** para revendedores
- 🔄 **API REST** completa
- 🔄 **Mobile app** para gestión

### Versión 2.0.0
- 🔄 **Microservicios** architecture
- 🔄 **Machine learning** para recomendaciones
- 🔄 **Advanced analytics** dashboard
- 🔄 **Multi-language** support

---

*Documentación generada automáticamente - Última actualización: 2024-01-15*

