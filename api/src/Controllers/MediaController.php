<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;

class MediaController
{
    public function __construct()
    {
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/security_bootstrap.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/background_functions.php';
    }

    /**
     * Build fully-qualified URL for external editors and mobile apps
     */
    private function makeAbsoluteUrl(string $relativeUrl): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . '/' . ltrim($relativeUrl, '/');
    }

    /**
     * GET /api/v1/media
     */
    public function list(array $params, array $body): void
    {
        Auth::requireAuth();

        $type = (string)($_GET['type'] ?? 'all');
        $result = [];

        $typeDirectories = [
            'images' => [
                'dir' => getDataPath('uploads/'),
                'url_prefix' => 'uploads/',
                'exts' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']
            ],
            'video' => [
                'dir' => getDataPath('files/videos/'),
                'url_prefix' => 'files/videos/',
                'exts' => ['mp4', 'webm', 'ogv', 'mov']
            ],
            'audio' => [
                'dir' => getDataPath('files/audio/'),
                'url_prefix' => 'files/audio/',
                'exts' => ['mp3', 'wav', 'ogg', 'm4a', 'aac']
            ],
            'documents' => [
                'dir' => getDataPath('files/documents/'),
                'url_prefix' => 'files/documents/',
                'exts' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', '7z']
            ],
            'fonts' => [
                'dir' => getDataPath('fonts/'),
                'url_prefix' => 'fonts/',
                'exts' => ['ttf', 'otf', 'woff', 'woff2']
            ]
        ];

        $dirsToScan = ($type === 'all') ? $typeDirectories : (isset($typeDirectories[$type]) ? [$type => $typeDirectories[$type]] : []);

        foreach ($dirsToScan as $cat => $info) {
            $dir = $info['dir'];
            if (!is_dir($dir)) continue;

            $files = scandir($dir);
            if (!$files) continue;

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                $filePath = $dir . $file;
                if (!is_file($filePath)) continue;

                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, $info['exts'], true)) continue;

                $stat = stat($filePath);
                $result[] = [
                    'filename' => $file,
                    'type' => $cat,
                    'extension' => $ext,
                    'size' => $stat['size'] ?? 0,
                    'uploaded_at' => $stat['mtime'] ?? 0,
                    'date' => date('d.m.Y H:i', $stat['mtime'] ?? time()),
                    'url' => getDataUrl($info['url_prefix'] . $file),
                    'absolute_url' => $this->makeAbsoluteUrl(getDataUrl($info['url_prefix'] . $file)),
                    'data_path' => 'data/' . $info['url_prefix'] . $file
                ];
            }
        }

        usort($result, fn($a, $b) => $b['uploaded_at'] - $a['uploaded_at']);

        Response::json($result, 200);
    }

    /**
     * POST /api/v1/media/upload
     * Supports both multipart/form-data and Base64 JSON
     */
    public function upload(array $params, array $body): void
    {
        Auth::requireAuth();

        $allowedCategories = [
            'image' => [
                'dir' => getDataPath('uploads/'),
                'url_prefix' => 'uploads/',
                'exts' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
                'max_size' => 20 * 1024 * 1024 // 20 MB
            ],
            'video' => [
                'dir' => getDataPath('files/videos/'),
                'url_prefix' => 'files/videos/',
                'exts' => ['mp4', 'webm', 'ogv', 'mov'],
                'max_size' => 100 * 1024 * 1024 // 100 MB
            ],
            'audio' => [
                'dir' => getDataPath('files/audio/'),
                'url_prefix' => 'files/audio/',
                'exts' => ['mp3', 'wav', 'ogg', 'm4a', 'aac'],
                'max_size' => 50 * 1024 * 1024 // 50 MB
            ],
            'document' => [
                'dir' => getDataPath('files/documents/'),
                'url_prefix' => 'files/documents/',
                'exts' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', '7z'],
                'max_size' => 50 * 1024 * 1024 // 50 MB
            ],
            'font' => [
                'dir' => getDataPath('fonts/'),
                'url_prefix' => 'fonts/',
                'exts' => ['ttf', 'otf', 'woff', 'woff2'],
                'max_size' => 20 * 1024 * 1024 // 20 MB
            ]
        ];

        // 1. Check for multipart/form-data upload
        $uploadedFile = $_FILES['file'] ?? $_FILES['image'] ?? $_FILES['media'] ?? null;
        if ($uploadedFile !== null && is_array($uploadedFile) && isset($uploadedFile['tmp_name'])) {
            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                Response::error('upload_error', 'Ошибка при загрузке файла: код ' . $uploadedFile['error'], 400);
            }

            $origName = (string)($uploadedFile['name'] ?? 'file');
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            // Auto-detect category from extension
            $category = (string)($body['type'] ?? '');
            if (!isset($allowedCategories[$category])) {
                foreach ($allowedCategories as $catKey => $catConfig) {
                    if (in_array($ext, $catConfig['exts'], true)) {
                        $category = $catKey;
                        break;
                    }
                }
            }

            if (!isset($allowedCategories[$category])) {
                Response::error('invalid_extension', "Недопустимое расширение файла: .$ext", 422);
            }

            $config = $allowedCategories[$category];
            if (!in_array($ext, $config['exts'], true)) {
                Response::error('invalid_extension', "Для типа '$category' допустимы только: " . implode(', ', $config['exts']), 422);
            }

            if ($uploadedFile['size'] > $config['max_size']) {
                Response::error('file_too_large', 'Размер файла превышает лимит: ' . ($config['max_size'] / 1024 / 1024) . ' МБ', 422);
            }

            $dir = $config['dir'];
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            if ($category === 'font') {
                $cleanBase = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($origName, PATHINFO_FILENAME));
                $safeFilename = ($cleanBase !== '' ? $cleanBase : uniqid('font_', false)) . '.' . $ext;
            } else {
                $safeFilename = uniqid('m_', true) . '.' . $ext;
            }
            $targetPath = validateSafePath($dir, $safeFilename);

            if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                if (!@copy($uploadedFile['tmp_name'], $targetPath)) {
                    Response::error('save_error', 'Не удалось сохранить файл на сервере', 500);
                }
                @unlink($uploadedFile['tmp_name']);
            }

            $url = getDataUrl($config['url_prefix'] . $safeFilename);
            Response::json([
                'filename' => $safeFilename,
                'original_name' => $origName,
                'type' => $category,
                'extension' => $ext,
                'size' => filesize($targetPath),
                'url' => $url,
                'absolute_url' => $this->makeAbsoluteUrl($url),
                'data_path' => 'data/' . $config['url_prefix'] . $safeFilename
            ], 201, 'Файл успешно загружен');
        }

        // 2. Check for Base64 payload in JSON
        if (!empty($body['base64'])) {
            $base64Data = (string)$body['base64'];
            $filename = trim((string)($body['filename'] ?? ''));

            // Strip data:image/...;base64, prefix if present
            if (preg_match('/^data:([^;]+);base64,(.+)$/is', $base64Data, $m)) {
                $base64Data = $m[2];
            }

            $binaryData = base64_decode($base64Data, true);
            if ($binaryData === false) {
                Response::error('invalid_base64', 'Некорректная Base64-строка', 400);
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = 'jpg'; // default to image
            }

            $category = (string)($body['type'] ?? '');
            if (!isset($allowedCategories[$category])) {
                foreach ($allowedCategories as $catKey => $catConfig) {
                    if (in_array($ext, $catConfig['exts'], true)) {
                        $category = $catKey;
                        break;
                    }
                }
            }

            if (!isset($allowedCategories[$category])) {
                $category = 'image';
            }

            $config = $allowedCategories[$category];
            if (!in_array($ext, $config['exts'], true)) {
                Response::error('invalid_extension', "Недопустимое расширение: .$ext", 422);
            }

            $dir = $config['dir'];
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            if ($category === 'font' && $filename !== '') {
                $cleanBase = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($filename, PATHINFO_FILENAME));
                $safeFilename = ($cleanBase !== '' ? $cleanBase : uniqid('font_', false)) . '.' . $ext;
            } else {
                $safeFilename = uniqid('m_', true) . '.' . $ext;
            }
            $targetPath = validateSafePath($dir, $safeFilename);

            if (file_put_contents($targetPath, $binaryData, LOCK_EX) === false) {
                Response::error('save_error', 'Не удалось записать файл на диск', 500);
            }

            $url = getDataUrl($config['url_prefix'] . $safeFilename);
            Response::json([
                'filename' => $safeFilename,
                'original_name' => $filename ?: $safeFilename,
                'type' => $category,
                'extension' => $ext,
                'size' => strlen($binaryData),
                'url' => $url,
                'absolute_url' => $this->makeAbsoluteUrl($url),
                'data_path' => 'data/' . $config['url_prefix'] . $safeFilename
            ], 201, 'Файл успешно загружен из Base64');
        }

        Response::error('missing_file', 'Файл не передан (ожидается multipart параметр file или JSON поле base64)', 400);
    }

    /**
     * DELETE /api/v1/media
     */
    public function delete(array $params, array $body): void
    {
        Auth::requireAuth();

        $filename = (string)($body['filename'] ?? $_GET['filename'] ?? '');
        $type = (string)($body['type'] ?? $_GET['type'] ?? 'image');

        if (empty($filename)) {
            Response::error('missing_filename', 'Имя файла обязательно', 400);
        }

        $directories = [
            'image' => getDataPath('uploads/'),
            'images' => getDataPath('uploads/'),
            'video' => getDataPath('files/videos/'),
            'audio' => getDataPath('files/audio/'),
            'document' => getDataPath('files/documents/'),
            'documents' => getDataPath('files/documents/'),
            'font' => getDataPath('fonts/'),
            'fonts' => getDataPath('fonts/')
        ];

        $dir = $directories[$type] ?? getDataPath('uploads/');
        $filePath = validateSafePath($dir, basename($filename));

        if (file_exists($filePath)) {
            @unlink($filePath);
            Response::json(['filename' => basename($filename)], 200, 'Файл успешно удален');
        }

        Response::error('file_not_found', 'Файл не найден на сервере', 404);
    }

    /**
     * GET /api/v1/media/backgrounds
     */
    public function getBackgrounds(array $params, array $body): void
    {
        Auth::requireAuth();

        $backgrounds = loadBackgrounds();
        Response::json($backgrounds, 200);
    }

    /**
     * POST /api/v1/media/backgrounds
     */
    public function saveBackground(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (int)($body['post_id'] ?? $body['postId'] ?? 0);
        if ($postId <= 0) {
            Response::error('invalid_post_id', 'Некорректный ID статьи', 400);
        }

        $bgSettings = [
            'background' => (string)($body['background'] ?? ''),
            'repeat' => (string)($body['repeat'] ?? 'no-repeat'),
            'size' => (string)($body['size'] ?? 'cover'),
            'position' => (string)($body['position'] ?? 'center'),
            'overlay_color' => (string)($body['overlay_color'] ?? '#000000'),
            'overlay_opacity' => (float)($body['overlay_opacity'] ?? 0.5)
        ];

        setPostBackground($postId, $bgSettings);

        // Apply to HTML if exists
        $blogDir = getDataPath('blog/');
        $htmlFile = validateSafePath($blogDir, "post-{$postId}.html");
        if (file_exists($htmlFile)) {
            applyBackgroundToHtml($htmlFile, $bgSettings);
        }

        Response::json([
            'post_id' => $postId,
            'background' => $bgSettings
        ], 200, 'Фон статьи успешно установлен');
    }

    /**
     * DELETE /api/v1/media/backgrounds/{postId}
     */
    public function removeBackground(array $params, array $body): void
    {
        Auth::requireAuth();

        $postId = (int)($params['postId'] ?? 0);
        removePostBackground($postId);

        // Clean from HTML
        $blogDir = getDataPath('blog/');
        $htmlFile = validateSafePath($blogDir, "post-{$postId}.html");
        if (file_exists($htmlFile)) {
            applyBackgroundToHtml($htmlFile, []);
        }

        Response::json(['post_id' => $postId], 200, 'Фон статьи удален');
    }

    /**
     * GET /api/v1/media/smiles
     */
    public function listSmiles(array $params, array $body): void
    {
        Auth::requireAuth();

        $smilesDir = getDataPath('smiles/');
        $sets = [];

        if (is_dir($smilesDir)) {
            $dirs = scandir($smilesDir);
            foreach ($dirs as $d) {
                if ($d === '.' || $d === '..') continue;
                $setPath = $smilesDir . $d;
                if (is_dir($setPath)) {
                    $images = [];
                    $files = scandir($setPath);
                    foreach ($files as $f) {
                        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                        if (in_array($ext, ['png', 'gif', 'webp', 'svg', 'jpg'], true)) {
                            $url = getDataUrl("smiles/{$d}/{$f}");
                            $images[] = [
                                'filename' => $f,
                                'url' => $url,
                                'absolute_url' => $this->makeAbsoluteUrl($url),
                                'data_path' => "data/smiles/{$d}/{$f}"
                            ];
                        }
                    }
                    $sets[] = [
                        'name' => $d,
                        'count' => count($images),
                        'items' => $images
                    ];
                }
            }
        }

        Response::json($sets, 200);
    }

    /**
     * GET /api/v1/media/fonts
     */
    public function listFonts(array $params, array $body): void
    {
        Auth::requireAuth();

        $fontsDir = getDataPath('fonts/');
        $fonts = [];

        if (is_dir($fontsDir)) {
            $files = scandir($fontsDir);
            foreach ($files as $f) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (in_array($ext, ['ttf', 'otf', 'woff', 'woff2'], true)) {
                    $fontName = pathinfo($f, PATHINFO_FILENAME);
                    $url = getDataUrl("fonts/{$f}");
                    $fonts[] = [
                        'filename' => $f,
                        'name' => $fontName,
                        'format' => $ext,
                        'url' => $url,
                        'absolute_url' => $this->makeAbsoluteUrl($url),
                        'data_path' => "data/fonts/{$f}"
                    ];
                }
            }
        }

        $customCss = function_exists('getCustomFontsCss') ? getCustomFontsCss() : '';

        Response::json([
            'fonts' => $fonts,
            'css' => $customCss
        ], 200);
    }

    /**
     * POST /api/v1/media/smiles
     * Upload smiles into a smile set (multipart files or base64 items)
     */
    public function uploadSmiles(array $params, array $body): void
    {
        Auth::requireAuth();

        $setName = (string)($body['setName'] ?? $body['set_name'] ?? $_POST['setName'] ?? $_POST['set_name'] ?? '');
        $setName = preg_replace('/[^a-zA-Z0-9_\-\sА-Яа-яЁё]/u', '', $setName);
        $setName = trim($setName);

        if (empty($setName) || $setName === '.' || $setName === '..') {
            Response::error('invalid_set_name', 'Неверное или пустое название набора смайлов', 422);
        }

        $smilesBaseDir = getDataPath('smiles/');
        if (!is_dir($smilesBaseDir)) {
            @mkdir($smilesBaseDir, 0755, true);
        }

        $targetDir = validateSafePath($smilesBaseDir, $setName . '/');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        if (!is_writable($targetDir)) {
            Response::error('dir_not_writable', "Папка набора смайлов недоступна для записи: {$setName}", 500);
        }

        $uploaded = [];
        $allowedExts = ['gif', 'png', 'webp', 'svg', 'jpg', 'jpeg'];

        // 1. Check multipart $_FILES
        $files = $_FILES['smiles'] ?? $_FILES['file'] ?? $_FILES['files'] ?? null;
        if ($files !== null && is_array($files) && isset($files['name'])) {
            $names = is_array($files['name']) ? $files['name'] : [$files['name']];
            $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
            $errors = is_array($files['error']) ? $files['error'] : [$files['error']];

            foreach ($names as $i => $name) {
                if (($errors[$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) continue;
                $tmpName = $tmpNames[$i];
                if (empty($tmpName) || !is_uploaded_file($tmpName)) continue;

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts, true)) continue;

                $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($name, PATHINFO_FILENAME));
                if (empty($baseName)) $baseName = 'smile';

                $safeName = $baseName . '.' . $ext;
                $counter = 1;
                while (file_exists($targetDir . $safeName)) {
                    $safeName = $baseName . '_' . $counter . '.' . $ext;
                    $counter++;
                }

                $destPath = validateSafePath($targetDir, $safeName);
                if (@move_uploaded_file($tmpName, $destPath)) {
                    $url = getDataUrl("smiles/{$setName}/{$safeName}");
                    $uploaded[] = [
                        'filename' => $safeName,
                        'url' => $url,
                        'absolute_url' => $this->makeAbsoluteUrl($url),
                        'data_path' => "data/smiles/{$setName}/{$safeName}"
                    ];
                }
            }
        }

        // 2. Check JSON items array (Base64)
        if (!empty($body['items']) && is_array($body['items'])) {
            foreach ($body['items'] as $item) {
                if (empty($item['base64'])) continue;
                $rawBase64 = (string)$item['base64'];
                if (preg_match('/^data:([^;]+);base64,(.+)$/is', $rawBase64, $m)) {
                    $rawBase64 = $m[2];
                }
                $bin = base64_decode($rawBase64, true);
                if ($bin === false) continue;

                $origName = (string)($item['filename'] ?? 'smile.gif');
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts, true)) $ext = 'gif';

                $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($origName, PATHINFO_FILENAME));
                if (empty($baseName)) $baseName = 'smile';

                $safeName = $baseName . '.' . $ext;
                $counter = 1;
                while (file_exists($targetDir . $safeName)) {
                    $safeName = $baseName . '_' . $counter . '.' . $ext;
                    $counter++;
                }

                $destPath = validateSafePath($targetDir, $safeName);
                if (file_put_contents($destPath, $bin, LOCK_EX) !== false) {
                    $url = getDataUrl("smiles/{$setName}/{$safeName}");
                    $uploaded[] = [
                        'filename' => $safeName,
                        'url' => $url,
                        'absolute_url' => $this->makeAbsoluteUrl($url),
                        'data_path' => "data/smiles/{$setName}/{$safeName}"
                    ];
                }
            }
        }

        if (empty($uploaded)) {
            Response::error('upload_failed', 'Не удалось загрузить ни одного файла смайла (поддерживаются форматы gif, png, webp, svg, jpg)', 400);
        }

        Response::json([
            'set_name' => $setName,
            'count' => count($uploaded),
            'items' => $uploaded
        ], 201, "Успешно загружено " . count($uploaded) . " смайлов в набор '{$setName}'");
    }

    /**
     * DELETE /api/v1/media/smiles/{setName}
     * Delete an entire smile set
     */
    public function deleteSmileSet(array $params, array $body): void
    {
        Auth::requireAuth();

        $setName = trim((string)($params['setName'] ?? ''));
        $setName = preg_replace('/[^a-zA-Z0-9_\-\sА-Яа-яЁё]/u', '', $setName);

        if (empty($setName) || $setName === '.' || $setName === '..') {
            Response::error('invalid_set_name', 'Неверное название набора смайлов', 422);
        }

        $smilesBaseDir = getDataPath('smiles/');
        $targetDir = validateSafePath($smilesBaseDir, $setName);

        if (!is_dir($targetDir)) {
            Response::error('not_found', "Набор смайлов '{$setName}' не найден", 404);
        }

        $files = glob(rtrim($targetDir, '/\\') . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        if (!@rmdir($targetDir)) {
            Response::error('delete_failed', "Не удалось удалить папку набора '{$setName}'. Проверьте права доступа.", 500);
        }

        Response::json(['set_name' => $setName], 200, "Набор смайлов '{$setName}' успешно удален");
    }

    /**
     * DELETE /api/v1/media/fonts/{filename}
     * Delete custom font file
     */
    public function deleteFont(array $params, array $body): void
    {
        Auth::requireAuth();

        $filename = basename((string)($params['filename'] ?? ''));
        if (empty($filename)) {
            Response::error('missing_filename', 'Имя файла шрифта обязательно', 400);
        }

        $fontsDir = getDataPath('fonts/');
        $targetFile = validateSafePath($fontsDir, $filename);

        if (!file_exists($targetFile)) {
            Response::error('not_found', "Шрифт '{$filename}' не найден", 404);
        }

        if (!@unlink($targetFile)) {
            Response::error('delete_failed', "Не удалось удалить файл шрифта '{$filename}'", 500);
        }

        Response::json(['filename' => $filename], 200, "Шрифт '{$filename}' успешно удален");
    }
}
