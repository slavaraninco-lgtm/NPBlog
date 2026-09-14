<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;
use NPBlog\Api\BlogContext;

class BlogsController
{
    public function __construct()
    {
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/security_bootstrap.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/rss_helper.php';
    }

    /**
     * GET /api/v1/blogs
     * List all configured blogs
     */
    public function list(array $params, array $body): void
    {
        Auth::requireAuth();

        $blogs = BlogContext::getAllBlogs();
        $activePath = BlogContext::getActiveBlogPath();

        Response::json($blogs, 200, 'Список настроенных блогов успешно получен', [
            'total' => count($blogs),
            'active_blog' => $activePath
        ]);
    }

    /**
     * GET /api/v1/blogs/active
     * Get details of currently active blog
     */
    public function getActive(array $params, array $body): void
    {
        Auth::requireAuth();

        $activePath = BlogContext::getActiveBlogPath();
        $details = BlogContext::getBlogDetails($activePath);

        Response::json($details, 200, 'Данные активного блога успешно получены');
    }

    /**
     * POST /api/v1/blogs/switch
     * Globally switch active blog (updates editor_settings.json and session)
     */
    public function switchActive(array $params, array $body): void
    {
        Auth::requireAuth();

        $target = trim((string)($body['path'] ?? $body['blog'] ?? $body['name'] ?? ''));
        if ($target === '') {
            Response::error('missing_parameter', 'Параметр path или blog обязателен для переключения блога', 422);
        }

        $resolved = BlogContext::resolveBlog($target);
        if ($resolved === null) {
            Response::error(
                'blog_not_found',
                "Блог '{$target}' не найден среди настроенных путей blog_paths.",
                404,
                ['available_blogs' => BlogContext::getBlogPaths()]
            );
        }

        $success = BlogContext::setActiveBlogPath($resolved, true);
        if (!$success) {
            Response::error('switch_failed', 'Не удалось переключить активный блог', 500);
        }

        // Regenerate RSS feed for the new active blog if supported
        if (function_exists('generateRssFeed')) {
            @generateRssFeed();
        }

        $details = BlogContext::getBlogDetails($resolved);

        Response::json($details, 200, 'Активный блог успешно переключен', [
            'blogUrl' => function_exists('getDataUrl') ? getDataUrl('blog.html') : ''
        ]);
    }

    /**
     * POST /api/v1/blogs
     * Add a new blog path to blog_paths in editor_settings.json
     */
    public function add(array $params, array $body): void
    {
        Auth::requireAuth();

        $path = trim((string)($body['path'] ?? ''));
        if ($path === '') {
            Response::error('missing_parameter', 'Параметр path обязателен', 422);
        }

        $makeActive = !empty($body['make_active']);
        $initialize = isset($body['initialize']) ? (bool)$body['initialize'] : true;

        try {
            $details = BlogContext::addBlog($path, $makeActive, $initialize);

            if ($makeActive && function_exists('generateRssFeed')) {
                @generateRssFeed();
            }

            Response::json($details, 201, 'Блог успешно добавлен в конфигурацию');
        } catch (\InvalidArgumentException $e) {
            Response::error('invalid_path', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            Response::error('add_blog_error', 'Ошибка при добавлении блога: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/v1/blogs
     * Remove a blog path from blog_paths
     */
    public function remove(array $params, array $body): void
    {
        Auth::requireAuth();

        $path = trim((string)($body['path'] ?? $_GET['path'] ?? $_GET['blog'] ?? ''));
        if ($path === '') {
            Response::error('missing_parameter', 'Параметр path обязателен для удаления блога из конфигурации', 422);
        }

        try {
            $success = BlogContext::removeBlog($path);
            if (!$success) {
                Response::error('remove_failed', 'Не удалось обновить настройки блогов', 500);
            }

            Response::json([
                'remaining_blogs' => BlogContext::getAllBlogs(),
                'active_blog' => BlogContext::getActiveBlogPath()
            ], 200, 'Путь к блогу успешно удален из конфигурации');
        } catch (\InvalidArgumentException $e) {
            Response::error('blog_not_found', $e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            Response::error('cannot_delete_last_blog', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error('remove_blog_error', 'Ошибка при удалении блога: ' . $e->getMessage(), 500);
        }
    }
}
