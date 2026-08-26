<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса'], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filename'])) {
    echo json_encode(['success' => false, 'error' => 'Не указано имя файла'], JSON_UNESCAPED_UNICODE);
    exit;
}

$includesDir = 'includes/';
$filepath = validateSafePath($includesDir, $data['filename']);
$filename = basename($filepath);

// Защита от удаления важных файлов
if ($filename === 'includes-meta.json' || !file_exists($filepath)) {
    echo json_encode(['success' => false, 'error' => 'Файл не найден'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (unlink($filepath)) {
    // Удаляем из метаданных
    $metaFile = $includesDir . 'includes-meta.json';
    if (file_exists($metaFile)) {
        $meta = json_decode(file_get_contents($metaFile), true) ?: [];
        if (isset($meta[$filename])) {
            unset($meta[$filename]);
            safeWriteJson($metaFile, $meta);
        }
    }
    
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при удалении файла'], JSON_UNESCAPED_UNICODE);
}
