<?php
header('Content-Type: application/json');

require_once 'background_functions.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['postId'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует ID статьи']);
    exit;
}

$postId = intval($data['postId']);
$overlayEnabled = isset($data['overlayEnabled']) ? (bool)$data['overlayEnabled'] : false;
$overlayColor = isset($data['overlayColor']) ? $data['overlayColor'] : '#ffffff';
$overlayOpacity = isset($data['overlayOpacity']) ? intval($data['overlayOpacity']) : 90;

// Получаем текущие настройки
$bgSettings = getPostBackground($postId) ?: [];

// Обновляем настройки подложки
$bgSettings['overlayEnabled'] = $overlayEnabled;
$bgSettings['overlayColor'] = $overlayColor;
$bgSettings['overlayOpacity'] = $overlayOpacity;

// Сохраняем настройки
setPostBackground($postId, $bgSettings);

// Обновляем HTML файл статьи
$metaFile = 'data/blog/posts-meta.json';
if (!file_exists($metaFile)) {
    echo json_encode(['success' => false, 'error' => 'Метаданные не найдены']);
    exit;
}

$meta = json_decode(file_get_contents($metaFile), true);
$postData = null;

foreach ($meta as $post) {
    if ($post['id'] == $postId) {
        $postData = $post;
        break;
    }
}

if (!$postData) {
    echo json_encode(['success' => false, 'error' => 'Статья не найдена']);
    exit;
}

// Применяем настройки к HTML файлу
if (isset($postData['filename'])) {
    $htmlFile = 'data/blog/' . $postData['filename'];
    applyBackgroundToHtml($htmlFile, $bgSettings);
}

echo json_encode(['success' => true]);
?>
