<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;

class BackupsController
{
    public function __construct()
    {
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/security_bootstrap.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/templates_helper.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/rss_helper.php';
    }

    /**
     * GET /api/v1/backups
     */
    public function list(array $params, array $body): void
    {
        Auth::requireAuth();

        $backupMetaFile = validateSafePath(getBackupPath(), 'backup-meta.json');
        if (!file_exists($backupMetaFile)) {
            Response::json([], 200);
        }

        $backupMeta = json_decode(@file_get_contents($backupMetaFile) ?: '[]', true) ?: [];

        uksort($backupMeta, function ($a, $b) {
            $aIsDel = str_starts_with((string)$a, 'deleted_');
            $bIsDel = str_starts_with((string)$b, 'deleted_');
            if ($aIsDel === $bIsDel) {
                return $aIsDel ? strcmp((string)$b, (string)$a) : ((int)$b - (int)$a);
            }
            return $aIsDel ? 1 : -1;
        });

        Response::json($backupMeta, 200);
    }

    /**
     * GET /api/v1/backups/{postId}
     */
    public function getPostBackups(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (string)($params['postId'] ?? '');
        $backupMetaFile = validateSafePath(getBackupPath(), 'backup-meta.json');

        if (!file_exists($backupMetaFile)) {
            Response::error('not_found', 'Реестр резервных копий пуст', 404);
        }

        $backupMeta = json_decode(@file_get_contents($backupMetaFile) ?: '[]', true) ?: [];

        if (!isset($backupMeta[$postId])) {
            Response::error('not_found', "Резервные копии для статьи $postId не найдены", 404);
        }

        Response::json($backupMeta[$postId], 200);
    }

    /**
     * GET /api/v1/backups/{postId}/{backupNumber}
     */
    public function getBackupContent(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (string)($params['postId'] ?? '');
        $backupNum = (string)($params['backupNumber'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $postId) || !preg_match('/^\d+$/', $backupNum)) {
            Response::error('invalid_params', 'Некорректные параметры статьи или номера бэкапа', 400);
        }

        $backupDir = validateSafePath(getBackupPath(), $postId) . '/';
        $filename = "{$postId}-{$backupNum}.html";
        $filepath = validateSafePath($backupDir, $filename);

        if (!file_exists($filepath)) {
            Response::error('backup_not_found', "Файл бэкапа $filename не найден", 404);
        }

        $content = file_get_contents($filepath);
        $cleanContent = extractPostContentFromHtml($content, (int)$postId);

        Response::json([
            'post_id' => $postId,
            'backup_number' => (int)$backupNum,
            'filename' => $filename,
            'content' => $cleanContent,
            'raw_html' => $content
        ], 200);
    }

    /**
     * POST /api/v1/backups/{postId}/{backupNumber}/restore
     */
    public function restore(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (string)($params['postId'] ?? '');
        $backupNum = (string)($params['backupNumber'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $postId) || !preg_match('/^\d+$/', $backupNum)) {
            Response::error('invalid_params', 'Некорректные параметры', 400);
        }

        $backupDir = validateSafePath(getBackupPath(), $postId) . '/';
        $backupFile = validateSafePath($backupDir, "{$postId}-{$backupNum}.html");

        if (!file_exists($backupFile)) {
            Response::error('backup_not_found', 'Файл резервной копии не найден', 404);
        }

        $backupContent = file_get_contents($backupFile);
        $blogDir = getDataPath('blog/');
        $postFile = validateSafePath($blogDir, "post-{$postId}.html");

        // Restore post HTML
        if (file_put_contents($postFile, $backupContent, LOCK_EX) === false) {
            Response::error('restore_error', 'Не удалось восстановить файл статьи', 500);
        }

        // Update posts-meta.json if needed
        $metaFile = validateSafePath($blogDir, 'posts-meta.json');
        if (file_exists($metaFile)) {
            $meta = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
            $postIdx = -1;
            foreach ($meta as $idx => $p) {
                if ((string)$p['id'] === $postId) {
                    $postIdx = $idx;
                    break;
                }
            }

            // Extract title from backup HTML
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $backupContent, $m)) {
                $restoredTitle = trim(strip_tags($m[1]));
                if ($postIdx !== -1) {
                    $meta[$postIdx]['title'] = $restoredTitle;
                    safeWriteJson($metaFile, $meta);
                }
            }
        }

        generateRssFeed();

        Response::json([
            'post_id' => $postId,
            'restored_backup_number' => (int)$backupNum
        ], 200, "Статья успешно восстановлена из бэкапа №$backupNum");
    }

    /**
     * DELETE /api/v1/backups/{postId}/{backupNumber}
     */
    public function delete(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (string)($params['postId'] ?? '');
        $backupNum = (string)($params['backupNumber'] ?? '');

        $backupDir = validateSafePath(getBackupPath(), $postId) . '/';
        $filename = "{$postId}-{$backupNum}.html";
        $filepath = validateSafePath($backupDir, $filename);

        if (file_exists($filepath)) {
            @unlink($filepath);
        }

        // Update backup-meta.json
        $backupMetaFile = validateSafePath(getBackupPath(), 'backup-meta.json');
        if (file_exists($backupMetaFile)) {
            $backupMeta = json_decode(@file_get_contents($backupMetaFile) ?: '[]', true) ?: [];
            if (isset($backupMeta[$postId]['backups'])) {
                $backupMeta[$postId]['backups'] = array_values(array_filter(
                    $backupMeta[$postId]['backups'],
                    fn($item) => (string)($item['backupNumber'] ?? '') !== $backupNum
                ));

                if (empty($backupMeta[$postId]['backups'])) {
                    unset($backupMeta[$postId]);
                    if (is_dir($backupDir)) {
                        @rmdir($backupDir);
                    }
                }

                safeWriteJson($backupMetaFile, $backupMeta);
            }
        }

        Response::json(['post_id' => $postId, 'backup_number' => (int)$backupNum], 200, 'Бэкап успешно удален');
    }
}
