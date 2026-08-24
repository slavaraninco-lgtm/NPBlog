<?php
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';
header('Content-Type: application/json; charset=utf-8');

function checkFile($filename, $searchString) {
    if (!file_exists($filename)) {
        return ['exists' => false, 'hasString' => false];
    }
    
    $content = file_get_contents($filename);
    $hasString = strpos($content, $searchString) !== false;
    
    return ['exists' => true, 'hasString' => $hasString];
}

initTemplatesSystem();
$mainTemplateFile = getTemplatePath();
$blogHtmlFile = getDataPath('blog.html');

$templateCheck = checkFile($mainTemplateFile, 'Powered by NPBlog');
$blogHtmlCheck = checkFile($blogHtmlFile, 'Powered by NPBlog');

$errors = [];

if (!$templateCheck['exists']) {
    $errors[] = 'Файл шаблона не найден: ' . basename($mainTemplateFile);
} elseif (!$templateCheck['hasString']) {
    $errors[] = 'В файле шаблона ' . basename($mainTemplateFile) . ' отсутствует надпись "Powered by NPBlog"';
}

if (!$blogHtmlCheck['exists']) {
    $errors[] = 'Файл blog.html не найден';
} elseif (!$blogHtmlCheck['hasString']) {
    $errors[] = 'В файле blog.html отсутствует надпись "Powered by NPBlog"';
}

echo json_encode([
    'success' => count($errors) === 0,
    'errors' => $errors
], JSON_UNESCAPED_UNICODE);
