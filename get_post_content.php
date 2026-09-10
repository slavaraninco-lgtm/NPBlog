<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $rawInput = file_get_contents(php_sapi_name() === 'cli' ? 'php://stdin' : 'php://input');
    
    if (empty($rawInput)) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Пустой запрос']);
        exit;
    }
    
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Ошибка парсинга JSON: ' . json_last_error_msg()]);
        exit;
    }
    
    if (!isset($data['id'])) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'ID статьи не указан']);
        exit;
    }
    
    $postId = intval($data['id']);

    $blogDir = getDataPath('blog/');
    $metaFile = validateSafePath($blogDir, 'posts-meta.json');
    if (!file_exists($metaFile)) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Файл метаданных posts-meta.json не найден в папке: ' . $blogDir]);
        exit;
    }

    $meta = json_decode(file_get_contents($metaFile), true);
    
    if (!$meta) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Ошибка чтения метаданных статей']);
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
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Статья с ID ' . $postId . ' не найдена в списке']);
        exit;
    }

    // Читаем файл статьи
    $filename = validateSafePath($blogDir, $post['filename']);
    if (!file_exists($filename)) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Файл статьи не найден: ' . $filename]);
        exit;
    }

    $content = file_get_contents($filename);

    // Извлекаем заголовок (пробуем сначала из метаданных, как надежный источник)
    $title = isset($post['title']) ? $post['title'] : '';
    if (empty($title)) {
        if (class_exists('DOMDocument')) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);
            $titleNode = $xpath->query('//h1')->item(0);
            $title = $titleNode ? $titleNode->textContent : '';
        } else {
            // Regex fallback for title
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $content, $matches)) {
                $title = trim(strip_tags($matches[1]));
            }
        }
    }

    // Извлекаем и очищаем контент
    require_once 'templates_helper.php';
    $rawContent = extractPostContentFromHtml($content, $postId);

    // Конвертируем статические пути к папке data в URL через getDataUrl
    $dataDir = getDataPath();
    $dirName = basename(rtrim(str_replace('\\', '/', $dataDir), '/'));
    $pattern = '/(src|href|poster)=(["\'])(?:\/)?' . preg_quote($dirName, '/') . '\//i';
    $rawContent = preg_replace($pattern, '$1=$2' . getDataUrl(''), $rawContent);

    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => true,
        'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
        'content' => $rawContent
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => 'Исключение при чтении статьи: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>