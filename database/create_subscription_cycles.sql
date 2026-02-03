-- Tabla para ciclos de suscripción y tracking de uso
CREATE TABLE subscription_cycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    license_key VARCHAR(255) NOT NULL,
    cycle_start_date TIMESTAMP NOT NULL,
    cycle_end_date TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sync_count INT DEFAULT 0,
    api_calls_count INT DEFAULT 0,
    products_synced INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_subscriber_id (subscriber_id),
    INDEX idx_license_key (license_key),
    INDEX idx_cycle_dates (cycle_start_date, cycle_end_date),
    INDEX idx_is_active (is_active)
);

-- Tabla para operaciones de sincronización detalladas
CREATE TABLE sync_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    license_key VARCHAR(255) NOT NULL,
    product_id INT,
    sync_type ENUM('manual', 'bulk', 'auto') DEFAULT 'manual',
    cycle_start_date TIMESTAMP NOT NULL,
    fields_updated TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_subscriber_id (subscriber_id),
    INDEX idx_license_key (license_key),
    INDEX idx_product_id (product_id),
    INDEX idx_sync_type (sync_type),
    INDEX idx_cycle_start (cycle_start_date)
);

-- Tabla para logs de llamadas API
CREATE TABLE api_calls_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    license_key VARCHAR(255) NOT NULL,
    product_id INT,
    api_type ENUM('search', 'release', 'master', 'artist', 'image') NOT NULL,
    operation_type ENUM('search', 'details', 'images') NOT NULL,
    cycle_start_date TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_subscriber_id (subscriber_id),
    INDEX idx_license_key (license_key),
    INDEX idx_product_id (product_id),
    INDEX idx_api_type (api_type),
    INDEX idx_cycle_start (cycle_start_date)
);

