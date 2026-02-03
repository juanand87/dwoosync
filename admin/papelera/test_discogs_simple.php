<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Simple de Discogs API</h1>";

// Probar sin autenticación primero
echo "<h3>1. Probando sin autenticación...</h3>";
$url = 'https://api.discogs.com/database/search?q=Pink+Floyd&type=master&per_page=5';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'User-Agent: DiscogsImporter/1.0.0',
        'Accept: application/json'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "<br>";
echo "Error: " . ($error ?: 'None') . "<br>";
echo "Response: " . htmlspecialchars($response) . "<br><br>";

// Probar con autenticación
echo "<h3>2. Probando con autenticación...</h3>";
$apiKey = 'RHwdshtjSotKMFGXAwRe';

$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'User-Agent: DiscogsImporter/1.0.0',
        'Accept: application/json',
        'Authorization: Discogs key=' . $apiKey
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$error2 = curl_error($ch2);
curl_close($ch2);

echo "HTTP Code: " . $httpCode2 . "<br>";
echo "Error: " . ($error2 ?: 'None') . "<br>";
echo "Response: " . htmlspecialchars($response2) . "<br><br>";

// Probar con formato de token
echo "<h3>3. Probando con formato token...</h3>";

$ch3 = curl_init();
curl_setopt_array($ch3, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'User-Agent: DiscogsImporter/1.0.0',
        'Accept: application/json',
        'Authorization: Discogs token=' . $apiKey
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response3 = curl_exec($ch3);
$httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
$error3 = curl_error($ch3);
curl_close($ch3);

echo "HTTP Code: " . $httpCode3 . "<br>";
echo "Error: " . ($error3 ?: 'None') . "<br>";
echo "Response: " . htmlspecialchars($response3) . "<br>";
?>

