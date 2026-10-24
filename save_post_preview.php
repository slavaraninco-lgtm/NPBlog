<?php
/**
 * ==============================================================================
 * NPBlog - Сохранение/загрузка превью для статьи (карточка в blog.html)
 * ==============================================================================
 * Поддерживает:
 * - Загрузку файла изображения (upload)
 * - Установку прямой ссылки/пути (set_url)
 * - Удаление превью статьи (remove)
 * - Получение информации о превью (get)
 * Совместимо с PHP 7.4+
 * ==============================================================================
 */

ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/security_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $postId = isset($_POST['postId']) ? intval($_POST['postId']) : 0;
    
    // Если запрос был отправлен как raw JSON
    if ($action === '' && $postId === 0) {
        $rawInput = file_get_contents(php_sapi_name() === 'cli' ? 'php://stdin' : 'php://input');
        if (!empty($rawInput)) {
            $jsonData = json_decode($rawInput, true);
            if (is_array($jsonData)) {
                $action = isset($jsonData['action']) ? $jsonData['action'] : '';
                $postId = isset($jsonData['postId']) ? intval($jsonData['postId']) : 0;
            }
        }
    }
    
    if ($postId <= 0) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Не указан корректный ID статьи']);
        exit;
    }
    
    $blogDir = getDataPath('blog/');
    $metaFile = validateSafePath($blogDir, 'posts-meta.json');
    if (!file_exists($metaFile)) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Файл метаданных posts-meta.json не найден']);
        exit;
    }
    
    $meta = json_decode(file_get_contents($metaFile), true);
    if (!is_array($meta)) {
        $meta = [];
    }
    
    $postIndex = -1;
    foreach ($meta as $idx => $item) {
        if (isset($item['id']) && (int)$item['id'] === $postId) {
            $postIndex = $idx;
            break;
        }
    }
    
    if ($postIndex === -1) {
        if (ob_get_length()) ob_clean();
        echo json_encode(['success' => false, 'error' => 'Статья с ID ' . $postId . ' не найдена']);
        exit;
    }
    
    $currentPreview = isset($meta[$postIndex]['preview']) ? (string)$meta[$postIndex]['preview'] : '';
    
    if ($action === 'get') {
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'preview' => $currentPreview
        ]);
        exit;
    }
    
    if ($action === 'remove') {
        // Если предыдущее превью было загруженным файлом preview_{postId}_, удаляем его
        if (!empty($currentPreview) && strpos($currentPreview, 'uploads/') === 0) {
            $prevFilename = basename($currentPreview);
            if (strpos($prevFilename, 'preview_' . $postId . '_') === 0) {
                $prevFilePath = getDataPath('uploads/') . $prevFilename;
                if (file_exists($prevFilePath)) {
                    @unlink($prevFilePath);
                }
            }
        }
        
        unset($meta[$postIndex]['preview']);
        safeWriteJson($metaFile, $meta);
        
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'preview' => ''
        ]);
        exit;
    }
    
    if ($action === 'set_url') {
        $url = '';
        if (isset($_POST['url'])) {
            $url = trim((string)$_POST['url']);
        } elseif (isset($jsonData['url'])) {
            $url = trim((string)$jsonData['url']);
        }
        
        if (empty($url)) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'URL изображения не указан']);
            exit;
        }
        
        // Запрещаем опасные псевдопротоколы (javascript:, vbscript:, data:)
        if (preg_match('/^(?:javascript|data|vbscript):/i', $url)) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Недопустимый протокол ссылки']);
            exit;
        }
        
        $meta[$postIndex]['preview'] = $url;
        safeWriteJson($metaFile, $meta);
        
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'preview' => $url
        ]);
        exit;
    }
    
    if ($action === 'upload') {
        if (!isset($_FILES['preview'])) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Файл превью не был передан']);
            exit;
        }
        
        $file = $_FILES['preview'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'Размер файла превышает допустимый лимит (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE  => 'Размер файла превышает лимит HTML-формы',
                UPLOAD_ERR_PARTIAL    => 'Файл был загружен только частично',
                UPLOAD_ERR_NO_FILE    => 'Файл не был загружен',
                UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка на сервере',
                UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
                UPLOAD_ERR_EXTENSION  => 'Загрузка файла остановлена PHP-расширением',
            ];
            $errText = isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Ошибка загрузки файла (' . $file['error'] . ')';
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => $errText]);
            exit;
        }
        
        $uploadsDir = getDataPath('uploads');
        if (!file_exists($uploadsDir)) {
            if (!@mkdir($uploadsDir, 0777, true)) {
                if (ob_get_length()) ob_clean();
                echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для загрузок']);
                exit;
            }
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (!in_array($ext, $allowedExts)) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Недопустимый формат файла. Разрешены: ' . implode(', ', $allowedExts)]);
            exit;
        }
        
        // Проверка реального MIME-типа (если доступен finfo)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon'
            ];
            if (!in_array($mime, $allowedMimes)) {
                if (ob_get_length()) ob_clean();
                echo json_encode(['success' => false, 'error' => 'Файл не является допустимым изображением (MIME: ' . $mime . ')']);
                exit;
            }
        }
        
        $newFilename = 'preview_' . $postId . '_' . uniqid() . '.' . $ext;
        $targetPath = getDataPath('uploads/') . $newFilename;
        
        $moved = @move_uploaded_file($file['tmp_name'], $targetPath);
        if (!$moved) {
            if (@copy($file['tmp_name'], $targetPath)) {
                @unlink($file['tmp_name']);
                $moved = true;
            }
        }
        
        if (!$moved) {
            if (ob_get_length()) ob_clean();
            echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл изображения']);
            exit;
        }
        
        // Удаляем старое загруженное превью этой статьи, если оно было
        if (!empty($currentPreview) && strpos($currentPreview, 'uploads/') === 0) {
            $prevFilename = basename($currentPreview);
            if (strpos($prevFilename, 'preview_' . $postId . '_') === 0 && $prevFilename !== $newFilename) {
                $prevFilePath = getDataPath('uploads/') . $prevFilename;
                if (file_exists($prevFilePath)) {
                    @unlink($prevFilePath);
                }
            }
        }
        
        $previewRelative = 'uploads/' . $newFilename;
        $meta[$postIndex]['preview'] = $previewRelative;
        safeWriteJson($metaFile, $meta);
        
        if (ob_get_length()) ob_clean();
        echo json_encode([
            'success' => true,
            'preview' => $previewRelative,
            'filename' => $newFilename
        ]);
        exit;
    }
    
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => 'Неизвестное действие: ' . htmlspecialchars($action)]);
    exit;
    
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => 'Исключение: ' . $e->getMessage()]);
    exit;
}
