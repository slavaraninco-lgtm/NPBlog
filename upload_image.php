<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

if (!isset($_FILES['image'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не был загружен']);
    exit;
}

$file = $_FILES['image'];

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
    $errorMsg = isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Ошибка загрузки изображения (' . $file['error'] . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$uploadsDir = getDataPath('uploads');
if (!file_exists($uploadsDir)) {
    if (!@mkdir($uploadsDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для загрузок. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($uploadsDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для загрузок недоступна для записи.']);
    exit;
}

$fileName = $file['name'];
$fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

$allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла. Разрешены: jpg, jpeg, png, gif, webp, svg']);
    exit;
}

$newFileName = uniqid() . '.' . $fileType;
$uploadPath = getDataPath('uploads/') . $newFileName;

if (@move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode([
        'success' => true,
        'url' => getDataUrl('uploads/' . $newFileName)
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка при сохранении файла']);
}
?>