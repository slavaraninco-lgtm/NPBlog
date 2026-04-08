<?php
header('Content-Type: application/json');

function checkFile($filename, $searchString) {
    if (!file_exists($filename)) {
        return ['exists' => false, 'hasString' => false];
    }
    
    $content = file_get_contents($filename);
    $hasString = strpos($content, $searchString) !== false;
    
    return ['exists' => true, 'hasString' => $hasString];
}

// Проверяем наличие "Powered by NPBlog" в файлах
$savePostCheck = checkFile('save_post.php', 'Powered by NPBlog');
$blogHtmlCheck = checkFile('data/blog.html', 'Powered by NPBlog');

$errors = [];

if (!$savePostCheck['exists']) {
    $errors[] = 'Файл save_post.php не найден';
} elseif (!$savePostCheck['hasString']) {
    $errors[] = 'В файле save_post.php отсутствует надпись "Powered by NPBlog"';
}

if (!$blogHtmlCheck['exists']) {
    $errors[] = 'Файл data/blog.html не найден';
} elseif (!$blogHtmlCheck['hasString']) {
    $errors[] = 'В файле data/blog.html отсутствует надпись "Powered by NPBlog"';
}

echo json_encode([
    'success' => count($errors) === 0,
    'errors' => $errors
]);
