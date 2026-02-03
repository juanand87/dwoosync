-- Tabla para tracking de importaciones de suscriptores
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
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,
    INDEX idx_subscriber_month (subscriber_id, created_at),
    INDEX idx_license_key (license_key),
    INDEX idx_import_type (import_type),
    INDEX idx_status (import_status)
);

-- Insertar datos de ejemplo para testing
INSERT INTO import_tracking (subscriber_id, license_key, import_type, products_imported, discogs_master_id, import_status, import_data) VALUES
(1, 'LIC-123456789', 'manual', 1, '12345', 'success', '{"artist": "Pink Floyd", "title": "Dark Side of the Moon", "year": 1973}'),
(1, 'LIC-123456789', 'manual', 1, '12346', 'success', '{"artist": "Led Zeppelin", "title": "IV", "year": 1971}'),
(1, 'LIC-123456789', 'bulk', 5, NULL, 'success', '{"total_imported": 5, "artists": ["Beatles", "Rolling Stones", "Queen", "AC/DC", "Metallica"]}'),
(1, 'LIC-123456789', 'manual', 1, '12347', 'failed', '{"artist": "Unknown", "title": "Test", "error": "Invalid master ID"}');





