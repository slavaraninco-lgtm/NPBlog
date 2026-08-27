<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'list_backups') {
    $backups = [];
    $dir = getEditorBackupPath();
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if (str_ends_with($file, '.zip')) {
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($dir . $file),
                    'time' => filemtime($dir . $file)
                ];
            }
        }
    }
    
    // Sort by time descending
    usort($backups, function($a, $b) {
        return $b['time'] - $a['time'];
    });
    
    echo json_encode(['success' => true, 'backups' => $backups]);
    exit;
}

if ($action === 'restore') {
    $data = json_decode(file_get_contents('php://input'), true);
    $filename = $data['filename'] ?? '';
    
    if (empty($filename)) {
        echo json_encode(['success' => false, 'error' => 'Файл бэкапа не указан']);
        exit;
    }
    
    $backupPath = validateSafePath(getEditorBackupPath(), basename($filename));
    
    if (!file_exists($backupPath)) {
        echo json_encode(['success' => false, 'error' => 'Файл бэкапа не найден']);
        exit;
    }
    
    $zip = new ZipArchive;
    if ($zip->open($backupPath) === TRUE) {
        $tmpDir = 'sys_update_tmp/restore_' . uniqid() . '/';
        mkdir($tmpDir, 0755, true);
        
        $zip->extractTo($tmpDir);
        $zip->close();
        
        // Copy files back to root
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $restoredCount = 0;
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relativePath = substr($item->getPathname(), strlen($tmpDir));
                
                $targetDir = dirname($relativePath);
                if ($targetDir !== '.' && !is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                
                if (@copy($item->getPathname(), $relativePath)) {
                    $restoredCount++;
                }
            }
        }
        
        // Cleanup temp dir
        function deleteRestoreDir($dirPath) {
            if (!is_dir($dirPath)) return;
            $files = array_diff(scandir($dirPath), array('.','..'));
            foreach ($files as $file) {
                (is_dir("$dirPath/$file")) ? deleteRestoreDir("$dirPath/$file") : unlink("$dirPath/$file");
            }
            rmdir($dirPath);
        }
        
        deleteRestoreDir($tmpDir);
        
        echo json_encode(['success' => true, 'restoredCount' => $restoredCount, 'message' => 'Система успешно восстановлена.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось открыть архив бэкапа.']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Неверное действие']);
