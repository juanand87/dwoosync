<?php
echo "<h1>Test Detallado del Endpoint</h1>";

echo "<h3>1. Probando endpoint con parámetros completos...</h3>";
$url = "http://localhost/api_discogs/api/index.php?endpoint=search&q=Pink+Floyd&license_key=LIC_68BF89D32E968_FBC2DE6A&domain=localhost&discogs_api_key=RHwdshtjSotKMFGXAwRe&discogs_api_secret=sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ";

echo "URL: " . $url . "<br><br>";

$response = file_get_contents($url);

if ($response === false) {
    echo "❌ Error al acceder al endpoint<br>";
} else {
    echo "✅ Archivo accesible<br>";
    echo "Respuesta completa: <pre>" . htmlspecialchars($response) . "</pre><br>";
    
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        if ($data['success']) {
            echo "✅ Respuesta exitosa<br>";
            echo "Resultados: " . count($data['results'] ?? []) . "<br>";
        } else {
            echo "❌ Error en la respuesta: " . ($data['error'] ?? 'Desconocido') . "<br>";
            if (isset($data['http_code'])) {
                echo "HTTP Code: " . $data['http_code'] . "<br>";
            }
        }
    } else {
        echo "❌ Respuesta inesperada<br>";
    }
}

echo "<br><hr><br>";

echo "<h3>2. Probando con cURL para más detalles...</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response2 = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headers = substr($response2, 0, $headerSize);
$body = substr($response2, $headerSize);

curl_close($ch);

echo "HTTP Code: " . $httpCode . "<br>";
echo "Headers: <pre>" . htmlspecialchars($headers) . "</pre>";
echo "Body: <pre>" . htmlspecialchars($body) . "</pre>";
?>

