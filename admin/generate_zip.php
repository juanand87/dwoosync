<?php
/**
 * Generador de ZIP del plugin - Acceso directo sin autenticación
 */

// Definir constante para acceso
define('API_ACCESS', true);

// Incluir configuración
require_once __DIR__ . '/../config/config.php';

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
    // Si hay error, mostrar mensaje
    echo "Error: " . $e->getMessage();
    exit;
}
?>
