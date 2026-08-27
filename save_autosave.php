<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$title = $_POST['title'] ?? null;
$content = $_POST['content'] ?? null;

if ($title === null || $content === null) {
    $input = file_get_contents('php://input');
    if ($input) {
        $data = json_decode($input, true);
        $title = $data['title'] ?? null;
        $content = $data['content'] ?? null;
    }
}

if ($title === null || $content === null) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют данные']);
    exit;
}

$autosaveDir = getAutosavePath();

// Генерируем уникальный ID на основе md5 хэша заголовка и времени для защиты от tainted-filename
$timestamp = time();
$id = md5($title) . '_' . $timestamp;

if (!preg_match('/^[a-f0-9]+_\d+$/', $id)) {
    echo json_encode(['success' => false, 'error' => 'Некорректный ID автосохранения']);
    exit;
}

$filepath = validateSafePath($autosaveDir, 'autosave_' . $id . '.json');

$autosave = [
    'id' => $id,
    'title' => $title,
    'content' => $content,
    'timestamp' => $timestamp,
    'date' => date('Y-m-d H:i:s', $timestamp)
];

if (safeWriteJson($filepath, $autosave)) {
    echo json_encode([
        'success' => true,
        'id' => $id,
        'timestamp' => $timestamp
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка сохранения файла']);
}
