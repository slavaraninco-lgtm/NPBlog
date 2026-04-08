<?php
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Не указано имя или контент'], JSON_UNESCAPED_UNICODE);
    exit;
}

$includesDir = 'includes/';
if (!is_dir($includesDir)) {
    mkdir($includesDir, 0755, true);
}

// Загружаем метаданные includes
$metaFile = $includesDir . 'includes-meta.json';
$meta = [];
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true) ?: [];
}

// Транслитерация кириллицы в латиницу
function transliterate($text) {
    $translitMap = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
        'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
        'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
        'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
        'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
        'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya'
    ];
    
    $text = strtr($text, $translitMap);
    // Заменяем пробелы на подчеркивания
    $text = str_replace(' ', '_', $text);
    // Удаляем все остальные недопустимые символы
    $text = preg_replace('/[^a-zA-Z0-9_\-]/', '', $text);
    
    return $text;
}

// Очищаем имя файла от недопустимых символов, оставляем кириллицу
$filename = transliterate($data['name']);
$filename = trim($filename);

if (empty($filename)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимое имя файла'], JSON_UNESCAPED_UNICODE);
    exit;
}

$filepath = $includesDir . $filename . '.txt';

// Проверяем, существует ли файл
if (file_exists($filepath)) {
    echo json_encode(['success' => false, 'error' => 'Файл с таким именем уже существует'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Сохраняем контент с правильной кодировкой
if (file_put_contents($filepath, $data['content'])) {
    // Сохраняем метаданные
    $meta[$filename . '.txt'] = $data['name'];
    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode(['success' => true, 'filename' => $filename . '.txt', 'displayName' => $data['name']], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении файла'], JSON_UNESCAPED_UNICODE);
}
