<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['history']) || !is_array($data['history'])) {
    echo json_encode(['success' => false, 'error' => 'Неверные данные']);
    exit;
}

$historyFile = 'history.json';

// Сохраняем историю в файл атомарно
$result = safeWriteJson($historyFile, $data);

if ($result === false) {
    echo json_encode(['success' => false, 'error' => 'Ошибка записи файла']);
} else {
    echo json_encode(['success' => true]);
}
