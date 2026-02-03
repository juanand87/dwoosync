<?php
/**
 * Notificación global de suscripción incompleta
 * Se incluye en todas las páginas del sitio
 */

// Solo mostrar si el usuario está logueado
if (isset($_SESSION['subscriber_id'])) {
    $subscriber_id = $_SESSION['subscriber_id'];
    $license_key = $_SESSION['license_key'] ?? '';
    
    // Verificar si hay factura (cualquier registro en billing_cycles)
    $has_active_invoice = false;
    
    try {
        $db = getDatabase();
        $stmt = $db->prepare("
            SELECT * FROM billing_cycles 
            WHERE subscriber_id = ? AND status = 'paid'
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$subscriber_id]);
        $billing_cycle_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug
        echo "<!-- NOTIFICATION DEBUG: subscriber_id = $subscriber_id, license_key = $license_key -->";
        if ($billing_cycle_data) {
            echo "<!-- NOTIFICATION DEBUG: Factura encontrada - ID: {$billing_cycle_data['id']}, Plan: {$billing_cycle_data['plan_type']}, Status: {$billing_cycle_data['status']} -->";
            $has_active_invoice = true;
        } else {
            echo "<!-- NOTIFICATION DEBUG: NO se encontró factura pagada -->";
        }
    } catch (Exception $e) {
        // En caso de error, asumir que no hay factura
        echo "<!-- NOTIFICATION DEBUG ERROR: " . $e->getMessage() . " -->";
        $has_active_invoice = false;
    }
    
    // Mostrar notificación si no hay factura
    if (!$has_active_invoice) {
        ?>
        <!-- Notificación global de suscripción incompleta -->
        <div id="globalSubscriptionNotification" style="
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 12px 20px;
            text-align: center;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        ">
            <div style="display: flex; align-items: center; justify-content: center; gap: 10px; max-width: 1200px; margin: 0 auto;">
                <div style="font-size: 1.2rem;">⚠️</div>
                <div style="flex: 1;">
                    <strong>¡Completa tu suscripción!</strong> 
                    Selecciona un plan para continuar usando dwoosync.
                </div>
                <a href="pages/checkout.php?plan=free" style="
                    background: rgba(255,255,255,0.2);
                    color: white;
                    padding: 8px 16px;
                    border-radius: 6px;
                    text-decoration: none;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    border: 1px solid rgba(255,255,255,0.3);
                " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                    Completar Ahora
                </a>
                <button onclick="hideNotification()" style="
                    background: none;
                    border: none;
                    color: white;
                    font-size: 1.5rem;
                    cursor: pointer;
                    padding: 0;
                    margin-left: 10px;
                " title="Cerrar notificación">×</button>
            </div>
        </div>
        
        <!-- Ajustar el body para la notificación -->
        <style>
            body {
                padding-top: 60px !important;
            }
            
            @media (max-width: 768px) {
                body {
                    padding-top: 80px !important;
                }
                
                #globalSubscriptionNotification {
                    padding: 15px 10px;
                }
                
                #globalSubscriptionNotification > div {
                    flex-direction: column;
                    gap: 10px;
                }
            }
        </style>
        
        <script>
            function hideNotification() {
                document.getElementById('globalSubscriptionNotification').style.display = 'none';
                document.body.style.paddingTop = '0px';
            }
            
            // Ocultar automáticamente después de 10 segundos (opcional)
            // setTimeout(hideNotification, 10000);
        </script>
        <?php
    }
}
?>



