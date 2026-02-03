# Sistema de Tracking de Importaciones - DiscogsSync

## 📋 Resumen

Sistema completo para controlar y registrar las importaciones de productos desde Discogs, con límites mensuales por suscripción y estadísticas en tiempo real.

## 🗄️ Base de Datos

### Tabla `import_tracking`
```sql
CREATE TABLE import_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    license_key VARCHAR(255) NOT NULL,
    import_type ENUM('manual', 'bulk', 'auto') DEFAULT 'manual',
    products_imported INT DEFAULT 1,
    discogs_master_id VARCHAR(50),
    discogs_release_id VARCHAR(50),
    import_status ENUM('success', 'failed', 'partial') DEFAULT 'success',
    error_message TEXT NULL,
    import_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);
```

## 🔌 API Endpoints

### 1. Registrar Importación
```
POST /api/index.php?endpoint=import_track
```
**Parámetros:**
- `subscriber_id` (int): ID del suscriptor
- `license_key` (string): Clave de licencia
- `import_type` (string): 'manual', 'bulk', 'auto'
- `products_imported` (int): Número de productos importados
- `discogs_master_id` (string, opcional): ID del master en Discogs
- `discogs_release_id` (string, opcional): ID del release en Discogs
- `import_status` (string): 'success', 'failed', 'partial'
- `import_data` (object, opcional): Datos adicionales de la importación

### 2. Obtener Estadísticas
```
GET /api/index.php?endpoint=import_stats&subscriber_id={id}
```

### 3. Verificar Límites
```
GET /api/index.php?endpoint=import_limits&subscriber_id={id}&license_key={key}
```

## 🎯 Dashboard

El dashboard muestra:
- **Importaciones usadas/restantes** del mes actual
- **Barra de progreso** visual del uso
- **Productos importados** (total, exitosos, fallidos)
- **Tasa de éxito** de las importaciones
- **Actividad reciente** de los últimos 30 días
- **Botón de prueba** para simular importaciones

## 🔧 Integración con WordPress

### 1. Incluir el archivo de integración
```php
require_once plugin_dir_path(__FILE__) . 'wordpress_integration/import_tracking_integration.php';
```

### 2. Usar en el plugin
```php
// Registrar importación individual
discogs_track_import($master_id, $release_id, $import_data);

// Registrar importación masiva
discogs_track_bulk_import($total_products, $import_data);

// Verificar si se puede importar
if (discogs_can_import()) {
    // Proceder con la importación
}

// Obtener estadísticas
$stats = discogs_get_import_stats();
```

### 3. Mostrar límites en admin
```php
// En el admin de WordPress
$discogs_import_tracker->display_import_limits();
```

## ⏰ Reset Mensual Automático

### Configurar Cron Job
```bash
# Ejecutar el primer día de cada mes a las 00:00
0 0 1 * * php /path/to/api/cron/reset_monthly_imports.php
```

### Ejecutar manualmente
```bash
php api/cron/reset_monthly_imports.php
# O con clave de seguridad:
php api/cron/reset_monthly_imports.php?cron_key=reset_imports_2024
```

## 📊 Límites por Plan

| Plan | Importaciones/Mes | Productos/Importación |
|------|------------------|----------------------|
| Free | 10 | 1 |
| Premium | 200 | 1-100 |
| Enterprise | Ilimitadas | 1-1000 |

## 🚨 Validaciones

1. **Verificación de límites** antes de cada importación
2. **Bloqueo automático** cuando se alcanza el límite
3. **Mensajes de advertencia** cuando quedan pocas importaciones
4. **Notificaciones** de reset mensual

## 📈 Estadísticas Disponibles

- Importaciones usadas/restantes del mes
- Total de productos importados
- Productos importados exitosamente
- Importaciones fallidas
- Tasa de éxito (%)
- Historial de los últimos 30 días
- Actividad por día

## 🔒 Seguridad

- Validación de `license_key` en cada request
- Verificación de `subscriber_id` activo
- Logs de todas las operaciones
- Rate limiting en la API

## 🧪 Testing

### Probar el sistema:
1. Ir a `http://localhost/api_discogs/subscribe/pages/dashboard.php`
2. Presionar "Probar Importación"
3. Verificar que se actualicen las estadísticas
4. Repetir hasta alcanzar el límite

### Verificar límites:
```bash
curl "http://localhost/api_discogs/api/index.php?endpoint=import_limits&subscriber_id=1&license_key=LIC-123456789"
```

## 📝 Logs

Todos los eventos se registran en:
- `api_logs` - Llamadas a la API
- `import_tracking` - Registro de importaciones
- Logger de PHP para errores del sistema

## 🎉 ¡Sistema Completo!

El sistema está listo para:
- ✅ Controlar límites mensuales
- ✅ Registrar todas las importaciones
- ✅ Mostrar estadísticas en tiempo real
- ✅ Integrar con WordPress
- ✅ Reset automático mensual
- ✅ Validaciones de seguridad





