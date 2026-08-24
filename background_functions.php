<?php
require_once __DIR__ . '/security_bootstrap.php';
// Функции для работы с настройками фонов статей

function getBackgroundsFile() {
    return getDataPath('post_backgrounds.json');
}

function loadBackgrounds() {
    $file = getBackgroundsFile();
    if (!file_exists($file)) {
        return [];
    }
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveBackgrounds($backgrounds) {
    $file = getBackgroundsFile();
    safeWriteJson($file, $backgrounds);
}

function getPostBackground($postId) {
    $backgrounds = loadBackgrounds();
    return isset($backgrounds[$postId]) ? $backgrounds[$postId] : null;
}

function setPostBackground($postId, $settings) {
    $backgrounds = loadBackgrounds();
    $backgrounds[$postId] = $settings;
    saveBackgrounds($backgrounds);
}

function removePostBackground($postId) {
    $backgrounds = loadBackgrounds();
    if (isset($backgrounds[$postId])) {
        unset($backgrounds[$postId]);
        saveBackgrounds($backgrounds);
    }
}

function applyBackgroundToHtml($htmlFile, $bgSettings) {
    if (!file_exists($htmlFile)) {
        return false;
    }
    
    $html = file_get_contents($htmlFile);
    
    // Удаляем старый wrapper и стиль body
    $html = preg_replace(
        '/<div class="content-wrapper" style="[^"]*">(\s*<h1>.*<a href="(?:\.\.\/\.\.\/data\/|\.\.\/)blog\.html" class="back-link">.*?<\/a>)\s*<\/div>/s',
        '$1',
        $html
    );
    $html = preg_replace('/<body\s+style="[^"]*"\s*>/i', '<body>', $html);
    $html = preg_replace('/<body\s*>/i', '<body>', $html);
    
    // Удаляем старую подложку
    $html = preg_replace(
        '/<div class="overlay-wrapper" style="[^"]*">\s*(<h1>.*?<\/a>)\s*<\/div>/s',
        '$1',
        $html
    );
    
    // Применяем фон если есть
    if (isset($bgSettings['background'])) {
        $bgFile = $bgSettings['background'];
        $bgMode = isset($bgSettings['backgroundMode']) ? $bgSettings['backgroundMode'] : 'cover';
        $bgScope = isset($bgSettings['backgroundScope']) ? $bgSettings['backgroundScope'] : 'content';
        
        $bgPath = getDataPath("backgrounds/{$bgFile}");
        $bgVer = file_exists($bgPath) ? filemtime($bgPath) : time();
        $bgUrl = getDataUrl("backgrounds/{$bgFile}") . '?v=' . $bgVer;
        if ($bgMode === 'repeat') {
            $backgroundStyle = "background-image: url('{$bgUrl}'); background-repeat: repeat; background-size: auto;";
        } elseif ($bgMode === 'contain') {
            $backgroundStyle = "background-image: url('{$bgUrl}'); background-size: contain; background-position: center; background-repeat: no-repeat;";
        } else { // cover
            $backgroundStyle = "background-image: url('{$bgUrl}'); background-size: cover; background-position: center; background-repeat: no-repeat;";
        }
        
        // Применяем фон в зависимости от области
        if ($bgScope === 'fullpage') {
            $backgroundStyle .= " background-attachment: fixed;";
            $html = preg_replace('/<body>/', '<body style="' . $backgroundStyle . '">', $html);
        } else {
            $backgroundStyle .= " min-height: 100vh; padding: 40px 60px;";
            $html = preg_replace(
                '/(<button class="theme-toggle".*?<\/button>\s*)(<h1>.*<a href="(?:\.\.\/\.\.\/data\/|\.\.\/)blog\.html" class="back-link">.*?<\/a>)/s',
                '$1<div class="content-wrapper" style="' . $backgroundStyle . '">$2</div>',
                $html
            );
        }
    }
    
    // Применяем подложку если включена
    if (isset($bgSettings['overlayEnabled']) && $bgSettings['overlayEnabled']) {
        $overlayColor = $bgSettings['overlayColor'];
        $overlayOpacity = $bgSettings['overlayOpacity'];
        
        // Конвертируем hex в rgb
        $hex = str_replace('#', '', $overlayColor);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $alpha = $overlayOpacity / 100;
        
        $overlayStyle = "background: rgba($r, $g, $b, $alpha); padding: 40px; border-radius: 12px;";
        
        // Оборачиваем от h1 до кнопки "Назад" в div с подложкой (поддерживает как <div class="content">, так и <article ...>)
        $html = preg_replace(
            '/(<h1>.*?<\/h1>.*?<div class="date">.*?<\/div>.*?(?:<div class="content">|<article[^>]*class="[^"]*content[^"]*"[^>]*>).*?<\/(?:div|article)>.*?<a href="(?:\.\.\/\.\.\/data\/|\.\.\/)blog\.html" class="back-link">.*?<\/a>)/s',
            '<div class="overlay-wrapper" style="' . $overlayStyle . '">$1</div>',
            $html
        );
    }
    
    file_put_contents($htmlFile, $html, LOCK_EX);
    return true;
}

function cleanupMissingBackgrounds() {
    $backgrounds = loadBackgrounds();
    $changed = false;
    
    foreach ($backgrounds as $id => $settings) {
        if (isset($settings['background'])) {
            $bgFile = getDataPath('backgrounds/') . $settings['background'];
            if (!file_exists($bgFile)) {
                // Файл не существует, удаляем запись о фоне
                unset($backgrounds[$id]['background']);
                unset($backgrounds[$id]['backgroundMode']);
                unset($backgrounds[$id]['backgroundScope']);
                $changed = true;
                
                // Если остались только настройки подложки или ничего не осталось
                if (empty($backgrounds[$id])) {
                    unset($backgrounds[$id]);
                }
            }
        }
    }
    
    if ($changed) {
        saveBackgrounds($backgrounds);
    }
    
    return $changed;
}
?>
