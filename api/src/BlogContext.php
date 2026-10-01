<?php
declare(strict_types=1);

namespace NPBlog\Api;

class BlogContext
{
    private static ?array $cachedSettings = null;

    /**
     * Get path to editor_settings.json
     */
    public static function getSettingsFilePath(): string
    {
        return (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2)) . '/editor_settings.json';
    }

    /**
     * Read editor_settings.json
     */
    public static function getSettings(bool $forceReload = false): array
    {
        if (self::$cachedSettings === null || $forceReload) {
            $file = self::getSettingsFilePath();
            if (file_exists($file)) {
                self::$cachedSettings = json_decode(@file_get_contents($file) ?: '[]', true) ?: [];
            } else {
                self::$cachedSettings = [];
            }
        }
        return self::$cachedSettings;
    }

    /**
     * Save settings to editor_settings.json
     */
    public static function saveSettings(array $settings): bool
    {
        self::$cachedSettings = $settings;
        $file = self::getSettingsFilePath();

        if (function_exists('safeWriteJson')) {
            return safeWriteJson($file, $settings);
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return @file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }

    /**
     * Normalize path for consistency
     */
    public static function normalizePath(string $path): string
    {
        $rootDir = defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2);
        $clean = rtrim(str_replace('\\', '/', trim($path)), '/');

        $isAbsolute = (strpos($clean, '/') === 0) ||
                      (strlen($clean) >= 2 && $clean[1] === ':');

        if (!$isAbsolute) {
            $clean = rtrim(str_replace('\\', '/', $rootDir), '/') . '/' . ltrim($clean, '/');
        }

        return $clean;
    }

    /**
     * Extract friendly folder name (matching NPBlog editor's getBlogFolderName JS function)
     */
    public static function getBlogFolderName(string $pathStr, ?array $allPaths = null): string
    {
        if (trim($pathStr) === '') {
            return 'data';
        }

        $clean = rtrim(str_replace('\\', '/', $pathStr), '/');
        $parts = explode('/', $clean);
        $last = end($parts) ?: $clean;

        if ($allPaths !== null && is_array($allPaths)) {
            $duplicates = 0;
            foreach ($allPaths as $p) {
                $c = rtrim(str_replace('\\', '/', $p), '/');
                $pts = explode('/', $c);
                if ((end($pts) ?: $c) === $last) {
                    $duplicates++;
                }
            }

            if ($duplicates > 1 && count($parts) >= 2) {
                return implode('/', array_slice($parts, -2));
            }
        }

        return $last;
    }

    /**
     * Get all configured blog paths
     */
    public static function getBlogPaths(): array
    {
        $settings = self::getSettings();
        $rootDir = defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2);
        $defaultPath = rtrim(str_replace('\\', '/', $rootDir), '/') . '/data';

        if (!empty($settings['blog_paths']) && is_array($settings['blog_paths']) && count($settings['blog_paths']) > 0) {
            $paths = [];
            foreach ($settings['blog_paths'] as $p) {
                $trimmed = trim((string)$p);
                if ($trimmed !== '') {
                    $paths[] = $trimmed;
                }
            }
            if (!empty($paths)) {
                return array_values(array_unique($paths));
            }
        }

        if (!empty($settings['data_path'])) {
            return [trim((string)$settings['data_path'])];
        }

        return [$defaultPath];
    }

    /**
     * Resolve blog identifier to configured path
     * Matches exact path, normalized path, folder name, or relative path
     */
    public static function resolveBlog(string $identifier): ?string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $allPaths = self::getBlogPaths();
        $normTarget = self::normalizePath($identifier);

        // 1. Exact string match
        foreach ($allPaths as $p) {
            if ($p === $identifier) {
                return $p;
            }
        }

        // 2. Normalized path match
        foreach ($allPaths as $p) {
            if (self::normalizePath($p) === $normTarget) {
                return $p;
            }
        }

        // 3. Folder name match (e.g. "data_tech" or "data")
        foreach ($allPaths as $p) {
            $folderName = self::getBlogFolderName($p, $allPaths);
            if (strcasecmp($folderName, $identifier) === 0) {
                return $p;
            }

            $c = rtrim(str_replace('\\', '/', $p), '/');
            $parts = explode('/', $c);
            $last = end($parts) ?: $c;
            if (strcasecmp($last, $identifier) === 0) {
                return $p;
            }
        }

        // 4. Case-insensitive normalized match
        foreach ($allPaths as $p) {
            if (strcasecmp(self::normalizePath($p), $normTarget) === 0) {
                return $p;
            }
        }

        // 5. Fallback: check if identifier matches an existing directory on disk
        $rootDir = defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2);
        $candidateDisk = (strpos($identifier, '/') === 0 || strpos($identifier, '\\') === 0 || (strlen($identifier) >= 2 && $identifier[1] === ':'))
            ? $identifier
            : $rootDir . '/' . ltrim($identifier, '/\\');

        if (is_dir($candidateDisk)) {
            $real = realpath($candidateDisk);
            if ($real !== false && is_dir($real)) {
                return $real;
            }
        }

        return null;
    }

    /**
     * Get active blog path
     */
    public static function getActiveBlogPath(): string
    {
        if (!empty($GLOBALS['NPBLOG_ACTIVE_BLOG_PATH'])) {
            return $GLOBALS['NPBLOG_ACTIVE_BLOG_PATH'];
        }

        $settings = self::getSettings();
        if (!empty($settings['active_blog_path'])) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['active_blog_path'] = $settings['active_blog_path'];
            }
            return $settings['active_blog_path'];
        }

        if (!empty($_SESSION['active_blog_path'])) {
            return $_SESSION['active_blog_path'];
        }

        $paths = self::getBlogPaths();
        return $paths[0];
    }

    /**
     * Set active blog path
     * @param string $path Target blog path
     * @param bool $persist If true, saves to editor_settings.json
     */
    public static function setActiveBlogPath(string $path, bool $persist = false): bool
    {
        $resolved = self::resolveBlog($path);
        if ($resolved === null) {
            return false;
        }

        $GLOBALS['NPBLOG_ACTIVE_BLOG_PATH'] = $resolved;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['active_blog_path'] = $resolved;
        }

        if ($persist) {
            $settings = self::getSettings(true);
            $settings['active_blog_path'] = $resolved;
            $settings['data_path'] = $resolved;
            return self::saveSettings($settings);
        }

        return true;
    }

    /**
     * Inspect incoming HTTP request for blog headers or query parameters
     * Automatically applies blog context for the current request
     */
    public static function initFromRequest(): void
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headerBlog = null;

        // Check headers
        foreach ($headers as $k => $v) {
            $kLower = strtolower((string)$k);
            if ($kLower === 'x-blog-path' || $kLower === 'x-blog') {
                $headerBlog = trim((string)$v);
                break;
            }
        }

        if ($headerBlog === null) {
            if (!empty($_SERVER['HTTP_X_BLOG_PATH'])) {
                $headerBlog = trim((string)$_SERVER['HTTP_X_BLOG_PATH']);
            } elseif (!empty($_SERVER['HTTP_X_BLOG'])) {
                $headerBlog = trim((string)$_SERVER['HTTP_X_BLOG']);
            }
        }

        // Check query parameters
        $queryBlog = null;
        if (!empty($_GET['blog_path'])) {
            $queryBlog = trim((string)$_GET['blog_path']);
        } elseif (!empty($_GET['blog'])) {
            $queryBlog = trim((string)$_GET['blog']);
        }

        $target = $headerBlog ?? $queryBlog;

        if ($target !== null && $target !== '') {
            $resolved = self::resolveBlog($target);
            if ($resolved === null) {
                // Check if current route is a global route (auth, system, settings, blogs list)
                $reqUri = $_SERVER['REQUEST_URI'] ?? '';
                $pathOnly = parse_url($reqUri, PHP_URL_PATH) ?: '';
                $relPath = preg_replace('#^/(?:api(?:/index\.php)?)#i', '', $pathOnly);
                $relPath = '/' . ltrim($relPath, '/');
                $isGlobalRoute = preg_match('#^/(?:v1/)?(?:auth|system|settings|blogs)(?:/|$)#i', $relPath) || $relPath === '/' || $relPath === '/v1' || $relPath === '/v1/';

                if ($isGlobalRoute) {
                    // Do not block global/auth/system endpoints on stale or invalid blog header
                    $active = self::getActiveBlogPath();
                    $GLOBALS['NPBLOG_ACTIVE_BLOG_PATH'] = $active;
                    return;
                }

                Response::error(
                    'blog_not_found',
                    "Указанный блог '{$target}' не найден среди настроенных путей blog_paths.",
                    404,
                    [
                        'requested_blog' => $target,
                        'available_blogs' => self::getBlogPaths()
                    ]
                );
            }

            self::setActiveBlogPath($resolved, false);
        } else {
            // Default to configured active blog
            $active = self::getActiveBlogPath();
            $GLOBALS['NPBLOG_ACTIVE_BLOG_PATH'] = $active;
        }
    }

    /**
     * Get detailed status and metadata for a blog path
     */
    public static function getBlogDetails(string $path, ?array $allPaths = null): array
    {
        $allPaths = $allPaths ?? self::getBlogPaths();
        $settings = self::getSettings();
        $rootDir = defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2);

        $normPath = self::normalizePath($path);
        $folderName = self::getBlogFolderName($path, $allPaths);
        $activePath = self::getActiveBlogPath();
        $defaultActivePath = $settings['active_blog_path'] ?? ($allPaths[0] ?? '');

        $isActive = ($normPath === self::normalizePath($activePath));
        $isDefault = ($normPath === self::normalizePath($defaultActivePath));
        $exists = is_dir($normPath);

        $isWritable = false;
        if ($exists) {
            if (function_exists('isDirectoryWritableSafe')) {
                $isWritable = isDirectoryWritableSafe($normPath);
            } else {
                $isWritable = is_writable($normPath);
            }
        }

        // Count posts
        $postsCount = 0;
        $metaFile = $normPath . '/blog/posts-meta.json';
        if (file_exists($metaFile)) {
            $metaData = json_decode(@file_get_contents($metaFile) ?: '[]', true);
            if (is_array($metaData)) {
                $postsCount = count($metaData);
            }
        }

        // Count drafts
        $draftsCount = 0;
        $draftsDir = $normPath . '/drafts';
        if (is_dir($draftsDir)) {
            $files = @scandir($draftsDir) ?: [];
            foreach ($files as $f) {
                if ($f !== '.' && $f !== '..' && pathinfo($f, PATHINFO_EXTENSION) === 'json') {
                    $draftsCount++;
                }
            }
        }

        // Compute web URL for blog.html
        $blogUrl = '';
        $prev = $GLOBALS['NPBLOG_ACTIVE_BLOG_PATH'] ?? null;
        $GLOBALS['NPBLOG_ACTIVE_BLOG_PATH'] = $path;
        if (function_exists('getDataUrl')) {
            $blogUrl = getDataUrl('blog.html');
        }
        $GLOBALS['NPBLOG_ACTIVE_BLOG_PATH'] = $prev;

        return [
            'id' => md5($normPath),
            'path' => $path,
            'normalized_path' => $normPath,
            'name' => $folderName,
            'folder_name' => $folderName,
            'is_active' => $isActive,
            'is_default' => $isDefault,
            'exists' => $exists,
            'is_writable' => $isWritable,
            'posts_count' => $postsCount,
            'drafts_count' => $draftsCount,
            'url' => $blogUrl
        ];
    }

    /**
     * Get details of all configured blogs
     */
    public static function getAllBlogs(): array
    {
        $allPaths = self::getBlogPaths();
        $list = [];

        foreach ($allPaths as $path) {
            $list[] = self::getBlogDetails($path, $allPaths);
        }

        return $list;
    }

    /**
     * Add a new blog path to blog_paths
     */
    public static function addBlog(string $path, bool $makeActive = false, bool $initialize = true): array
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Путь к папке блога не может быть пустым');
        }

        $allPaths = self::getBlogPaths();
        $normNew = self::normalizePath($trimmed);

        // Check if already in list
        $existing = null;
        foreach ($allPaths as $p) {
            if (self::normalizePath($p) === $normNew) {
                $existing = $p;
                break;
            }
        }

        if ($existing === null) {
            $allPaths[] = $trimmed;
        }

        // Initialize directory structure if requested
        if ($initialize) {
            $dirsToCreate = [
                $normNew,
                $normNew . '/blog',
                $normNew . '/uploads',
                $normNew . '/drafts',
                $normNew . '/templates',
                $normNew . '/custom_fonts',
                $normNew . '/backgrounds',
                $normNew . '/smiles'
            ];

            foreach ($dirsToCreate as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
            }

            // Create initial posts-meta.json if not present
            $metaFile = $normNew . '/blog/posts-meta.json';
            if (!file_exists($metaFile)) {
                if (function_exists('safeWriteJson')) {
                    safeWriteJson($metaFile, []);
                } else {
                    @file_put_contents($metaFile, '[]', LOCK_EX);
                }
            }
        }

        $settings = self::getSettings(true);
        $settings['blog_paths'] = array_values(array_unique($allPaths));

        if ($makeActive || empty($settings['active_blog_path'])) {
            $settings['active_blog_path'] = $trimmed;
            $settings['data_path'] = $trimmed;
            self::setActiveBlogPath($trimmed, false);
        }

        self::saveSettings($settings);

        return self::getBlogDetails($trimmed, $allPaths);
    }

    /**
     * Remove a blog path from blog_paths
     */
    public static function removeBlog(string $path): bool
    {
        $resolved = self::resolveBlog($path);
        if ($resolved === null) {
            throw new \InvalidArgumentException("Блог '{$path}' не найден среди настроенных путей.");
        }

        $allPaths = self::getBlogPaths();
        if (count($allPaths) <= 1) {
            throw new \RuntimeException('Нельзя удалить единственный оставшийся блог.');
        }

        $filtered = array_values(array_filter($allPaths, function ($p) use ($resolved) {
            return self::normalizePath($p) !== self::normalizePath($resolved);
        }));

        $settings = self::getSettings(true);
        $settings['blog_paths'] = $filtered;

        // If removed blog was the active blog, switch active to the first remaining blog
        if (self::normalizePath($settings['active_blog_path'] ?? '') === self::normalizePath($resolved)) {
            $newActive = $filtered[0];
            $settings['active_blog_path'] = $newActive;
            $settings['data_path'] = $newActive;
            self::setActiveBlogPath($newActive, false);
        }

        return self::saveSettings($settings);
    }
}
