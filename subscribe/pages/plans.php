<?php
// Definir constante para acceso a la API
define('API_ACCESS', true);

// Incluir configuración de base de datos
require_once '../../config/database.php';

// Incluir funciones auxiliares
require_once __DIR__ . '/../includes/functions.php';

// Verificar sesión
session_start();
if (!isset($_SESSION['subscriber_id'])) {
    header('Location: ../index.php');
    exit;
}

$subscriber_id = $_SESSION['subscriber_id'];

// Detectar idioma del navegador
$browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
$currentLang = $_GET['lang'] ?? ($_SESSION['selected_language'] ?? $browserLang);
$isEnglish = ($currentLang === 'en');

// Guardar idioma seleccionado en sesión
if (isset($_GET['lang'])) {
    $_SESSION['selected_language'] = $_GET['lang'];
}

// Función para traducir texto
function t($spanish, $english) {
    global $isEnglish;
    return $isEnglish ? $english : $spanish;
}

// Obtener información del suscriptor
$subscriber = $pdo->prepare("SELECT * FROM subscribers WHERE id = ?");
$subscriber->execute([$subscriber_id]);
$subscriber_data = $subscriber->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planes - DiscogsSync</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .nav-links {
            display: flex;
            gap: 20px;
        }
        
        .nav-links a {
            color: #333;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-logout {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
        }
        
        .btn-logout:hover {
            background: linear-gradient(45deg, #ee5a24, #ff6b6b);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .plans-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .plans-header h1 {
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            font-size: 2.5rem;
        }
        
        .plans-header p {
            color: #666;
            font-size: 1.2rem;
        }
        
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .plan-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            text-align: center;
        }
        
        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .plan-card.featured {
            border: 3px solid #f59e0b;
            transform: scale(1.05);
        }
        
        .plan-card.featured::before {
            content: 'MÁS POPULAR';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(45deg, #f59e0b, #d97706);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .plan-name {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .plan-price {
            font-size: 3rem;
            font-weight: bold;
            background: linear-gradient(45deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
        
        .plan-price .currency {
            font-size: 1.5rem;
            vertical-align: top;
        }
        
        .plan-price .period {
            font-size: 1rem;
            color: #666;
            font-weight: normal;
        }
        
        .plan-description {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .plan-features {
            list-style: none;
            margin-bottom: 30px;
        }
        
        .plan-features li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .plan-features li::before {
            content: '✅';
            font-size: 1.2rem;
        }
        
        .plan-features li.unavailable::before {
            content: '❌';
        }
        
        .plan-features li.unavailable {
            color: #999;
            text-decoration: line-through;
        }
        
        .btn-plan {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        
        .btn-plan:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-plan.featured {
            background: linear-gradient(45deg, #f59e0b, #d97706);
        }
        
        .btn-plan.featured:hover {
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3);
        }
        
        .btn-plan.current {
            background: linear-gradient(45deg, #10b981, #059669);
            cursor: default;
        }
        
        .btn-plan.current:hover {
            transform: none;
            box-shadow: none;
        }
        
        .current-plan {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid #10b981;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .current-plan h3 {
            color: #10b981;
            margin-bottom: 10px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #764ba2;
        }
        
        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }
            
            .plan-card.featured {
                transform: none;
            }
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
    
    <?php renderWhatsAppStyles(); ?>
</head>
<body>
    <div class="header">
        <div class="nav">
            <div class="logo">🎵 DiscogsSync</div>
            <div class="nav-links">
                <a href="dashboard.php">🏠 Dashboard</a>
                <a href="profile.php">👤 Perfil</a>
                <a href="billing.php">💳 Facturación</a>
                <a href="logout.php" class="btn-logout">🚪 Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="plans-header">
            <h1>🚀 Elige tu Plan</h1>
            <p>Selecciona el plan que mejor se adapte a tus necesidades</p>
        </div>

        <!-- Plan Actual -->
        <div class="current-plan">
            <h3>📋 Tu Plan Actual</h3>
            <p><strong><?php echo ucfirst($subscriber_data['plan_type'] ?? 'free'); ?></strong> - 
            <?php 
            $plan_type = $subscriber_data['plan_type'] ?? 'free';
            switch($plan_type) {
                case 'free':
                    echo 'Plan gratuito con funcionalidades básicas';
                    break;
                case 'premium':
                    echo 'Plan premium con funcionalidades avanzadas';
                    break;
                case 'enterprise':
                    echo 'Plan +Spotify con integración avanzada';
                    break;
                default:
                    echo 'Plan personalizado';
            }
            ?>
            </p>
        </div>

        <div class="plans-grid">
            <!-- Plan Free -->
            <div class="plan-card">
                <div class="plan-name">Free</div>
                <div class="plan-price">
                    <span class="currency">$</span>0
                    <span class="period">/<?php echo t('mes', 'month'); ?></span>
                </div>
                <div class="plan-description">
                    Perfecto para comenzar con DiscogsSync
                </div>
                <ul class="plan-features">
                    <li>Importaciones ilimitadas</li>
                    <li>Soporte por email</li>
                    <li>Actualizaciones básicas</li>
                    <li class="unavailable">Sincronización automática</li>
                    <li class="unavailable">Soporte prioritario</li>
                </ul>
                <?php if ($plan_type === 'free'): ?>
                    <button class="btn-plan current">Plan Actual</button>
                <?php else: ?>
                    <a href="#" class="btn-plan" onclick="alert('Contacta soporte para cambiar a este plan')">Seleccionar</a>
                <?php endif; ?>
            </div>

            <!-- Plan Premium -->
            <div class="plan-card featured">
                <div class="plan-name">Premium</div>
                <div class="plan-price">
                    <span class="currency">$</span>29
                    <span class="period">/<?php echo t('mes', 'month'); ?></span>
                </div>
                <div class="plan-description">
                    Ideal para tiendas medianas y profesionales
                </div>
                <ul class="plan-features">
                    <li>Importaciones ilimitadas</li>
                    <li>Soporte prioritario</li>
                    <li>Actualizaciones automáticas</li>
                    <li>Sincronización programada</li>
                    <li>Análisis de rendimiento</li>
                    <li class="unavailable">Widget Spotify</li>
                </ul>
                <?php if ($plan_type === 'premium'): ?>
                    <button class="btn-plan current">Plan Actual</button>
                <?php else: ?>
                    <a href="payment.php?plan=premium" class="btn-plan featured">Mejorar a Premium</a>
                <?php endif; ?>
            </div>

            <!-- Plan +Spotify -->
            <div class="plan-card">
                <div class="plan-name">+Spotify</div>
                <div class="plan-price">
                    <span class="currency">$</span>29
                    <span class="period">/<?php echo t('mes', 'month'); ?></span>
                </div>
                <div class="plan-description">
                    Plan premium con integración de Spotify
                </div>
                <ul class="plan-features">
                    <li>Importaciones ilimitadas</li>
                    <li>Integración con Spotify</li>
                    <li>Soporte prioritario</li>
                    <li>Actualizaciones automáticas</li>
                    <li>Análisis de rendimiento</li>
                </ul>
                <?php if ($plan_type === 'enterprise'): ?>
                    <button class="btn-plan current">Plan Actual</button>
                <?php else: ?>
                    <a href="payment.php?plan=enterprise" class="btn-plan">Mejorar a +Spotify</a>
                <?php endif; ?>
            </div>
        </div>

        <div style="text-align: center;">
            <a href="dashboard.php" class="back-link">← Volver al Dashboard</a>
        </div>
    </div>

    <script>
        // Funcionalidad para mostrar información adicional de los planes
        document.querySelectorAll('.btn-plan').forEach(btn => {
            if (!btn.classList.contains('current')) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const planName = this.closest('.plan-card').querySelector('.plan-name').textContent;
                    alert(`Funcionalidad de cambio de plan para ${planName} estará disponible próximamente.\n\nContacta soporte para más información.`);
                });
            }
        });
    </script>
    
    <?php renderWhatsAppButton($currentLang); ?>
    <?php renderWhatsAppScript($currentLang); ?>
</body>
</html>
