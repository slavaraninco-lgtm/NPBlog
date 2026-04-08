<?php
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filename'])) {
    echo json_encode(['success' => false, 'error' => 'Не указано имя файла'], JSON_UNESCAPED_UNICODE);
    exit;
}

$filepath = 'includes/' . $data['filename'];

if (!file_exists($filepath)) {
    echo json_encode(['success' => false, 'error' => 'Файл не найден'], JSON_UNESCAPED_UNICODE);
    exit;
}

$content = file_get_contents($filepath);

echo json_encode(['success' => true, 'content' => $content], JSON_UNESCAPED_UNICODE);
