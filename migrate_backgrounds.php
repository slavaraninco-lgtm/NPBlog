<?php
// Скрипт миграции настроек фонов из posts-meta.json в post_backgrounds.json

$metaFile = 'data/blog/posts-meta.json';
$backgroundsFile = 'data/post_backgrounds.json';

if (!file_exists($metaFile)) {
    echo "Файл posts-meta.json не найден\n";
    exit;
}

$meta = json_decode(file_get_contents($metaFile), true);
$backgrounds = [];

// Извлекаем настройки фонов и подложек из метаданных
foreach ($meta as $post) {
    $postId = $post['id'];
    $bgSettings = [];
    
    if (isset($post['background'])) {
        $bgSettings['background'] = $post['background'];
    }
    if (isset($post['backgroundMode'])) {
        $bgSettings['backgroundMode'] = $post['backgroundMode'];
    }
    if (isset($post['backgroundScope'])) {
        $bgSettings['backgroundScope'] = $post['backgroundScope'];
    }
    if (isset($post['overlayEnabled'])) {
        $bgSettings['overlayEnabled'] = $post['overlayEnabled'];
    }
    if (isset($post['overlayColor'])) {
        $bgSettings['overlayColor'] = $post['overlayColor'];
    }
    if (isset($post['overlayOpacity'])) {
        $bgSettings['overlayOpacity'] = $post['overlayOpacity'];
    }
    
    if (!empty($bgSettings)) {
        $backgrounds[$postId] = $bgSettings;
    }
}

// Сохраняем в новый файл
file_put_contents($backgroundsFile, json_encode($backgrounds, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Очищаем posts-meta.json от настроек фонов
$cleanMeta = [];
foreach ($meta as $post) {
    $cleanPost = [
        'id' => $post['id'],
        'title' => $post['title'],
        'date' => $post['date'],
        'filename' => $post['filename']
    ];
    $cleanMeta[] = $cleanPost;
}

file_put_contents($metaFile, json_encode($cleanMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Миграция завершена!\n";
echo "Создан файл: $backgroundsFile\n";
echo "Обработано статей: " . count($backgrounds) . "\n";
?>
