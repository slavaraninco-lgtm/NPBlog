<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['title'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует заголовок']);
    exit;
}

$settingsFile = getDataPath('blog-view-settings.json');
$settings = [
    'title' => $data['title']
];

if (safeWriteJson($settingsFile, $settings)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить настройки']);
}
?>
