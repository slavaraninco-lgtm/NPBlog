<?php
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';

/**
 * Генерирует или удаляет RSS-ленту на основе настроек блога
 * @return bool
 */
function generateRssFeed() {
    $settingsFile = __DIR__ . '/editor_settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }
    
    $rssEnabled = isset($settings['rss_enabled']) ? (bool)$settings['rss_enabled'] : false;
    $xmlFile = getDataPath('feed.xml');
    
    // Если RSS выключен, удаляем файл ленты
    if (!$rssEnabled) {
        if (file_exists($xmlFile)) {
            @unlink($xmlFile);
        }
        return true;
    }
    
    $baseUrl = isset($settings['rss_base_url']) ? rtrim($settings['rss_base_url'], '/') : '';
    $rssTitle = isset($settings['rss_title']) && trim($settings['rss_title']) !== '' 
        ? trim($settings['rss_title']) 
        : 'NPBlog Feed';
    $rssDesc = isset($settings['rss_description']) && trim($settings['rss_description']) !== '' 
        ? trim($settings['rss_description']) 
        : 'NPBlog RSS Feed';
    $useFirstLine = isset($settings['rss_use_first_line']) ? (bool)$settings['rss_use_first_line'] : true;
    
    $contentTemplate = isset($settings['rss_content_template']) && trim($settings['rss_content_template']) !== '' 
        ? $settings['rss_content_template'] 
        : "*content*\n\n<p><a href=\"*url*\">Читать в блоге</a></p>";
    
    $blogDir = getDataPath('blog/');
    $metaFile = validateSafePath($blogDir, 'posts-meta.json');
    $posts = [];
    if (file_exists($metaFile)) {
        $posts = json_decode(file_get_contents($metaFile), true) ?: [];
    }
    
    // Сортируем статьи по ID в обратном порядке (сначала новые)
    usort($posts, function($a, $b) {
        return (int)$b['id'] - (int)$a['id'];
    });
    
    $itemsXml = '';
    foreach ($posts as $post) {
        $postFile = validateSafePath($blogDir, $post['filename']);
        if (!file_exists($postFile)) {
            continue;
        }
        
        $html = file_get_contents($postFile);
        $postContent = extractPostContentFromHtml($html, $post['id']);
        
        $itemContent = '';
        if ($useFirstLine) {
            $plainText = trim(strip_tags($postContent));
            $lines = explode("\n", $plainText);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $itemContent = $line;
                    break;
                }
            }
        } else {
            $itemContent = $postContent;
        }
        
        // Формируем абсолютный URL статьи
        $articleUrl = $baseUrl . '/data/blog/' . $post['filename'];
        
        // Заменяем плейсхолдеры в шаблоне контента
        $formattedContent = str_replace(
            ['*content*', '*url*'],
            [$itemContent, $articleUrl],
            $contentTemplate
        );
        
        // Преобразуем дату статьи (формат d.m.Y H:i) в формат RFC 822 (DATE_RSS)
        $cleanDate = trim(preg_replace('/\s*\(отредактировано\)/u', '', $post['date']));
        $dateObj = DateTime::createFromFormat('d.m.Y H:i', $cleanDate);
        $pubDate = $dateObj ? $dateObj->format(DATE_RSS) : date(DATE_RSS);
        
        $itemsXml .= "    <item>\n";
        $itemsXml .= "      <title>" . htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') . "</title>\n";
        $itemsXml .= "      <link>" . htmlspecialchars($articleUrl, ENT_QUOTES, 'UTF-8') . "</link>\n";
        $itemsXml .= "      <guid isPermaLink=\"true\">" . htmlspecialchars($articleUrl, ENT_QUOTES, 'UTF-8') . "</guid>\n";
        $itemsXml .= "      <description><![CDATA[" . $formattedContent . "]]></description>\n";
        $itemsXml .= "      <pubDate>" . $pubDate . "</pubDate>\n";
        $itemsXml .= "    </item>\n";
    }
    
    $feedUrl = $baseUrl . '/data/feed.xml';
    $lastBuildDate = date(DATE_RSS);
    
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
    $xml .= "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
    $xml .= "  <channel>\n";
    $xml .= "    <title>" . htmlspecialchars($rssTitle, ENT_QUOTES, 'UTF-8') . "</title>\n";
    $xml .= "    <link>" . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . "</link>\n";
    $xml .= "    <description>" . htmlspecialchars($rssDesc, ENT_QUOTES, 'UTF-8') . "</description>\n";
    $xml .= "    <pubDate>" . $lastBuildDate . "</pubDate>\n";
    $xml .= "    <lastBuildDate>" . $lastBuildDate . "</lastBuildDate>\n";
    $xml .= "    <generator>NPBlog RSS Generator</generator>\n";
    if (!empty($baseUrl)) {
        $xml .= "    <atom:link href=\"" . htmlspecialchars($feedUrl, ENT_QUOTES, 'UTF-8') . "\" rel=\"self\" type=\"application/rss+xml\" />\n";
    }
    $xml .= $itemsXml;
    $xml .= "  </channel>\n";
    $xml .= "</rss>\n";
    
    return file_put_contents($xmlFile, $xml) !== false;
}
?>
