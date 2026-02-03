<?php
/**
 * Debug del checkout para ver qué está pasando con las características
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h2>Debug del Checkout</h2>";

try {
    $db = getDatabase();
    
    // Obtener plan enterprise
    $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
    $stmt->execute(['enterprise']);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($plan) {
        echo "<h3>Plan Enterprise desde la BD:</h3>";
        echo "<pre>";
        print_r($plan);
        echo "</pre>";
        
        echo "<h3>Features JSON Raw:</h3>";
        echo "<p><strong>Raw:</strong> " . htmlspecialchars($plan['features']) . "</p>";
        
        echo "<h3>Features Decoded:</h3>";
        $features = json_decode($plan['features'], true);
        echo "<p><strong>Decoded:</strong> " . json_encode($features, JSON_PRETTY_PRINT) . "</p>";
        
        echo "<h3>Features como Array:</h3>";
        if (is_array($features)) {
            echo "<ul>";
            foreach ($features as $feature) {
                echo "<li>" . htmlspecialchars($feature) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>No es un array válido</p>";
        }
        
        // Probar con características por defecto
        echo "<h3>Características por defecto para Enterprise:</h3>";
        $defaultFeatures = [
            'Importaciones ilimitadas',
            'Soporte prioritario',
            'Documentación completa',
            'Dominios ilimitados',
            'Actualizaciones',
            'Estadística detallada',
            'Widget Spotify',
            'API personalizada',
            'Integración dedicada'
        ];
        
        echo "<ul>";
        foreach ($defaultFeatures as $feature) {
            echo "<li>" . htmlspecialchars($feature) . "</li>";
        }
        echo "</ul>";
        
    } else {
        echo "<p style='color: red;'>Plan enterprise no encontrado</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
ul { margin: 10px 0; }
li { margin: 5px 0; }

        .spinning-disc {
            animation: spin 3s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .nav-logo h2 {
            font-family: 'Orbitron', monospace;
            font-weight: 700;
            letter-spacing: 1px;
            background: linear-gradient(45deg, #1db954, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 0 20px rgba(29, 185, 84, 0.3);
        }
    </style>

