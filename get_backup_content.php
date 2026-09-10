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
$filepath = validateSafePath($backupDir, $data['filename']);

if (!file_exists($filepath)) {
    echo json_encode(['success' => false, 'error' => 'Файл бэкапа не найден'], JSON_UNESCAPED_UNICODE);
    exit;
}

$content = file_get_contents($filepath);

echo json_encode(['success' => true, 'content' => $content], JSON_UNESCAPED_UNICODE);
