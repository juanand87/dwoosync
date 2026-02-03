<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout con Modal de MercadoPago</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .plan-card {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
            background: white;
        }
        
        .plan-card.premium {
            border-color: #3483FA;
            background: linear-gradient(135deg, #f8faff 0%, #e8f2ff 100%);
        }
        
        .plan-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #1f2937;
        }
        
        .plan-price {
            font-size: 36px;
            font-weight: bold;
            color: #3483FA;
            margin: 20px 0;
        }
        
        .plan-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .plan-features li {
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .plan-features li:last-child {
            border-bottom: none;
        }
        
        .btn {
            background: #3483FA;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background: #2563eb;
        }
        
        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        /* Modal de MercadoPago */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            position: relative;
            background-color: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 10px;
            width: 95%;
            max-width: 1200px;
            height: 85%;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            background: #3483FA;
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            padding: 0;
            background: none;
            border: none;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s;
        }
        
        .close:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .modal-body {
            height: calc(100% - 60px);
            overflow: hidden;
        }
        
        .modal-iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 0 0 10px 10px;
        }
        
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            font-size: 18px;
            color: #6b7280;
        }
        
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #3483FA;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-right: 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .success-message {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
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
    <div class="container">
        <h1>Checkout - Plan Premium</h1>
        
        <div class="plan-card premium">
            <div class="plan-title">Plan Premium</div>
            <div class="plan-price">$9.990 CLP/mes</div>
            <ul class="plan-features">
                <li>✅ 1,000 importaciones por mes</li>
                <li>✅ Sincronización automática</li>
                <li>✅ Soporte prioritario</li>
                <li>✅ Actualizaciones automáticas</li>
            </ul>
            
            <button id="mercadopagoBtn" class="btn" onclick="openMercadoPagoModal()">
                <i class="fas fa-credit-card"></i> Pagar con MercadoPago
            </button>
            
            <div id="statusMessage"></div>
        </div>
    </div>
    
    <!-- Modal de MercadoPago -->
    <div id="mercadopagoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Pago con MercadoPago</h3>
                <button class="close" onclick="closeMercadoPagoModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="loadingDiv" class="loading">
                    <div class="spinner"></div>
                    Creando ciclo de facturación...
                </div>
                <iframe id="mercadopagoIframe" class="modal-iframe" style="display: none;"></iframe>
            </div>
        </div>
    </div>

    <script>
        let billingCycleId = null;
        
        // Función para crear ciclo de facturación pendiente
        function createPendingBillingCycle() {
            return new Promise((resolve, reject) => {
                const planType = 'premium';
                const subscriberId = '<?php echo $_SESSION['subscriber_id'] ?? 'test'; ?>';
                
                console.log('Creando ciclo de facturación pendiente...');
                console.log('Plan Type:', planType);
                console.log('Subscriber ID:', subscriberId);
                
                const formData = new FormData();
                formData.append('plan_type', planType);
                formData.append('subscriber_id', subscriberId);
                
                fetch('create-pending-billing.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => {
                    console.log('Respuesta recibida, status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Datos recibidos:', data);
                    
                    if (data.success) {
                        console.log('Ciclo de facturación pendiente creado:', data.billing_cycle_id);
                        resolve(data.billing_cycle_id);
                    } else {
                        console.error('Error creando ciclo de facturación:', data.message);
                        resolve(null);
                    }
                })
                .catch(error => {
                    console.error('Error en la petición:', error);
                    resolve(null);
                });
            });
        }
        
        // Función para abrir el modal de MercadoPago
        function openMercadoPagoModal() {
            const modal = document.getElementById('mercadopagoModal');
            const loadingDiv = document.getElementById('loadingDiv');
            const iframe = document.getElementById('mercadopagoIframe');
            const btn = document.getElementById('mercadopagoBtn');
            
            // Mostrar modal
            modal.style.display = 'block';
            loadingDiv.style.display = 'flex';
            iframe.style.display = 'none';
            
            // Deshabilitar botón
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            
            // Crear ciclo de facturación
            createPendingBillingCycle().then(cycleId => {
                if (cycleId) {
                    billingCycleId = cycleId;
                    
                    // Ocultar loading y mostrar iframe
                    loadingDiv.style.display = 'none';
                    iframe.style.display = 'block';
                    
                    // Cargar MercadoPago
                    const mpUrl = 'https://www.mercadopago.cl/subscriptions/checkout?preapproval_plan_id=90334be51787402bad7d41110e0904fb&success_url=https%3A%2F%2Fda7a140816ec.ngrok-free.app%2Fapi_discogs%2Fsubscribe%2Fpages%2Fpayment-confirmation.php&failure_url=https%3A%2F%2Fda7a140816ec.ngrok-free.app%2Fapi_discogs%2Fsubscribe%2Fpages%2Fcheckout.php%3Fplan%3Dpremium%26error%3Dpayment_failed&external_reference=' + subscriberId;
                    iframe.src = mpUrl;
                    
                    showStatus('Ciclo de facturación creado. Redirigiendo a MercadoPago...', 'success');
                } else {
                    closeMercadoPagoModal();
                    showStatus('Error al crear el ciclo de facturación. Por favor, intenta nuevamente.', 'error');
                }
                
                // Rehabilitar botón
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-credit-card"></i> Pagar con MercadoPago';
            });
        }
        
        // Función para cerrar el modal
        function closeMercadoPagoModal() {
            const modal = document.getElementById('mercadopagoModal');
            const iframe = document.getElementById('mercadopagoIframe');
            
            modal.style.display = 'none';
            iframe.src = '';
        }
        
        // Función para mostrar mensajes de estado
        function showStatus(message, type) {
            const statusDiv = document.getElementById('statusMessage');
            statusDiv.innerHTML = '<div class="' + type + '-message">' + message + '</div>';
            
            // Auto-ocultar después de 5 segundos
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 5000);
        }
        
        // Cerrar modal al hacer clic fuera de él
        window.onclick = function(event) {
            const modal = document.getElementById('mercadopagoModal');
            if (event.target === modal) {
                closeMercadoPagoModal();
            }
        }
        
        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeMercadoPagoModal();
            }
        });
    </script>
</body>
</html>
