<?php
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/lang_helper.php';
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!headers_sent()) {
        http_response_code(405);
    }
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$title = trim((string)($data['title'] ?? ''));
$folder = trim((string)($data['folder'] ?? ''));
$makeActive = !empty($data['make_active']);

if ($title === '') {
    $title = 'Новый блог';
}

if ($folder === '') {
    echo json_encode(['success' => false, 'error' => 'Укажите название папки для блога']);
    exit;
}

// Проверка валидности имени папки
// Разрешены латинские буквы, цифры, дефис и подчеркивание
if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $folder)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Название папки может содержать только латинские буквы, цифры, тире и подчеркивание (без пробелов и спецсимволов)'
    ]);
    exit;
}

if (strlen($folder) > 64) {
    echo json_encode([
        'success' => false, 
        'error' => 'Название папки слишком длинное (максимум 64 символа)'
    ]);
    exit;
}

// Запрещенные имена системных каталогов
$forbiddenNames = [
    'api', 'lang', 'modals', 'modals_editor', 'includes', 'editor_backup',
    'autosave', 'data_backup', 'vendor', '.git', 'css', 'js', 'assets'
];

if (in_array(strtolower($folder), $forbiddenNames, true)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Папка с именем "' . htmlspecialchars($folder) . '" зарезервирована системой'
    ]);
    exit;
}

$targetDir = __DIR__ . '/' . $folder;

if (file_exists($targetDir)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Папка "' . htmlspecialchars($folder) . '" уже существует на сервере'
    ]);
    exit;
}

// Рекурсивное копирование директории
if (!function_exists('copyDirRecursive')) {
    function copyDirRecursive($src, $dst) {
        if (!is_dir($src)) return false;
        if (!is_dir($dst)) {
            if (!@mkdir($dst, 0777, true)) return false;
        }
        $dir = @opendir($src);
        if (!$dir) return false;
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                copyDirRecursive($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
        return true;
    }
}

// Создаем основную директорию блога
if (!@mkdir($targetDir, 0777, true)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Не удалось создать папку: ' . htmlspecialchars($folder) . '. Проверьте права на запись на сервере.'
    ]);
    exit;
}

// Создаем поддиректории блога
$subDirs = [
    'uploads',
    'drafts',
    'backgrounds',
    'files',
    'files/videos',
    'files/audio',
    'files/documents',
    'fonts',
    'smiles',
    'blog',
    'blog/templates',
    'blog/templates/NPBlog'
];

foreach ($subDirs as $sd) {
    $p = $targetDir . '/' . $sd;
    if (!is_dir($p)) {
        @mkdir($p, 0777, true);
    }
}

// Копируем assets (blog-post.css, blog-post.js, katex)
$sourceDataDir = __DIR__ . '/data';
if (is_dir($sourceDataDir . '/blog/assets')) {
    copyDirRecursive($sourceDataDir . '/blog/assets', $targetDir . '/blog/assets');
}

// Копируем шаблон поста (blog/template_post.html)
if (file_exists($sourceDataDir . '/blog/template_post.html')) {
    @copy($sourceDataDir . '/blog/template_post.html', $targetDir . '/blog/template_post.html');
}

// Копируем шаблоны блога (NPBlog/main.html и settings.json)
if (file_exists($sourceDataDir . '/blog/templates/NPBlog/main.html')) {
    @copy($sourceDataDir . '/blog/templates/NPBlog/main.html', $targetDir . '/blog/templates/NPBlog/main.html');
}
if (file_exists($sourceDataDir . '/blog/templates/settings.json')) {
    @copy($sourceDataDir . '/blog/templates/settings.json', $targetDir . '/blog/templates/settings.json');
} else {
    file_put_contents($targetDir . '/blog/templates/settings.json', json_encode([
        'templates' => [
            [
                'id' => 'NPBlog',
                'name' => 'NPBlog',
                'active' => true
            ]
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Создаем пустой список статей blog/posts-meta.json
file_put_contents($targetDir . '/blog/posts-meta.json', json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Создаем пустой post_backgrounds.json
file_put_contents($targetDir . '/post_backgrounds.json', json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Создаем global-settings.json
file_put_contents($targetDir . '/global-settings.json', json_encode([
    'hidePoweredBy' => false
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Создаем blog-view-settings.json с названием блога
file_put_contents($targetDir . '/blog-view-settings.json', json_encode([
    'title' => $title
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Создаем blog.html на основе исходного шаблона
if (file_exists($sourceDataDir . '/blog.html')) {
    $blogHtml = file_get_contents($sourceDataDir . '/blog.html');
    // Заменяем title
    $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $blogHtml = preg_replace('/<title>.*?<\/title>/i', '<title>' . $escapedTitle . ' - Список статей</title>', $blogHtml, 1);
    // Заменяем h1 внутри header
    $blogHtml = preg_replace('/(<header[^>]*>\s*<h1>).*?(<\/h1>)/is', '$1' . $escapedTitle . '$2', $blogHtml, 1);
    file_put_contents($targetDir . '/blog.html', $blogHtml);
}

// Формируем путь для editor_settings.json
$realTarget = realpath($targetDir);
if ($realTarget) {
    $newBlogPath = str_replace('/', DIRECTORY_SEPARATOR, $realTarget);
} else {
    $newBlogPath = str_replace('/', DIRECTORY_SEPARATOR, $targetDir);
}

// Добавляем путь в editor_settings.json
$settingsFile = __DIR__ . '/editor_settings.json';
$settings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];

$existingPaths = isset($settings['blog_paths']) && is_array($settings['blog_paths']) ? $settings['blog_paths'] : [];
$normalizedNew = strtolower(str_replace('\\', '/', $newBlogPath));
$exists = false;
foreach ($existingPaths as $ep) {
    if (strtolower(str_replace('\\', '/', $ep)) === $normalizedNew) {
        $exists = true;
        break;
    }
}
if (!$exists) {
    $existingPaths[] = $newBlogPath;
}
$settings['blog_paths'] = array_values($existingPaths);

if ($makeActive) {
    $settings['active_blog_path'] = $newBlogPath;
    $settings['data_path'] = $newBlogPath;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['active_blog_path'] = $newBlogPath;
    }
}

safeWriteJson($settingsFile, $settings);

echo json_encode([
    'success' => true,
    'message' => 'Блог успешно создан',
    'path' => $newBlogPath,
    'folder' => $folder,
    'title' => $title,
    'make_active' => $makeActive
], JSON_UNESCAPED_UNICODE);
