<?php
/**
 * ==============================================================================
 * NPBlog - Сохранение настроек вида списка статей (blog-view-settings.json)
 * ==============================================================================
 * Поддерживает:
 * - Сохранение заголовка страницы (title)
 * - Сохранение режима отображения (layout: 'list' | 'grid')
 * - Применение для текущего блога (action: 'save')
 * - Применение для всех блогов (action: 'apply_all')
 * Совместимо с PHP 7.4+
 * ==============================================================================
 */

ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $rawInput = file_get_contents(php_sapi_name() === 'cli' ? 'php://stdin' : 'php://input');
    $jsonData = json_decode($rawInput, true);
    $data = is_array($jsonData) ? $jsonData : (is_array($_POST) && !empty($_POST) ? $_POST : []);

    $action = isset($data['action']) ? (string)$data['action'] : 'save';

    // Загрузка путей блогов из editor_settings.json
    $editorSettingsFile = __DIR__ . '/editor_settings.json';
    $editorSettings = file_exists($editorSettingsFile) ? (json_decode(file_get_contents($editorSettingsFile), true) ?: []) : [];
    $blogPaths = isset($editorSettings['blog_paths']) && is_array($editorSettings['blog_paths']) ? $editorSettings['blog_paths'] : [];
    if (empty($blogPaths)) {
        $blogPaths = [isset($editorSettings['data_path']) && !empty($editorSettings['data_path']) ? $editorSettings['data_path'] : 'data'];
    }

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

    function updateBlogViewSettingsFile($blogDir, $updates) {
        $blogDir = normalizeBlogDir($blogDir);
        if ($blogDir === '' || !is_dir($blogDir)) {
            return ['success' => false, 'error' => 'Папка блога не найдена: ' . $blogDir];
        }
        
        $settingsPath = $blogDir . 'blog-view-settings.json';
        $settings = [];
        if (file_exists($settingsPath)) {
            $decoded = json_decode(file_get_contents($settingsPath), true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        
        foreach ($updates as $key => $val) {
            if ($val !== null) {
                $settings[$key] = $val;
            }
        }
        
        $saved = safeWriteJson($settingsPath, $settings);
        if (!$saved) {
            return ['success' => false, 'error' => 'Не удалось записать ' . $settingsPath];
        }
        
        return ['success' => true];
    }

    $layout = null;
    if (isset($data['layout'])) {
        $layout = in_array($data['layout'], ['grid', 'list']) ? $data['layout'] : 'list';
    }

    $title = null;
    if (isset($data['title'])) {
        $title = trim((string)$data['title']);
    }

    if ($action === 'apply_all') {
        if ($layout === null && $title === null) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Нет данных для сохранения']);
            exit;
        }
        
        $successCount = 0;
        $details = [];
        $updates = [];
        if ($layout !== null) {
            $updates['layout'] = $layout;
        }
        if ($title !== null && !empty($data['apply_title_all'])) {
            $updates['title'] = $title;
        }
        
        foreach ($blogPaths as $path) {
            $res = updateBlogViewSettingsFile($path, $updates);
            $details[$path] = $res;
            if (!empty($res['success'])) {
                $successCount++;
            }
        }
        
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'updated_count' => $successCount,
            'details' => $details
        ]);
        exit;
    } else {
        // action === 'save' (текущий блог)
        $activeBlogPath = isset($_SESSION['active_blog_path']) ? $_SESSION['active_blog_path'] : (isset($editorSettings['active_blog_path']) ? $editorSettings['active_blog_path'] : 'data');
        
        $updates = [];
        if ($layout !== null) {
            $updates['layout'] = $layout;
        }
        if ($title !== null) {
            $updates['title'] = $title;
        }
        
        if (empty($updates)) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Нет данных для сохранения']);
            exit;
        }
        
        $res = updateBlogViewSettingsFile($activeBlogPath, $updates);
        if (ob_get_length()) ob_clean();
        echo json_encode($res);
        exit;
    }
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => 'Исключение: ' . $e->getMessage()]);
    exit;
}
