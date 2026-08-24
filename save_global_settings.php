<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$settingsFile = getDataPath('global-settings.json');
$settings = [];

if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

// Обновляем настройки
if (isset($data['hidePoweredBy'])) {
    $settings['hidePoweredBy'] = (bool)$data['hidePoweredBy'];
}
if (isset($data['baseUrl'])) {
    $settings['baseUrl'] = (string)$data['baseUrl'];
}
if (isset($data['defaultOgImage'])) {
    $settings['defaultOgImage'] = (string)$data['defaultOgImage'];
}
if (isset($data['defaultOgDescription'])) {
    $settings['defaultOgDescription'] = (string)$data['defaultOgDescription'];
}

// Сохраняем файл атомарно
if (safeWriteJson($settingsFile, $settings)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить настройки']);
}
?>
