# 🚀 Instrucciones para Cambiar a Producción

## 📁 Archivo de Configuración
**Ubicación:** `wp-content/plugins/Discogs-Importer/config/api-config.php`

## 🔧 Cambio a Producción

### 1. Abrir el archivo de configuración:
```bash
wp-content/plugins/Discogs-Importer/config/api-config.php
```

### 2. Cambiar la línea 12:
```php
// ANTES (Desarrollo):
define('WDI_API_ENVIRONMENT', 'local');

// DESPUÉS (Producción):
define('WDI_API_ENVIRONMENT', 'production');
```

### 3. Verificar la URL de producción:
```php
'production' => [
    'base_url' => 'https://www.discogsync.com/api/index.php?endpoint=',
    'timeout' => 30,
    'description' => 'Entorno de producción'
],
```

## ✅ Verificación

### 1. Panel de Administración:
- Ir a **WooCommerce > Discogs Importer**
- Verificar que muestre "Entorno actual: Entorno de producción"

### 2. Probar Funcionalidad:
- Hacer una búsqueda de prueba
- Verificar que se conecte a la API de producción

## 🔄 Revertir a Desarrollo

### Si necesitas volver a desarrollo:
```php
define('WDI_API_ENVIRONMENT', 'local');
```

## 📋 Archivos Modificados

### Archivos Principales:
- ✅ `config/api-config.php` - Configuración centralizada
- ✅ `wordpress_integration/class-wdi-api-client.php` - Cliente API
- ✅ `admin/class-wdi-admin.php` - Panel de administración

### Archivos de Respaldo:
- ✅ `wordpress_integration/class-wdi-api-client.php.backup` - Respaldo del cliente

## 🛡️ Seguridad

- ✅ **Validación obligatoria** en cada petición
- ✅ **Rate limiting** por IP
- ✅ **Logs de seguridad** completos
- ✅ **Monitoreo** de intentos de bypass

## 📞 Soporte

Si tienes problemas:
1. Revisar logs de error de WordPress
2. Verificar conectividad a la API
3. Comprobar configuración de licencia
