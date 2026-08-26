<?php
require_once __DIR__ . '/security_bootstrap.php';
// Отключаем вывод ошибок в браузер
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

try {
    $rawInput = file_get_contents(php_sapi_name() === 'cli' ? 'php://stdin' : 'php://input');
    
    if (empty($rawInput)) {
        echo json_encode(['success' => false, 'error' => 'Пустой запрос']);
        exit;
    }
    
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Ошибка парсинга JSON: ' . json_last_error_msg()]);
        exit;
    }
    
    if (!isset($data['id'])) {
        echo json_encode(['success' => false, 'error' => 'ID статьи не указан']);
        exit;
    }
    
    $postId = intval($data['id']);

    $blogDir = getDataPath('blog/');
    $metaFile = validateSafePath($blogDir, 'posts-meta.json');
    if (!file_exists($metaFile)) {
        echo json_encode(['success' => false, 'error' => 'Метаданные не найдены']);
        exit;
    }

    $meta = json_decode(file_get_contents($metaFile), true);
    
    if (!$meta) {
        echo json_encode(['success' => false, 'error' => 'Ошибка чтения метаданных']);
        exit;
    }
    
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
    $filename = validateSafePath($blogDir, $post['filename']);
    if (!file_exists($filename)) {
        echo json_encode(['success' => false, 'error' => 'Файл статьи не найден: ' . $filename]);
        exit;
    }

    $content = file_get_contents($filename);

    // Парсим HTML с явным указанием UTF-8 кодировки
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Извлекаем заголовок (пробуем сначала из метаданных, как надежный источник)
    $title = isset($post['title']) ? $post['title'] : '';
    if (empty($title)) {
        $titleNode = $xpath->query('//h1')->item(0);
        $title = $titleNode ? $titleNode->textContent : '';
    }

    // Извлекаем и очищаем контент
    require_once 'templates_helper.php';
    $rawContent = extractPostContentFromHtml($content, $postId);

    // Конвертируем статические пути к папке data в пути через serve_data.php, чтобы картинки загружались в редакторе
    $dataDir = getDataPath();
    $dirName = basename(rtrim(str_replace('\\', '/', $dataDir), '/'));
    $pattern = '/(src|href|poster)=(["\'])(?:\/)?' . preg_quote($dirName, '/') . '\//i';
    $rawContent = preg_replace($pattern, '$1=$2serve_data.php?file=', $rawContent);

    echo json_encode([
        'success' => true,
        'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
        'content' => $rawContent
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Исключение: ' . $e->getMessage()]);
}