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

// Загружаем метаданные статей
$metaFile = 'data/blog/posts-meta.json';
if (!file_exists($metaFile)) {
    echo json_encode(['success' => false, 'error' => 'Метаданные статей не найдены'], JSON_UNESCAPED_UNICODE);
    exit;
}

$meta = json_decode(file_get_contents($metaFile), true);
$postIndex = -1;

// Ищем статью по ID
foreach ($meta as $index => $item) {
    if ($item['id'] == $data['postId']) {
        $postIndex = $index;
        break;
    }
}

if ($postIndex === -1) {
    echo json_encode(['success' => false, 'error' => 'Статья не найдена'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Копируем бэкап в основной файл
$targetPath = 'data/blog/' . $meta[$postIndex]['filename'];
$backupContent = file_get_contents($backupPath);

if (file_put_contents($targetPath, $backupContent)) {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при восстановлении'], JSON_UNESCAPED_UNICODE);
}
