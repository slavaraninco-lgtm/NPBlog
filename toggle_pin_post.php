<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $rawInput = file_get_contents(php_sapi_name() === 'cli' ? 'php://stdin' : 'php://input');
    
    if (empty($rawInput)) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Пустой запрос']);
        exit;
    }
    
    $data = json_decode($rawInput, true);
    
    if (!isset($data['id'])) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'ID статьи не указан']);
        exit;
    }
    
    $postId = intval($data['id']);
    $blogDir = getDataPath('blog/');
    $metaFile = validateSafePath($blogDir, 'posts-meta.json');
    
    if (!file_exists($metaFile)) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Файл метаданных posts-meta.json не найден']);
        exit;
    }
    
    $meta = json_decode(file_get_contents($metaFile), true) ?: [];
    $foundIndex = -1;
    
    foreach ($meta as $idx => $item) {
        if ((int)($item['id'] ?? 0) === $postId) {
            $foundIndex = $idx;
            break;
        }
    }
    
    if ($foundIndex === -1) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Статья не найдена']);
        exit;
    }
    
    $newPinned = isset($data['pinned']) ? (bool)$data['pinned'] : empty($meta[$foundIndex]['pinned']);
    if ($newPinned) {
        $meta[$foundIndex]['pinned'] = true;
    } else {
        unset($meta[$foundIndex]['pinned']);
    }
    
    safeWriteJson($metaFile, $meta);
    
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => true,
        'id' => $postId,
        'pinned' => $newPinned,
        'message' => $newPinned ? 'Статья закреплена' : 'Статья откреплена'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
