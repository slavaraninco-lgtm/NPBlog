<?php
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
$postId = $data['id'];

// Загружаем метаданные
$metaFile = 'data/blog/posts-meta.json';
if (!file_exists($metaFile)) {
    echo json_encode(['success' => false, 'error' => 'Метаданные не найдены']);
    exit;
}

$meta = json_decode(file_get_contents($metaFile), true);
$post = null;

// Ищем статью по ID
foreach ($meta as $item) {
    if ($item['id'] == $postId) {
        $post = $item;
        break;
    }
}

if (!$post) {
    echo json_encode(['success' => false, 'error' => 'Статья не найдена']);
    exit;
}

// Читаем файл статьи
$filename = 'data/blog/' . $post['filename'];
if (!file_exists($filename)) {
    echo json_encode(['success' => false, 'error' => 'Файл статьи не найден']);
    exit;
}

$content = file_get_contents($filename);

// Парсим HTML
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

// Извлекаем заголовок
$titleNode = $xpath->query('//h1')->item(0);
$title = $titleNode ? $titleNode->textContent : '';

// Извлекаем контент
$contentNode = $xpath->query('//div[@class="content"]')->item(0);
$rawContent = '';
if ($contentNode) {
    foreach ($contentNode->childNodes as $child) {
        $rawContent .= $dom->saveHTML($child);
    }
}

echo json_encode([
    'success' => true,
    'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
    'content' => html_entity_decode($rawContent, ENT_QUOTES, 'UTF-8')
]);