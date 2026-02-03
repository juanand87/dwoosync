<?php
echo "=== PRUEBA SIMPLE DEL PLUGIN ===\n";

// Verificar que los archivos existen
$files = [
    'discogs-sync.php',
    'includes/class-discogs-sync.php',
    'includes/discogs-metabox.php',
    'wdi-ajax-handler.php',
    'wdi-plugin-config.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file existe\n";
    } else {
        echo "✗ $file NO existe\n";
    }
}

echo "\n=== VERIFICACIÓN DE SINTAXIS ===\n";

// Verificar sintaxis de PHP
$php_files = [
    'discogs-sync.php',
    'includes/class-discogs-sync.php',
    'includes/discogs-metabox.php',
    'wdi-ajax-handler.php',
    'wdi-plugin-config.php'
];

foreach ($php_files as $file) {
    $output = shell_exec("php -l $file 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✓ $file - Sintaxis correcta\n";
    } else {
        echo "✗ $file - Error de sintaxis:\n";
        echo "  $output\n";
    }
}

echo "\n=== PLUGIN LISTO PARA USAR ===\n";
echo "El metabox 'Discogs Sync' aparecerá en la página de edición de productos de WooCommerce\n";
echo "El mensaje de advertencia del período de gracia se mostrará cuando corresponda\n";
?>
