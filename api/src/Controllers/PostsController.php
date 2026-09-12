<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;

class PostsController
{
    public function __construct()
    {
        // Require global bootstrap functions
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/security_bootstrap.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/templates_helper.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/background_functions.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/rss_helper.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/get_custom_fonts_css.php';
    }

    /**
     * GET /api/v1/posts
     */
    public function list(array $params, array $body): void
    {
        Auth::requireAuth();

        $blogDir = getDataPath('blog/');
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        $posts = [];

        if (file_exists($metaFile)) {
            $posts = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
        }

        // Search query filter
        $search = trim((string)($_GET['q'] ?? ''));
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $posts = array_filter($posts, function ($item) use ($searchLower) {
                $title = mb_strtolower((string)($item['title'] ?? ''));
                $id = (string)($item['id'] ?? '');
                return str_contains($title, $searchLower) || $id === $searchLower;
            });
        }

        // Sorting
        $sort = (string)($_GET['sort'] ?? 'id_desc');
        usort($posts, function ($a, $b) use ($sort) {
            $idA = (int)($a['id'] ?? 0);
            $idB = (int)($b['id'] ?? 0);
            $dateA = strtotime($a['date'] ?? '') ?: 0;
            $dateB = strtotime($b['date'] ?? '') ?: 0;

            return match ($sort) {
                'id_asc' => $idA - $idB,
                'date_asc' => $dateA - $dateB,
                'date_desc' => $dateB - $dateA,
                default => $idB - $idA, // id_desc
            };
        });

        $total = count($posts);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $totalPages = $total > 0 ? (int)ceil($total / $limit) : 1;
        $offset = ($page - 1) * $limit;

        $pagedPosts = array_slice(array_values($posts), $offset, $limit);

        // Enhance post summaries with direct URLs
        foreach ($pagedPosts as &$item) {
            $item['url'] = getDataUrl('blog/' . ($item['filename'] ?? 'post-' . $item['id'] . '.html'));
        }

        Response::json($pagedPosts, 200, null, [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages
        ]);
    }

    /**
     * GET /api/v1/posts/{id}
     */
    public function get(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (int)($params['id'] ?? 0);
        if ($postId <= 0) {
            Response::error('invalid_id', 'Некорректный ID статьи', 400);
        }

        $blogDir = getDataPath('blog/');
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        if (!file_exists($metaFile)) {
            Response::error('meta_not_found', 'Файл метаданных posts-meta.json не найден', 404);
        }

        $meta = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
        $post = null;

        foreach ($meta as $item) {
            if ((int)($item['id'] ?? 0) === $postId) {
                $post = $item;
                break;
            }
        }

        if (!$post) {
            Response::error('post_not_found', "Статья с ID $postId не найдена", 404);
        }

        $filename = validateSafePath($blogDir, $post['filename'] ?? 'post-' . $postId . '.html');
        if (!file_exists($filename)) {
            Response::error('file_not_found', "HTML-файл статьи не найден: " . basename($filename), 404);
        }

        $rawHtml = file_get_contents($filename);
        $extractedContent = extractPostContentFromHtml($rawHtml, $postId);

        // Convert static paths to canonical data URLs for editors
        $dataDir = getDataPath();
        $dirName = basename(rtrim(str_replace('\\', '/', $dataDir), '/'));
        $staticPrefix = '/' . $dirName . '/';
        $canonicalDataUrl = getDataUrl();
        $content = str_replace($staticPrefix, $canonicalDataUrl, $extractedContent);
        // Clean any legacy /api/data/ or /api/... references in content
        $content = preg_replace('/(?:https?:\/\/[^\/]+)?(?:\/)?api\/' . preg_quote($dirName, '/') . '\//i', $canonicalDataUrl, $content);
        $content = preg_replace('/(?:https?:\/\/[^\/]+)?(?:\/)?api\/(uploads|files|fonts|smiles)\//i', $canonicalDataUrl . '$1/', $content);

        // Get background settings
        $background = getPostBackground($postId);

        Response::json([
            'id' => $postId,
            'title' => $post['title'] ?? '',
            'date' => $post['date'] ?? '',
            'filename' => $post['filename'] ?? '',
            'content' => $content,
            'background' => $background,
            'url' => getDataUrl('blog/' . ($post['filename'] ?? 'post-' . $postId . '.html'))
        ], 200);
    }

    /**
     * POST /api/v1/posts
     */
    public function create(array $params, array $body): void
    {
        Auth::requireAuth();

        $title = trim((string)($body['title'] ?? ''));
        $content = (string)($body['content'] ?? '');
        $date = trim((string)($body['date'] ?? ''));

        if ($title === '') {
            Response::error('missing_title', 'Заголовок статьи обязателен', 422);
        }
        if ($content === '') {
            Response::error('missing_content', 'Контент статьи обязателен', 422);
        }

        if ($date === '') {
            $date = date('d.m.Y H:i');
        }

        $cleanContent = $this->formatArticleContent($content);
        $blogDir = getDataPath('blog/');
        if (!is_dir($blogDir)) {
            mkdir($blogDir, 0755, true);
        }

        // Determine next post ID
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        $meta = [];
        if (file_exists($metaFile)) {
            $meta = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
        }

        $maxId = 0;
        foreach ($meta as $item) {
            if (isset($item['id']) && (int)$item['id'] > $maxId) {
                $maxId = (int)$item['id'];
            }
        }
        $nextId = $maxId + 1;

        // Load template
        initTemplatesSystem();
        $templateFile = getTemplatePath($nextId);
        if (!file_exists($templateFile)) {
            $templateFile = __DIR__ . '/../../data/blog/template_post.html';
        }
        $templateHtml = file_get_contents($templateFile);

        // Build final HTML
        $articleHtml = $this->buildArticleHtml($nextId, $title, $date, $cleanContent, $templateHtml);

        // Write HTML file
        $filename = validateSafePath($blogDir, 'post-' . $nextId . '.html');
        file_put_contents($filename, $articleHtml, LOCK_EX);

        // Create initial backup (v1)
        $backupDir = validateSafePath(getBackupPath(), (string)$nextId) . '/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $backupFilename = validateSafePath($backupDir, $nextId . '-1.html');
        file_put_contents($backupFilename, $articleHtml, LOCK_EX);

        // Update backup meta
        $backupMetaFile = validateSafePath(getBackupPath(), 'backup-meta.json');
        $backupMeta = [];
        if (file_exists($backupMetaFile)) {
            $backupMeta = json_decode(@file_get_contents($backupMetaFile) ?: '[]', true) ?: [];
        }
        $backupMeta[$nextId] = [
            'postId' => $nextId,
            'postTitle' => $title,
            'backups' => [
                [
                    'backupNumber' => 1,
                    'filename' => $nextId . '-1.html',
                    'date' => $date,
                    'title' => $title
                ]
            ]
        ];
        safeWriteJson($backupMetaFile, $backupMeta);

        // Update posts-meta.json
        $meta[] = [
            'id' => $nextId,
            'title' => $title,
            'date' => $date,
            'filename' => 'post-' . $nextId . '.html'
        ];
        safeWriteJson($metaFile, $meta);

        // Regenerate RSS
        generateRssFeed();

        Response::json([
            'id' => $nextId,
            'title' => $title,
            'date' => $date,
            'filename' => 'post-' . $nextId . '.html',
            'url' => getDataUrl('blog/post-' . $nextId . '.html')
        ], 201, 'Статья успешно создана');
    }

    /**
     * PUT /api/v1/posts/{id}
     */
    public function update(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (int)($params['id'] ?? 0);
        if ($postId <= 0) {
            Response::error('invalid_id', 'Некорректный ID статьи', 400);
        }

        $title = trim((string)($body['title'] ?? ''));
        $content = (string)($body['content'] ?? '');
        $date = trim((string)($body['date'] ?? ''));

        if ($title === '') {
            Response::error('missing_title', 'Заголовок статьи обязателен', 422);
        }
        if ($content === '') {
            Response::error('missing_content', 'Контент статьи обязателен', 422);
        }

        $blogDir = getDataPath('blog/');
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        if (!file_exists($metaFile)) {
            Response::error('meta_not_found', 'Файл метаданных не найден', 404);
        }

        $meta = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
        $postIndex = -1;

        foreach ($meta as $idx => $item) {
            if ((int)($item['id'] ?? 0) === $postId) {
                $postIndex = $idx;
                break;
            }
        }

        if ($postIndex === -1) {
            Response::error('post_not_found', "Статья с ID $postId не найдена", 404);
        }

        if ($date === '') {
            $date = $meta[$postIndex]['date'] ?? date('d.m.Y H:i');
        }

        $cleanContent = $this->formatArticleContent($content);

        // Load template
        initTemplatesSystem();
        $templateFile = getTemplatePath($postId);
        if (!file_exists($templateFile)) {
            $templateFile = __DIR__ . '/../../data/blog/template_post.html';
        }
        $templateHtml = file_get_contents($templateFile);

        $articleHtml = $this->buildArticleHtml($postId, $title, $date, $cleanContent, $templateHtml);

        // Write HTML
        $postFilename = $meta[$postIndex]['filename'] ?? 'post-' . $postId . '.html';
        $filename = validateSafePath($blogDir, $postFilename);
        file_put_contents($filename, $articleHtml, LOCK_EX);

        // Create incremental backup
        $backupDir = validateSafePath(getBackupPath(), (string)$postId) . '/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupMetaFile = validateSafePath(getBackupPath(), 'backup-meta.json');
        $backupMeta = [];
        if (file_exists($backupMetaFile)) {
            $backupMeta = json_decode(@file_get_contents($backupMetaFile) ?: '[]', true) ?: [];
        }

        $existingBackups = $backupMeta[$postId]['backups'] ?? [];
        $nextBackupNumber = 1;
        foreach ($existingBackups as $b) {
            if (isset($b['backupNumber']) && (int)$b['backupNumber'] >= $nextBackupNumber) {
                $nextBackupNumber = (int)$b['backupNumber'] + 1;
            }
        }

        $backupFilename = validateSafePath($backupDir, $postId . '-' . $nextBackupNumber . '.html');
        file_put_contents($backupFilename, $articleHtml, LOCK_EX);

        if (!isset($backupMeta[$postId])) {
            $backupMeta[$postId] = [
                'postId' => $postId,
                'postTitle' => $title,
                'backups' => []
            ];
        }
        $backupMeta[$postId]['postTitle'] = $title;
        $backupMeta[$postId]['backups'][] = [
            'backupNumber' => $nextBackupNumber,
            'filename' => $postId . '-' . $nextBackupNumber . '.html',
            'date' => date('d.m.Y H:i'),
            'title' => $title
        ];
        safeWriteJson($backupMetaFile, $backupMeta);

        // Update posts-meta.json
        $meta[$postIndex]['title'] = $title;
        $meta[$postIndex]['date'] = $date;
        safeWriteJson($metaFile, $meta);

        // Regenerate RSS
        generateRssFeed();

        Response::json([
            'id' => $postId,
            'title' => $title,
            'date' => $date,
            'filename' => $postFilename,
            'backup_version' => $nextBackupNumber,
            'url' => getDataUrl('blog/' . $postFilename)
        ], 200, 'Статья успешно обновлена');
    }

    /**
     * DELETE /api/v1/posts/{id}
     */
    public function delete(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (int)($params['id'] ?? 0);
        if ($postId <= 0) {
            Response::error('invalid_id', 'Некорректный ID статьи', 400);
        }

        $blogDir = getDataPath('blog/');
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        if (!file_exists($metaFile)) {
            Response::error('meta_not_found', 'Файл метаданных не найден', 404);
        }

        $meta = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
        $foundIndex = -1;
        $postTitle = '';
        $filename = '';

        foreach ($meta as $idx => $item) {
            if ((int)($item['id'] ?? 0) === $postId) {
                $foundIndex = $idx;
                $postTitle = $item['title'] ?? '';
                $filename = $item['filename'] ?? 'post-' . $postId . '.html';
                break;
            }
        }

        if ($foundIndex === -1) {
            Response::error('post_not_found', "Статья с ID $postId не найдена", 404);
        }

        // Delete post HTML file
        $postFilePath = validateSafePath($blogDir, $filename);
        if (file_exists($postFilePath)) {
            @unlink($postFilePath);
        }

        // Remove from meta
        array_splice($meta, $foundIndex, 1);
        safeWriteJson($metaFile, $meta);

        // Remove background if any
        removePostBackground($postId);

        // Mark backups as deleted in backup-meta.json
        $backupMetaFile = validateSafePath(getBackupPath(), 'backup-meta.json');
        if (file_exists($backupMetaFile)) {
            $backupMeta = json_decode(@file_get_contents($backupMetaFile) ?: '[]', true) ?: [];
            if (isset($backupMeta[$postId])) {
                $deletedKey = 'deleted_' . $postId . '_' . time();
                $backupMeta[$deletedKey] = $backupMeta[$postId];
                $backupMeta[$deletedKey]['deletedAt'] = date('d.m.Y H:i');
                unset($backupMeta[$postId]);
                safeWriteJson($backupMetaFile, $backupMeta);
            }
        }

        // Regenerate RSS
        generateRssFeed();

        // Optional auto-renumber
        $renumbered = false;
        if (!empty($_GET['renumber']) && filter_var($_GET['renumber'], FILTER_VALIDATE_BOOLEAN)) {
            $this->performRenumbering();
            $renumbered = true;
        }

        Response::json([
            'id' => $postId,
            'title' => $postTitle,
            'renumbered' => $renumbered
        ], 200, 'Статья успешно удалена');
    }

    /**
     * POST /api/v1/posts/renumber
     */
    public function renumber(array $params, array $body): void
    {
        Auth::requireAuth();
        $changes = $this->performRenumbering();
        Response::json($changes, 200, 'Сквозная перенумерация успешно выполнена');
    }

    /**
     * POST /api/v1/posts/regenerate
     */
    public function regenerate(array $params, array $body): void
    {
        Auth::requireAuth();

        $blogDir = getDataPath('blog/');
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        if (!file_exists($metaFile)) {
            Response::error('meta_not_found', 'Файл метаданных posts-meta.json не найден', 404);
        }

        $meta = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
        $count = 0;

        foreach ($meta as $post) {
            $postId = (int)$post['id'];
            $filename = validateSafePath($blogDir, $post['filename'] ?? 'post-' . $postId . '.html');

            if (file_exists($filename)) {
                $rawHtml = file_get_contents($filename);
                $content = extractPostContentFromHtml($rawHtml, $postId);
                $templateFile = getTemplatePath($postId);
                if (file_exists($templateFile)) {
                    $templateHtml = file_get_contents($templateFile);
                    $newHtml = $this->buildArticleHtml($postId, $post['title'], $post['date'], $content, $templateHtml);
                    file_put_contents($filename, $newHtml, LOCK_EX);
                    $count++;
                }
            }
        }

        generateRssFeed();

        Response::json(['regenerated_count' => $count], 200, "Перегенерация завершена. Обновлено статей: $count");
    }

    /**
     * GET /api/v1/posts/{id}/preview
     */
    public function preview(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (int)($params['id'] ?? 0);
        $blogDir = getDataPath('blog/');
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        $meta = file_exists($metaFile) ? json_decode(@file_get_contents($metaFile) ?: '[]', true) : [];

        $post = null;
        foreach ($meta as $item) {
            if ((int)($item['id'] ?? 0) === $postId) {
                $post = $item;
                break;
            }
        }

        if (!$post) {
            Response::error('post_not_found', 'Статья не найдена', 404);
        }

        $postFile = validateSafePath($blogDir, $post['filename'] ?? 'post-' . $postId . '.html');
        if (!file_exists($postFile)) {
            Response::error('file_not_found', 'HTML файл статьи не найден', 404);
        }

        Response::raw(file_get_contents($postFile), 'text/html; charset=utf-8');
    }

    /**
     * Format article HTML structure
     */
    private function formatArticleContent(string $html): string
    {
        $dataDir = getDataPath();
        $dirName = basename(rtrim(str_replace('\\', '/', $dataDir), '/'));
        $staticPrefix = '/' . $dirName . '/';
        // 1. Clean any /api/data/... or /api/uploads/... paths to canonical static prefix (/data/)
        $html = preg_replace('/(?:https?:\/\/[^\/]+)?(?:\/)?api\/' . preg_quote($dirName, '/') . '\//i', $staticPrefix, $html);
        $html = preg_replace('/(?:https?:\/\/[^\/]+)?(?:\/)?api\/(uploads|files|fonts|smiles)\//i', $staticPrefix . '$1/', $html);

        // 2. Normalize full URLs with host to canonical relative static prefix
        $html = preg_replace('/https?:\/\/[^\/]+\/' . preg_quote($dirName, '/') . '\//i', $staticPrefix, $html);

        // 3. Clean serve_data.php paths and timestamps
        $html = preg_replace('/(?:https?:\/\/[^\/]+)?(?:\/)?serve_data\.php\?file=/i', $staticPrefix, $html);
        $html = preg_replace('/(?:[?&]|&amp;)t=\d+/i', '', $html);

        $preBlocks = [];
        $formatted = preg_replace_callback('/(<pre[^>]*>[\s\S]*?<\/pre>)/i', function ($matches) use (&$preBlocks) {
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
            $cleanLines[] = '        ' . $trimmed;
        }

        $finalHtml = "\n" . implode("\n", $cleanLines) . "\n    ";

        foreach ($preBlocks as $index => $preBlock) {
            $finalHtml = str_replace('___PRE_PLACEHOLDER_' . $index . '___', $preBlock, $finalHtml);
        }

        return $finalHtml;
    }

    /**
     * Assemble full post HTML from template
     */
    private function buildArticleHtml(int $postId, string $title, string $date, string $content, string $templateHtml): string
    {
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/seo_helper.php';

        $articleHtml = str_replace('{{TITLE}}', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), $templateHtml);
        $articleHtml = str_replace('{{DATE}}', htmlspecialchars($date, ENT_QUOTES, 'UTF-8'), $articleHtml);
        $articleHtml = str_replace('{{POST_ID}}', (string)$postId, $articleHtml);

        // Custom fonts
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/get_custom_fonts_css.php';
        $customFontsCss = function_exists('getCustomFontsCss') ? \getCustomFontsCss() : '';
        $articleHtml = str_replace('{{CUSTOM_FONTS}}', $customFontsCss, $articleHtml);

        // SEO tags
        $metaTags = function_exists('generateSeoMetaTagsBlock') ? \generateSeoMetaTagsBlock($postId, $title, $content) : '';
        $articleHtml = str_replace('{{META_TAGS}}', $metaTags, $articleHtml);

        // Body style and content width
        $editorSettingsFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/editor_settings.json';
        $editorSettings = file_exists($editorSettingsFile) ? json_decode(@file_get_contents($editorSettingsFile) ?: '[]', true) : [];
        $contentWidth = isset($editorSettings['contentWidth']) ? (int)$editorSettings['contentWidth'] : 920;
        $bodyStyle = "style=\"max-width: {$contentWidth}px;\"";
        $articleHtml = str_replace('{{BODY_STYLE}}', $bodyStyle, $articleHtml);
        $articleHtml = str_replace('{{CONTENT_WRAPPER_START}}', '', $articleHtml);
        $articleHtml = str_replace('{{CONTENT_WRAPPER_END}}', '', $articleHtml);

        $wrappedContent = $content;
        if (!str_contains($articleHtml, 'id="npblog-post-content"')) {
            $wrappedContent = '<article id="npblog-post-content" class="content">' . $content . '</article>';
        }

        return str_replace('{{CONTENT}}', $wrappedContent, $articleHtml);
    }

    /**
     * Perform sequential renumbering of all posts
     */
    private function performRenumbering(): array
    {
        $metaFile = validateSafePath(getDataPath('blog/'), 'posts-meta.json');
        $backupMetaFile = validateSafePath(getBackupPath(), 'backup-meta.json');

        if (!file_exists($metaFile)) {
            return [];
        }

        $meta = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
        if (empty($meta)) {
            return [];
        }

        usort($meta, fn($a, $b) => (int)$a['id'] - (int)$b['id']);

        $changes = [];
        $backupMeta = file_exists($backupMetaFile) ? (json_decode(@file_get_contents($backupMetaFile) ?: '[]', true) ?: []) : [];
        $backgrounds = loadBackgrounds();
        $newBackgrounds = [];

        foreach ($meta as $index => &$post) {
            $oldId = (int)$post['id'];
            $newId = $index + 1;

            if ($oldId !== $newId) {
                $changes[] = [
                    'old_id' => $oldId,
                    'new_id' => $newId,
                    'title' => $post['title']
                ];

                // Rename post HTML
                $oldFilename = validateSafePath(getDataPath('blog/'), 'post-' . $oldId . '.html');
                $newFilename = validateSafePath(getDataPath('blog/'), 'post-' . $newId . '.html');
                if (file_exists($oldFilename)) {
                    $c = file_get_contents($oldFilename);
                    $c = str_replace('post-' . $oldId . '.html', 'post-' . $newId . '.html', $c);
                    $c = preg_replace('/<meta name="post-id" content="\d+">/', '<meta name="post-id" content="' . $newId . '">', $c);
                    file_put_contents($newFilename, $c);
                    @unlink($oldFilename);
                }

                // Rename backups folder
                $oldBackupDir = validateSafePath(getBackupPath(), (string)$oldId);
                $newBackupDir = validateSafePath(getBackupPath(), (string)$newId);
                if (is_dir($oldBackupDir)) {
                    @rename($oldBackupDir, $newBackupDir);
                }

                if (isset($backupMeta[$oldId])) {
                    $backupMeta[$newId] = $backupMeta[$oldId];
                    $backupMeta[$newId]['postId'] = $newId;
                    unset($backupMeta[$oldId]);
                }

                if (isset($backgrounds[$oldId])) {
                    $newBackgrounds[$newId] = $backgrounds[$oldId];
                }

                $post['id'] = $newId;
                $post['filename'] = 'post-' . $newId . '.html';
            } else {
                if (isset($backgrounds[$oldId])) {
                    $newBackgrounds[$oldId] = $backgrounds[$oldId];
                }
            }
        }

        safeWriteJson($metaFile, $meta);
        safeWriteJson($backupMetaFile, $backupMeta);
        saveBackgrounds($newBackgrounds);
        generateRssFeed();

        return $changes;
    }
}
