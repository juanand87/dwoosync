<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Endpoint Simple</h1>";

echo "<h3>1. Probando acceso directo al archivo...</h3>";
$url = 'http://localhost/api_discogs/api/index.php?q=Pink+Floyd&license_key=LIC_68BF89D32E968_FBC2DE6A&domain=localhost&discogs_api_key=RHwdshtjSotKMFGXAwRe&discogs_api_secret=sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ';

echo "URL: " . $url . "<br><br>";

$response = file_get_contents($url);

if ($response === false) {
    echo "❌ Error al acceder al archivo<br>";
} else {
    echo "✅ Archivo accesible<br>";
    echo "Respuesta: " . htmlspecialchars($response) . "<br>";
}

echo "<h3>2. Probando endpoint con rewrite...</h3>";
$url2 = 'http://localhost/api_discogs/api/search?q=Pink+Floyd&license_key=LIC_68BF89D32E968_FBC2DE6A&domain=localhost&discogs_api_key=RHwdshtjSotKMFGXAwRe&discogs_api_secret=sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ';

echo "URL: " . $url2 . "<br><br>";

$response2 = file_get_contents($url2);

if ($response2 === false) {
    echo "❌ Error al acceder al endpoint<br>";
} else {
    echo "✅ Endpoint accesible<br>";
    echo "Respuesta: " . htmlspecialchars($response2) . "<br>";
}
?>

