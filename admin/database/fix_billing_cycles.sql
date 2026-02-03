-- Agregar campo plan_type a billing_cycles
ALTER TABLE billing_cycles ADD COLUMN plan_type VARCHAR(50) NOT NULL DEFAULT 'free' AFTER subscriber_id;

-- Crear índice para plan_type
CREATE INDEX idx_plan_type ON billing_cycles(plan_type);

-- Actualizar registros existentes con el plan_type correcto
UPDATE billing_cycles bc
JOIN subscribers s ON bc.subscriber_id = s.id
SET bc.plan_type = s.plan_type;

-- Actualizar amounts basándose en el plan_type
UPDATE billing_cycles bc
JOIN subscription_plans sp ON bc.plan_type = sp.id
SET bc.amount = sp.price;





