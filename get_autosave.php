<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? $data['id'] : null;

$autosaveDir = getAutosavePath();

if ($id) {
    // Загружаем конкретное автосохранение по ID
    $filepath = validateSafePath($autosaveDir, 'autosave_' . $id . '.json');
    
    if (file_exists($filepath)) {
        $content = file_get_contents($filepath);
        $autosave = json_decode($content, true);
        
        if ($autosave) {
            echo json_encode(['success' => true, 'autosave' => $autosave]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ошибка чтения файла']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Автосохранение не найдено']);
    }
} else {
    // Загружаем последнее автосохранение (для обратной совместимости)
    $files = glob($autosaveDir . 'autosave_*.json');
    
    if (empty($files)) {
        echo json_encode(['success' => false, 'error' => 'Автосохранения не найдены']);
        exit;
    }
    
    // Сортируем по времени модификации (последний файл первым)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $filepath = $files[0];
    $content = file_get_contents($filepath);
    $autosave = json_decode($content, true);
    
    if ($autosave) {
        echo json_encode(['success' => true, 'autosave' => $autosave]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка чтения файла']);
    }
}
