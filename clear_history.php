<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$historyFile = 'history.json';

// Очищаем файл истории атомарно
$result = safeWriteJson($historyFile, ['history' => [], 'index' => -1]);

if ($result === false) {
    echo json_encode(['success' => false, 'error' => 'Ошибка очистки файла']);
} else {
    echo json_encode(['success' => true]);
}
