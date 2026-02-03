<?php
echo "<h1>Debug de Path</h1>";

echo "<h3>Variables del servidor:</h3>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'No definido') . "<br>";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'No definido') . "<br>";
echo "PATH_INFO: " . ($_SERVER['PATH_INFO'] ?? 'No definido') . "<br>";

echo "<h3>Procesamiento del path:</h3>";
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
echo "Request URI original: " . $requestUri . "<br>";

$path = parse_url($requestUri, PHP_URL_PATH);
echo "Path extraído: " . $path . "<br>";

$path = str_replace('/api_discogs/api/', '', $path);
echo "Path después de reemplazo: " . $path . "<br>";

$pathParts = explode('/', trim($path, '/'));
echo "Path parts: " . print_r($pathParts, true) . "<br>";

$endpoint = $pathParts[0] ?? '';
echo "Endpoint extraído: '" . $endpoint . "'<br>";

echo "<h3>Parámetros GET:</h3>";
print_r($_GET);

echo "<h3>Prueba con endpoint 'search':</h3>";
$_SERVER['REQUEST_URI'] = '/api_discogs/api/search?q=Pink+Floyd&license_key=test';
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace('/api_discogs/api/', '', $path);
$pathParts = explode('/', trim($path, '/'));
$endpoint = $pathParts[0] ?? '';
echo "Con /api/search: endpoint = '" . $endpoint . "'<br>";
?>

