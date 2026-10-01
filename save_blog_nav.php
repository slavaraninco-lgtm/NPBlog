<?php
require_once __DIR__ . '/security_bootstrap.php';
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!headers_sent()) {
        http_response_code(405);
    }
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
$action = $_POST['action'] ?? ($jsonData['action'] ?? 'save');
if (isset($_POST['buttons'])) {
    $buttons = is_array($_POST['buttons']) ? $_POST['buttons'] : json_decode($_POST['buttons'], true);
} elseif (isset($jsonData['buttons'])) {
    $buttons = $jsonData['buttons'];
} else {
    $buttons = [];
}
if (!is_array($buttons)) {
    $buttons = [];
}

// Загрузка настроек редактора для получения путей блогов
$editorSettingsFile = __DIR__ . '/editor_settings.json';
$editorSettings = file_exists($editorSettingsFile) ? (json_decode(file_get_contents($editorSettingsFile), true) ?: []) : [];
$blogPaths = isset($editorSettings['blog_paths']) && is_array($editorSettings['blog_paths']) ? $editorSettings['blog_paths'] : [];
if (empty($blogPaths)) {
    $blogPaths = [isset($editorSettings['data_path']) && !empty($editorSettings['data_path']) ? $editorSettings['data_path'] : 'data'];
}

// Санитизация кнопок навигации против XSS
$sanitizedButtons = [];
foreach ($buttons as $btn) {
    if (!isset($btn['url']) || !isset($btn['text'])) continue;
    $url = trim((string)$btn['url']);
    $text = trim((string)$btn['text']);
    // Запрещаем опасные псевдопротоколы (javascript:, data:, vbscript:)
    if (preg_match('/^(?:javascript|data|vbscript):/i', $url)) {
        continue;
    }
    $sanitizedButtons[] = [
        'url' => $url,
        'text' => $text
    ];
}
$buttons = $sanitizedButtons;

// Нормализация путей с поддержкой Windows и Unix
if (!function_exists('normalizeBlogDir')) {
    function normalizeBlogDir($path) {
        $path = trim((string)$path);
        if ($path === '') return '';
        $path = str_replace('\\', '/', $path);
        $isAbsolute = (strpos($path, '/') === 0) || (strlen($path) >= 2 && $path[1] === ':');
        if (!$isAbsolute) {
            $path = str_replace('\\', '/', __DIR__) . '/' . ltrim($path, '/');
        }
        return rtrim($path, '/') . '/';
    }
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
                                const safeUrl = String(btn.url || '').trim();
                                if (/^(?:javascript|data|vbscript):/i.test(safeUrl)) {
                                    return;
                                }
                                const a = document.createElement('a');
                                a.href = safeUrl;
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

if (!function_exists('processBlog')) {
    function processBlog($blogDir, $buttons, $jsInjection) {
        $blogDir = normalizeBlogDir($blogDir);
        if ($blogDir === '' || !is_dir($blogDir)) {
            return ['success' => false, 'message' => 'Папка блога не найдена: ' . $blogDir];
        }
        
        $blogHtmlPath = $blogDir . 'blog.html';
        $settingsPath = $blogDir . 'blog-view-settings.json';
        
        if (!file_exists($blogHtmlPath)) {
            return ['success' => false, 'message' => 'Файл blog.html не найден: ' . $blogHtmlPath];
        }
        
        $blogHtmlContent = file_get_contents($blogHtmlPath);
        $isStandard = (strpos($blogHtmlContent, 'function loadBlogViewSettings()') !== false) && (strpos($blogHtmlContent, '<header>') !== false);
        
        if (!$isStandard) {
            return ['success' => false, 'message' => 'Нестандартный шаблон blog.html', 'is_standard' => false];
        }
        
        // Внедряем JS, если его еще нет
        if (strpos($blogHtmlContent, 'cross-blog-nav') === false) {
            $pattern = '/(document\.(?:documentElement|body)\.style\.backgroundImage\s*=\s*\'none\';[\s\S]*?\})/i';
            if (preg_match($pattern, $blogHtmlContent)) {
                $blogHtmlContent = preg_replace($pattern, "$1\n" . $jsInjection, $blogHtmlContent, 1);
                file_put_contents($blogHtmlPath, $blogHtmlContent);
            } elseif (preg_match('/(\}\s*catch\s*\(\s*error\s*\)\s*\{)/i', $blogHtmlContent)) {
                $blogHtmlContent = preg_replace('/(\}\s*catch\s*\(\s*error\s*\)\s*\{)/i', $jsInjection . "\n        $1", $blogHtmlContent, 1);
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
}

if ($action === 'save') {
    $activeBlogPath = isset($_SESSION['active_blog_path']) ? $_SESSION['active_blog_path'] : (isset($editorSettings['active_blog_path']) ? $editorSettings['active_blog_path'] : 'data');
    $result = processBlog($activeBlogPath, $buttons, $jsInjection);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} else if ($action === 'apply_all') {
    $results = [];
    $successCount = 0;
    foreach ($blogPaths as $path) {
        $res = processBlog($path, $buttons, $jsInjection);
        $results[$path] = $res;
        if (!empty($res['success'])) {
            $successCount++;
        }
    }
    echo json_encode(['success' => true, 'updated_count' => $successCount, 'details' => $results], JSON_UNESCAPED_UNICODE);
} else if ($action === 'check') {
    $activeBlogPath = isset($_SESSION['active_blog_path']) ? $_SESSION['active_blog_path'] : (isset($editorSettings['active_blog_path']) ? $editorSettings['active_blog_path'] : 'data');
    $activeBlogPath = normalizeBlogDir($activeBlogPath);
    $blogHtmlPath = $activeBlogPath . 'blog.html';
    
    if (!file_exists($blogHtmlPath)) {
        echo json_encode(['is_standard' => false, 'message' => 'blog.html не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $blogHtmlContent = file_get_contents($blogHtmlPath);
    $isStandard = (strpos($blogHtmlContent, 'function loadBlogViewSettings()') !== false) && (strpos($blogHtmlContent, '<header>') !== false);
    
    $settingsPath = $activeBlogPath . 'blog-view-settings.json';
    $viewSettings = file_exists($settingsPath) ? (json_decode(file_get_contents($settingsPath), true) ?: []) : [];
    $currentButtons = isset($viewSettings['crossBlogNav']) ? $viewSettings['crossBlogNav'] : [];
    
    echo json_encode(['is_standard' => $isStandard, 'buttons' => $currentButtons], JSON_UNESCAPED_UNICODE);
}
