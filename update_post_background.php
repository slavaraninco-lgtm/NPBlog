<?php
header('Content-Type: application/json');

function updatePostBackground($postId) {
    // Загружаем метаданные
    $metaFile = 'data/blog/posts-meta.json';
    if (!file_exists($metaFile)) {
        return false;
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
        return false;
    }
    
    // Читаем существующий HTML файл
    $filename = 'data/blog/' . $postData['filename'];
    if (!file_exists($filename)) {
        return false;
    }
    
    $html = file_get_contents($filename);
    
    // ВСЕГДА удаляем старый wrapper и стиль body перед применением нового
    $html = preg_replace(
        '/<div class="content-wrapper" style="[^"]*">(\s*<h1>.*<a href="\.\.\/\.\.\/data\/blog\.html" class="back-link">.*?<\/a>)\s*<\/div>/s',
        '$1',
        $html
    );
    
    // Удаляем старый стиль body если есть
    $html = preg_replace('/<body style="[^"]*">/', '<body>', $html);
    
    // Определяем стиль фона
    if (isset($postData['background'])) {
        $bgMode = isset($postData['backgroundMode']) ? $postData['backgroundMode'] : 'cover';
        $bgScope = isset($postData['backgroundScope']) ? $postData['backgroundScope'] : 'content';
        
        // Формируем стиль в зависимости от режима
        if ($bgMode === 'repeat') {
            $backgroundStyle = "background-image: url('/data/backgrounds/{$postData['background']}'); background-repeat: repeat; background-size: auto;";
        } elseif ($bgMode === 'contain') {
            $backgroundStyle = "background-image: url('/data/backgrounds/{$postData['background']}'); background-size: contain; background-position: center; background-repeat: no-repeat;";
        } else { // cover
            $backgroundStyle = "background-image: url('/data/backgrounds/{$postData['background']}'); background-size: cover; background-position: center;";
        }
        
        // Применяем фон в зависимости от области
        if ($bgScope === 'fullpage') {
            // Фон на всю страницу - применяем к body
            $backgroundStyle .= " background-attachment: fixed;";
            $html = preg_replace('/<body>/', '<body style="' . $backgroundStyle . '">', $html);
        } else {
            // Фон только для статьи - оборачиваем контент
            $backgroundStyle .= " min-height: 100vh; padding: 40px 60px;";
            $html = preg_replace(
                '/(<button class="theme-toggle".*?<\/button>\s*)(<h1>.*<a href="\.\.\/\.\.\/data\/blog\.html" class="back-link">.*?<\/a>)/s',
                '$1<div class="content-wrapper" style="' . $backgroundStyle . '">$2</div>',
                $html
            );
        }
    }
    
    // Сохраняем обновленный файл
    file_put_contents($filename, $html);
    
    return true;
}

// Если вызывается напрямую
if (isset($_POST['postId']) || isset($_GET['postId'])) {
    $postId = isset($_POST['postId']) ? intval($_POST['postId']) : intval($_GET['postId']);
    
    if (updatePostBackground($postId)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось обновить фон']);
    }
}
?>
