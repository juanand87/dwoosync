-- Actualizar precios de los planes de suscripción
-- Fecha: 2026-02-03

-- Actualizar plan Premium de $12 a $6
UPDATE subscription_plans 
SET price = 6.00, 
    updated_at = NOW() 
WHERE plan_type = 'premium';

-- Actualizar plan Enterprise (+Spotify) de $16 a $10
UPDATE subscription_plans 
SET price = 10.00, 
    updated_at = NOW() 
WHERE plan_type = 'enterprise';

-- Verificar los cambios
SELECT plan_name, plan_type, price, currency 
FROM subscription_plans 
ORDER BY price;
