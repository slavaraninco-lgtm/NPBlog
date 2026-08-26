<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$rawInput = file_get_contents(php_sapi_name() === 'cli' ? 'php://stdin' : 'php://input');
$data = json_decode($rawInput, true);

if (!$data || !isset($data['id']) || !isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$postId = intval($data['id']);

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
$metaFile = validateSafePath($blogDir, 'posts-meta.json');
if (!file_exists($metaFile)) {
    echo json_encode(['success' => false, 'error' => 'Метаданные не найдены']);
    exit;
}

$meta = json_decode(file_get_contents($metaFile), true);
$postIndex = -1;

// Ищем статью по ID
foreach ($meta as $index => $item) {
    if ($item['id'] == $postId) {
        $postIndex = $index;
        break;
    }
}

if ($postIndex === -1) {
    echo json_encode(['success' => false, 'error' => 'Статья не найдена']);
    exit;
}

// Сохраняем оригинальную дату создания
$originalDate = $meta[$postIndex]['date'];
$currentDate = date('d.m.Y H:i');

// Обновляем HTML файл статьи (перенесено ниже с использованием шаблона)




// Получаем шаблон
require_once 'templates_helper.php';
$templateFile = getTemplatePath($postId);
if (!file_exists($templateFile)) {
    echo json_encode(['success' => false, 'error' => 'Файл шаблона не найден']);
    exit;
}

$articleHtml = getTemplateHtml($templateFile);

// Подготавливаем данные
$title = htmlspecialchars($data['title']);
$displayDate = $originalDate . ' (отредактировано)';

// Добавляем пользовательские шрифты
require_once 'get_custom_fonts_css.php';
$customFontsCss = getCustomFontsCss();

// Добавляем метатеги для SEO и соцсетей
require_once 'seo_helper.php';
$seoMetaBlock = generateSeoMetaTagsBlock($postId, $data['title'], $cleanContent);

// Заменяем плейсхолдеры в шаблоне
$articleHtml = str_replace('{{POST_ID}}', $postId, $articleHtml);
$articleHtml = str_replace('{{TITLE}}', $title, $articleHtml);
$articleHtml = str_replace('{{DATE}}', $displayDate, $articleHtml);
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

// Сохраняем обновленный файл
$filename = validateSafePath($blogDir, $meta[$postIndex]['filename']);
file_put_contents($filename, $articleHtml, LOCK_EX);

// Создаем бэкап перед обновлением
$backupDir = validateSafePath(__DIR__ . '/data_backup/', (string)$postId) . '/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Определяем следующий номер бэкапа
$existingBackups = glob($backupDir . $postId . '-*.html');
$maxBackupNumber = 0;
foreach ($existingBackups as $backup) {
    if (preg_match('/' . $postId . '-(\d+)\.html$/', $backup, $match)) {
        $backupNum = intval($match[1]);
        if ($backupNum > $maxBackupNumber) {
            $maxBackupNumber = $backupNum;
        }
    }
}
$nextBackupNumber = $maxBackupNumber + 1;

// Сохраняем бэкап
$backupFilename = validateSafePath($backupDir, $postId . '-' . $nextBackupNumber . '.html');
file_put_contents($backupFilename, $articleHtml, LOCK_EX);

// Сохраняем метаданные бэкапа
$backupMetaFile = validateSafePath(__DIR__ . '/data_backup/', 'backup-meta.json');
$backupMeta = [];
if (file_exists($backupMetaFile)) {
    $backupMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];
}

if (!isset($backupMeta[$postId])) {
    $backupMeta[$postId] = [
        'postId' => $postId,
        'postTitle' => $data['title'],
        'backups' => []
    ];
}

$backupMeta[$postId]['postTitle'] = $data['title'];
$backupMeta[$postId]['backups'][] = [
    'backupNumber' => $nextBackupNumber,
    'filename' => $postId . '-' . $nextBackupNumber . '.html',
    'date' => $currentDate,
    'title' => $data['title']
];

safeWriteJson($backupMetaFile, $backupMeta);

// Обновляем posts-meta.json (сохраняем оригинальную дату)
$meta[$postIndex]['title'] = $data['title'];
// Дату НЕ обновляем - она остается оригинальной
safeWriteJson($metaFile, $meta);

require_once __DIR__ . '/rss_helper.php';
generateRssFeed();

echo json_encode(['success' => true]);
?>
