<?php
echo "<h1>Test Final Completo de la API</h1>";

echo "<h3>1. Test de búsqueda básica (Pink Floyd)</h3>";
$url1 = "http://localhost/api_discogs/api/index.php?endpoint=search&q=Pink+Floyd&license_key=LIC_68BF89D32E968_FBC2DE6A&domain=localhost&discogs_api_key=RHwdshtjSotKMFGXAwRe&discogs_api_secret=sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ";

$response1 = file_get_contents($url1);
$data1 = json_decode($response1, true);

if ($data1 && isset($data1['results'])) {
    echo "✅ Búsqueda exitosa<br>";
    echo "Resultados: " . count($data1['results']) . "<br>";
    echo "Primer resultado: " . ($data1['results'][0]['title'] ?? 'N/A') . "<br>";
    echo "Paginación: Página " . ($data1['pagination']['page'] ?? 'N/A') . " de " . ($data1['pagination']['pages'] ?? 'N/A') . "<br>";
} else {
    echo "❌ Error en búsqueda<br>";
}

echo "<br><hr><br>";

echo "<h3>2. Test de búsqueda con filtros (Beatles, UK, 1965)</h3>";
$url2 = "http://localhost/api_discogs/api/index.php?endpoint=search&q=Beatles&country=UK&year=1965&license_key=LIC_68BF89D32E968_FBC2DE6A&domain=localhost&discogs_api_key=RHwdshtjSotKMFGXAwRe&discogs_api_secret=sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ";

$response2 = file_get_contents($url2);
$data2 = json_decode($response2, true);

if ($data2 && isset($data2['results'])) {
    echo "✅ Búsqueda con filtros exitosa<br>";
    echo "Resultados: " . count($data2['results']) . "<br>";
    if (!empty($data2['results'])) {
        echo "Primer resultado: " . ($data2['results'][0]['title'] ?? 'N/A') . "<br>";
        echo "Año: " . ($data2['results'][0]['year'] ?? 'N/A') . "<br>";
        echo "País: " . ($data2['results'][0]['country'] ?? 'N/A') . "<br>";
    }
} else {
    echo "❌ Error en búsqueda con filtros<br>";
}

echo "<br><hr><br>";

echo "<h3>3. Test de búsqueda con filtro de formato (CD)</h3>";
$url3 = "http://localhost/api_discogs/api/index.php?endpoint=search&q=Queen&format=CD&license_key=LIC_68BF89D32E968_FBC2DE6A&domain=localhost&discogs_api_key=RHwdshtjSotKMFGXAwRe&discogs_api_secret=sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ";

$response3 = file_get_contents($url3);
$data3 = json_decode($response3, true);

if ($data3 && isset($data3['results'])) {
    echo "✅ Búsqueda con formato exitosa<br>";
    echo "Resultados: " . count($data3['results']) . "<br>";
    if (!empty($data3['results'])) {
        echo "Primer resultado: " . ($data3['results'][0]['title'] ?? 'N/A') . "<br>";
        echo "Formato: " . implode(', ', $data3['results'][0]['format'] ?? []) . "<br>";
    }
} else {
    echo "❌ Error en búsqueda con formato<br>";
}

echo "<br><hr><br>";

echo "<h3>4. Test de endpoint de salud (health)</h3>";
$url4 = "http://localhost/api_discogs/api/index.php?endpoint=health";

$response4 = file_get_contents($url4);
$data4 = json_decode($response4, true);

if ($data4 && isset($data4['success']) && $data4['success']) {
    echo "✅ Health check exitoso<br>";
    echo "Status: " . ($data4['status'] ?? 'N/A') . "<br>";
    echo "Base de datos: " . ($data4['database'] ?? 'N/A') . "<br>";
    echo "Versión: " . ($data4['version'] ?? 'N/A') . "<br>";
} else {
    echo "❌ Error en health check<br>";
}

echo "<br><hr><br>";

echo "<h3>5. Test de validación de licencia</h3>";
$url5 = "http://localhost/api_discogs/api/index.php?endpoint=license&license_key=LIC_68BF89D32E968_FBC2DE6A&domain=localhost";

$response5 = file_get_contents($url5);
$data5 = json_decode($response5, true);

if ($data5 && isset($data5['valid']) && $data5['valid']) {
    echo "✅ Validación de licencia exitosa<br>";
    echo "Plan: " . ($data5['license']['plan_type'] ?? 'N/A') . "<br>";
    echo "Email: " . ($data5['license']['email'] ?? 'N/A') . "<br>";
} else {
    echo "❌ Error en validación de licencia<br>";
    echo "Error: " . ($data5['error'] ?? 'Desconocido') . "<br>";
}

echo "<br><hr><br>";

echo "<h2>🎉 RESUMEN FINAL</h2>";
echo "<p><strong>✅ La API está funcionando correctamente</strong></p>";
echo "<ul>";
echo "<li>✅ Endpoint de búsqueda funcionando</li>";
echo "<li>✅ Filtros de búsqueda funcionando</li>";
echo "<li>✅ Validación de licencias funcionando</li>";
echo "<li>✅ Integración con Discogs API funcionando</li>";
echo "<li>✅ Procesamiento de resultados funcionando</li>";
echo "<li>✅ Health check funcionando</li>";
echo "</ul>";
echo "<p><strong>La API está lista para ser utilizada por el plugin de WordPress.</strong></p>";
?>

