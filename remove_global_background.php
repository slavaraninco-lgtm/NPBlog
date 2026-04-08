<?php
header('Content-Type: application/json');

// Удаляем файл глобального фона
$backgroundsDir = 'data/backgrounds/';
$files = glob($backgroundsDir . 'global-bg.*');
foreach ($files as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}

// Удаляем глобальные настройки
$settingsFile = 'data/global-settings.json';
if (file_exists($settingsFile)) {
    unlink($settingsFile);
}

// Очищаем opcode cache если включен
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($settingsFile, true);
}

// Удаляем фон из всех статей (только если у них нет своего фона)
$metaFile = 'data/blog/posts-meta.json';
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true);
    
    foreach ($meta as $post) {
        // Удаляем только если у статьи нет своего фона
        if (!isset($post['background']) && isset($post['filename'])) {
            $htmlFile = 'data/blog/' . $post['filename'];
            if (file_exists($htmlFile)) {
                $html = file_get_contents($htmlFile);
                
                // Удаляем wrapper с фоном
                $html = preg_replace(
                    '/<div class="content-wrapper" style="[^"]*">(\s*<h1>.*<a href="\.\.\/\.\.\/data\/blog\.html" class="back-link">.*?<\/a>)\s*<\/div>/s',
                    '$1',
                    $html
                );
                
                // Удаляем стиль body с фоном
                $html = preg_replace('/<body style="[^"]*">/', '<body>', $html);
                
                file_put_contents($htmlFile, $html);
            }
        }
    }
}

echo json_encode(['success' => true, 'deleted' => !file_exists($settingsFile)]);
?>
