<?php
header('Content-Type: application/json');

require_once 'background_functions.php';

$backgroundsData = loadBackgrounds();
$updated = 0;
$errors = [];

foreach ($backgroundsData as $postId => $settings) {
    if (!isset($settings['background']) || $settings['backgroundScope'] !== 'content') {
        continue;
    }
    
    // Находим файл статьи
    $metaFile = 'data/blog/posts-meta.json';
    if (!file_exists($metaFile)) {
        continue;
    }
    
    $meta = json_decode(file_get_contents($metaFile), true);
    $filename = null;
    
    foreach ($meta as $post) {
        if ($post['id'] == $postId) {
            $filename = $post['filename'];
            break;
        }
    }
    
    if (!$filename) {
        $errors[] = "Файл для статьи $postId не найден в meta";
        continue;
    }
    
    $htmlFile = 'data/blog/' . $filename;
    if (!file_exists($htmlFile)) {
        $errors[] = "HTML файл $htmlFile не существует";
        continue;
    }
    
    $html = file_get_contents($htmlFile);
    $originalHtml = $html;
    
    // Ищем div с классом content-wrapper и добавляем/обновляем padding в inline-стиле
    // Паттерн: <div class="content-wrapper" style="...">
    $html = preg_replace_callback(
        '/<div class="content-wrapper" style="([^"]*)">/i',
        function($matches) {
            $style = $matches[1];
            
            // Удаляем старый padding если есть
            $style = preg_replace('/padding:\s*[^;]+;?/', '', $style);
            
            // Добавляем новый padding
            $style = trim($style);
            if (!empty($style) && !preg_match('/;\s*$/', $style)) {
                $style .= ';';
            }
            $style .= ' padding: 40px 60px;';
            
            return '<div class="content-wrapper" style="' . $style . '">';
        },
        $html
    );
    
    // Сохраняем только если были изменения
    if ($html !== $originalHtml) {
        file_put_contents($htmlFile, $html);
        $updated++;
    }
}

echo json_encode([
    'success' => true, 
    'updated' => $updated,
    'errors' => $errors,
    'checked' => count($backgroundsData)
]);
?>
