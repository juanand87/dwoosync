-- Agregar columna usage_count a billing_cycles
ALTER TABLE billing_cycles ADD COLUMN usage_count INT DEFAULT 0 AFTER api_calls_count;



