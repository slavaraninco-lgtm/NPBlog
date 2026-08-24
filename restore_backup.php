<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['postId']) || !isset($data['filename'])) {
    echo json_encode(['success' => false, 'error' => 'Не указаны параметры'], JSON_UNESCAPED_UNICODE);
    exit;
}

$postId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$data['postId']);
$backupFilename = basename($data['filename']);

if (empty($postId) || empty($backupFilename)) {
    echo json_encode(['success' => false, 'error' => 'Некорректные параметры'], JSON_UNESCAPED_UNICODE);
    exit;
}

$backupDir = validateSafePath(__DIR__ . '/data_backup/', $postId) . '/';
$backupPath = validateSafePath($backupDir, $backupFilename);

if (!file_exists($backupPath)) {
    echo json_encode(['success' => false, 'error' => 'Файл бэкапа не найден'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Загружаем метаданные статей
$metaFile = getDataPath('blog/posts-meta.json');
if (!file_exists($metaFile)) {
    echo json_encode(['success' => false, 'error' => 'Метаданные статей не найдены'], JSON_UNESCAPED_UNICODE);
    exit;
}

$meta = json_decode(file_get_contents($metaFile), true) ?: [];
$postIndex = -1;

// Ищем статью по ID
foreach ($meta as $index => $item) {
    if ($item['id'] == $postId) {
        $postIndex = $index;
        break;
    }
}

if ($postIndex === -1) {
    echo json_encode(['success' => false, 'error' => 'Статья не найдена'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Копируем бэкап в основной файл
$targetPath = validateSafePath(getDataPath('blog/'), $meta[$postIndex]['filename']);
$backupContent = file_get_contents($backupPath);

if (file_put_contents($targetPath, $backupContent, LOCK_EX) !== false) {
    // Извлекаем заголовок из бэкапа для синхронизации метаданных
    $backupTitle = '';
    $backupMetaFile = validateSafePath(__DIR__ . '/data_backup/', 'backup-meta.json');
    if (file_exists($backupMetaFile)) {
        $bMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];
        if (isset($bMeta[$postId]['backups'])) {
            foreach ($bMeta[$postId]['backups'] as $bItem) {
                if ($bItem['filename'] === $backupFilename && !empty($bItem['title'])) {
                    $backupTitle = $bItem['title'];
                    break;
                }
            }
        }
    }
    
    if (empty($backupTitle)) {
        if (preg_match('/<title>(.*?)<\/title>/is', $backupContent, $m)) {
            $backupTitle = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        } elseif (preg_match('/<h1>(.*?)<\/h1>/is', $backupContent, $m)) {
            $backupTitle = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }
    }
    
    if (!empty($backupTitle)) {
        $meta[$postIndex]['title'] = $backupTitle;
        safeWriteJson($metaFile, $meta);
    }
    
    require_once __DIR__ . '/rss_helper.php';
    generateRssFeed();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при восстановлении'], JSON_UNESCAPED_UNICODE);
}
