<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/templates_helper.php';

if (!isset($_FILES['template_file'])) {
    echo json_encode(['success' => false, 'error' => 'Файл не был передан']);
    exit;
}

$uploadedFile = $_FILES['template_file'];

if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Размер файла превышает допустимый лимит (upload_max_filesize в php.ini)',
        UPLOAD_ERR_FORM_SIZE  => 'Размер файла превышает лимит HTML-формы',
        UPLOAD_ERR_PARTIAL    => 'Файл был загружен только частично',
        UPLOAD_ERR_NO_FILE    => 'Файл не был загружен',
        UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка на сервере',
        UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск',
        UPLOAD_ERR_EXTENSION  => 'Загрузка файла остановлена PHP-расширением',
    ];
    $errorMsg = isset($uploadErrors[$uploadedFile['error']]) ? $uploadErrors[$uploadedFile['error']] : 'Ошибка загрузки шаблона (' . $uploadedFile['error'] . ')';
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

$filename = $uploadedFile['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if ($ext !== 'html' && $ext !== 'htm' && $ext !== 'zip') {
    echo json_encode(['success' => false, 'error' => 'Разрешено загружать только файлы .html или .zip']);
    exit;
}

$templatesDir = getDataPath('blog/templates/');

if (!is_dir($templatesDir)) {
    if (!@mkdir($templatesDir, 0777, true)) {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для шаблонов. Проверьте права доступа.']);
        exit;
    }
    @chmod($templatesDir, 0777);
}

if (!is_writable($templatesDir)) {
    echo json_encode(['success' => false, 'error' => 'Директория для шаблонов недоступна для записи.']);
    exit;
}

initTemplatesSystem();

// Generate unique name for the template directory/key
$baseName = pathinfo($filename, PATHINFO_FILENAME);
$cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $baseName);
if (empty($cleanName)) {
    $cleanName = 'custom_' . time();
}

// Avoid overwriting system templates
if ($cleanName === 'main' || $cleanName === 'NPBlog') {
    $cleanName = 'theme_' . time();
}

// Ensure unique folder name
$finalName = $cleanName;
$counter = 1;
while (is_dir($templatesDir . $finalName)) {
    $finalName = $cleanName . '_' . $counter;
    $counter++;
}

$destSubdir = $templatesDir . $finalName . '/';
if (!@mkdir($destSubdir, 0777, true)) {
    echo json_encode(['success' => false, 'error' => 'Не удалось создать папку для шаблона']);
    exit;
}
@chmod($destSubdir, 0777);

// Helper function for recursive deletion in case of errors
function deleteUploadedTemplateDir($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        $path = rtrim($dir, '/\\') . '/' . $file;
        (is_dir($path)) ? deleteUploadedTemplateDir($path) : @unlink($path);
    }
    return @rmdir($dir);
}

// Helper to find the first html file recursively
function findHtmlFileInDir($dir) {
    if (!is_dir($dir)) return null;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        $path = rtrim($dir, '/\\') . '/' . $file;
        if (is_dir($path)) {
            $found = findHtmlFileInDir($path);
            if ($found) return $found;
        } else {
            $fileExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($fileExt === 'html' || $fileExt === 'htm') {
                return $path;
            }
        }
    }
    return null;
}

$templatePathInSettings = '';

if ($ext === 'zip') {
    // Unpack ZIP with strict security validation
    $zip = new ZipArchive;
    if ($zip->open($uploadedFile['tmp_name']) === TRUE) {
        $realDest = realpath($destSubdir);
        if (!$realDest) {
            $realDest = str_replace('\\', '/', $destSubdir);
        } else {
            $realDest = str_replace('\\', '/', $realDest);
        }
        $realDest = rtrim($realDest, '/') . '/';

        $blockedExts = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar', 'inc', 'pht', 'cgi', 'pl', 'py', 'jsp', 'asp', 'aspx', 'exe', 'bat', 'sh', 'cmd', 'htaccess', 'htpasswd'];
        
        // 1. First pass: Validate ALL files before extracting anything
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            $normalized = str_replace('\\', '/', $entryName);
            
            // Check for directory traversal / Zip Slip
            if (strpos($normalized, '../') !== false || strpos($normalized, '/..') !== false || strpos($normalized, '..') === 0 || strpos($normalized, '/') === 0) {
                $zip->close();
                deleteUploadedTemplateDir($destSubdir);
                echo json_encode(['success' => false, 'error' => 'Архив содержит недопустимые пути к файлам (Zip Slip обнаружен)']);
                exit;
            }
            
            $entryExt = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
            if (in_array($entryExt, $blockedExts)) {
                $zip->close();
                deleteUploadedTemplateDir($destSubdir);
                echo json_encode(['success' => false, 'error' => 'Архив содержит запрещенные исполняемые файлы (.' . $entryExt . ')']);
                exit;
            }
        }
        
        // 2. Safe extraction
        $zip->extractTo($destSubdir);
        $zip->close();
    } else {
        deleteUploadedTemplateDir($destSubdir);
        echo json_encode(['success' => false, 'error' => 'Не удалось открыть zip архив']);
        exit;
    }
    
    // Find template html file inside
    $htmlFile = findHtmlFileInDir($destSubdir);
    if (!$htmlFile) {
        deleteUploadedTemplateDir($destSubdir);
        echo json_encode(['success' => false, 'error' => 'В архиве не найден файл шаблона .html']);
        exit;
    }
    
    $code = @file_get_contents($htmlFile);
    if ($code === false) {
        deleteUploadedTemplateDir($destSubdir);
        echo json_encode(['success' => false, 'error' => 'Не удалось прочитать файл шаблона из архива']);
        exit;
    }
    
    // Validate placeholders
    $missingPlaceholders = validateTemplateCode($code);
    if (!empty($missingPlaceholders)) {
        deleteUploadedTemplateDir($destSubdir);
        echo json_encode([
            'success' => false,
            'error' => 'В файле шаблона отсутствуют необходимые плейсхолдеры: ' . implode(', ', $missingPlaceholders),
            'missing' => $missingPlaceholders
        ]);
        exit;
    }
    
    // Compute path relative to templates root
    $relPath = ltrim(substr($htmlFile, strlen($templatesDir)), '/\\');
    $relPath = str_replace('\\', '/', $relPath);
    $relPath = preg_replace('#/+#', '/', $relPath);
    $templatePathInSettings = $relPath;
    
    // Rewrite paths physically in the HTML file
    $rewrittenCode = rewriteTemplateRelativePaths($code, $htmlFile);
    @file_put_contents($htmlFile, $rewrittenCode);
} else {
    // Regular HTML file
    $code = @file_get_contents($uploadedFile['tmp_name']);
    if ($code === false) {
        deleteUploadedTemplateDir($destSubdir);
        echo json_encode(['success' => false, 'error' => 'Не удалось прочитать загруженный файл']);
        exit;
    }
    
    // Validate placeholders
    $missingPlaceholders = validateTemplateCode($code);
    if (!empty($missingPlaceholders)) {
        deleteUploadedTemplateDir($destSubdir);
        echo json_encode([
            'success' => false,
            'error' => 'В загружаемом шаблоне отсутствуют необходимые плейсхолдеры: ' . implode(', ', $missingPlaceholders),
            'missing' => $missingPlaceholders
        ]);
        exit;
    }
    
    // Save to templates/$finalName/$finalName.html
    $destFile = $destSubdir . $finalName . '.html';
    if (!@move_uploaded_file($uploadedFile['tmp_name'], $destFile)) {
        deleteUploadedTemplateDir($destSubdir);
        echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл шаблона']);
        exit;
    }
    @chmod($destFile, 0666);
    
    $templatePathInSettings = $finalName . '/' . $finalName . '.html';
    
    // Rewrite paths physically in the HTML file
    $rewrittenCode = rewriteTemplateRelativePaths($code, $destFile);
    @file_put_contents($destFile, $rewrittenCode);
}

// Update settings.json
$settingsFile = $templatesDir . 'settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(@file_get_contents($settingsFile), true) ?: [];
}

if (!isset($settings['templates'])) {
    $settings['templates'] = [];
}

$settings['templates'][$finalName] = [
    'title' => htmlspecialchars($baseName),
    'description' => 'Пользовательский шаблон, загруженный ' . date('d.m.Y H:i'),
    'is_system' => false,
    'path' => $templatePathInSettings
];

if (@file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить настройки шаблонов']);
    exit;
}
@chmod($settingsFile, 0666);

echo json_encode(['success' => true, 'name' => $finalName]);
?>
