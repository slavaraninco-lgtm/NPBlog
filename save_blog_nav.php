<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Invalid method']));
}

$action = $_POST['action'] ?? 'save';
$buttonsJson = $_POST['buttons'] ?? '[]';
$buttons = json_decode($buttonsJson, true);

if (!is_array($buttons)) {
    $buttons = [];
}

// Поиск и получение всех доступных путей к блогам
$editorSettingsFile = __DIR__ . '/editor_settings.json';
$editorSettings = file_exists($editorSettingsFile) ? (json_decode(file_get_contents($editorSettingsFile), true) ?: []) : [];
$blogPaths = isset($editorSettings['blog_paths']) ? $editorSettings['blog_paths'] : [];
if (empty($blogPaths)) {
    $blogPaths = [isset($editorSettings['data_path']) ? $editorSettings['data_path'] : 'data'];
}

// JS-код для рендеринга кнопок, который мы будем вставлять в blog.html
$jsInjection = <<<'JS'
                    // Кнопки перехода между блогами
                    const header = document.querySelector('header');
                    let navContainer = document.getElementById('cross-blog-nav');
                    if (settings.crossBlogNav && settings.crossBlogNav.length > 0) {
                        if (!navContainer && header) {
                            navContainer = document.createElement('div');
                            navContainer.id = 'cross-blog-nav';
                            navContainer.style.marginTop = '15px';
                            navContainer.style.display = 'flex';
                            navContainer.style.justifyContent = 'center';
                            navContainer.style.flexWrap = 'wrap';
                            navContainer.style.gap = '10px';
                            header.appendChild(navContainer);
                        }
                        if (navContainer) {
                            navContainer.innerHTML = '';
                            settings.crossBlogNav.forEach(btn => {
                                const a = document.createElement('a');
                                a.href = btn.url;
                                a.textContent = btn.text;
                                a.style.display = 'inline-block';
                                a.style.padding = '6px 12px';
                                a.style.background = 'transparent';
                                a.style.color = 'var(--text-color)';
                                a.style.border = '1px solid var(--border-color)';
                                a.style.textDecoration = 'none';
                                a.style.fontSize = '14px';
                                a.style.borderRadius = '4px';
                                a.style.transition = 'all 0.2s';
                                a.onmouseover = () => { a.style.background = 'var(--text-color)'; a.style.color = 'var(--bg-color)'; };
                                a.onmouseout = () => { a.style.background = 'transparent'; a.style.color = 'var(--text-color)'; };
                                navContainer.appendChild(a);
                            });
                        }
                    } else if (navContainer) {
                        navContainer.remove();
                    }
JS;

function processBlog($blogDir, $buttons, $jsInjection) {
    if (strpos($blogDir, '/') !== 0 && strpos($blogDir, ':\\') !== 1) {
        $blogDir = __DIR__ . '/' . ltrim($blogDir, '/');
    }
    $blogDir = rtrim($blogDir, '/\\') . '/';
    
    if (!is_dir($blogDir)) {
        return ['success' => false, 'message' => 'Папка блога не найдена: ' . $blogDir];
    }
    
    $blogHtmlPath = $blogDir . 'blog.html';
    $settingsPath = $blogDir . 'blog-view-settings.json';
    
    if (!file_exists($blogHtmlPath)) {
        return ['success' => false, 'message' => 'Файл blog.html не найден'];
    }
    
    $blogHtmlContent = file_get_contents($blogHtmlPath);
    $isStandard = (strpos($blogHtmlContent, 'function loadBlogViewSettings()') !== false) && (strpos($blogHtmlContent, '<header>') !== false);
    
    if (!$isStandard) {
        return ['success' => false, 'message' => 'Нестандартный шаблон blog.html', 'is_standard' => false];
    }
    
    // Внедряем JS, если его еще нет
    if (strpos($blogHtmlContent, 'cross-blog-nav') === false) {
        $pattern = '/(document\.body\.style\.backgroundImage\s*=\s*\'none\';\s*\})/s';
        if (preg_match($pattern, $blogHtmlContent)) {
            $blogHtmlContent = preg_replace($pattern, "$1\n" . $jsInjection, $blogHtmlContent);
            file_put_contents($blogHtmlPath, $blogHtmlContent);
        } else {
            return ['success' => false, 'message' => 'Не удалось найти точку вставки в blog.html', 'is_standard' => false];
        }
    }
    
    // Сохраняем настройки
    $viewSettings = file_exists($settingsPath) ? (json_decode(file_get_contents($settingsPath), true) ?: []) : [];
    $viewSettings['crossBlogNav'] = $buttons;
    file_put_contents($settingsPath, json_encode($viewSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return ['success' => true, 'is_standard' => true];
}

if ($action === 'save') {
    $activeBlogPath = isset($_SESSION['active_blog_path']) ? $_SESSION['active_blog_path'] : (isset($editorSettings['active_blog_path']) ? $editorSettings['active_blog_path'] : 'data');
    $result = processBlog($activeBlogPath, $buttons, $jsInjection);
    echo json_encode($result);
} else if ($action === 'apply_all') {
    $results = [];
    $successCount = 0;
    foreach ($blogPaths as $path) {
        $res = processBlog($path, $buttons, $jsInjection);
        $results[$path] = $res;
        if ($res['success']) {
            $successCount++;
        }
    }
    echo json_encode(['success' => true, 'updated_count' => $successCount, 'details' => $results]);
} else if ($action === 'check') {
    $activeBlogPath = isset($_SESSION['active_blog_path']) ? $_SESSION['active_blog_path'] : (isset($editorSettings['active_blog_path']) ? $editorSettings['active_blog_path'] : 'data');
    if (strpos($activeBlogPath, '/') !== 0 && strpos($activeBlogPath, ':\\') !== 1) {
        $activeBlogPath = __DIR__ . '/' . ltrim($activeBlogPath, '/');
    }
    $activeBlogPath = rtrim($activeBlogPath, '/\\') . '/';
    $blogHtmlPath = $activeBlogPath . 'blog.html';
    
    if (!file_exists($blogHtmlPath)) {
        echo json_encode(['is_standard' => false, 'message' => 'blog.html не найден']);
        exit;
    }
    $blogHtmlContent = file_get_contents($blogHtmlPath);
    $isStandard = (strpos($blogHtmlContent, 'function loadBlogViewSettings()') !== false) && (strpos($blogHtmlContent, '<header>') !== false);
    
    $settingsPath = $activeBlogPath . 'blog-view-settings.json';
    $viewSettings = file_exists($settingsPath) ? (json_decode(file_get_contents($settingsPath), true) ?: []) : [];
    $currentButtons = isset($viewSettings['crossBlogNav']) ? $viewSettings['crossBlogNav'] : [];
    
    echo json_encode(['is_standard' => $isStandard, 'buttons' => $currentButtons]);
}

?>
