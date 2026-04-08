<?php
header('Content-Type: application/json');

require_once 'background_functions.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['postId'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует ID статьи']);
    exit;
}

$postId = intval($data['postId']);

// Удаляем файл фона
$backgroundsDir = 'data/backgrounds/';
$files = glob($backgroundsDir . 'bg-' . $postId . '.*');
foreach ($files as $file) {
    unlink($file);
}

// Получаем текущие настройки
$bgSettings = getPostBackground($postId);

// Удаляем настройки фона, но сохраняем настройки подложки
if ($bgSettings) {
    unset($bgSettings['background']);
    unset($bgSettings['backgroundMode']);
    unset($bgSettings['backgroundScope']);
    
    if (empty($bgSettings)) {
        removePostBackground($postId);
    } else {
        setPostBackground($postId, $bgSettings);
    }
}

// Проверяем наличие глобального фона
$globalSettingsFile = 'data/global-settings.json';
$globalSettings = null;

if (file_exists($globalSettingsFile)) {
    $globalSettings = json_decode(file_get_contents($globalSettingsFile), true);
}

// Обновляем HTML файл статьи
$metaFile = 'data/blog/posts-meta.json';
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
    
    foreach ($meta as $post) {
        if ($post['id'] == $postId && isset($post['filename'])) {
            $htmlFile = 'data/blog/' . $post['filename'];
            if (file_exists($htmlFile)) {
                // Если есть глобальный фон, применяем его
                if ($globalSettings && isset($globalSettings['background'])) {
                    $applySettings = $bgSettings ?: [];
                    $applySettings['background'] = $globalSettings['background'];
                    $applySettings['backgroundMode'] = $globalSettings['backgroundMode'];
                    $applySettings['backgroundScope'] = $globalSettings['backgroundScope'];
                    
                    applyBackgroundToHtml($htmlFile, $applySettings);
                } else {
                    // Если глобального фона нет, применяем оставшиеся настройки (подложку если есть)
                    applyBackgroundToHtml($htmlFile, $bgSettings ?: []);
                }
            }
            break;
        }
    }
}

echo json_encode(['success' => true, 'globalApplied' => ($globalSettings && isset($globalSettings['background']))]);
?>
