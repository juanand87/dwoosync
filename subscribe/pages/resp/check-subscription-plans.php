<?php
/**
 * Script para verificar la estructura de subscription_plans
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

echo "<h2>Verificación de Tabla subscription_plans</h2>";

try {
    $db = getDatabase();
    
    // 1. Verificar estructura de la tabla
    echo "<h3>1. Estructura de la tabla:</h3>";
    $stmt = $db->query("DESCRIBE subscription_plans");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Verificar datos existentes
    echo "<h3>2. Datos existentes:</h3>";
    $stmt = $db->query("SELECT * FROM subscription_plans ORDER BY price ASC");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($plans)) {
        echo "<p style='color: red;'>No hay planes en la tabla</p>";
        
        // Insertar planes de ejemplo
        echo "<h3>3. Insertando planes de ejemplo:</h3>";
        
        $insertPlans = [
            [
                'plan_type' => 'free',
                'plan_name' => 'Gratuito',
                'price' => 0,
                'currency' => 'USD',
                'features' => json_encode([
                    'Hasta 10 importaciones por mes',
                    'Llamadas API limitadas',
                    'Sin soporte',
                    'Documentación básica',
                    '1 Dominio/Sitio Web',
                    'Actualizaciones',
                    'Estadística detallada',
                    'Widget Spotify'
                ]),
                'requests_per_month' => 10
            ],
            [
                'plan_type' => 'premium',
                'plan_name' => 'Premium',
                'price' => 22,
                'currency' => 'USD',
                'features' => json_encode([
                    'Importaciones ilimitadas',
                    'Soporte prioritario',
                    '1 Dominio/Sitio Web',
                    'Actualizaciones',
                    'Estadística detallada',
                    'Widget Spotify'
                ]),
                'requests_per_month' => 999999
            ],
            [
                'plan_type' => 'enterprise',
                'plan_name' => 'Enterprise',
                'price' => 45,
                'currency' => 'USD',
                'features' => json_encode([
                    'Importaciones ilimitadas',
                    'Soporte prioritario',
                    'Documentación completa',
                    'Dominios ilimitados',
                    'Actualizaciones',
                    'Estadística detallada',
                    'Widget Spotify',
                    'API personalizada',
                    'Integración dedicada'
                ]),
                'requests_per_month' => 999999
            ]
        ];
        
        $insertStmt = $db->prepare("
            INSERT INTO subscription_plans (plan_type, plan_name, price, currency, features, requests_per_month, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        foreach ($insertPlans as $plan) {
            $result = $insertStmt->execute([
                $plan['plan_type'],
                $plan['plan_name'],
                $plan['price'],
                $plan['currency'],
                $plan['features'],
                $plan['requests_per_month']
            ]);
            
            if ($result) {
                echo "<p style='color: green;'>✅ Plan {$plan['plan_name']} insertado correctamente</p>";
            } else {
                echo "<p style='color: red;'>❌ Error insertando plan {$plan['plan_name']}</p>";
            }
        }
        
        // Mostrar planes insertados
        echo "<h3>4. Planes después de la inserción:</h3>";
        $stmt = $db->query("SELECT * FROM subscription_plans ORDER BY price ASC");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (!empty($plans)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Plan Type</th><th>Plan Name</th><th>Price</th><th>Currency</th><th>Features</th><th>Requests/Month</th></tr>";
        foreach ($plans as $plan) {
            echo "<tr>";
            echo "<td>{$plan['id']}</td>";
            echo "<td>{$plan['plan_type']}</td>";
            echo "<td>{$plan['plan_name']}</td>";
            echo "<td>\${$plan['price']}</td>";
            echo "<td>{$plan['currency']}</td>";
            echo "<td>" . (strlen($plan['features']) > 50 ? substr($plan['features'], 0, 50) . '...' : $plan['features']) . "</td>";
            echo "<td>{$plan['requests_per_month']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Probar consulta específica
    echo "<h3>5. Probando consulta específica para plan 'premium':</h3>";
    $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE plan_type = ?");
    $stmt->execute(['premium']);
    $premiumPlan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($premiumPlan) {
        echo "<p style='color: green;'>✅ Plan premium encontrado:</p>";
        echo "<ul>";
        echo "<li><strong>ID:</strong> {$premiumPlan['id']}</li>";
        echo "<li><strong>Plan Type:</strong> {$premiumPlan['plan_type']}</li>";
        echo "<li><strong>Plan Name:</strong> {$premiumPlan['plan_name']}</li>";
        echo "<li><strong>Price:</strong> \${$premiumPlan['price']}</li>";
        echo "<li><strong>Currency:</strong> {$premiumPlan['currency']}</li>";
        echo "<li><strong>Features:</strong> " . json_encode(json_decode($premiumPlan['features'], true), JSON_PRETTY_PRINT) . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ Plan premium no encontrado</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }

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

