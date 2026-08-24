<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$includesDir = 'includes/';

if (!is_dir($includesDir)) {
    echo json_encode(['success' => true, 'files' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

// Загружаем метаданные для отображения оригинальных названий
$metaFile = $includesDir . 'includes-meta.json';
$meta = [];
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true) ?: [];
}

$files = glob($includesDir . '*.txt');
$fileList = [];

foreach ($files as $file) {
    $filename = basename($file);
    
    // Пропускаем файл метаданных
    if ($filename === 'includes-meta.json') continue;
    
    $displayName = isset($meta[$filename]) ? $meta[$filename] : pathinfo($filename, PATHINFO_FILENAME);
    
    $fileList[] = [
        'name' => $filename,
        'displayName' => $displayName
    ];
}

// Сортируем по имени с учетом UTF-8 кириллицы
usort($fileList, function($a, $b) {
    if (function_exists('mb_strcasecmp')) {
        return mb_strcasecmp($a['displayName'], $b['displayName'], 'UTF-8');
    }
    return strcasecmp(mb_strtolower($a['displayName'], 'UTF-8'), mb_strtolower($b['displayName'], 'UTF-8'));
});

echo json_encode(['success' => true, 'files' => $fileList], JSON_UNESCAPED_UNICODE);
