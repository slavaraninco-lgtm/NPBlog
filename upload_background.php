<?php
header('Content-Type: application/json');

require_once 'background_functions.php';

if (!isset($_FILES['background']) || !isset($_POST['postId'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$postId = intval($_POST['postId']);
$file = $_FILES['background'];
$mode = isset($_POST['mode']) ? $_POST['mode'] : 'cover';
$scope = isset($_POST['scope']) ? $_POST['scope'] : 'content';

// Проверяем режим отображения
$allowedModes = ['cover', 'contain', 'repeat'];
if (!in_array($mode, $allowedModes)) {
    $mode = 'cover';
}

// Проверяем область фона
$allowedScopes = ['content', 'fullpage'];
if (!in_array($scope, $allowedScopes)) {
    $scope = 'content';
}

// Проверяем тип файла
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла']);
    exit;
}

// Создаем папку backgrounds если её нет
$backgroundsDir = 'data/backgrounds/';
if (!is_dir($backgroundsDir)) {
    mkdir($backgroundsDir, 0755, true);
}

// Генерируем имя файла
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'bg-' . $postId . '.' . $extension;
$filepath = $backgroundsDir . $filename;

// Удаляем старый фон если есть
$oldFiles = glob($backgroundsDir . 'bg-' . $postId . '.*');
foreach ($oldFiles as $oldFile) {
    unlink($oldFile);
}

// Загружаем новый файл
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Сохраняем настройки в post_backgrounds.json
    $bgSettings = [
        'background' => $filename,
        'backgroundMode' => $mode,
        'backgroundScope' => $scope
    ];
    
    // Сохраняем существующие настройки подложки если есть
    $existingSettings = getPostBackground($postId);
    if ($existingSettings) {
        if (isset($existingSettings['overlayEnabled'])) {
            $bgSettings['overlayEnabled'] = $existingSettings['overlayEnabled'];
        }
        if (isset($existingSettings['overlayColor'])) {
            $bgSettings['overlayColor'] = $existingSettings['overlayColor'];
        }
        if (isset($existingSettings['overlayOpacity'])) {
            $bgSettings['overlayOpacity'] = $existingSettings['overlayOpacity'];
        }
    }
    
    setPostBackground($postId, $bgSettings);
    
    // Применяем фон к HTML файлу статьи
    $metaFile = 'data/blog/posts-meta.json';
    if (file_exists($metaFile)) {
        $meta = json_decode(file_get_contents($metaFile), true);
        
        foreach ($meta as $post) {
            if ($post['id'] == $postId && isset($post['filename'])) {
                $htmlFile = 'data/blog/' . $post['filename'];
                applyBackgroundToHtml($htmlFile, $bgSettings);
                break;
            }
        }
    }
    
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
}
?>
