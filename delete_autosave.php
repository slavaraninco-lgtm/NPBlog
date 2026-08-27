<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? $data['id'] : null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID не указан']);
    exit;
}

$autosaveDir = getAutosavePath();
$filepath = validateSafePath($autosaveDir, 'autosave_' . $id . '.json');

if (file_exists($filepath)) {
    if (unlink($filepath)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка удаления файла']);
    }
} else {
    echo json_encode(['success' => true]); // Файл уже не существует
}
