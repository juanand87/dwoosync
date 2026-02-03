# 🎵 DiscogsSync - Sistema Completo de Importación Discogs

[![Version](https://img.shields.io/badge/version-1.0.0-green.svg)](https://github.com/tu-usuario/discogssync)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org)

## 🎯 Descripción

**DiscogsSync** es un sistema completo que permite importar datos de discos desde la API de Discogs directamente a productos de WooCommerce en WordPress. Incluye un sistema de suscripciones mensuales con tracking de uso, gestión de licencias y dashboard de usuario.

## ✨ Características Principales

### 🔌 Plugin de WordPress
- **Importación automática** de datos de Discogs
- **Integración completa** con WooCommerce
- **Búsqueda en tiempo real** de discos
- **Sincronización manual** de productos
- **Reproductor Spotify** integrado
- **Validación de licencias** en tiempo real

### 🌐 API Intermediaria
- **Gestión de licencias** y suscripciones
- **Tracking de uso** mensual por usuario
- **Sistema de pagos** (PayPal, Stripe, manual)
- **Webhooks automáticos** para confirmaciones
- **Validación OAuth** de credenciales Discogs

### 💳 Sistema de Suscripciones
- **3 planes disponibles**: Free, Premium, Enterprise
- **Tracking de uso** en tiempo real
- **Renovación automática** cada 30 días
- **Dashboard completo** de estadísticas
- **Historial de ciclos** anteriores

## 🚀 Instalación Rápida

### Requisitos del Sistema
- **WordPress**: 5.0 o superior
- **WooCommerce**: 3.0 o superior
- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior
- **cURL**: Habilitado
- **OpenSSL**: Para HTTPS

### 1. Instalación del Plugin

```bash
# Descargar y extraer en el directorio de plugins
cd /wp-content/plugins/
git clone https://github.com/tu-usuario/discogssync.git Discogs-Importer

# Activar el plugin desde WordPress Admin
```

### 2. Configuración de la API

```bash
# Clonar la API en el servidor
git clone https://github.com/tu-usuario/discogssync-api.git api_discogs

# Configurar base de datos
mysql -u root -p < database_schema.sql

# Configurar archivos de configuración
cp config/config.example.php config/config.php
# Editar config/config.php con tus datos
```

## 📖 Documentación

### Documentación Técnica
- **[Documentación Completa](DOCUMENTACION_TECNICA.md)** - Guía técnica detallada
- **[Diagramas del Sistema](DIAGRAMAS_SISTEMA.md)** - Flujos y arquitectura
- **[Guía de Configuración](CONFIGURACION_EJEMPLO.md)** - Configuración paso a paso

## 💰 Planes de Suscripción

| Plan | Precio | Sincronizaciones | Características |
|------|--------|------------------|-----------------|
| **Free** | €0/mes | 10/mes | Básicas |
| **Premium** | €22/mes | 100/mes | Avanzadas + Soporte |
| **Enterprise** | €69/mes | Ilimitadas | Todas + Soporte dedicado |

## 🔧 API Endpoints

### Autenticación
- `POST /api/license-validate` - Validar licencia
- `POST /api/track-usage` - Tracking de uso

### Discogs
- `POST /api/discogs-search` - Buscar en Discogs
- `POST /api/discogs-release` - Obtener detalles de release
- `POST /api/test-discogs-oauth-connection` - Probar conexión OAuth

### Pagos
- `POST /api/payment-confirmation` - Confirmar pago
- `POST /api/paypal-webhook` - Webhook PayPal
- `POST /api/stripe-webhook` - Webhook Stripe
- `POST /api/manual-payment` - Pago manual

## 🧪 Testing

```bash
# Ejecutar tests del sistema
php test_payment_endpoints.php

# Tests de base de datos
php tests/test_database.php

# Tests de API
php tests/test_api_endpoints.php
```

## 📞 Soporte

### Contacto
- **Email**: soporte@discogssync.com
- **Documentación**: [docs.discogssync.com](https://docs.discogssync.com)
- **GitHub Issues**: [github.com/tu-usuario/discogssync/issues](https://github.com/tu-usuario/discogssync/issues)

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo [LICENSE](LICENSE) para más detalles.

---

**Desarrollado con ❤️ para la comunidad de coleccionistas de discos**

*Última actualización: 2024-01-15*