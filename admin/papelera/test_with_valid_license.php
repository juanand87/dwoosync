<?php
/**
 * Script de prueba con licencia válida
 */

// Usar una licencia válida de la base de datos
$valid_license = 'LIC_E1674BC2'; // De la salida anterior
$valid_domain = 'example2.com';

$search_params = [
    'endpoint' => 'search',
    'q' => 'Amor Amarillo',
    'license_key' => $valid_license,
    'domain' => $valid_domain,
    'discogs_api_key' => 'RHwdshtjSotKMFGXAwRe',
    'discogs_api_secret' => 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ',
    'format' => 'Vinyl',
    'country' => ''
];

echo "=== PRUEBA CON LICENCIA VÁLIDA ===\n\n";

// 1. Probar la API con licencia válida
echo "1. Probando API con licencia válida...\n";
$api_url = 'http://localhost/api_discogs/api/index.php?' . http_build_query($search_params);
echo "URL: $api_url\n";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'User-Agent: WordPress/6.0; http://localhost',
        'timeout' => 30
    ]
]);

$response = file_get_contents($api_url, false, $context);
echo "Respuesta: $response\n\n";

// 2. Probar con cURL
echo "2. Probando con cURL...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress/6.0; http://localhost');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$curl_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
if ($curl_error) {
    echo "cURL Error: $curl_error\n";
} else {
    echo "Respuesta: $curl_response\n";
}

// 3. Probar endpoint de salud
echo "\n3. Probando endpoint de salud...\n";
$health_url = 'http://localhost/api_discogs/api/index.php?endpoint=health';
$health_response = file_get_contents($health_url, false, $context);
echo "Respuesta de salud: $health_response\n";

echo "\n=== FIN DE PRUEBA ===\n";
?>


