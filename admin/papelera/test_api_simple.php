<?php
// Test simple de la API
echo "=== TEST API SIMPLE ===\n";

// Simular petición GET
$_GET = [
    'q' => 'Artaud',
    'license_key' => 'LIC_68BF89D32E968_FBC2DE6A',
    'domain' => 'localhost',
    'discogs_api_key' => 'RHwdshtjSotKMFGXAwRe',
    'discogs_api_secret' => 'sXYOPlqNkzCosZwHAmNYjVlHQfMkCKvJ',
    'format' => 'Vinyl'
];

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/search';

echo "Parámetros GET:\n";
print_r($_GET);

echo "\nIncluyendo archivos...\n";

try {
    require_once 'config/config.php';
    echo "✓ config.php cargado\n";
    
    require_once 'classes/Database.php';
    echo "✓ Database.php cargado\n";
    
    require_once 'classes/LicenseManager.php';
    echo "✓ LicenseManager.php cargado\n";
    
    require_once 'classes/DiscogsAPI.php';
    echo "✓ DiscogsAPI.php cargado\n";
    
    echo "\nEjecutando API...\n";
    include 'api/index.php';
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN TEST ===\n";
?>

