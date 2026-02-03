<?php
/**
 * Script de prueba para los endpoints de confirmación de pago
 */

echo "<h1>🧪 Prueba de Endpoints de Pago</h1>";

// Función para hacer requests
function makeRequest($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($data))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $http_code,
        'response' => json_decode($response, true)
    ];
}

// 1. Probar endpoint principal de confirmación
echo "<h2>1. Endpoint Principal de Confirmación</h2>";

$confirmation_data = [
    'payment_id' => 'PAY_TEST_123',
    'status' => 'completed',
    'subscriber_id' => 1, // Cambiar por un ID real
    'payment_method' => 'test',
    'amount' => 22.00,
    'currency' => 'USD',
    'transaction_id' => 'TXN_TEST_123',
    'notes' => 'Prueba de endpoint'
];

$result = makeRequest('http://localhost/api_discogs/api/payment-confirmation.php', $confirmation_data);
echo "<p><strong>HTTP Code:</strong> " . $result['http_code'] . "</p>";
echo "<p><strong>Response:</strong> " . json_encode($result['response'], JSON_PRETTY_PRINT) . "</p>";

// 2. Probar webhook de PayPal
echo "<h2>2. Webhook de PayPal</h2>";

$paypal_data = [
    'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
    'resource' => [
        'id' => 'PAY_TEST_123',
        'amount' => [
            'value' => '22.00',
            'currency_code' => 'USD'
        ]
    ]
];

$result = makeRequest('http://localhost/api_discogs/api/paypal-webhook.php', $paypal_data);
echo "<p><strong>HTTP Code:</strong> " . $result['http_code'] . "</p>";
echo "<p><strong>Response:</strong> " . json_encode($result['response'], JSON_PRETTY_PRINT) . "</p>";

// 3. Probar webhook de Stripe
echo "<h2>3. Webhook de Stripe</h2>";

$stripe_data = [
    'type' => 'payment_intent.succeeded',
    'data' => [
        'object' => [
            'id' => 'PAY_TEST_123',
            'amount' => 2200, // Stripe usa centavos
            'currency' => 'usd'
        ]
    ]
];

$result = makeRequest('http://localhost/api_discogs/api/stripe-webhook.php', $stripe_data);
echo "<p><strong>HTTP Code:</strong> " . $result['http_code'] . "</p>";
echo "<p><strong>Response:</strong> " . json_encode($result['response'], JSON_PRETTY_PRINT) . "</p>";

// 4. Probar pago manual
echo "<h2>4. Pago Manual</h2>";

$manual_data = [
    'payment_id' => 'PAY_TEST_123',
    'subscriber_id' => 1,
    'admin_key' => 'ADMIN_KEY_2024',
    'status' => 'completed',
    'payment_method' => 'bank_transfer',
    'amount' => 22.00,
    'currency' => 'USD',
    'transaction_id' => 'BANK_TXN_123',
    'notes' => 'Transferencia bancaria confirmada',
    'confirmed_by' => 'admin_test'
];

$result = makeRequest('http://localhost/api_discogs/api/manual-payment.php', $manual_data);
echo "<p><strong>HTTP Code:</strong> " . $result['http_code'] . "</p>";
echo "<p><strong>Response:</strong> " . json_encode($result['response'], JSON_PRETTY_PRINT) . "</p>";

echo "<h2>✅ Pruebas Completadas</h2>";
echo "<p>Revisa los logs del servidor para ver los detalles de procesamiento.</p>";
?>

