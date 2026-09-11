<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;

class TemplatesController
{
    public function __construct()
    {
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/security_bootstrap.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/templates_helper.php';
    }

    /**
     * GET /api/v1/templates
     */
    public function list(array $params, array $body): void
    {
        Auth::requireAuth();

        initTemplatesSystem();
        $templatesDir = getDataPath('blog/templates/');
        $settingsFile = $templatesDir . 'settings.json';

        $settings = file_exists($settingsFile) ? (json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: []) : [];
        $responseTemplates = [];

        if (isset($settings['templates']) && is_array($settings['templates'])) {
            foreach ($settings['templates'] as $name => $meta) {
                $path = $meta['path'] ?? ($name === 'main' ? 'NPBlog/main.html' : $name . '.html');
                $templateFile = $templatesDir . $path;
                $code = file_exists($templateFile) ? (@file_get_contents($templateFile) ?: '') : '';

                $responseTemplates[] = [
                    'name' => $name,
                    'title' => $meta['title'] ?? $name,
                    'description' => $meta['description'] ?? '',
                    'is_system' => $meta['is_system'] ?? false,
                    'code' => $code
                ];
            }
        }

        Response::json([
            'templates' => $responseTemplates,
            'default' => $settings['default'] ?? 'main',
            'post_templates' => $settings['post_templates'] ?? new \stdClass()
        ], 200);
    }

    /**
     * GET /api/v1/templates/{name}
     */
    public function get(array $params, array $body): void
    {
        Auth::requireAuth();

        $name = (string)($params['name'] ?? '');
        initTemplatesSystem();
        $templatesDir = getDataPath('blog/templates/');
        $settingsFile = $templatesDir . 'settings.json';
        $settings = file_exists($settingsFile) ? (json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: []) : [];

        if (!isset($settings['templates'][$name])) {
            Response::error('template_not_found', "Шаблон '$name' не найден", 404);
        }

        $meta = $settings['templates'][$name];
        $path = $meta['path'] ?? ($name === 'main' ? 'NPBlog/main.html' : $name . '.html');
        $templateFile = $templatesDir . $path;
        $code = file_exists($templateFile) ? file_get_contents($templateFile) : '';

        Response::json([
            'name' => $name,
            'title' => $meta['title'] ?? $name,
            'description' => $meta['description'] ?? '',
            'is_system' => $meta['is_system'] ?? false,
            'code' => $code
        ], 200);
    }

    /**
     * POST /api/v1/templates
     */
    public function save(array $params, array $body): void
    {
        Auth::requireAuth();

        $name = trim((string)($body['name'] ?? ''));
        $title = trim((string)($body['title'] ?? ''));
        $description = trim((string)($body['description'] ?? ''));
        $code = (string)($body['code'] ?? '');

        if ($name === '' || !preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            Response::error('invalid_name', 'Имя шаблона должно содержать только латинские буквы, цифры, дефис и подчеркивание', 422);
        }
        if ($title === '') {
            $title = $name;
        }
        if ($code === '') {
            Response::error('empty_code', 'Код шаблона не может быть пустым', 422);
        }

        initTemplatesSystem();
        $templatesDir = getDataPath('blog/templates/');
        $settingsFile = $templatesDir . 'settings.json';
        $settings = file_exists($settingsFile) ? (json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: []) : [];

        $templateFilename = ($name === 'main' ? 'NPBlog/main.html' : $name . '.html');
        $templatePath = $templatesDir . $templateFilename;

        $dir = dirname($templatePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (file_put_contents($templatePath, $code, LOCK_EX) === false) {
            Response::error('save_error', 'Не удалось сохранить файл шаблона', 500);
        }

        $settings['templates'][$name] = [
            'title' => $title,
            'description' => $description,
            'path' => $templateFilename,
            'is_system' => ($name === 'main')
        ];

        safeWriteJson($settingsFile, $settings);

        Response::json([
            'name' => $name,
            'title' => $title,
            'description' => $description
        ], 200, 'Шаблон успешно сохранен');
    }

    /**
     * POST /api/v1/templates/apply
     */
    public function apply(array $params, array $body): void
    {
        Auth::requireAuth();

        $templateName = trim((string)($body['template'] ?? 'main'));
        $postId = isset($body['post_id']) ? (int)$body['post_id'] : null;

        initTemplatesSystem();
        $templatesDir = getDataPath('blog/templates/');
        $settingsFile = $templatesDir . 'settings.json';
        $settings = file_exists($settingsFile) ? (json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: []) : [];

        if (!isset($settings['templates'][$templateName])) {
            Response::error('template_not_found', "Шаблон '$templateName' не существует", 404);
        }

        if ($postId !== null && $postId > 0) {
            // Apply to specific post
            if (!isset($settings['post_templates']) || !is_array($settings['post_templates'])) {
                $settings['post_templates'] = [];
            }
            $settings['post_templates'][(string)$postId] = $templateName;
            safeWriteJson($settingsFile, $settings);

            // Re-render post HTML
            $blogDir = getDataPath('blog/');
            $postFile = validateSafePath($blogDir, "post-{$postId}.html");
            if (file_exists($postFile)) {
                $html = file_get_contents($postFile);
                $content = extractPostContentFromHtml($html, $postId);
                $templatePath = getTemplatePath($postId);
                if (file_exists($templatePath)) {
                    $tplHtml = file_get_contents($templatePath);
                    $metaFile = validateSafePath($blogDir, 'posts-meta.json');
                    $meta = file_exists($metaFile) ? (json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: []) : [];
                    $title = 'Post ' . $postId;
                    $date = date('d.m.Y H:i');
                    foreach ($meta as $p) {
                        if ((int)$p['id'] === $postId) {
                            $title = $p['title'];
                            $date = $p['date'];
                            break;
                        }
                    }

                    require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/seo_helper.php';
                    $newHtml = str_replace('{{TITLE}}', htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), $tplHtml);
                    $newHtml = str_replace('{{DATE}}', htmlspecialchars($date, ENT_QUOTES, 'UTF-8'), $newHtml);
                    $newHtml = str_replace('{{POST_ID}}', (string)$postId, $newHtml);
                    $newHtml = str_replace('{{CONTENT}}', $content, $newHtml);
                    $newHtml = str_replace('{{CUSTOM_FONTS}}', getCustomFontsCss() ?: '', $newHtml);
                    $metaTags = function_exists('generateSeoMetaTagsBlock') ? \generateSeoMetaTagsBlock($postId, $title, $content) : '';
                    $newHtml = str_replace('{{META_TAGS}}', $metaTags, $newHtml);
                    $newHtml = str_replace('{{BODY_STYLE}}', '', $newHtml);
                    $newHtml = str_replace('{{CONTENT_WRAPPER_START}}', '', $newHtml);
                    $newHtml = str_replace('{{CONTENT_WRAPPER_END}}', '', $newHtml);

                    file_put_contents($postFile, $newHtml, LOCK_EX);
                }
            }

            Response::json(['post_id' => $postId, 'template' => $templateName], 200, "Шаблон '$templateName' успешно применен к статье $postId");
        } else {
            // Set as default template for all
            $settings['default'] = $templateName;
            safeWriteJson($settingsFile, $settings);

            Response::json(['default_template' => $templateName], 200, "Шаблон '$templateName' установлен по умолчанию для блога");
        }
    }

    /**
     * DELETE /api/v1/templates/{name}
     */
    public function delete(array $params, array $body): void
    {
        Auth::requireAuth();

        $name = (string)($params['name'] ?? '');
        if ($name === 'main') {
            Response::error('system_template', 'Системный шаблон "main" не может быть удален', 400);
        }

        initTemplatesSystem();
        $templatesDir = getDataPath('blog/templates/');
        $settingsFile = $templatesDir . 'settings.json';
        $settings = file_exists($settingsFile) ? (json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: []) : [];

        if (!isset($settings['templates'][$name])) {
            Response::error('template_not_found', 'Шаблон не найден', 404);
        }

        $path = $settings['templates'][$name]['path'] ?? ($name . '.html');
        $file = $templatesDir . $path;
        if (file_exists($file)) {
            @unlink($file);
        }

        unset($settings['templates'][$name]);
        if (($settings['default'] ?? '') === $name) {
            $settings['default'] = 'main';
        }

        safeWriteJson($settingsFile, $settings);

        Response::json(['deleted' => $name], 200, "Шаблон '$name' успешно удален");
    }

    /**
     * GET /api/v1/includes
     */
    public function listIncludes(array $params, array $body): void
    {
        Auth::requireAuth();

        $includesDir = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/includes/';
        if (!is_dir($includesDir)) {
            Response::json([], 200);
        }

        $metaFile = $includesDir . 'includes-meta.json';
        $meta = file_exists($metaFile) ? (json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: []) : [];

        $files = glob($includesDir . '*.txt');
        $result = [];

        if ($files) {
            foreach ($files as $file) {
                $filename = basename($file);
                if ($filename === 'includes-meta.json') continue;

                $displayName = $meta[$filename] ?? pathinfo($filename, PATHINFO_FILENAME);
                $result[] = [
                    'name' => $filename,
                    'display_name' => $displayName,
                    'size' => filesize($file),
                    'updated_at' => filemtime($file)
                ];
            }
        }

        Response::json($result, 200);
    }

    /**
     * GET /api/v1/includes/{name}
     */
    public function getInclude(array $params, array $body): void
    {
        Auth::requireAuth();

        $name = (string)($params['name'] ?? '');
        $includesDir = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/includes/';
        $filePath = validateSafePath($includesDir, $name);

        if (!file_exists($filePath)) {
            Response::error('include_not_found', "Вставка '$name' не найдена", 404);
        }

        $metaFile = $includesDir . 'includes-meta.json';
        $meta = file_exists($metaFile) ? (json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: []) : [];

        Response::json([
            'name' => basename($filePath),
            'display_name' => $meta[basename($filePath)] ?? pathinfo(basename($filePath), PATHINFO_FILENAME),
            'content' => file_get_contents($filePath)
        ], 200);
    }

    /**
     * POST /api/v1/includes
     */
    public function saveInclude(array $params, array $body): void
    {
        Auth::requireAuth();

        $displayName = trim((string)($body['display_name'] ?? $body['name'] ?? ''));
        $content = (string)($body['content'] ?? '');

        if ($displayName === '') {
            Response::error('missing_name', 'Имя вставки обязательно', 422);
        }

        $includesDir = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/includes/';
        if (!is_dir($includesDir)) {
            @mkdir($includesDir, 0755, true);
        }

        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $displayName) . '.txt';
        $filePath = validateSafePath($includesDir, $filename);

        if (file_put_contents($filePath, $content, LOCK_EX) === false) {
            Response::error('save_error', 'Не удалось сохранить файл вставки', 500);
        }

        $metaFile = $includesDir . 'includes-meta.json';
        $meta = file_exists($metaFile) ? (json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: []) : [];
        $meta[$filename] = $displayName;
        safeWriteJson($metaFile, $meta);

        Response::json([
            'name' => $filename,
            'display_name' => $displayName
        ], 201, 'Вставка успешно сохранена');
    }

    /**
     * DELETE /api/v1/includes/{name}
     */
    public function deleteInclude(array $params, array $body): void
    {
        Auth::requireAuth();

        $name = (string)($params['name'] ?? '');
        $includesDir = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/includes/';
        $filePath = validateSafePath($includesDir, $name);

        if (file_exists($filePath)) {
            @unlink($filePath);

            $metaFile = $includesDir . 'includes-meta.json';
            if (file_exists($metaFile)) {
                $meta = json_decode(@file_get_contents($metaFile) ?: '[]', true) ?: [];
                unset($meta[basename($filePath)]);
                safeWriteJson($metaFile, $meta);
            }

            Response::json(['deleted' => basename($filePath)], 200, 'Вставка успешно удалена');
        }

        Response::error('include_not_found', 'Файл вставки не найден', 404);
    }
}
