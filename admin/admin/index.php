<?php
/**
 * Panel de administración de la API
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración y clases
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/LicenseManager.php';
require_once __DIR__ . '/../classes/CacheManager.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/AdminAuth.php';

// Verificar autenticación de administrador
$auth = new AdminAuth();

if (!$auth->isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();

// Procesar logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

// Obtener estadísticas
$db = Database::getInstance();
$licenseManager = new LicenseManager();
$cacheManager = new CacheManager();
$logger = new Logger();

$stats = [
    'subscribers' => $db->fetch("SELECT COUNT(*) as count FROM subscribers")['count'],
    'licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses")['count'],
    'active_licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses WHERE status = 'active'")['count'],
    'api_requests_today' => $db->fetch("SELECT COUNT(*) as count FROM api_logs WHERE DATE(created_at) = CURDATE()")['count'],
    'cache_entries' => $db->fetch("SELECT COUNT(*) as count FROM cache")['count'],
    'cache_hits' => $db->fetch("SELECT SUM(hits) as hits FROM cache")['hits'] ?? 0
];

$recent_requests = $db->fetchAll("
    SELECT al.*, s.email, s.first_name, s.last_name 
    FROM api_logs al 
    JOIN subscribers s ON al.subscriber_id = s.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");

$top_subscribers = $db->fetchAll("
    SELECT s.email, s.first_name, s.last_name, s.plan_type, COUNT(al.id) as request_count
    FROM subscribers s 
    LEFT JOIN api_logs al ON s.id = al.subscriber_id 
    WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY s.id 
    ORDER BY request_count DESC 
    LIMIT 10
");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Discogs API</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin-nav.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007cba; margin-bottom: 5px; }
        .stat-label { color: #666; font-size: 0.9em; }
        .section { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h3 { margin-top: 0; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .status-active { color: green; font-weight: bold; }
        .status-inactive { color: red; font-weight: bold; }
        .logout { float: right; background: #dc3545; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; }
        .logout:hover { background: #c82333; }
        .refresh-btn { background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 20px; }
        .refresh-btn:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Panel de Administración - Discogs API</h1>
        <a href="?logout=1" class="logout">Cerrar Sesión</a>
        <button onclick="location.reload()" class="refresh-btn">Actualizar</button>
    </div>

    <?php include 'includes/admin-nav.php'; ?>

    <div class="section">
        <h3>Herramientas de Administración</h3>
        <p>
            <button onclick="cleanCache()" class="refresh-btn">Limpiar Caché</button>
            <button onclick="cleanLogs()" class="refresh-btn">Limpiar Logs Antiguos</button>
            <button onclick="generatePluginZip()" class="refresh-btn" style="background: #17a2b8;">Generar ZIP del Plugin</button>
        </p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['subscribers']); ?></div>
            <div class="stat-label">Suscriptores</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['licenses']); ?></div>
            <div class="stat-label">Licencias</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['active_licenses']); ?></div>
            <div class="stat-label">Licencias Activas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['api_requests_today']); ?></div>
            <div class="stat-label">Peticiones Hoy</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['cache_entries']); ?></div>
            <div class="stat-label">Entradas en Caché</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['cache_hits']); ?></div>
            <div class="stat-label">Cache Hits</div>
        </div>
    </div>

    <div class="section">
        <h3>Peticiones Recientes</h3>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Endpoint</th>
                    <th>Método</th>
                    <th>IP</th>
                    <th>Estado</th>
                    <th>Tiempo (ms)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_requests as $request): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($request['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($request['endpoint']); ?></td>
                    <td><?php echo htmlspecialchars($request['method']); ?></td>
                    <td><?php echo htmlspecialchars($request['ip_address']); ?></td>
                    <td class="<?php echo $request['status_code'] >= 200 && $request['status_code'] < 300 ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $request['status_code']; ?>
                    </td>
                    <td><?php echo number_format($request['response_time'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Top Suscriptores (Últimos 7 días)</h3>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Plan</th>
                    <th>Peticiones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_subscribers as $subscriber): ?>
                <tr>
                    <td><?php echo htmlspecialchars($subscriber['first_name'] . ' ' . $subscriber['last_name']); ?></td>
                    <td><?php echo ucfirst($subscriber['plan_type']); ?></td>
                    <td><?php echo number_format($subscriber['request_count']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function cleanCache() {
            if (confirm('¿Estás seguro de que quieres limpiar el caché?')) {
                fetch('actions.php?action=clean_cache', {method: 'POST'})
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        location.reload();
                    });
            }
        }

        function cleanLogs() {
            if (confirm('¿Estás seguro de que quieres limpiar los logs antiguos?')) {
                fetch('actions.php?action=clean_logs', {method: 'POST'})
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        location.reload();
                    });
            }
        }

        function generatePluginZip() {
            if (confirm('¿Generar ZIP del plugin desde C:\\xampp\\htdocs\\api_discogs\\subscribe\\plugin?')) {
                // Redirigir directamente al generador de ZIP
                window.location.href = 'generate_zip.php';
            }
        }
    </script>
</body>
</html>