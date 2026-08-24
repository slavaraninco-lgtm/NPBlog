<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют данные']);
    exit;
}

$title = $data['title'];
$content = $data['content'];

$draftDir = getDataPath('drafts/');
if (!is_dir($draftDir)) {
    @mkdir($draftDir, 0755, true);
}

// Генерируем уникальное имя файла
$timestamp = time();
$filename = $timestamp . '.json';
$filepath = validateSafePath($draftDir, $filename);

// Сохраняем черновик
$draft = [
    'title' => $title,
    'content' => $content,
    'timestamp' => $timestamp,
    'date' => date('Y-m-d H:i:s', $timestamp)
];

if (safeWriteJson($filepath, $draft)) {
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'timestamp' => $timestamp
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка сохранения файла']);
}
