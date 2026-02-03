-- Eliminar tablas obsoletas si existen
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS subscription_cycles;

-- Tabla para ciclos de facturación (reemplaza subscription_cycles e invoices)
CREATE TABLE billing_cycles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,
    plan_type VARCHAR(50) NOT NULL DEFAULT 'free',
    license_key VARCHAR(255) NOT NULL,
    cycle_start_date DATE NOT NULL,
    cycle_end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    
    -- Información de facturación
    invoice_number VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('pending', 'paid', 'cancelled', 'refunded', 'overdue') DEFAULT 'pending',
    due_date DATE NOT NULL,
    paid_date DATE NULL,
    payment_method VARCHAR(50) NULL,
    payment_reference VARCHAR(100) NULL,
    
    -- Información de uso
    sync_count INT DEFAULT 0,
    api_calls_count INT DEFAULT 0,
    products_synced INT DEFAULT 0,
    
    -- Metadatos
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_subscriber_id (subscriber_id),
    INDEX idx_plan_type (plan_type),
    INDEX idx_license_key (license_key),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    INDEX idx_cycle_dates (cycle_start_date, cycle_end_date),
    
    -- Claves foráneas
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE
);
