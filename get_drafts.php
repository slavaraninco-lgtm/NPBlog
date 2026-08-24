<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$drafts = [];
$draftDir = getDataPath('drafts/');

$dirsToCheck = [$draftDir];
if (is_dir(__DIR__ . '/draft')) {
    $dirsToCheck[] = __DIR__ . '/draft/';
}

$seenFiles = [];

foreach ($dirsToCheck as $dir) {
    if (!is_dir($dir)) continue;
    $files = glob($dir . '*.json');
    if (!$files) continue;
    
    foreach ($files as $file) {
        $baseName = basename($file);
        if (isset($seenFiles[$baseName])) continue;
        $seenFiles[$baseName] = true;
        
        $content = file_get_contents($file);
        $draft = json_decode($content, true);
        
        if ($draft) {
            $draft['filename'] = $baseName;
            $drafts[] = $draft;
        }
    }
}

// Сортируем по времени (новые первыми)
usort($drafts, function($a, $b) {
    $tA = isset($a['timestamp']) ? (int)$a['timestamp'] : 0;
    $tB = isset($b['timestamp']) ? (int)$b['timestamp'] : 0;
    return $tB - $tA;
});

echo json_encode(['success' => true, 'drafts' => $drafts], JSON_UNESCAPED_UNICODE);
