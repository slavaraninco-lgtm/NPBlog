<?php
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json');

$uploadDir = getDataPath('files/');

if (!file_exists($uploadDir)) {
    if (!@mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать директорию для файлов. Проверьте права доступа.']);
        exit;
    }
}

if (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для файлов недоступна для записи.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename($file['name']);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        
        $allowedDocExts = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 
            'odt', 'ods', 'odp', 'csv', 'zip', 'rar', '7z', 'tar', 'gz'
        ];
        
        if (!in_array($extension, $allowedDocExts)) {
            echo json_encode(['success' => false, 'error' => 'Недопустимый формат документа']);
            exit;
        }
        
        // Очищаем имя файла от опасных символов
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.а-яА-ЯёЁ]/u', '_', $baseName);
        if (empty($safeName)) {
            $safeName = 'doc_' . uniqid();
        }
        $fileName = $safeName . '.' . $extension;
        
        $targetPath = $uploadDir . $fileName;
        
        // Проверяем, существует ли файл
        $counter = 1;
        $fileInfo = pathinfo($fileName);
        while (file_exists($targetPath)) {
            $fileName = $fileInfo['filename'] . '_' . $counter . '.' . $fileInfo['extension'];
            $targetPath = $uploadDir . $fileName;
            $counter++;
        }
        
        if (@move_uploaded_file($file['tmp_name'], $targetPath)) {
            echo json_encode([
                'success' => true,
                'fileName' => $fileName,
                'filePath' => $targetPath,
                'fileSize' => filesize($targetPath)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл']);
        }
    } else {
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
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Неверный запрос']);
}
?>
