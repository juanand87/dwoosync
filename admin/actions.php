<?php
/**
 * Acciones del panel de administración
 * 
 * @package DiscogsAPI
 * @version 1.0.0
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración y clases
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/CacheManager.php';
require_once __DIR__ . '/../classes/Logger.php';

// Verificar autenticación
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Obtener acción
$action = $_GET['action'] ?? '';

// Solo establecer Content-Type JSON para acciones que no sean descarga de archivos
if ($action !== 'generate_plugin_zip') {
    header('Content-Type: application/json');
}

try {
    switch ($action) {
        case 'clean_cache':
            cleanCache();
            break;
        
        case 'clean_logs':
            cleanLogs();
            break;
        
        case 'get_stats':
            getStats();
            break;
        
        case 'get_subscribers':
            getSubscribers();
            break;
        
        case 'suspend_license':
            suspendLicense();
            break;
        
        case 'activate_license':
            activateLicense();
            break;
        
        case 'generate_plugin_zip':
            generatePluginZip();
            break;
        
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Limpiar caché
 */
function cleanCache() {
    $cacheManager = new CacheManager();
    $deleted = $cacheManager->clear();
    
    echo json_encode([
        'success' => true,
        'message' => "Se eliminaron {$deleted} entradas del caché"
    ]);
}

/**
 * Limpiar logs antiguos
 */
function cleanLogs() {
    $logger = new Logger();
    $deleted = $logger->cleanOldLogs();
    
    echo json_encode([
        'success' => true,
        'message' => "Se eliminaron {$deleted} logs antiguos"
    ]);
}

/**
 * Obtener estadísticas
 */
function getStats() {
    $db = Database::getInstance();
    $cacheManager = new CacheManager();
    $logger = new Logger();
    
    $stats = [
        'subscribers' => $db->fetch("SELECT COUNT(*) as count FROM subscribers")['count'],
        'licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses")['count'],
        'active_licenses' => $db->fetch("SELECT COUNT(*) as count FROM licenses WHERE status = 'active'")['count'],
        'api_requests_today' => $db->fetch("SELECT COUNT(*) as count FROM api_logs WHERE DATE(created_at) = CURDATE()")['count'],
        'cache_stats' => $cacheManager->getStats(),
        'log_stats' => $logger->getLogStats(7)
    ];
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

/**
 * Obtener suscriptores
 */
function getSubscribers() {
    $db = Database::getInstance();
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $subscribers = $db->fetchAll("
        SELECT s.*, l.license_key, l.status as license_status, l.expires_at,
               COUNT(al.id) as total_requests
        FROM subscribers s
        LEFT JOIN licenses l ON s.id = l.subscriber_id
        LEFT JOIN api_logs al ON s.id = al.subscriber_id
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ", ['limit' => $limit, 'offset' => $offset]);
    
    $total = $db->fetch("SELECT COUNT(*) as count FROM subscribers")['count'];
    
    echo json_encode([
        'success' => true,
        'data' => $subscribers,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Suspender licencia
 */
function suspendLicense() {
    $licenseKey = $_POST['license_key'] ?? '';
    
    if (empty($licenseKey)) {
        echo json_encode(['success' => false, 'error' => 'License key requerida']);
        return;
    }
    
    $db = Database::getInstance();
    $result = $db->query("UPDATE licenses SET status = 'inactive' WHERE license_key = :key", ['key' => $licenseKey]);
    
    if ($result->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Licencia suspendida']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Licencia no encontrada']);
    }
}

/**
 * Activar licencia
 */
function activateLicense() {
    $licenseKey = $_POST['license_key'] ?? '';
    
    if (empty($licenseKey)) {
        echo json_encode(['success' => false, 'error' => 'License key requerida']);
        return;
    }
    
    $db = Database::getInstance();
    $result = $db->query("UPDATE licenses SET status = 'active' WHERE license_key = :key", ['key' => $licenseKey]);
    
    if ($result->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Licencia activada']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Licencia no encontrada']);
    }
}

/**
 * Generar ZIP del plugin
 */
function generatePluginZip() {
    try {
        // Ruta del plugin fuente
        $pluginDir = __DIR__ . '/../subscribe/plugin/';
        $zipFile = __DIR__ . '/../temp/dwoosync.zip';
        
        // Crear directorio temp si no existe
        if (!is_dir(__DIR__ . '/../temp/')) {
            mkdir(__DIR__ . '/../temp/', 0755, true);
        }
        
        // Verificar que el directorio existe
        if (!is_dir($pluginDir)) {
            throw new Exception('El directorio del plugin no existe: ' . $pluginDir);
        }
        
        // Verificar si ZipArchive está disponible
        if (!class_exists('ZipArchive')) {
            throw new Exception('La extensión ZipArchive no está disponible en este servidor');
        }
        
        // Crear ZIP usando PHP
        $zip = new ZipArchive();
        $zipResult = $zip->open($zipFile, ZipArchive::CREATE);
        
        if ($zipResult === TRUE) {
            // Agregar archivos del plugin de forma recursiva
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            $fileCount = 0;
            foreach ($iterator as $file) {
                $filePath = $file->getPathname();
                
                // Normalizar las rutas para evitar problemas con separadores de directorio
                $normalizedPluginDir = rtrim(str_replace('\\', '/', $pluginDir), '/') . '/';
                $normalizedFilePath = str_replace('\\', '/', $filePath);
                
                // Obtener la ruta relativa dentro del plugin
                $relativePath = str_replace($normalizedPluginDir, '', $normalizedFilePath);
                
                // Solo agregar el prefijo 'discogs-sync/' sin incluir la ruta completa
                $zipPath = 'discogs-sync/' . $relativePath;
                
                if ($file->isDir()) {
                    $zip->addEmptyDir($zipPath);
                } else {
                    $zip->addFile($filePath, $zipPath);
                    $fileCount++;
                }
            }
            
            $zip->close();
            
            // Verificar que el archivo ZIP se creó
            if (!file_exists($zipFile)) {
                throw new Exception('El archivo ZIP no se creó correctamente');
            }
            
            $fileSize = filesize($zipFile);
            
            // Descargar el archivo
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="dwoosync.zip"');
            header('Content-Length: ' . $fileSize);
            readfile($zipFile);
            unlink($zipFile); // Eliminar archivo temporal
            exit;
            
        } else {
            throw new Exception('No se pudo crear el archivo ZIP');
        }
        
    } catch (Exception $e) {
        // Si hay error, enviar respuesta JSON
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

