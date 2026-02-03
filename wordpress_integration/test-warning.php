<?php
/**
 * Prueba para verificar que el warning del período de gracia se genera correctamente
 */

// Simular una petición a la API
$api_url = 'http://localhost/api_discogs/api/index.php';
$license_key = 'test_license_key'; // Usar una licencia de prueba
$domain = 'localhost';

// Parámetros de búsqueda
$search_params = [
    'endpoint' => 'search',
    'q' => 'Pink Floyd',
    'license_key' => $license_key,
    'domain' => $domain
];

$api_url = $api_url . '?' . http_build_query($search_params);

echo "=== PRUEBA DE WARNING DEL PERÍODO DE GRACIA ===\n";
echo "URL de la API: $api_url\n\n";

// Hacer la petición
$response = file_get_contents($api_url);
$data = json_decode($response, true);

if ($data) {
    echo "Respuesta de la API:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    
    if (isset($data['warning'])) {
        echo "✅ WARNING ENCONTRADO: " . $data['warning'] . "\n";
    } else {
        echo "❌ No se encontró warning en la respuesta\n";
    }
    
    if (isset($data['data']['warning'])) {
        echo "✅ WARNING EN DATA: " . $data['data']['warning'] . "\n";
    } else {
        echo "❌ No se encontró warning en data\n";
    }
} else {
    echo "❌ Error: No se pudo decodificar la respuesta JSON\n";
    echo "Respuesta raw: $response\n";
}
?>
