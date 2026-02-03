<?php

require_once 'CacheManager.php';

class DiscogsAPI {
    private $apiKey;
    private $apiSecret;
    private $baseUrl = 'https://api.discogs.com/';
    private $cache;

    public function __construct($apiKey, $apiSecret) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->cache = new CacheManager();
    }

    /**
     * Buscar releases en Discogs (búsqueda flexible de música)
     */
    public function searchMasters($query, $filters = []) {
        // Verificar que la consulta no esté vacía
        if (empty(trim($query))) {
            return [
                'success' => false,
                'error' => 'La búsqueda no puede estar vacía',
                'results' => [],
                'total' => 0,
                'country_stats' => []
            ];
        }

        $cacheKey = 'search_masters_' . md5($query . serialize($filters));
        
        // Verificar caché
        if (API_CACHE_ENABLED) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Usar comillas para búsqueda exacta y específica
        $searchQuery = '"' . $query . '"';
        
        $params = [
            'q' => $searchQuery,
            'type' => 'release',
            'per_page' => 1000
        ];

        // Agregar filtros si están especificados
        if (isset($filters['format'])) {
            $params['format'] = $filters['format'];
        }
        if (isset($filters['country'])) {
            $params['country'] = $filters['country'];
        }
        if (isset($filters['year'])) {
            $params['year'] = $filters['year'];
        }
        if (isset($filters['label'])) {
            $params['label'] = $filters['label'];
        }
        if (isset($filters['genre'])) {
            $params['genre'] = $filters['genre'];
        }
        if (isset($filters['style'])) {
            $params['style'] = $filters['style'];
        }

        $response = $this->makeRequest('database/search', $params);
        
        if (!$response) {
            return [
                'success' => false,
                'error' => 'Error en la búsqueda de Discogs'
            ];
        }

        $results = $response['results'] ?? [];
        $totalItems = $response['pagination']['items'] ?? count($results);
        $perPage = $response['pagination']['per_page'] ?? 1000;
        
        // Procesar resultados
        $processedResults = $this->processSearchResults($results);
        
        // Contar países
        $countryCounts = [];
        foreach ($processedResults as $result) {
            $country = $result['country'] ?? 'Unknown';
            if (!isset($countryCounts[$country])) {
                $countryCounts[$country] = 0;
            }
            $countryCounts[$country]++;
        }
        
        // Convertir countryCounts a formato esperado por el frontend
        $countryStats = [];
        foreach ($countryCounts as $country => $count) {
            $countryStats[] = [
                'name' => $country,
                'code' => $country,
                'count' => $count
            ];
        }

        $result = [
            'success' => true,
            'results' => $processedResults,
            'total' => $totalItems,
            'per_page' => $perPage,
            'pages' => $totalItems > 0 ? ceil($totalItems / $perPage) : 1,
            'country_stats' => $countryStats,
            'query' => $query,
            'filters' => $filters
        ];
        
        // Guardar en caché
        if (API_CACHE_ENABLED) {
            $this->cache->set($cacheKey, $result, 3600); // 1 hora
        }
        
        return $result;
    }

    /**
     * Procesar resultados de búsqueda
     */
    private function processSearchResults($results) {
        $processed = [];
        
        foreach ($results as $result) {
            $processed[] = [
                'id' => $result['id'],
                'title' => $result['title'],
                'year' => $result['year'] ?? null,
                'country' => $result['country'] ?? 'Unknown',
                'format' => is_array($result['format']) ? $result['format'] : [$result['format']],
                'label' => is_array($result['label']) ? $result['label'] : [$result['label']],
                'genre' => is_array($result['genre']) ? $result['genre'] : [$result['genre']],
                'style' => is_array($result['style']) ? $result['style'] : [$result['style']],
                'thumb' => $result['thumb'] ?? '',
                'cover_image' => $result['cover_image'] ?? '',
                'resource_url' => $result['resource_url'] ?? '',
                'catno' => $result['catno'] ?? ''
            ];
        }
        
        return $processed;
    }

    /**
     * Realizar petición a la API de Discogs
     */
    private function makeRequest($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint . '?' . http_build_query($params);
        
        $headers = [
            'User-Agent: DiscogsAPI/1.0 +https://discogs.com',
            'Authorization: Discogs key=' . $this->apiKey . ', secret=' . $this->apiSecret
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log('[DiscogsAPI] cURL Error: ' . $error);
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log('[DiscogsAPI] HTTP Error: ' . $httpCode . ' - ' . $response);
            return false;
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[DiscogsAPI] JSON Error: ' . json_last_error_msg());
            return false;
        }
        
        return $data;
    }

    /**
     * Obtener información de un release específico
     */
    public function getRelease($releaseId) {
        $cacheKey = 'release_' . $releaseId;
        
        if (API_CACHE_ENABLED) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $response = $this->makeRequest('releases/' . $releaseId);
        
        if (!$response) {
            return [
                'success' => false,
                'error' => 'Error al obtener información del release'
            ];
        }
        
        $result = [
            'success' => true,
            'data' => $response
        ];
        
        if (API_CACHE_ENABLED) {
            $this->cache->set($cacheKey, $result, 3600);
        }
        
        return $result;
    }

    /**
     * Obtener información de un artista
     */
    public function getArtist($artistId) {
        $cacheKey = 'artist_' . $artistId;
        
        if (API_CACHE_ENABLED) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $response = $this->makeRequest('artists/' . $artistId);
        
        if (!$response) {
            return [
                'success' => false,
                'error' => 'Error al obtener información del artista'
            ];
        }
        
        $result = [
            'success' => true,
            'data' => $response
        ];
        
        if (API_CACHE_ENABLED) {
            $this->cache->set($cacheKey, $result, 3600);
        }
        
        return $result;
    }

    /**
     * Obtener imagen de un release
     */
    public function getReleaseImage($releaseId) {
        $cacheKey = 'release_image_' . $releaseId;
        
        if (API_CACHE_ENABLED) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        $response = $this->makeRequest('releases/' . $releaseId . '/images');
        
        if (!$response) {
            return [
                'success' => false,
                'error' => 'Error al obtener imágenes del release'
            ];
        }
        
        $result = [
            'success' => true,
            'data' => $response
        ];
        
        if (API_CACHE_ENABLED) {
            $this->cache->set($cacheKey, $result, 3600);
        }
        
        return $result;
    }
}