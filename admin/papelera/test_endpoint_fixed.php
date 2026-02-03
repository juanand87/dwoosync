<?php
echo "<h1>Test de Endpoint Corregido</h1>";

// Probar acceso directo al archivo con parámetro endpoint
echo "<h3>1. Probando acceso directo con parámetro endpoint...</h3>";
$url = "http://localhost/api_discogs/api/index.php?endpoint=search&q=Pink+Floyd&license_key=LIC_68BF89D32E968_FBC2DE6A&domain=localhost&discogs_api_key=RHwdshtjSotKMFGXAwRe&discogs_api_secret=sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ";

echo "URL: " . $url . "<br><br>";

$response = file_get_contents($url);

if ($response === false) {
    echo "❌ Error al acceder al endpoint<br>";
} else {
    echo "✅ Archivo accesible<br>";
    echo "Respuesta: " . $response . "<br><br>";
    
    $data = json_decode($response, true);
    if ($data && isset($data['success'])) {
        if ($data['success']) {
            echo "✅ Respuesta exitosa<br>";
            echo "Resultados: " . count($data['results'] ?? []) . "<br>";
        } else {
            echo "❌ Error en la respuesta: " . ($data['error'] ?? 'Desconocido') . "<br>";
        }
    } else {
        echo "❌ Respuesta inesperada<br>";
    }
}

echo "<br><hr><br>";

// Probar endpoint con rewrite
echo "<h3>2. Probando endpoint con rewrite...</h3>";
$url2 = "http://localhost/api_discogs/api/search?q=Pink+Floyd&license_key=LIC_68BF89D32E968_FBC2DE6A&domain=localhost&discogs_api_key=RHwdshtjSotKMFGXAwRe&discogs_api_secret=sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ";

echo "URL: " . $url2 . "<br><br>";

$response2 = file_get_contents($url2);

if ($response2 === false) {
    echo "❌ Error al acceder al endpoint<br>";
} else {
    echo "✅ Archivo accesible<br>";
    echo "Respuesta: " . $response2 . "<br><br>";
    
    $data2 = json_decode($response2, true);
    if ($data2 && isset($data2['success'])) {
        if ($data2['success']) {
            echo "✅ Respuesta exitosa<br>";
            echo "Resultados: " . count($data2['results'] ?? []) . "<br>";
        } else {
            echo "❌ Error en la respuesta: " . ($data2['error'] ?? 'Desconocido') . "<br>";
        }
    } else {
        echo "❌ Respuesta inesperada<br>";
    }
}
?>

