<?php
header('Content-Type: application/json; charset=utf-8');

$backupMetaFile = 'data_backup/backup-meta.json';

if (!file_exists($backupMetaFile)) {
    echo json_encode(['success' => true, 'backups' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$backupMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];

// Сортируем: сначала активные статьи по ID, потом удалённые
uksort($backupMeta, function($a, $b) {
    $aIsDeleted = strpos($a, 'deleted_') === 0;
    $bIsDeleted = strpos($b, 'deleted_') === 0;
    
    // Если оба удалены или оба активны
    if ($aIsDeleted === $bIsDeleted) {
        if ($aIsDeleted) {
            // Оба удалены - сортируем по строке
            return strcmp($b, $a);
        } else {
            // Оба активны - сортируем по числу
            return intval($b) - intval($a);
        }
    }
    
    // Активные статьи идут первыми
    return $aIsDeleted ? 1 : -1;
});

echo json_encode(['success' => true, 'backups' => $backupMeta], JSON_UNESCAPED_UNICODE);
