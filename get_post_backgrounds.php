<?php
header('Content-Type: application/json');

require_once 'background_functions.php';

$postId = isset($_GET['postId']) ? intval($_GET['postId']) : null;

if ($postId) {
    // Получаем настройки для конкретной статьи
    $settings = getPostBackground($postId);
    
    // Проверяем существование файла фона если он указан
    if ($settings && isset($settings['background'])) {
        $bgFile = 'data/backgrounds/' . $settings['background'];
        if (!file_exists($bgFile)) {
            // Файл не существует, удаляем запись о фоне
            unset($settings['background']);
            unset($settings['backgroundMode']);
            unset($settings['backgroundScope']);
            
            // Если остались только настройки подложки или ничего не осталось
            if (empty($settings)) {
                removePostBackground($postId);
                $settings = null;
            } else {
                setPostBackground($postId, $settings);
            }
        }
    }
    
    echo json_encode(['success' => true, 'settings' => $settings]);
} else {
    // Получаем все настройки
    $backgrounds = loadBackgrounds();
    
    // Проверяем существование файлов для всех фонов
    foreach ($backgrounds as $id => $settings) {
        if (isset($settings['background'])) {
            $bgFile = 'data/backgrounds/' . $settings['background'];
            if (!file_exists($bgFile)) {
                // Файл не существует, удаляем запись о фоне
                unset($backgrounds[$id]['background']);
                unset($backgrounds[$id]['backgroundMode']);
                unset($backgrounds[$id]['backgroundScope']);
                
                // Если остались только настройки подложки или ничего не осталось
                if (empty($backgrounds[$id])) {
                    unset($backgrounds[$id]);
                }
            }
        }
    }
    
    // Сохраняем очищенные настройки
    saveBackgrounds($backgrounds);
    
    echo json_encode(['success' => true, 'backgrounds' => $backgrounds]);
}
?>
