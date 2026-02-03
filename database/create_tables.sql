-- Crear base de datos
CREATE DATABASE IF NOT EXISTS discogs_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE discogs_api;

-- Tabla de suscriptores
CREATE TABLE subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    domain VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    city VARCHAR(100),
    country VARCHAR(100),
    phone VARCHAR(20),
    company VARCHAR(255),
    plan_type ENUM('free', 'premium', 'enterprise') DEFAULT 'free',
    status ENUM('active', 'inactive', 'suspended', 'expired') DEFAULT 'active',
    subscription_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiration_date TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_domain (domain),
    INDEX idx_status (status),
    INDEX idx_plan_type (plan_type)
);

-- Tabla de licencias
CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    subscription_code VARCHAR(255) UNIQUE NOT NULL,
    license_key VARCHAR(255) UNIQUE NOT NULL,
    domain VARCHAR(255) NOT NULL,
    plugin_version VARCHAR(20) DEFAULT '1.0.0',
    status ENUM('active', 'inactive', 'expired', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    last_used TIMESTAMP NULL,
    usage_count INT DEFAULT 0,
    usage_limit INT DEFAULT 10,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_subscription_code (subscription_code),
    INDEX idx_license_key (license_key),
    INDEX idx_domain (domain),
    INDEX idx_status (status),
    INDEX idx_subscriber_id (subscriber_id)
);

-- Tabla de planes de suscripción
CREATE TABLE subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(100) NOT NULL,
    plan_type ENUM('free', 'premium', 'enterprise') NOT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'USD',
    duration_days INT DEFAULT 30,
    requests_per_hour INT DEFAULT 100,
    requests_per_day INT DEFAULT 1000,
    requests_per_month INT DEFAULT 10000,
    cache_ttl INT DEFAULT 3600,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_plan_type (plan_type),
    INDEX idx_is_active (is_active)
);

-- Tabla de logs de API
CREATE TABLE api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT,
    license_key VARCHAR(255),
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    request_data JSON,
    response_data JSON,
    response_time INT,
    status_code INT NOT NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE SET NULL,
    INDEX idx_subscriber_id (subscriber_id),
    INDEX idx_license_key (license_key),
    INDEX idx_endpoint (endpoint),
    INDEX idx_created_at (created_at),
    INDEX idx_status_code (status_code)
);

-- Tabla de caché
CREATE TABLE cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(255) UNIQUE NOT NULL,
    cache_data LONGTEXT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    hits INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cache_key (cache_key),
    INDEX idx_expires_at (expires_at)
);

-- Tabla de estadísticas de uso
CREATE TABLE usage_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    date DATE NOT NULL,
    requests_count INT DEFAULT 0,
    requests_successful INT DEFAULT 0,
    requests_failed INT DEFAULT 0,
    total_response_time INT DEFAULT 0,
    cache_hits INT DEFAULT 0,
    cache_misses INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_subscriber_date (subscriber_id, date),
    INDEX idx_subscriber_id (subscriber_id),
    INDEX idx_date (date)
);

-- Tabla de pagos
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    payment_id VARCHAR(255) UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(50),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_subscriber_id (subscriber_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status)
);

-- Tabla de configuraciones del sistema
CREATE TABLE system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(255) UNIQUE NOT NULL,
    config_value TEXT,
    description TEXT,
    is_encrypted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
);

-- Insertar planes de suscripción por defecto
INSERT INTO subscription_plans (plan_name, plan_type, price, duration_days, requests_per_hour, requests_per_day, requests_per_month, cache_ttl, features) VALUES
('Plan Gratuito', 'free', 0.00, 365, 100, 1000, 10000, 3600, '{"max_imports_per_day": 10, "support": "email", "features": ["basic_search", "basic_import"]}'),
('Plan Premium', 'premium', 29.99, 30, 500, 5000, 50000, 1800, '{"max_imports_per_day": 100, "support": "priority", "features": ["advanced_search", "bulk_import", "country_filter", "priority_support"]}'),
('Plan Enterprise', 'enterprise', 99.99, 30, 2000, 20000, 200000, 900, '{"max_imports_per_day": 1000, "support": "dedicated", "features": ["unlimited_search", "unlimited_import", "api_access", "custom_integration", "dedicated_support"]}');

-- Insertar configuraciones del sistema
INSERT INTO system_config (config_key, config_value, description) VALUES
('discogs_api_key', '', 'Clave de API de Discogs'),
('discogs_user_agent', 'DiscogsImporter/1.0.0', 'User Agent para peticiones a Discogs'),
('rate_limit_enabled', '1', 'Habilitar límite de peticiones'),
('cache_enabled', '1', 'Habilitar sistema de caché'),
('log_level', 'info', 'Nivel de logging (debug, info, warning, error)'),
('max_log_retention_days', '30', 'Días de retención de logs'),
('maintenance_mode', '0', 'Modo de mantenimiento'),
('api_version', '1.0.0', 'Versión de la API');
