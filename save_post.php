<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'error' => 'Отсутствуют необходимые данные']);
    exit;
}

$allowedTags = '<b><i><u><s><sup><sub><h2><ul><li><a><p><br><img><pre><span><div><iframe><audio><source><center><details><summary><mark>';

$content = $data['content'];
$content = str_replace("\n", "<br>", $content);

// Определяем следующий ID на основе существующих файлов в папке data/blog
$blogDir = 'data/blog/';
if (!is_dir($blogDir)) {
    mkdir($blogDir, 0755, true);
}

$maxId = 0;
$files = glob($blogDir . 'post-*.html');
foreach ($files as $file) {
    if (preg_match('/post-(\d+)\.html$/', $file, $match)) {
        $id = intval($match[1]);
        if ($id > $maxId) {
            $maxId = $id;
        }
    }
}

$nextId = $maxId + 1;
$date = date('d.m.Y H:i');

// Создаем HTML файл статьи
$title = htmlspecialchars($data['title']);
$cleanContent = strip_tags($content, $allowedTags);

$articleHtml = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #000000;
            --border-color: #000000;
            --hover-bg: #000000;
            --hover-text: #ffffff;
        }
        
        [data-theme="dark"] {
            --bg-color: #000000;
            --text-color: #ffffff;
            --border-color: #ffffff;
            --hover-bg: #ffffff;
            --hover-text: #000000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            max-width: 920px;
            margin: 0 auto;
            padding: 20px;
            background-color: var(--bg-color);
            color: var(--text-color);
            transition: background-color 0.3s, color 0.3s;
            line-height: 1.6;
        }

        h1 {
            color: var(--text-color);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
            font-size: 2.5em;
            font-weight: bold;
        }

        .date {
            color: var(--text-color);
            font-size: 0.9em;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.7;
        }

        .content {
            margin-top: 20px;
            font-size: 1.1em;
            line-height: 1.8;
        }

        .content img {
            max-width: 100%;
            height: auto;
            border: 2px solid var(--border-color);
            margin: 20px 0;
        }

        .content h2 {
            margin-top: 30px;
            margin-bottom: 15px;
            color: var(--text-color);
            font-weight: bold;
        }

        .content p {
            margin-bottom: 15px;
        }

        .content ul, .content ol {
            margin: 15px 0;
            padding-left: 30px;
        }

        .content li {
            margin-bottom: 8px;
        }

        .content a {
            color: var(--text-color);
            text-decoration: none;
            border-bottom: 2px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .content a:hover {
            background: var(--hover-bg);
            color: var(--hover-text);
        }

        .back-link {
            display: inline-block;
            margin-top: 40px;
            padding: 12px 24px;
            background: var(--bg-color);
            color: var(--text-color);
            text-decoration: none;
            border: 2px solid var(--border-color);
            border-radius: 0;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .back-link:hover {
            background: var(--hover-bg);
            color: var(--hover-text);
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: var(--bg-color);
            color: var(--text-color);
            border: 2px solid var(--border-color);
            border-radius: 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            z-index: 100;
        }

        .theme-toggle:hover {
            background: var(--hover-bg);
            color: var(--hover-text);
        }

        .powered-by {
            position: fixed;
            bottom: 20px;
            left: 20px;
            font-size: 12px;
            color: var(--text-color);
            opacity: 0.4;
            transition: opacity 0.2s ease;
            z-index: 50;
        }

        .powered-by:hover {
            opacity: 0.7;
        }

        /* Модальное окно для просмотра изображений */
        .image-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .image-modal.show {
            display: flex;
        }
        
        .image-modal-container {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            cursor: grab;
        }
        
        .image-modal-container.dragging {
            cursor: grabbing;
        }
        
        .image-modal-content {
            position: absolute;
            max-width: none;
            max-height: none;
            object-fit: contain;
            transition: transform 0.3s ease;
            user-select: none;
            -webkit-user-drag: none;
            pointer-events: auto;
        }
        
        .image-modal-close {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            color: #fff;
            font-size: 28px;
            line-height: 44px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            z-index: 1001;
            padding: 0;
        }
        
        .image-modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .image-modal-toolbar {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            padding: 12px 20px;
            display: flex;
            gap: 8px;
            z-index: 1001;
            backdrop-filter: blur(10px);
        }
        
        .image-modal-btn {
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .image-modal-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }
        
        .image-modal-zoom-level {
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            padding: 0 12px;
            display: flex;
            align-items: center;
            min-width: 60px;
            justify-content: center;
        }
        
        .content img {
            cursor: zoom-in;
            transition: opacity 0.2s;
        }
        
        .content img:hover {
            opacity: 0.9;
        }

        /* Стили для сворачиваемого блока (spoiler) */
        .spoiler-block {
            background: var(--bg-color);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            margin: 15px 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .spoiler-title {
            display: block;
            padding: 12px 16px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            user-select: none;
            background: rgba(0, 0, 0, 0.03);
            transition: background 0.2s ease;
            list-style: none;
        }

        .spoiler-title::-webkit-details-marker {
            display: none;
        }

        .spoiler-title::before {
            content: "\25B6";
            display: inline-block;
            margin-right: 8px;
            transition: transform 0.3s ease;
            font-size: 12px;
        }

        .spoiler-block[open] .spoiler-title::before {
            transform: rotate(90deg);
        }

        .spoiler-title:hover {
            background: rgba(0, 0, 0, 0.06);
        }

        [data-theme="dark"] .spoiler-title {
            background: rgba(255, 255, 255, 0.03);
        }

        [data-theme="dark"] .spoiler-title:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .spoiler-content {
            padding: 16px;
            line-height: 1.6;
            animation: spoilerFadeIn 0.3s ease;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        @keyframes spoilerFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Стили для маркера (выделение текста) */
        mark {
            padding: 2px 4px;
            background: var(--marker-color, rgba(255, 235, 59, 0.45));
            color: inherit;
            font-weight: inherit;
            position: relative;
            display: inline;
        }

        /* Ровное выделение */
        mark[data-marker-style="straight"] {
            border-radius: 3px;
        }

        /* Кривое выделение (неровные края) */
        mark[data-marker-style="rough"] {
            border-radius: 255px 15px 225px 15px/15px 225px 15px 255px;
            transform: rotate(-1deg);
        }

        /* Зигзагообразное выделение */
        mark[data-marker-style="zigzag"] {
            border-radius: 15px 255px 15px 225px/225px 15px 255px 15px;
        }

        /* Волнистое выделение */
        mark[data-marker-style="wavy"] {
            border-radius: 225px 255px 15px 225px/255px 15px 225px 15px;
        }

        mark[data-marker-color="yellow"] {
            --marker-color: rgba(255, 235, 59, 0.45);
        }

        mark[data-marker-color="green"] {
            --marker-color: rgba(76, 175, 80, 0.45);
        }

        mark[data-marker-color="blue"] {
            --marker-color: rgba(33, 150, 243, 0.45);
        }

        mark[data-marker-color="orange"] {
            --marker-color: rgba(255, 152, 0, 0.5);
        }

        mark[data-marker-color="pink"] {
            --marker-color: rgba(233, 30, 99, 0.45);
        }

        mark[data-marker-color="purple"] {
            --marker-color: rgba(156, 39, 176, 0.45);
        }

        /* Контейнер для фона статьи */
        .content-wrapper {
            max-width: 920px;
            margin: -20px auto 0;
            padding: 40px 60px;
        }

        /* Обертка для подложки */
        .overlay-wrapper {
            padding: 40px;
            border-radius: 12px;
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            h1 {
                font-size: 2em;
            }
            
            .theme-toggle {
                position: static;
                margin: 10px auto 20px;
                display: block;
                width: fit-content;
            }
            
            .content-wrapper {
                margin: -15px auto 0;
                padding: 20px 30px;
            }
            
            .overlay-wrapper {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">🌓 Тема</button>
    
    <h1>$title</h1>
    <div class="date">📅 $date</div>
    <div class="content">$cleanContent</div>
    <a href="../../data/blog.html" class="back-link">← Назад к списку статей</a>
    
    <div class="powered-by">Powered by NPBlog</div>

    <!-- Модальное окно для просмотра изображений -->
    <div class="image-modal" id="imageModal">
        <button class="image-modal-close" onclick="closeImageModal()">×</button>
        <div class="image-modal-container" id="imageContainer">
            <img class="image-modal-content" id="modalImage" src="" alt="">
        </div>
        <div class="image-modal-toolbar">
            <button class="image-modal-btn" onclick="zoomOut()" title="Уменьшить">−</button>
            <div class="image-modal-zoom-level" id="zoomLevel">100%</div>
            <button class="image-modal-btn" onclick="zoomIn()" title="Увеличить">+</button>
            <button class="image-modal-btn" onclick="resetZoom()" title="Сбросить">⟲</button>
            <button class="image-modal-btn" onclick="downloadImage()" title="Скачать">⬇</button>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        }

        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        let currentZoom = 1;
        let currentImageSrc = '';
        let isDragging = false;
        let startX, startY, translateX = 0, translateY = 0;
        
        document.addEventListener('DOMContentLoaded', function() {
            const contentImages = document.querySelectorAll('.content img');
            contentImages.forEach(function(img) {
                img.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openImageModal(this.src);
                });
            });
        });
        
        function openImageModal(src) {
            currentImageSrc = src;
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            const container = document.getElementById('imageContainer');
            
            modalImg.src = src;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
            updateImageTransform();
            
            modalImg.onload = function() {
                centerImage();
            };
        }
        
        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
            currentZoom = 1;
            translateX = 0;
            translateY = 0;
        }
        
        function centerImage() {
            const modalImg = document.getElementById('modalImage');
            const container = document.getElementById('imageContainer');
            const containerRect = container.getBoundingClientRect();
            const imgWidth = modalImg.naturalWidth * currentZoom;
            const imgHeight = modalImg.naturalHeight * currentZoom;
            
            translateX = (containerRect.width - imgWidth) / 2;
            translateY = (containerRect.height - imgHeight) / 2;
            updateImageTransform();
        }
        
        function updateImageTransform() {
            const modalImg = document.getElementById('modalImage');
            const zoomLevel = document.getElementById('zoomLevel');
            modalImg.style.transform = 'translate(' + translateX + 'px, ' + translateY + 'px) scale(' + currentZoom + ')';
            zoomLevel.textContent = Math.round(currentZoom * 100) + '%';
        }
        
        function zoomIn() {
            if (currentZoom < 5) {
                currentZoom += 0.25;
                updateImageTransform();
            }
        }
        
        function zoomOut() {
            if (currentZoom > 0.25) {
                currentZoom -= 0.25;
                updateImageTransform();
            }
        }
        
        function resetZoom() {
            currentZoom = 1;
            centerImage();
        }
        
        function downloadImage() {
            const link = document.createElement('a');
            link.href = currentImageSrc;
            link.download = currentImageSrc.split('/').pop();
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        const container = document.getElementById('imageContainer');
        const modalImg = document.getElementById('modalImage');
        
        modalImg.addEventListener('dragstart', function(e) {
            e.preventDefault();
        });
        
        container.addEventListener('mousedown', function(e) {
            if (e.target === modalImg) {
                e.preventDefault();
                isDragging = true;
                startX = e.clientX - translateX;
                startY = e.clientY - translateY;
                container.classList.add('dragging');
            }
        });
        
        document.addEventListener('mousemove', function(e) {
            if (isDragging) {
                e.preventDefault();
                translateX = e.clientX - startX;
                translateY = e.clientY - startY;
                updateImageTransform();
            }
        });
        
        document.addEventListener('mouseup', function() {
            isDragging = false;
            container.classList.remove('dragging');
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
        
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });
    </script>
</body>
</html>
HTML;

// Сохраняем файл статьи
$filename = $blogDir . 'post-' . $nextId . '.html';
file_put_contents($filename, $articleHtml);

// Создаем бэкап новой статьи
$backupDir = 'data_backup/' . $nextId . '/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$backupNumber = 1;
$backupFilename = $backupDir . $nextId . '-' . $backupNumber . '.html';
file_put_contents($backupFilename, $articleHtml);

// Сохраняем метаданные бэкапа
$backupMetaFile = 'data_backup/backup-meta.json';
$backupMeta = [];
if (file_exists($backupMetaFile)) {
    $backupMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];
}

if (!isset($backupMeta[$nextId])) {
    $backupMeta[$nextId] = [
        'postId' => $nextId,
        'postTitle' => $data['title'],
        'backups' => []
    ];
}

$backupMeta[$nextId]['backups'][] = [
    'backupNumber' => $backupNumber,
    'filename' => $nextId . '-' . $backupNumber . '.html',
    'date' => $date,
    'title' => $data['title']
];

file_put_contents($backupMetaFile, json_encode($backupMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Обновляем posts-meta.json для статического хостинга
$metaFile = $blogDir . 'posts-meta.json';
$meta = [];
if (file_exists($metaFile)) {
    $meta = json_decode(file_get_contents($metaFile), true) ?: [];
}

$meta[] = [
    'id' => $nextId,
    'title' => $data['title'],
    'date' => $date,
    'filename' => 'post-' . $nextId . '.html'
];

file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Применяем глобальный фон если он установлен
$globalSettingsFile = 'data/global-settings.json';
if (file_exists($globalSettingsFile)) {
    $globalSettings = json_decode(file_get_contents($globalSettingsFile), true);
    if (isset($globalSettings['background'])) {
        $html = file_get_contents($filename);
        $bgFile = $globalSettings['background'];
        $bgMode = isset($globalSettings['backgroundMode']) ? $globalSettings['backgroundMode'] : 'cover';
        $bgScope = isset($globalSettings['backgroundScope']) ? $globalSettings['backgroundScope'] : 'content';
        
        // Формируем стиль в зависимости от режима
        if ($bgMode === 'repeat') {
            $backgroundStyle = "background-image: url('/data/backgrounds/{$bgFile}'); background-repeat: repeat; background-size: auto;";
        } elseif ($bgMode === 'contain') {
            $backgroundStyle = "background-image: url('/data/backgrounds/{$bgFile}'); background-size: contain; background-position: center; background-repeat: no-repeat;";
        } else { // cover
            $backgroundStyle = "background-image: url('/data/backgrounds/{$bgFile}'); background-size: cover; background-position: center;";
        }
        
        // Применяем фон в зависимости от области
        if ($bgScope === 'fullpage') {
            // Фон на всю страницу - применяем к body
            $backgroundStyle .= " background-attachment: fixed;";
            $html = preg_replace('/<body>/', '<body style="' . $backgroundStyle . '">', $html);
        } else {
            // Фон только для статьи - оборачиваем контент
            $backgroundStyle .= " min-height: 100vh; padding: 40px 60px;";
            $html = preg_replace(
                '/(<button class="theme-toggle".*?<\/button>\s*)(<h1>.*<a href="\.\.\/\.\.\/data\/blog\.html" class="back-link">.*?<\/a>)/s',
                '$1<div class="content-wrapper" style="' . $backgroundStyle . '">$2</div>',
                $html
            );
        }
        
        file_put_contents($filename, $html);
    }
}

echo json_encode(['success' => true, 'id' => $nextId]);
?>
