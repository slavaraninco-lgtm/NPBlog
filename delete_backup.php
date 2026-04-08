<?php
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['postId']) || !isset($data['filename'])) {
    echo json_encode(['success' => false, 'error' => 'Не указаны параметры'], JSON_UNESCAPED_UNICODE);
    exit;
}

$backupPath = 'data_backup/' . $data['postId'] . '/' . $data['filename'];

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
$backupMetaFile = 'data_backup/backup-meta.json';
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
            $backupDir = 'data_backup/' . $data['postId'];
            if (is_dir($backupDir) && count(scandir($backupDir)) == 2) { // только . и ..
                rmdir($backupDir);
            }
        }
        
        file_put_contents($backupMetaFile, json_encode($backupMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
