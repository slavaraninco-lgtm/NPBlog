<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;

class SystemController
{
    public function __construct()
    {
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/security_bootstrap.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/lang_helper.php';
    }

    /**
     * GET /api/v1/system/status
     */
    public function status(array $params, array $body): void
    {
        Auth::requireAuth();

        $blogDir = getDataPath('blog/');
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        $postsCount = 0;
        if (file_exists($metaFile)) {
            $posts = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
            $postsCount = count($posts);
        }

        $mediaCount = 0;
        $uploadsDir = getDataPath('uploads/');
        if (is_dir($uploadsDir)) {
            $mediaFiles = scandir($uploadsDir);
            $mediaCount = count(array_filter($mediaFiles, fn($f) => $f !== '.' && $f !== '..'));
        }

        $draftsCount = 0;
        $draftsDir = getDataPath('drafts/');
        if (is_dir($draftsDir)) {
            $draftFiles = glob($draftsDir . '*.json');
            $draftsCount = count($draftFiles ?: []);
        }

        $dataDir = getDataPath();
        $freeSpace = @disk_free_space($dataDir) ?: 0;
        $totalSpace = @disk_total_space($dataDir) ?: 0;

        $versionFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/version.json';
        $version = '2.287';
        if (file_exists($versionFile)) {
            $vData = json_decode(@file_get_contents($versionFile) ?: '[]', true);
            $version = $vData['version'] ?? $version;
        }

        Response::json([
            'version' => $version,
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache',
            'posts_count' => $postsCount,
            'media_count' => $mediaCount,
            'drafts_count' => $draftsCount,
            'disk' => [
                'free_bytes' => $freeSpace,
                'total_bytes' => $totalSpace,
                'free_mb' => round($freeSpace / 1024 / 1024, 2),
                'total_mb' => round($totalSpace / 1024 / 1024, 2)
            ],
            'directories_writable' => [
                'data' => isDirectoryWritableSafe(getDataPath()),
                'backup' => isDirectoryWritableSafe(getBackupPath()),
                'autosave' => isDirectoryWritableSafe(getAutosavePath())
            ]
        ], 200);
    }

    /**
     * GET /api/v1/system/integrity
     */
    public function integrity(array $params, array $body): void
    {
        Auth::requireAuth();

        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/templates_helper.php';
        initTemplatesSystem();

        $errors = [];
        $blogDir = getDataPath('blog/');
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');

        if (!file_exists($metaFile)) {
            $errors[] = 'Файл метаданных posts-meta.json отсутствует';
        } else {
            $meta = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
            foreach ($meta as $post) {
                $id = $post['id'] ?? null;
                $filename = $post['filename'] ?? "post-{$id}.html";
                $postFile = validateSafePath($blogDir, $filename);
                if (!file_exists($postFile)) {
                    $errors[] = "Отсутствует HTML-файл для статьи #$id: $filename";
                }
            }
        }

        $mainTemplate = getTemplatePath();
        if (!file_exists($mainTemplate)) {
            $errors[] = 'Файл основного шаблона не найден: ' . basename($mainTemplate);
        }

        $blogHtml = getDataPath('blog.html');
        if (!file_exists($blogHtml)) {
            $errors[] = 'Файл blog.html не найден в папке данных';
        }

        Response::json([
            'healthy' => count($errors) === 0,
            'errors_count' => count($errors),
            'errors' => $errors
        ], 200);
    }

    /**
     * POST /api/v1/system/integrity/fix
     */
    public function fixIntegrity(array $params, array $body): void
    {
        Auth::requireAuth();

        $fixedActions = [];
        $blogDir = getDataPath('blog/');
        if (!is_dir($blogDir)) {
            mkdir($blogDir, 0755, true);
            $fixedActions[] = 'Создана директория blog/';
        }

        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        if (!file_exists($metaFile)) {
            // Rebuild from existing post-*.html files
            $postFiles = glob($blogDir . 'post-*.html');
            $newMeta = [];
            if ($postFiles) {
                foreach ($postFiles as $pf) {
                    $base = basename($pf);
                    if (preg_match('/post-(\d+)\.html/', $base, $m)) {
                        $id = (int)$m[1];
                        $html = file_get_contents($pf);
                        $title = "Статья $id";
                        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $tm)) {
                            $title = trim(strip_tags($tm[1]));
                        }
                        $newMeta[] = [
                            'id' => $id,
                            'title' => $title,
                            'date' => date('d.m.Y H:i', filemtime($pf)),
                            'filename' => $base
                        ];
                    }
                }
            }
            safeWriteJson($metaFile, $newMeta);
            $fixedActions[] = 'Восстановлен файл posts-meta.json из файлов статей (' . count($newMeta) . ' статей)';
        }

        Response::json([
            'success' => true,
            'actions_taken' => $fixedActions
        ], 200, 'Процедура восстановления целостности завершена');
    }

    /**
     * GET /api/v1/system/languages
     */
    public function languages(array $params, array $body): void
    {
        $languages = function_exists('getAvailableLanguages') ? getAvailableLanguages() : [];
        Response::json($languages, 200);
    }

    /**
     * GET /api/v1/system/history
     */
    public function getHistory(array $params, array $body): void
    {
        Auth::requireAuth();

        $historyFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/history.json';
        $history = file_exists($historyFile) ? (json_decode(@file_get_contents($historyFile) ?: '[]', true) ?: []) : [];

        Response::json($history, 200);
    }

    /**
     * POST /api/v1/system/history
     */
    public function saveHistory(array $params, array $body): void
    {
        Auth::requireAuth();

        $historyFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/history.json';
        safeWriteJson($historyFile, $body);

        Response::json(null, 200, 'История сохранена');
    }

    /**
     * DELETE /api/v1/system/history
     */
    public function clearHistory(array $params, array $body): void
    {
        Auth::requireAuth();

        $historyFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/history.json';
        safeWriteJson($historyFile, ['undo' => [], 'redo' => []]);

        Response::json(null, 200, 'История очищена');
    }
}
