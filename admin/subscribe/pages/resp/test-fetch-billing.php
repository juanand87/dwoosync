<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba Fetch - Crear Ciclo de Facturación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .result {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    
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
</head>
<body>
    <h1>Prueba Fetch - Crear Ciclo de Facturación</h1>
    
    <div class="result">
        <h3>Información de la Sesión:</h3>
        <p><strong>Subscriber ID:</strong> <?php echo $_SESSION['subscriber_id'] ?? 'No definido'; ?></p>
        <p><strong>Plan Type:</strong> premium</p>
        <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
    </div>
    
    <div class="result">
        <h3>Prueba con Fetch API:</h3>
        <button onclick="testCreatePendingBillingCycleWithFetch()" class="btn">Probar con Fetch</button>
        <div id="result"></div>
    </div>
    
    <div class="result">
        <h3>Log de la consola:</h3>
        <div id="console-log"></div>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="checkout.php?plan=premium" class="btn">Ir al Checkout</a>
        <a href="test-simple-mercadopago.php" class="btn">Prueba Simple</a>
        <a href="dashboard.php" class="btn">Ir al Dashboard</a>
    </div>

    <script>
        // Función para mostrar logs en la página
        function log(message) {
            console.log(message);
            const logDiv = document.getElementById('console-log');
            logDiv.innerHTML += '<p>' + new Date().toLocaleTimeString() + ': ' + message + '</p>';
        }
        
        // Función para mostrar resultados
        function showResult(message, isSuccess = true) {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div class="' + (isSuccess ? 'success' : 'error') + '">' + message + '</div>';
        }
        
        // Función createPendingBillingCycle usando Fetch
        function createPendingBillingCycleWithFetch() {
            return new Promise((resolve, reject) => {
                const planType = 'premium';
                const subscriberId = '<?php echo $_SESSION['subscriber_id'] ?? 'test'; ?>';
                
                log('Iniciando createPendingBillingCycle con Fetch...');
                log('Plan Type: ' + planType);
                log('Subscriber ID: ' + subscriberId);
                
                const formData = new FormData();
                formData.append('plan_type', planType);
                formData.append('subscriber_id', subscriberId);
                
                fetch('create-pending-billing.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin' // Importante para enviar cookies de sesión
                })
                .then(response => {
                    log('Respuesta recibida, status: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    log('Datos parseados: ' + JSON.stringify(data));
                    
                    if (data.success) {
                        log('Ciclo de facturación pendiente creado: ' + data.billing_cycle_id);
                        resolve(data.billing_cycle_id);
                    } else {
                        log('Error creando ciclo de facturación: ' + data.message);
                        resolve(null);
                    }
                })
                .catch(error => {
                    log('Error en la petición: ' + error.message);
                    reject(error);
                });
            });
        }
        
        // Función de prueba
        function testCreatePendingBillingCycleWithFetch() {
            log('=== INICIANDO PRUEBA CON FETCH ===');
            showResult('Iniciando prueba con Fetch...', true);
            
            createPendingBillingCycleWithFetch().then(billingCycleId => {
                if (billingCycleId) {
                    log('✅ Prueba exitosa - Billing Cycle ID: ' + billingCycleId);
                    showResult('✅ ÉXITO: Ciclo de facturación creado con ID ' + billingCycleId, true);
                } else {
                    log('❌ Prueba fallida - No se pudo crear el ciclo');
                    showResult('❌ ERROR: No se pudo crear el ciclo de facturación', false);
                }
            }).catch(error => {
                log('❌ Error en la promesa: ' + error.message);
                showResult('❌ ERROR: ' + error.message, false);
            });
        }
        
        // Inicializar
        log('Página cargada, listo para probar con Fetch');
    </script>
</body>
</html>


