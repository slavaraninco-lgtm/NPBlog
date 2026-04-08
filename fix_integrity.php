<?php
header('Content-Type: application/json');

$fixed = [];
$errors = [];

// Исправляем save_post.php
if (file_exists('save_post.php')) {
    $content = file_get_contents('save_post.php');
    
    if (strpos($content, 'Powered by NPBlog') === false) {
        // Добавляем стили для powered-by
        $styleToAdd = '
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
';
        
        // Ищем место для вставки стилей (перед @media)
        if (preg_match('/@media \(max-width: 768px\) \{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $position = $matches[0][1];
            $content = substr_replace($content, $styleToAdd . "\n        ", $position, 0);
        }
        
        // Добавляем HTML элемент
        $htmlToAdd = "\n    \n    <div class=\"powered-by\">Powered by NPBlog</div>\n\n    <script>";
        $content = str_replace("\n    <script>", $htmlToAdd, $content);
        
        if (file_put_contents('save_post.php', $content)) {
            $fixed[] = 'save_post.php';
        } else {
            $errors[] = 'Не удалось записать изменения в save_post.php';
        }
    }
} else {
    $errors[] = 'Файл save_post.php не найден';
}

// Исправляем update_post.php
if (file_exists('update_post.php')) {
    $content = file_get_contents('update_post.php');
    
    if (strpos($content, 'Powered by NPBlog') === false) {
        // Добавляем стили для powered-by
        $styleToAdd = '
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
';
        
        // Ищем место для вставки стилей (перед @media)
        if (preg_match('/@media \(max-width: 768px\) \{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $position = $matches[0][1];
            $content = substr_replace($content, $styleToAdd . "\n        ", $position, 0);
        }
        
        // Добавляем HTML элемент
        $htmlToAdd = "\n    \n    <div class=\"powered-by\">Powered by NPBlog</div>\n\n    <script>";
        $content = str_replace("\n    <script>", $htmlToAdd, $content);
        
        if (file_put_contents('update_post.php', $content)) {
            $fixed[] = 'update_post.php';
        } else {
            $errors[] = 'Не удалось записать изменения в update_post.php';
        }
    }
}

// Исправляем data/blog.html
if (file_exists('data/blog.html')) {
    $content = file_get_contents('data/blog.html');
    
    if (strpos($content, 'Powered by NPBlog') === false) {
        // Добавляем стили для powered-by
        $styleToAdd = '
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
';
        
        // Ищем место для вставки стилей (перед @media)
        if (preg_match('/@media \(max-width: 768px\) \{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $position = $matches[0][1];
            $content = substr_replace($content, $styleToAdd . "\n        ", $position, 0);
        }
        
        // Добавляем HTML элемент
        $htmlToAdd = "\n\n    <div class=\"powered-by\">Powered by NPBlog</div>\n\n    <script>";
        $content = str_replace("\n    <script>", $htmlToAdd, $content);
        
        if (file_put_contents('data/blog.html', $content)) {
            $fixed[] = 'data/blog.html';
        } else {
            $errors[] = 'Не удалось записать изменения в data/blog.html';
        }
    }
} else {
    $errors[] = 'Файл data/blog.html не найден';
}

echo json_encode([
    'success' => count($errors) === 0,
    'fixed' => $fixed,
    'errors' => $errors
]);
