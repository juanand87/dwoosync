<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prueba JavaScript - Crear Ciclo de Facturación</title>
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
    <h1>Prueba JavaScript - Crear Ciclo de Facturación</h1>
    
    <div class="result">
        <h3>Información de la Sesión:</h3>
        <p><strong>Subscriber ID:</strong> <?php echo $_SESSION['subscriber_id'] ?? 'No definido'; ?></p>
        <p><strong>Plan Type:</strong> premium</p>
        <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
    </div>
    
    <div class="result">
        <h3>Prueba de la función createPendingBillingCycle():</h3>
        <button onclick="testCreatePendingBillingCycle()" class="btn">Probar Crear Ciclo Pendiente</button>
        <div id="result"></div>
    </div>
    
    <div class="result">
        <h3>Log de la consola:</h3>
        <div id="console-log"></div>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="checkout.php?plan=premium" class="btn">Ir al Checkout</a>
        <a href="test-mercadopago-billing.php" class="btn">Probar MercadoPago</a>
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
        
        // Función createPendingBillingCycle (copiada del checkout.php)
        function createPendingBillingCycle() {
            return new Promise((resolve, reject) => {
                const planType = 'premium';
                const subscriberId = '<?php echo $_SESSION['subscriber_id'] ?? 'test'; ?>';
                
                log('Iniciando createPendingBillingCycle...');
                log('Plan Type: ' + planType);
                log('Subscriber ID: ' + subscriberId);
                
                // Crear formulario oculto
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'create-pending-billing-debug.php';
                form.style.display = 'none';
                
                // Agregar campos
                const planField = document.createElement('input');
                planField.type = 'hidden';
                planField.name = 'plan_type';
                planField.value = planType;
                form.appendChild(planField);
                
                const subscriberField = document.createElement('input');
                subscriberField.type = 'hidden';
                subscriberField.name = 'subscriber_id';
                subscriberField.value = subscriberId;
                form.appendChild(subscriberField);
                
                log('Formulario creado con campos: plan_type=' + planType + ', subscriber_id=' + subscriberId);
                
                // Crear iframe oculto para la respuesta
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.name = 'billing_iframe';
                iframe.onload = function() {
                    log('Iframe cargado, procesando respuesta...');
                    try {
                        const response = iframe.contentDocument.body.textContent;
                        log('Respuesta recibida: ' + response.substring(0, 200) + '...');
                        
                        // Buscar si hay un billing_cycle_id en la respuesta
                        if (response.includes('Billing Cycle ID:')) {
                            const match = response.match(/Billing Cycle ID: (\d+)/);
                            if (match) {
                                const billingCycleId = match[1];
                                log('Ciclo de facturación pendiente creado: ' + billingCycleId);
                                resolve(billingCycleId);
                            } else {
                                log('No se pudo extraer el ID del ciclo de la respuesta');
                                resolve(null);
                            }
                        } else if (response.includes('ERROR')) {
                            log('Error en la respuesta: ' + response);
                            resolve(null);
                        } else {
                            log('Respuesta inesperada: ' + response);
                            resolve(null);
                        }
                    } catch (error) {
                        log('Error procesando respuesta: ' + error.message);
                        resolve(null);
                    }
                    
                    // Limpiar
                    document.body.removeChild(form);
                    document.body.removeChild(iframe);
                };
                
                form.target = 'billing_iframe';
                document.body.appendChild(form);
                document.body.appendChild(iframe);
                
                log('Enviando formulario...');
                form.submit();
            });
        }
        
        // Función de prueba
        function testCreatePendingBillingCycle() {
            log('=== INICIANDO PRUEBA ===');
            showResult('Iniciando prueba...', true);
            
            createPendingBillingCycle().then(billingCycleId => {
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
        log('Página cargada, listo para probar');
    </script>
</body>
</html>


