<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$rawInput = file_get_contents(php_sapi_name() === 'cli' ? 'php://stdin' : 'php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$allowedTags = '<b><i><u><s><sup><sub><h2><ul><li><a><p><br><img><pre><span><div><iframe><audio><source><center><details><summary><mark>';

$content = $data['content'];

// Заменяем все пути serve_data.php на статические прямые пути для готовой статьи
$dataDir = getDataPath();
$dirName = basename(rtrim(str_replace('\\', '/', $dataDir), '/'));
$staticPrefix = '/' . $dirName . '/';
$content = preg_replace('/(?:https?:\/\/[^\/]+)?(?:\/)?serve_data.php\?file=/i', $staticPrefix, $content);
$content = preg_replace('/(?:[?&]|&amp;)t=\d+/i', '', $content);

// Функция для красивого форматирования HTML структуры с сохранением блоков <pre>
function formatArticleContent($html) {
    // 1. Извлекаем блоки <pre>, чтобы полностью сохранить их форматирование и пробелы
    $preBlocks = [];
    $formatted = preg_replace_callback('/(<pre[^>]*>[\s\S]*?<\/pre>)/i', function($matches) use (&$preBlocks) {
        $preBlocks[] = $matches[0];
        return '___PRE_PLACEHOLDER_' . (count($preBlocks) - 1) . '___';
    }, $html);

    $blockTags = ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'table', 'tr', 'iframe', 'audio', 'center', 'details', 'summary', 'blockquote', 'hr'];
    $tagsRegex = implode('|', $blockTags);
    
    $formatted = preg_replace('/(<(?:' . $tagsRegex . ')(?:\s+[^>]*)?>)/i', "\n$1", $formatted);
    $formatted = preg_replace('/(<\/(?:' . $tagsRegex . ')>)/i', "$1\n", $formatted);
    
    $lines = explode("\n", $formatted);
    $cleanLines = [];
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;
        $cleanLines[] = "        " . $trimmed;
    }
    
    $finalHtml = "\n" . implode("\n", $cleanLines) . "\n    ";

    // 2. Восстанавливаем блоки <pre> без каких-либо изменений
    foreach ($preBlocks as $index => $preBlock) {
        $finalHtml = str_replace('___PRE_PLACEHOLDER_' . $index . '___', $preBlock, $finalHtml);
    }

    return $finalHtml;
}

$cleanContent = formatArticleContent($content);
$blogDir = getDataPath('blog/');
if (!is_dir($blogDir)) {
    mkdir($blogDir, 0777, true);
}

$maxId = 0;
$files = glob($blogDir . 'post-*.html');
foreach ($files as $file) {
    if (preg_match('/post-(\d+)\.html$/', $file, $match)) {
        $id = intval($match[1]);
        if ($id > $maxId) {
            $maxId = $id;
        }
    }
}

$nextId = $maxId + 1;
$date = date('d.m.Y H:i');

// Получаем шаблон
require_once 'templates_helper.php';
$templateFile = getTemplatePath();
if (!file_exists($templateFile)) {
    echo json_encode(['success' => false, 'error' => 'Файл шаблона не найден']);
    exit;
}

$articleHtml = getTemplateHtml($templateFile);

// Подготавливаем данные
$title = htmlspecialchars($data['title']);

// Добавляем пользовательские шрифты
require_once 'get_custom_fonts_css.php';
$customFontsCss = getCustomFontsCss();

// Добавляем метатеги для SEO и соцсетей
require_once 'seo_helper.php';
$seoMetaBlock = generateSeoMetaTagsBlock($nextId, $data['title'], $cleanContent);

// Заменяем плейсхолдеры в шаблоне
$articleHtml = str_replace('{{POST_ID}}', $nextId, $articleHtml);
$articleHtml = str_replace('{{TITLE}}', $title, $articleHtml);
$articleHtml = str_replace('{{DATE}}', $date, $articleHtml);
$articleHtml = str_replace('{{META_TAGS}}', $seoMetaBlock, $articleHtml);
$articleHtml = replaceCustomFontsPlaceholder($articleHtml, $customFontsCss);

$editorSettingsFile = __DIR__ . '/editor_settings.json';
$editorSettings = [];
if (file_exists($editorSettingsFile)) {
    $editorSettings = json_decode(file_get_contents($editorSettingsFile), true) ?: [];
}
$contentWidth = isset($editorSettings['contentWidth']) ? (int)$editorSettings['contentWidth'] : 920;
$bodyStyle = "style=\"max-width: {$contentWidth}px;\"";
$articleHtml = str_replace('{{BODY_STYLE}}', $bodyStyle, $articleHtml);
$articleHtml = str_replace('{{CONTENT_WRAPPER_START}}', '', $articleHtml);
$articleHtml = str_replace('{{CONTENT_WRAPPER_END}}', '', $articleHtml);

// Вставляем контент статьи в самую последнюю очередь
$wrappedContent = $cleanContent;
if (strpos($articleHtml, 'id="npblog-post-content"') === false) {
    $wrappedContent = '<article id="npblog-post-content" class="content">' . $cleanContent . '</article>';
}
$articleHtml = str_replace('{{CONTENT}}', $wrappedContent, $articleHtml);

// Сохраняем файл статьи
$filename = validateSafePath($blogDir, 'post-' . $nextId . '.html');
file_put_contents($filename, $articleHtml, LOCK_EX);

// Создаем бэкап новой статьи
$backupDir = validateSafePath(__DIR__ . '/data_backup/', (string)$nextId) . '/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$backupNumber = 1;
$backupFilename = validateSafePath($backupDir, $nextId . '-' . $backupNumber . '.html');
file_put_contents($backupFilename, $articleHtml, LOCK_EX);

// Сохраняем метаданные бэкапа
$backupMetaFile = validateSafePath(__DIR__ . '/data_backup/', 'backup-meta.json');
$backupMeta = [];
if (file_exists($backupMetaFile)) {
    $backupMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];
}

if (!isset($backupMeta[$nextId])) {
    $backupMeta[$nextId] = [
        'postId' => $nextId,
        'postTitle' => $data['title'],
        'backups' => []
    ];
}

$backupMeta[$nextId]['backups'][] = [
    'backupNumber' => $backupNumber,
    'filename' => $nextId . '-' . $backupNumber . '.html',
    'date' => $date,
    'title' => $data['title']
];

safeWriteJson($backupMetaFile, $backupMeta);

// Обновляем posts-meta.json для статического хостинга
$metaFile = validateSafePath($blogDir, 'posts-meta.json');
$meta = [];
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true) ?: [];
}

$meta[] = [
    'id' => $nextId,
    'title' => $data['title'],
    'date' => $date,
    'filename' => 'post-' . $nextId . '.html'
];

safeWriteJson($metaFile, $meta);

require_once __DIR__ . '/rss_helper.php';
generateRssFeed();

echo json_encode(['success' => true, 'id' => $nextId]);
?>
