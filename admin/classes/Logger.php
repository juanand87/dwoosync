<?php
/**
 * Clase para manejo de logs
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

class Logger {
    private $db;
    private $logLevel;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logLevel = $this->getLogLevel();
    }

    /**
     * Registrar log de API
     */
    public function logApiRequest($subscriberId, $licenseKey, $endpoint, $method, $ipAddress, $userAgent, $requestData, $responseData, $responseTime, $statusCode, $errorMessage = null) {
        try {
            $data = [
                'subscriber_id' => $subscriberId,
                'license_key' => $licenseKey,
                'endpoint' => $endpoint,
                'method' => $method,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'request_data' => json_encode($requestData),
                'response_data' => json_encode($responseData),
                'response_time' => $responseTime,
                'status_code' => $statusCode,
                'error_message' => $errorMessage
            ];

            $this->db->insert('api_logs', $data);

            // Actualizar estadísticas de uso
            $this->updateUsageStats($subscriberId, $statusCode, $responseTime);

        } catch (Exception $e) {
            error_log("Error registrando log de API: " . $e->getMessage());
        }
    }

    /**
     * Registrar evento de seguridad
     */
    public function logSecurityEvent($eventType, $data = []) {
        try {
            $logData = [
                'event_type' => $eventType,
                'ip_address' => $data['ip'] ?? 'unknown',
                'user_agent' => $data['user_agent'] ?? 'unknown',
                'license_key' => $data['license_key'] ?? 'unknown',
                'domain' => $data['domain'] ?? 'unknown',
                'subscriber_id' => $data['subscriber_id'] ?? null,
                'endpoint' => $data['endpoint'] ?? 'unknown',
                'reason' => $data['reason'] ?? null,
                'error' => $data['error'] ?? null,
                'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
                'data' => json_encode($data)
            ];

            $this->db->insert('security_logs', $logData);

            // También log en archivo para alertas inmediatas
            $this->logToFile('SECURITY', $eventType . ': ' . json_encode($data));

        } catch (Exception $e) {
            error_log("Error registrando evento de seguridad: " . $e->getMessage());
        }
    }

    /**
     * Log a archivo para alertas inmediatas
     */
    private function logToFile($level, $message) {
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Registrar log del sistema
     */
    public function log($level, $message, $context = []) {
        if (!$this->shouldLog($level)) {
            return;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        $this->writeToFile($logEntry);
    }

    /**
     * Log de debug
     */
    public function debug($message, $context = []) {
        $this->log('debug', $message, $context);
    }

    /**
     * Log de información
     */
    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }

    /**
     * Log de advertencia
     */
    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }

    /**
     * Log de error
     */
    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }

    /**
     * Actualizar estadísticas de uso
     */
    private function updateUsageStats($subscriberId, $statusCode, $responseTime) {
        try {
            $today = date('Y-m-d');
            
            // Verificar si ya existe registro para hoy
            $sql = "SELECT id FROM usage_stats WHERE subscriber_id = :subscriber_id AND date = :date";
            $existing = $this->db->fetch($sql, [
                'subscriber_id' => $subscriberId,
                'date' => $today
            ]);

            if ($existing) {
                // Actualizar registro existente
                $sql = "UPDATE usage_stats SET 
                        requests_count = requests_count + 1,
                        requests_successful = requests_successful + :successful,
                        requests_failed = requests_failed + :failed,
                        total_response_time = total_response_time + :response_time
                        WHERE subscriber_id = :subscriber_id AND date = :date";
                
                $this->db->query($sql, [
                    'subscriber_id' => $subscriberId,
                    'date' => $today,
                    'successful' => $statusCode >= 200 && $statusCode < 300 ? 1 : 0,
                    'failed' => $statusCode >= 400 ? 1 : 0,
                    'response_time' => $responseTime
                ]);
            } else {
                // Crear nuevo registro
                $data = [
                    'subscriber_id' => $subscriberId,
                    'date' => $today,
                    'requests_count' => 1,
                    'requests_successful' => $statusCode >= 200 && $statusCode < 300 ? 1 : 0,
                    'requests_failed' => $statusCode >= 400 ? 1 : 0,
                    'total_response_time' => $responseTime
                ];
                
                $this->db->insert('usage_stats', $data);
            }

        } catch (Exception $e) {
            error_log("Error actualizando estadísticas: " . $e->getMessage());
        }
    }

    /**
     * Verificar si debe registrar el log según el nivel
     */
    private function shouldLog($level) {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $currentLevel = $levels[$this->logLevel] ?? 1;
        $messageLevel = $levels[$level] ?? 1;
        
        return $messageLevel >= $currentLevel;
    }

    /**
     * Escribir log a archivo
     */
    private function writeToFile($logEntry) {
        try {
            $logDir = dirname(LOG_FILE);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $logLine = sprintf(
                "[%s] %s: %s %s\n",
                $logEntry['timestamp'],
                $logEntry['level'],
                $logEntry['message'],
                !empty($logEntry['context']) ? json_encode($logEntry['context']) : ''
            );

            file_put_contents(LOG_FILE, $logLine, FILE_APPEND | LOCK_EX);

        } catch (Exception $e) {
            error_log("Error escribiendo log: " . $e->getMessage());
        }
    }

    /**
     * Obtener nivel de log desde configuración
     */
    private function getLogLevel() {
        try {
            $result = $this->db->fetch("SELECT config_value FROM system_config WHERE config_key = 'log_level'");
            return $result ? $result['config_value'] : 'info';
        } catch (Exception $e) {
            return 'info';
        }
    }

    /**
     * Limpiar logs antiguos
     */
    public function cleanOldLogs() {
        try {
            $retentionDays = $this->getLogRetentionDays();
            $sql = "DELETE FROM api_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $result = $this->db->query($sql, ['days' => $retentionDays]);
            return $result->rowCount();
        } catch (Exception $e) {
            error_log("Error limpiando logs antiguos: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener días de retención de logs
     */
    private function getLogRetentionDays() {
        try {
            $result = $this->db->fetch("SELECT config_value FROM system_config WHERE config_key = 'max_log_retention_days'");
            return $result ? (int)$result['config_value'] : 30;
        } catch (Exception $e) {
            return 30;
        }
    }

    /**
     * Obtener estadísticas de logs
     */
    public function getLogStats($days = 7) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_requests,
                        COUNT(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 END) as successful_requests,
                        COUNT(CASE WHEN status_code >= 400 THEN 1 END) as failed_requests,
                        AVG(response_time) as avg_response_time,
                        MAX(response_time) as max_response_time
                    FROM api_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            return $this->db->fetch($sql, ['days' => $days]);
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de logs: " . $e->getMessage());
            return null;
        }
    }
}

