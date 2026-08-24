<?php
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit();
}

$fixed = [];
$errors = [];

initTemplatesSystem();
$mainTemplateFile = getTemplatePath();

// Исправляем шаблон статьи
if (file_exists($mainTemplateFile)) {
    $content = file_get_contents($mainTemplateFile);
    if (strpos($content, 'Powered by NPBlog') === false) {
        $badge = "\n    <div class=\"powered-by\">Powered by NPBlog</div>";
        if (strpos($content, '</body>') !== false) {
            $content = str_replace('</body>', $badge . "\n</body>", $content);
        } else {
            $content .= $badge;
        }
        
        if (file_put_contents($mainTemplateFile, $content, LOCK_EX)) {
            $fixed[] = basename($mainTemplateFile);
        } else {
            $errors[] = 'Не удалось записать изменения в шаблон: ' . basename($mainTemplateFile);
        }
    }
}

// Исправляем blog.html
$blogHtmlFile = getDataPath('blog.html');
if (file_exists($blogHtmlFile)) {
    $content = file_get_contents($blogHtmlFile);
    if (strpos($content, 'Powered by NPBlog') === false) {
        $badge = "\n    <div class=\"powered-by\">Powered by NPBlog</div>";
        if (strpos($content, '</body>') !== false) {
            $content = str_replace('</body>', $badge . "\n</body>", $content);
        } else {
            $content .= $badge;
        }
        
        if (file_put_contents($blogHtmlFile, $content, LOCK_EX)) {
            $fixed[] = 'blog.html';
        } else {
            $errors[] = 'Не удалось записать изменения в blog.html';
        }
    }
}

echo json_encode([
    'success' => count($errors) === 0,
    'fixed' => $fixed,
    'errors' => $errors
], JSON_UNESCAPED_UNICODE);
