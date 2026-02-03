<?php
/**
 * Gestor de caché para la API
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

class CacheManager {
    private $db;
    private $cachePrefix = 'discogs_api_';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener datos del caché
     */
    public function get($key) {
        try {
            $cacheKey = $this->cachePrefix . $key;
            $result = $this->db->fetch(
                "SELECT cache_data, expires_at FROM cache WHERE cache_key = ? AND expires_at > NOW()",
                [$cacheKey]
            );
            
            if ($result) {
                // Incrementar hits
                $this->db->query(
                    "UPDATE cache SET hits = hits + 1 WHERE cache_key = ?",
                    [$cacheKey]
                );
                
                return json_decode($result['cache_data'], true);
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error obteniendo caché: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Guardar datos en el caché
     */
    public function set($key, $data, $ttl = 3600) {
        try {
            $cacheKey = $this->cachePrefix . $key;
            $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
            $cacheData = json_encode($data);
            
            $this->db->query(
                "INSERT INTO cache (cache_key, cache_data, expires_at) VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE cache_data = VALUES(cache_data), expires_at = VALUES(expires_at), hits = 0",
                [$cacheKey, $cacheData, $expiresAt]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Error guardando caché: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Eliminar del caché
     */
    public function delete($key) {
        try {
            $cacheKey = $this->cachePrefix . $key;
            $this->db->query("DELETE FROM cache WHERE cache_key = ?", [$cacheKey]);
            return true;
        } catch (Exception $e) {
            error_log("Error eliminando caché: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Limpiar caché expirado
     */
    public function cleanExpired() {
        try {
            $this->db->query("DELETE FROM cache WHERE expires_at <= NOW()");
            return true;
        } catch (Exception $e) {
            error_log("Error limpiando caché: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generar clave de caché
     */
    public function generateKey($prefix, $params = []) {
        return $prefix . '_' . md5(serialize($params));
    }
}