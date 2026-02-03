<?php
/**
 * Endpoint para probar la conexión con la API de Discogs
 * 
 * Este endpoint recibe una API Key de Discogs y hace una llamada de prueba
 * para verificar que la conexión funciona correctamente.
 */

// Configuración de CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener API Key del POST
$input = json_decode(file_get_contents('php://input'), true);
$api_key = $input['api_key'] ?? $_POST['api_key'] ?? '';

if (empty($api_key)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'API Key requerida']);
    exit;
}

try {
    // URL de la API de Discogs para hacer una llamada de prueba
    $discogs_url = 'https://api.discogs.com/database/search?q=test&type=release&per_page=1';
    
    // Headers para la llamada a Discogs
    $headers = array(
        'User-Agent' => 'DiscogsSync/1.0 +https://www.dwoosync.com',
        'Authorization' => 'Discogs token=' . $api_key
    );
    
    // Hacer la llamada a Discogs
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $discogs_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception('Error cURL: ' . $curl_error);
    }
    
    if ($http_code === 401) {
        throw new Exception('API Key inválida o no autorizada');
    }
    
    if ($http_code === 403) {
        throw new Exception('API Key sin permisos suficientes');
    }
    
    if ($http_code !== 200) {
        throw new Exception('Error HTTP: ' . $http_code);
    }
    
    $data = json_decode($response, true);
    
    if (!$data) {
        throw new Exception('Respuesta inválida de Discogs API');
    }
    
    // Si llegamos aquí, la conexión fue exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Conexión con Discogs API exitosa',
        'data' => [
            'results_count' => count($data['results'] ?? []),
            'http_code' => $http_code
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

