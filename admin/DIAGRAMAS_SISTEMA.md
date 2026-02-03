# 📊 Diagramas del Sistema DiscogsSync

## 🔄 Flujo Principal de Importación

```
Usuario en WordPress
        │
        ▼
1. Busca disco en plugin
        │
        ▼
2. Plugin → API Intermediaria
        │
        ▼
3. API → Discogs API (OAuth)
        │
        ▼
4. Discogs responde con datos
        │
        ▼
5. API valida licencia y tracking
        │
        ▼
6. Plugin actualiza producto WooCommerce
        │
        ▼
7. Tracking de uso guardado en BD
```

## 💳 Flujo de Suscripción y Pago

```
Usuario visita signup.php
        │
        ▼
1. Llena formulario + selecciona plan
        │
        ▼
2. Redirige a checkout.php
        │
        ▼
3. Confirma pago (PayPal/Stripe/Manual)
        │
        ▼
4. Webhook → payment-confirmation.php
        │
        ▼
5. Crea suscripción INACTIVA
        │
        ▼
6. Si pago exitoso → Activa suscripción
        │
        ▼
7. Crea ciclo mensual de 30 días
        │
        ▼
8. Redirige a dashboard.php
```

## 🗄️ Estructura de Base de Datos

```
discogs_api
├── subscribers (usuarios)
│   ├── id (PK)
│   ├── email (UNIQUE)
│   ├── domain (UNIQUE)
│   ├── plan_type (free/premium/enterprise)
│   └── status (active/inactive)
│
├── licenses (licencias)
│   ├── id (PK)
│   ├── subscriber_id (FK)
│   ├── license_key (UNIQUE)
│   ├── status (active/inactive/expired)
│   └── expires_at
│
├── subscription_cycles (ciclos mensuales)
│   ├── id (PK)
│   ├── subscriber_id (FK)
│   ├── cycle_start_date
│   ├── cycle_end_date
│   ├── is_active (BOOLEAN)
│   ├── sync_count
│   ├── api_calls_count
│   └── products_synced
│
├── payments (pagos)
│   ├── id (PK)
│   ├── payment_id (UNIQUE)
│   ├── subscriber_id (FK)
│   ├── amount
│   ├── status (pending/completed/failed)
│   └── payment_method
│
├── sync_operations (operaciones de sync)
│   ├── id (PK)
│   ├── subscriber_id (FK)
│   ├── product_id
│   ├── sync_type (manual/automatic)
│   └── fields_updated
│
└── api_calls_log (logs de API)
    ├── id (PK)
    ├── subscriber_id (FK)
    ├── endpoint
    ├── call_type (search/release/master)
    ├── success (BOOLEAN)
    └── response_time
```

## 🔐 Sistema de Autenticación

```
WordPress Plugin
        │
        ▼
1. Obtiene license_key de configuración
        │
        ▼
2. Envía a API: /license-validate
        │
        ▼
3. API valida en BD:
   - Licencia existe y está activa
   - Dominio coincide
   - No ha expirado
        │
        ▼
4. Retorna subscriber_id y límites
        │
        ▼
5. Plugin usa subscriber_id para tracking
```

## 📊 Tracking de Uso

```
Cada operación en plugin
        │
        ▼
1. WDI_Usage_Tracker inicializado
        │
        ▼
2. Busca ciclo activo en BD
        │
        ▼
3. Incrementa contador correspondiente:
   - sync_count (botón sincronizar)
   - api_calls_count (llamadas API)
   - products_synced (productos actualizados)
        │
        ▼
4. Guarda en subscription_cycles
        │
        ▼
5. Verifica límites y muestra alertas
```

## 🌐 Arquitectura de API

```
Cliente (WordPress Plugin)
        │
        ▼
API Gateway (api/index.php)
        │
        ▼
┌─────────────────────────────────────┐
│           ROUTER                    │
├─────────────────────────────────────┤
│  /license-validate                  │
│  /track-usage                       │
│  /discogs-search                    │
│  /discogs-release                   │
│  /test-discogs-oauth-connection     │
│  /payment-confirmation              │
└─────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────┐
│        HANDLERS                     │
├─────────────────────────────────────┤
│  - LicenseManager                   │
│  - UsageTracker                     │
│  - DiscogsAPI                       │
│  - PaymentProcessor                 │
└─────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────┐
│        EXTERNAL APIs                │
├─────────────────────────────────────┤
│  - Discogs API (OAuth 1.0a)        │
│  - PayPal Webhooks                  │
│  - Stripe Webhooks                  │
└─────────────────────────────────────┘
```

## 💰 Estados de Pago

```
Pago Iniciado
        │
        ▼
┌─────────────────────────────────────┐
│         PENDING                     │
│  - Suscripción INACTIVA             │
│  - Licencia INACTIVA                │
│  - Usuario puede ver dashboard      │
│  - Muestra "Pago pendiente"         │
└─────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────┐
│        COMPLETED                    │
│  - Suscripción ACTIVA               │
│  - Licencia ACTIVA                  │
│  - Crea ciclo mensual               │
│  - Usuario puede usar plugin        │
└─────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────┐
│         FAILED                      │
│  - Suscripción INACTIVA             │
│  - Licencia INACTIVA                │
│  - Usuario debe reintentar pago     │
│  - Muestra "Pago fallido"           │
└─────────────────────────────────────┘
```

## 🔄 Ciclo de Renovación

```
Pago Confirmado
        │
        ▼
1. Crea nuevo ciclo (30 días)
        │
        ▼
2. Desactiva ciclo anterior
        │
        ▼
3. Reinicia contadores a 0
        │
        ▼
4. Preserva historial anterior
        │
        ▼
5. Actualiza expires_at en licenses
        │
        ▼
6. Envía email de confirmación
```

## 📱 Dashboard de Usuario

```
Dashboard Layout
┌─────────────────────────────────────┐
│  Header: Logo + Navigation          │
├─────────────────────────────────────┤
│  User Info:                         │
│  - Nombre, Email, Dominio           │
│  - Plan actual + Botón "Mejorar"    │
│  - Estado de suscripción            │
├─────────────────────────────────────┤
│  Current Cycle Usage:               │
│  - Sincronizaciones: X/100 (barra)  │
│  - Llamadas API: X (sin límite)     │
│  - Productos: X sincronizados       │
├─────────────────────────────────────┤
│  License Info:                      │
│  - Clave completa + Botón copiar    │
│  - Fecha renovación + Estado        │
├─────────────────────────────────────┤
│  History:                           │
│  - Ciclos anteriores                │
│  - Gráficos de uso                  │
└─────────────────────────────────────┘
```

## 🚨 Sistema de Alertas

```
Límites de Uso
        │
        ▼
┌─────────────────────────────────────┐
│  sync_count >= 80% del límite       │
│  → Mostrar advertencia              │
└─────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────┐
│  sync_count >= 100% del límite      │
│  → Bloquear nuevas sincronizaciones │
└─────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────┐
│  Licencia expira en 7 días          │
│  → Mostrar aviso de renovación      │
└─────────────────────────────────────┘
```

## 🔧 Flujo de Debugging

```
Problema Reportado
        │
        ▼
1. Revisar logs de error
        │
        ▼
2. Verificar estado de BD
        │
        ▼
3. Probar endpoints individualmente
        │
        ▼
4. Verificar configuración de licencia
        │
        ▼
5. Probar conexión a Discogs API
        │
        ▼
6. Verificar webhooks de pago
        │
        ▼
7. Aplicar fix y documentar
```

---

*Diagramas generados automáticamente - Última actualización: 2024-01-15*

