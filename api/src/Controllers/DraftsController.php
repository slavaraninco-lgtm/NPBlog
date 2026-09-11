<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;

class DraftsController
{
    public function __construct()
    {
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/security_bootstrap.php';
    }

    /**
     * GET /api/v1/drafts
     */
    public function list(array $params, array $body): void
    {
        Auth::requireAuth();

        $draftDir = getDataPath('drafts/');
        $dirsToCheck = [$draftDir];
        $legacyDir = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/draft/';
        if (is_dir($legacyDir)) {
            $dirsToCheck[] = $legacyDir;
        }

        $drafts = [];
        $seenFiles = [];

        foreach ($dirsToCheck as $dir) {
            if (!is_dir($dir)) continue;
            $files = glob($dir . '*.json');
            if (!$files) continue;

            foreach ($files as $file) {
                $baseName = basename($file);
                if (isset($seenFiles[$baseName])) continue;
                $seenFiles[$baseName] = true;

                $content = @file_get_contents($file);
                $draft = json_decode($content ?: '[]', true);

                if ($draft) {
                    $draft['filename'] = $baseName;
                    $drafts[] = $draft;
                }
            }
        }

        usort($drafts, fn($a, $b) => ((int)($b['timestamp'] ?? 0)) - ((int)($a['timestamp'] ?? 0)));

        Response::json($drafts, 200);
    }

    /**
     * GET /api/v1/drafts/{filename}
     */
    public function get(array $params, array $body): void
    {
        Auth::requireAuth();

        $filename = (string)($params['filename'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_\-.]+\.json$/i', $filename)) {
            Response::error('invalid_filename', 'Некорректное имя файла черновика', 400);
        }

        $draftDir = getDataPath('drafts/');
        $filepath = validateSafePath($draftDir, $filename);

        if (!file_exists($filepath)) {
            $legacyPath = validateSafePath((defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/draft/', $filename);
            if (file_exists($legacyPath)) {
                $filepath = $legacyPath;
            } else {
                Response::error('draft_not_found', "Черновик '$filename' не найден", 404);
            }
        }

        $data = json_decode(@file_get_contents($filepath) ?: '[]', true);
        if (!$data) {
            Response::error('corrupt_draft', 'Ошибка чтения файла черновика', 500);
        }

        $data['filename'] = $filename;
        Response::json($data, 200);
    }

    /**
     * POST /api/v1/drafts
     */
    public function save(array $params, array $body): void
    {
        Auth::requireAuth();

        $title = trim((string)($body['title'] ?? ''));
        $content = (string)($body['content'] ?? '');

        if ($title === '' && $content === '') {
            Response::error('empty_draft', 'Заголовок или контент черновика обязательны', 422);
        }

        $draftDir = getDataPath('drafts/');
        if (!is_dir($draftDir)) {
            @mkdir($draftDir, 0755, true);
        }

        $timestamp = time();
        $filename = $timestamp . '.json';
        $filepath = validateSafePath($draftDir, $filename);

        $draft = [
            'title' => $title,
            'content' => $content,
            'timestamp' => $timestamp,
            'date' => date('Y-m-d H:i:s', $timestamp)
        ];

        if (safeWriteJson($filepath, $draft)) {
            Response::json([
                'filename' => $filename,
                'timestamp' => $timestamp,
                'title' => $title
            ], 201, 'Черновик успешно сохранен');
        } else {
            Response::error('write_error', 'Не удалось сохранить файл черновика', 500);
        }
    }

    /**
     * DELETE /api/v1/drafts/{filename}
     */
    public function delete(array $params, array $body): void
    {
        Auth::requireAuth();

        $filename = (string)($params['filename'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_\-.]+\.json$/i', $filename)) {
            Response::error('invalid_filename', 'Некорректное имя файла черновика', 400);
        }

        $draftDir = getDataPath('drafts/');
        $filepath = validateSafePath($draftDir, $filename);

        if (file_exists($filepath)) {
            @unlink($filepath);
            Response::json(['filename' => $filename], 200, 'Черновик успешно удален');
        }

        $legacyPath = validateSafePath((defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/draft/', $filename);
        if (file_exists($legacyPath)) {
            @unlink($legacyPath);
            Response::json(['filename' => $filename], 200, 'Черновик успешно удален');
        }

        Response::error('draft_not_found', 'Файл черновика не найден', 404);
    }

    /**
     * GET /api/v1/autosaves
     */
    public function listAutosaves(array $params, array $body): void
    {
        Auth::requireAuth();

        $autosaveDir = getAutosavePath();
        $files = glob($autosaveDir . 'autosave_*.json');
        $result = [];

        if ($files) {
            foreach ($files as $file) {
                $base = basename($file);
                if (preg_match('/autosave_(.+)\.json$/', $base, $match)) {
                    $postId = $match[1];
                    $json = json_decode(@file_get_contents($file) ?: '[]', true);
                    $result[] = [
                        'post_id' => $postId,
                        'title' => $json['title'] ?? '',
                        'updated_at' => $json['timestamp'] ?? filemtime($file),
                        'date' => isset($json['timestamp']) ? date('d.m.Y H:i', $json['timestamp']) : date('d.m.Y H:i', filemtime($file)),
                        'filename' => $base
                    ];
                }
            }
        }

        usort($result, fn($a, $b) => ((int)$b['updated_at']) - ((int)$a['updated_at']));

        Response::json($result, 200);
    }

    /**
     * GET /api/v1/autosaves/{postId}
     */
    public function getAutosave(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (string)($params['postId'] ?? '0');
        $autosaveDir = getAutosavePath();
        $filename = "autosave_{$postId}.json";
        $filepath = validateSafePath($autosaveDir, $filename);

        if (!file_exists($filepath)) {
            Response::error('autosave_not_found', "Автосохранение для статьи $postId не найдено", 404);
        }

        $data = json_decode(@file_get_contents($filepath) ?: '[]', true);
        if (!$data) {
            Response::error('corrupt_autosave', 'Ошибка чтения файла автосохранения', 500);
        }

        Response::json($data, 200);
    }

    /**
     * POST /api/v1/autosaves
     */
    public function saveAutosave(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (string)($body['post_id'] ?? $body['postId'] ?? '0');
        $title = (string)($body['title'] ?? '');
        $content = (string)($body['content'] ?? '');

        $autosaveDir = getAutosavePath();
        if (!is_dir($autosaveDir)) {
            @mkdir($autosaveDir, 0755, true);
        }

        $filename = "autosave_{$postId}.json";
        $filepath = validateSafePath($autosaveDir, $filename);

        $payload = [
            'post_id' => $postId,
            'title' => $title,
            'content' => $content,
            'timestamp' => time(),
            'date' => date('d.m.Y H:i:s')
        ];

        if (safeWriteJson($filepath, $payload)) {
            Response::json(['post_id' => $postId, 'updated_at' => $payload['timestamp']], 200, 'Автосохранение сохранено');
        } else {
            Response::error('write_error', 'Не удалось сохранить автосохранение', 500);
        }
    }

    /**
     * DELETE /api/v1/autosaves/{postId}
     */
    public function deleteAutosave(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (string)($params['postId'] ?? '0');
        $autosaveDir = getAutosavePath();
        $filename = "autosave_{$postId}.json";
        $filepath = validateSafePath($autosaveDir, $filename);

        if (file_exists($filepath)) {
            @unlink($filepath);
            Response::json(['post_id' => $postId], 200, 'Автосохранение удалено');
        }

        Response::error('autosave_not_found', 'Файл автосохранения не найден', 404);
    }

    /**
     * DELETE /api/v1/autosaves
     */
    public function clearAllAutosaves(array $params, array $body): void
    {
        Auth::requireAuth();

        $autosaveDir = getAutosavePath();
        $files = glob($autosaveDir . 'autosave_*.json');
        $count = 0;

        if ($files) {
            foreach ($files as $f) {
                if (@unlink($f)) {
                    $count++;
                }
            }
        }

        Response::json(['deleted_count' => $count], 200, "Все автосохранения очищены ($count)");
    }
}
