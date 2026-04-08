<?php
header('Content-Type: application/json');

require_once 'background_functions.php';

if (!isset($_FILES['background'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствует файл']);
    exit;
}

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
$filename = 'global-bg.' . $extension;
$filepath = $backgroundsDir . $filename;

// Удаляем старый глобальный фон если есть
$oldFiles = glob($backgroundsDir . 'global-bg.*');
foreach ($oldFiles as $oldFile) {
    unlink($oldFile);
}

// Загружаем новый файл
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Сохраняем глобальные настройки
    $settingsFile = 'data/global-settings.json';
    $settings = [
        'background' => $filename,
        'backgroundMode' => $mode,
        'backgroundScope' => $scope
    ];
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Удаляем все индивидуальные фоны статей из post_backgrounds.json
    $backgrounds = loadBackgrounds();
    $newBackgrounds = [];
    
    foreach ($backgrounds as $postId => $bgSettings) {
        // Удаляем файлы индивидуальных фонов
        if (isset($bgSettings['background'])) {
            $bgFile = 'data/backgrounds/' . $bgSettings['background'];
            if (file_exists($bgFile) && strpos($bgSettings['background'], 'bg-') === 0) {
                unlink($bgFile);
            }
        }
        
        // Сохраняем только настройки подложки если они есть
        $newSettings = [];
        if (isset($bgSettings['overlayEnabled'])) {
            $newSettings['overlayEnabled'] = $bgSettings['overlayEnabled'];
        }
        if (isset($bgSettings['overlayColor'])) {
            $newSettings['overlayColor'] = $bgSettings['overlayColor'];
        }
        if (isset($bgSettings['overlayOpacity'])) {
            $newSettings['overlayOpacity'] = $bgSettings['overlayOpacity'];
        }
        
        if (!empty($newSettings)) {
            $newBackgrounds[$postId] = $newSettings;
        }
    }
    
    // Сохраняем очищенные настройки
    saveBackgrounds($newBackgrounds);
    
    // Применяем глобальный фон ко всем статьям
    $metaFile = 'data/blog/posts-meta.json';
    if (file_exists($metaFile)) {
        $meta = json_decode(file_get_contents($metaFile), true);
        
        foreach ($meta as $post) {
            if (isset($post['filename'])) {
                $htmlFile = 'data/blog/' . $post['filename'];
                if (file_exists($htmlFile)) {
                    // Получаем настройки подложки если есть
                    $postBgSettings = isset($newBackgrounds[$post['id']]) ? $newBackgrounds[$post['id']] : [];
                    
                    // Добавляем глобальный фон
                    $postBgSettings['background'] = $filename;
                    $postBgSettings['backgroundMode'] = $mode;
                    $postBgSettings['backgroundScope'] = $scope;
                    
                    applyBackgroundToHtml($htmlFile, $postBgSettings);
                }
            }
        }
    }
    
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
}
?>
