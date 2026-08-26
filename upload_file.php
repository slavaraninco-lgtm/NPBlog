<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не выбран']);
    exit;
}

$file = $_FILES['file'];

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
    $errorMsg = isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Ошибка загрузки файла (' . $file['error'] . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$uploadDir = getDataPath('files/');

// Создаем папку если не существует
if (!file_exists($uploadDir)) {
    if (!@mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для файлов. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для файлов недоступна для записи.']);
    exit;
}

// Проверяем размер файла (максимум 50MB)
$maxSize = 50 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'Файл слишком большой (максимум 50MB)']);
    exit;
}

// Генерируем безопасное имя файла
$originalName = basename($file['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$baseName = pathinfo($originalName, PATHINFO_FILENAME);

// Список разрешенных расширений для общих файлов
$allowedExtensions = [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 
    'odt', 'ods', 'odp', 'csv', 'zip', 'rar', '7z', 'tar', 'gz', 'bz2',
    'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'mp3', 'wav', 'ogg', 
    'mp4', 'webm', 'flac', 'aac', 'm4a', 'mov', 'mkv', 'avi', 'json', 'xml'
];

// Черный список исполняемых расширений
$blockedExtensions = [
    'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar', 
    'inc', 'pht', 'htm', 'html', 'shtml', 'cgi', 'pl', 'py', 'jsp', 'asp', 
    'aspx', 'exe', 'bat', 'sh', 'cmd', 'htaccess', 'htpasswd', 'js', 'vbs'
];

if (in_array($extension, $blockedExtensions) || !in_array($extension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла. Загрузка исполняемых файлов запрещена.']);
    exit;
}

// Очищаем имя файла от опасных символов
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.а-яА-ЯёЁ]/u', '_', $baseName);
if (empty($safeName)) {
    $safeName = 'file_' . uniqid();
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
        'originalName' => $originalName,
        'size' => $file['size'],
        'path' => $targetPath
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при загрузке файла']);
}
?>
