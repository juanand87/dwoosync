-- Crear tabla security_logs para registrar eventos de seguridad
CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    license_key VARCHAR(255),
    domain VARCHAR(255),
    subscriber_id INT NULL,
    endpoint VARCHAR(100),
    reason TEXT NULL,
    error TEXT NULL,
    timestamp DATETIME NOT NULL,
    data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para mejorar el rendimiento
CREATE INDEX idx_security_logs_event_type ON security_logs(event_type);
CREATE INDEX idx_security_logs_timestamp ON security_logs(timestamp);
CREATE INDEX idx_security_logs_subscriber_id ON security_logs(subscriber_id);
CREATE INDEX idx_security_logs_license_key ON security_logs(license_key);



