<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$autosaveDir = getAutosavePath();
$files = glob($autosaveDir . 'autosave_*.json');

if (empty($files)) {
    echo json_encode(['success' => true, 'message' => 'Нет файлов для удаления']);
    exit;
}

$deleted = 0;
$errors = 0;

foreach ($files as $file) {
    if (unlink($file)) {
        $deleted++;
    } else {
        $errors++;
    }
}

if ($errors > 0) {
    echo json_encode([
        'success' => false, 
        'error' => "Удалено: $deleted, Ошибок: $errors"
    ]);
} else {
    echo json_encode([
        'success' => true, 
        'message' => "Удалено файлов: $deleted"
    ]);
}
