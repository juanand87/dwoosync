<?php
/**
 * Clase para manejo de licencias y suscripciones
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

class LicenseManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Validar una licencia (optimizado con período de gracia)
     */
    public function validateLicense($licenseKey, $domain) {
        try {
            // Obtener configuración del período de gracia
            $graceConfig = $this->db->fetch("SELECT config_value FROM system_config WHERE config_key = 'grace_period_enabled'");
            $graceDays = $this->db->fetch("SELECT config_value FROM system_config WHERE config_key = 'grace_period_days'");
            
            $graceEnabled = $graceConfig && $graceConfig['config_value'] == '1';
            $graceDaysValue = $graceDays ? (int)$graceDays['config_value'] : 3;
            
            // Primero verificar si la licencia existe y está activa (sin validar dominio)
            $sql = "SELECT l.id, l.subscriber_id, l.license_key, l.domain, l.status, l.expires_at, l.usage_count, l.usage_limit,
                           s.email, s.first_name, s.last_name, s.status as subscriber_status, s.plan_type,
                           sp.requests_per_hour, sp.requests_per_day, sp.requests_per_month, sp.features
                    FROM licenses l
                    INNER JOIN subscribers s ON l.subscriber_id = s.id
                    INNER JOIN subscription_plans sp ON s.plan_type = sp.plan_type AND sp.is_active = 1
                    WHERE l.license_key = :license_key 
                    AND l.status = 'active'
                    AND s.status = 'active'";
            
            // Aplicar validación de expiración según configuración
            if ($graceEnabled) {
                $sql .= " AND (l.expires_at IS NULL OR l.expires_at > DATE_SUB(NOW(), INTERVAL :grace_days DAY))";
            } else {
                $sql .= " AND (l.expires_at IS NULL OR l.expires_at > NOW())";
            }
            
            $sql .= " LIMIT 1";

            $params = ['license_key' => $licenseKey];
            if ($graceEnabled) {
                $params['grace_days'] = $graceDaysValue;
            }

            $license = $this->db->fetch($sql, $params);

            if (!$license) {
                return [
                    'valid' => false,
                    'error' => 'LICENSE_NOT_FOUND',
                    'message' => 'Invalid or expired license'
                ];
            }

            // Verificar si el dominio coincide
            if ($license['domain'] !== $domain) {
                return [
                    'valid' => false,
                    'error' => 'DOMAIN_MISMATCH',
                    'message' => 'The domain does not correspond to the contracted subscription',
                    'registered_domain' => $license['domain']
                ];
            }

            // Verificar si está en período de gracia
            $response = [
                'valid' => true,
                'license' => $license
            ];
            
            if ($graceEnabled && $license['expires_at'] && $license['expires_at'] <= date('Y-m-d H:i:s')) {
                $graceEnd = date('Y-m-d H:i:s', strtotime($license['expires_at'] . ' +' . $graceDaysValue . ' days'));
                if (date('Y-m-d H:i:s') <= $graceEnd) {
                    // En período de gracia
                    $graceDaysRemaining = floor((strtotime($graceEnd) - time()) / (60*60*24));
                    $response['warning'] = "Your subscription has expired, in {$graceDaysRemaining} days your subscription will be blocked, you must renew it here: http://www.dwoosync.com";
                } else {
                    // Período de gracia expirado
                    return [
                        'valid' => false,
                        'error' => 'LICENSE_EXPIRED',
                        'message' => 'Licencia expirada'
                    ];
                }
            }

            return $response;

        } catch (Exception $e) {
            error_log("Error validating license: " . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'INTERNAL_ERROR',
                'message' => 'Error interno del servidor'
            ];
        }
    }

    /**
     * Verificar límites de uso
     */
    public function checkUsageLimits($subscriberId, $planType) {
        try {
            $today = date('Y-m-d');
            $thisHour = date('Y-m-d H:00:00');
            $thisMonth = date('Y-m');

            // Obtener límites del plan
            $sql = "SELECT requests_per_hour, requests_per_day, requests_per_month 
                    FROM subscription_plans 
                    WHERE plan_type = :plan_type AND is_active = 1";
            $plan = $this->db->fetch($sql, ['plan_type' => $planType]);

            if (!$plan) {
                return ['allowed' => false, 'error' => 'Plan no válido'];
            }

            // Verificar límite por hora
            $sql = "SELECT COUNT(*) as count FROM api_logs 
                    WHERE subscriber_id = :subscriber_id 
                    AND created_at >= :this_hour";
            $hourlyUsage = $this->db->fetch($sql, [
                'subscriber_id' => $subscriberId,
                'this_hour' => $thisHour
            ]);

            if ($hourlyUsage['count'] >= $plan['requests_per_hour']) {
                return ['allowed' => false, 'error' => 'Límite por hora excedido'];
            }

            // Verificar límite por día
            $sql = "SELECT COUNT(*) as count FROM api_logs 
                    WHERE subscriber_id = :subscriber_id 
                    AND DATE(created_at) = :today";
            $dailyUsage = $this->db->fetch($sql, [
                'subscriber_id' => $subscriberId,
                'today' => $today
            ]);

            if ($dailyUsage['count'] >= $plan['requests_per_day']) {
                return ['allowed' => false, 'error' => 'Límite diario excedido'];
            }

            // Verificar límite por mes
            $sql = "SELECT COUNT(*) as count FROM api_logs 
                    WHERE subscriber_id = :subscriber_id 
                    AND YEAR(created_at) = :year 
                    AND MONTH(created_at) = :month";
            $monthlyUsage = $this->db->fetch($sql, [
                'subscriber_id' => $subscriberId,
                'year' => date('Y'),
                'month' => date('n')
            ]);

            if ($monthlyUsage['count'] >= $plan['requests_per_month']) {
                return ['allowed' => false, 'error' => 'Límite mensual excedido'];
            }

            return ['allowed' => true];

        } catch (Exception $e) {
            error_log("Error verificando límites: " . $e->getMessage());
            return ['allowed' => false, 'error' => 'Error interno del servidor'];
        }
    }

    /**
     * Crear nueva suscripción
     */
    public function createSubscription($data) {
        try {
            $this->db->beginTransaction();

            // Crear suscriptor
            $subscriberData = [
                'email' => $data['email'],
                'domain' => $data['domain'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'phone' => $data['phone'] ?? null,
                'company' => $data['company'] ?? null,
                'plan_type' => $data['plan_type'] ?? 'free',
                'status' => 'active',
                'expiration_date' => $data['expiration_date'] ?? null
            ];

            $subscriberId = $this->db->insert('subscribers', $subscriberData);

            // Crear licencia
            $planType = $data['plan_type'] ?? 'free';
            $licenseData = [
                'subscriber_id' => $subscriberId,
                'subscription_code' => $this->generateSubscriptionCode(),
                'license_key' => $this->generateLicenseKey($planType),
                'domain' => $data['domain'],
                'plugin_version' => $data['plugin_version'] ?? '1.0.0',
                'status' => 'active',
                'expires_at' => $data['expiration_date'] ?? null,
                'usage_limit' => $this->getUsageLimitForPlan($planType)
            ];

            $licenseId = $this->db->insert('licenses', $licenseData);

            $this->db->commit();

            return [
                'success' => true,
                'subscriber_id' => $subscriberId,
                'license_id' => $licenseId,
                'license_key' => $licenseData['license_key'],
                'subscription_code' => $licenseData['subscription_code']
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error creando suscripción: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error creando suscripción'
            ];
        }
    }

    /**
     * Renovar suscripción
     */
    public function renewSubscription($subscriptionCode, $newExpirationDate) {
        try {
            $sql = "UPDATE licenses l 
                    JOIN subscribers s ON l.subscriber_id = s.id 
                    SET l.expires_at = :expiration_date, 
                        l.status = 'active',
                        s.status = 'active'
                    WHERE l.subscription_code = :subscription_code";

            $result = $this->db->query($sql, [
                'expiration_date' => $newExpirationDate,
                'subscription_code' => $subscriptionCode
            ]);

            return $result->rowCount() > 0;

        } catch (Exception $e) {
            error_log("Error renovando suscripción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Suspender suscripción
     */
    public function suspendSubscription($subscriptionCode) {
        try {
            $sql = "UPDATE licenses l 
                    JOIN subscribers s ON l.subscriber_id = s.id 
                    SET l.status = 'inactive',
                        s.status = 'suspended'
                    WHERE l.subscription_code = :subscription_code";

            $result = $this->db->query($sql, ['subscription_code' => $subscriptionCode]);
            return $result->rowCount() > 0;

        } catch (Exception $e) {
            error_log("Error suspendiendo suscripción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener información de suscripción
     */
    public function getSubscriptionInfo($subscriptionCode) {
        try {
            $sql = "SELECT s.*, l.license_key, l.domain, l.plugin_version, l.status as license_status, 
                           l.expires_at, l.usage_count, l.usage_limit, sp.plan_name, sp.features
                    FROM subscribers s
                    JOIN licenses l ON s.id = l.subscriber_id
                    JOIN subscription_plans sp ON s.plan_type = sp.plan_type
                    WHERE l.subscription_code = :subscription_code";

            return $this->db->fetch($sql, ['subscription_code' => $subscriptionCode]);

        } catch (Exception $e) {
            error_log("Error obteniendo información de suscripción: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generar código de suscripción único
     */
    private function generateSubscriptionCode() {
        do {
            $code = 'SUB_' . strtoupper(uniqid());
            $sql = "SELECT COUNT(*) as count FROM licenses WHERE subscription_code = :code";
            $result = $this->db->fetch($sql, ['code' => $code]);
        } while ($result['count'] > 0);

        return $code;
    }

    /**
     * Generar clave de licencia única con formato estandarizado
     */
    private function generateLicenseKey($planType = 'free') {
        do {
            // Generar license_key según el formato estandarizado
            if ($planType === 'free') {
                // Plan Free: FREE + 14 caracteres
                $key = 'FREE' . strtoupper(substr(md5(uniqid(rand(), true) . time()), 0, 14));
            } else {
                // Plan Premium/Enterprise: DW + 16 caracteres
                $key = 'DW' . strtoupper(substr(md5(uniqid(rand(), true) . time()), 0, 16));
            }
            
            // Verificar que sea única en la base de datos
            $sql = "SELECT COUNT(*) as count FROM licenses WHERE license_key = :key";
            $result = $this->db->fetch($sql, ['key' => $key]);
        } while ($result['count'] > 0);

        return $key;
    }

    /**
     * Obtener límite de uso para un plan
     */
    private function getUsageLimitForPlan($planType) {
        $limits = [
            'free' => 10,        // 10 importaciones al mes
            'premium' => -1,     // Ilimitadas
            'enterprise' => -1   // Ilimitadas
        ];

        return $limits[$planType] ?? 10;
    }

    /**
     * Actualizar último uso de licencia
     */
    private function updateLastUsed($licenseKey) {
        try {
            $sql = "UPDATE licenses SET last_used = NOW(), usage_count = usage_count + 1 WHERE license_key = :license_key";
            $this->db->query($sql, ['license_key' => $licenseKey]);
        } catch (Exception $e) {
            error_log("Error actualizando último uso: " . $e->getMessage());
        }
    }

    /**
     * Actualizar último uso de forma asíncrona (optimizado)
     */
    public function updateLastUsedAsync($licenseKey) {
        try {
            // Obtener subscriber_id desde la licencia
            $licenseSql = "SELECT subscriber_id FROM licenses WHERE license_key = :license_key AND status = 'active'";
            $license = $this->db->fetch($licenseSql, ['license_key' => $licenseKey]);
            
            if (!$license) {
                error_log("No se encontró licencia activa para: " . $licenseKey);
                return;
            }
            
            // Actualizar last_used en licenses
            $sql = "UPDATE licenses 
                    SET last_used = NOW() 
                    WHERE license_key = :license_key 
                    AND status = 'active'";
            
            $this->db->query($sql, ['license_key' => $licenseKey]);
            
            // Actualizar api_calls_count en billing_cycles
            $billingSql = "UPDATE billing_cycles 
                          SET api_calls_count = api_calls_count + 1 
                          WHERE subscriber_id = :subscriber_id 
                          AND is_active = 1 
                          ORDER BY created_at DESC 
                          LIMIT 1";
            
            $this->db->query($billingSql, ['subscriber_id' => $license['subscriber_id']]);
            
        } catch (Exception $e) {
            error_log("Error actualizando último uso asíncrono: " . $e->getMessage());
        }
    }
}
