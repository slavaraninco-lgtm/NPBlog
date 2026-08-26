<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['filename'])) {
    echo json_encode(['success' => false, 'error' => 'Не указан файл'], JSON_UNESCAPED_UNICODE);
    exit;
}

$safeFilename = basename($data['filename']);
$filepath = validateSafePath(getDataPath('drafts/'), $safeFilename);

if (file_exists($filepath)) {
    if (unlink($filepath)) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка удаления файла'], JSON_UNESCAPED_UNICODE);
    }
} else {
    // Check legacy draft folder
    $legacyPath = validateSafePath(__DIR__ . '/draft/', $safeFilename);
    if (file_exists($legacyPath)) {
        if (unlink($legacyPath)) {
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'Файл не найден'], JSON_UNESCAPED_UNICODE);
}
