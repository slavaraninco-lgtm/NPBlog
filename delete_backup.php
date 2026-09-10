<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['postId']) || !isset($data['filename'])) {
    echo json_encode(['success' => false, 'error' => 'Не указаны параметры'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $data['postId'])) {
    echo json_encode(['success' => false, 'error' => 'Некорректный ID статьи'], JSON_UNESCAPED_UNICODE);
    exit;
}

$backupDir = validateSafePath(getBackupPath(), (string)$data['postId']) . '/';
$backupPath = validateSafePath($backupDir, $data['filename']);

if (!file_exists($backupPath)) {
    echo json_encode(['success' => false, 'error' => 'Файл бэкапа не найден'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Удаляем файл бэкапа
if (!unlink($backupPath)) {
    echo json_encode(['success' => false, 'error' => 'Не удалось удалить файл'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Обновляем метаданные
$backupMetaFile = validateSafePath(getBackupPath(), 'backup-meta.json');
if (file_exists($backupMetaFile)) {
    $backupMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];
    
    if (isset($backupMeta[$data['postId']])) {
        // Удаляем бэкап из списка
        $backupMeta[$data['postId']]['backups'] = array_filter(
            $backupMeta[$data['postId']]['backups'],
            function($backup) use ($data) {
                return $backup['filename'] !== $data['filename'];
            }
        );
        
        // Переиндексируем массив
        $backupMeta[$data['postId']]['backups'] = array_values($backupMeta[$data['postId']]['backups']);
        
        // Если у статьи не осталось бэкапов, удаляем запись о статье
        if (empty($backupMeta[$data['postId']]['backups'])) {
            unset($backupMeta[$data['postId']]);
            
            // Удаляем пустую папку
            if (is_dir($backupDir) && count(scandir($backupDir)) == 2) { // только . и ..
                rmdir($backupDir);
            }
        }
        
        safeWriteJson($backupMetaFile, $backupMeta);
    }
}

echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
