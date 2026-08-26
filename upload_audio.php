<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

if (!isset($_FILES['audio'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не был загружен']);
    exit;
}

$file = $_FILES['audio'];

// Проверяем код ошибки загрузки
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Размер файла превышает допустимый лимит (upload_max_filesize в php.ini)',
        UPLOAD_ERR_FORM_SIZE  => 'Размер файла превышает лимит HTML-формы',
        UPLOAD_ERR_PARTIAL    => 'Файл был загружен только частично',
        UPLOAD_ERR_NO_FILE    => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка на сервере',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION  => 'Загрузка файла остановлена PHP-расширением',
    ];
    $errorMsg = isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Ошибка загрузки аудио (' . $file['error'] . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$uploadDir = getDataPath('files/audio/');

// Создаем директорию если её нет
if (!file_exists($uploadDir)) {
    if (!@mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для аудио. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для аудио недоступна для записи.']);
    exit;
}

$allowedAudioExts = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'opus'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowedAudioExts)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимое расширение аудио файла. Разрешены только: ' . implode(', ', $allowedAudioExts)]);
    exit;
}

// Проверяем тип файла безопасно
$mimeType = '';
if (function_exists('finfo_open')) {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mimeType = @finfo_file($finfo, $file['tmp_name']);
        @finfo_close($finfo);
    }
}
if (empty($mimeType) && function_exists('mime_content_type')) {
    $mimeType = @mime_content_type($file['tmp_name']);
}

$allowedTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/m4a', 'audio/x-m4a', 'audio/aac', 'audio/flac', 'audio/opus', 'application/ogg', 'application/octet-stream'];
if (!empty($mimeType) && !in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла. Разрешены только аудио файлы.']);
    exit;
}

// Генерируем безопасное имя файла
$baseName = pathinfo($file['name'], PATHINFO_FILENAME);
// Удаляем только опасные символы, сохраняя кириллицу
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.а-яА-ЯёЁ]/u', '_', $baseName);
if (empty($safeName)) {
    $safeName = 'audio_' . uniqid();
}
$fileName = $safeName . '.' . $extension;

// Проверяем, существует ли файл с таким именем
$counter = 1;
while (file_exists($uploadDir . $fileName)) {
    $fileName = $safeName . '_' . $counter . '.' . $extension;
    $counter++;
}

$targetPath = $uploadDir . $fileName;

if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode([
        'success' => true,
        'filename' => $fileName,
        'path' => getDataUrl('files/audio/' . $fileName)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении файла']);
}
?>
