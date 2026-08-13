<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// Защищенные файлы и папки, которые НЕ должны обновляться
$protectedPaths = [
    getDataPath('blog/'),
    getDataPath('fonts/'),
    getDataPath('images/'),
    getDataPath('audio/'),
    getDataPath('video/'),
    getDataPath('files/'),
    'data_backup/',
    'editor_backup/',
    getDataPath('global-settings.json'),
    'editor_settings.json',
    'post_backgrounds.json',
    'blog-view-settings.json',
    'appearance_settings.json',
    'version.json' // version.json is updated separately
];

function isProtected($path) {
    global $protectedPaths;
    
    // Специальное исключение: JS и CSS файлы (скрипты/стили) должны обновляться всегда
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'js' || $ext === 'css') {
        return false;
    }
    
    // Специальное исключение: data/blog.html должен обновляться
    if ($path === getDataPath('blog.html')) {
        return false;
    }
    
    // Защищаем все json файлы (метаданные, история, настройки и т.д.)
    if (str_ends_with(strtolower($path), '.json')) {
        return true;
    }
    
    foreach ($protectedPaths as $protected) {
        // Если это папка
        if (str_ends_with($protected, '/')) {
            if (str_starts_with($path, $protected)) {
                return true;
            }
        } else {
            // Если это конкретный файл
            if ($path === $protected) {
                return true;
            }
        }
    }
    return false;
}

function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return true;
    $files = array_diff(scandir($dirPath), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dirPath/$file")) ? deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
    }
    return rmdir($dirPath);
}

if ($action === 'preview') {
    if (!isset($_FILES['updateFile'])) {
        echo json_encode(['success' => false, 'error' => 'Файл не передан']);
        exit;
    }
    
    $file = $_FILES['updateFile'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
        exit;
    }
    
    $zip = new ZipArchive;
    if ($zip->open($file['tmp_name']) === TRUE) {
        $filesToReplace = [];
        $tmpToken = uniqid('update_');
        $tmpDir = 'sys_update_tmp/' . $tmpToken . '/';
        
        mkdir($tmpDir, 0755, true);
        $zip->extractTo($tmpDir);
        
        $newVersion = 'Unknown';
        
        // Рекурсивный обход распакованных файлов
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relativePath = substr($item->getPathname(), strlen($tmpDir));
                $filesToReplace[] = $relativePath;
            }
        }
        $zip->close();
        
        // Проверяем, есть ли корневая папка в архиве
        $rootFolder = '';
        $firstLevelFolders = [];
        foreach ($filesToReplace as $f) {
            $parts = explode('/', $f);
            if (count($parts) > 1) {
                $firstLevelFolders[$parts[0]] = true;
            }
        }
        
        if (count($firstLevelFolders) === 1) {
            $rootFolder = array_key_first($firstLevelFolders) . '/';
        }
        
        $filteredFiles = [];
        foreach ($filesToReplace as $f) {
            $actualPath = $f;
            if ($rootFolder && str_starts_with($f, $rootFolder)) {
                $actualPath = substr($f, strlen($rootFolder));
            }
            
            // Check version
            if ($actualPath === 'version.json') {
                $vData = @json_decode(file_get_contents($tmpDir . $f), true);
                if ($vData) {
                    if (!empty($vData['dev']) && ($vData['dev'] === true || $vData['dev'] === 'true')) {
                        $newVersion = 'dev';
                    } elseif (isset($vData['version'])) {
                        $newVersion = $vData['version'];
                    }
                }
            }
            
            if ($actualPath && !isProtected($actualPath)) {
                $filteredFiles[] = $actualPath;
            }
        }
        
        // Get current version
        $currentVersion = 'Unknown';
        if (file_exists('version.json')) {
            $currData = @json_decode(file_get_contents('version.json'), true);
            if ($currData) {
                if (!empty($currData['dev']) && ($currData['dev'] === true || $currData['dev'] === 'true')) {
                    $currentVersion = 'dev';
                } elseif (isset($currData['version'])) {
                    $currentVersion = $currData['version'];
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'files' => $filteredFiles,
            'token' => $tmpToken,
            'rootFolder' => $rootFolder,
            'currentVersion' => $currentVersion,
            'newVersion' => $newVersion
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось открыть ZIP архив']);
    }
} 
elseif ($action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    $rootFolder = $data['rootFolder'] ?? '';
    
    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'Нет токена обновления']);
        exit;
    }
    
    $tmpDir = 'sys_update_tmp/' . $token . '/';
    if (!is_dir($tmpDir)) {
        echo json_encode(['success' => false, 'error' => 'Временная папка не найдена']);
        exit;
    }
    
    // 1. Создаем бекап всего проекта
    $backupDir = 'editor_backup/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backupFilename = $backupDir . 'backup_' . date('Ymd_His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($backupFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $rootPath = realpath('./');
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);
                
                if (!str_starts_with($relativePath, 'sys_update_tmp/') && !str_starts_with($relativePath, 'editor_backup/') && !str_starts_with($relativePath, 'rollback_tmp/')) {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
        $zip->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать бекап']);
        exit;
    }
    
    // 2. Pre-flight checks and build atomic operations
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $operations = [];
    $rollbackTmpDir = 'rollback_tmp/' . $token . '/';
    mkdir($rollbackTmpDir, 0755, true);
    
    $newVersionData = null;
    $migrationFiles = [];
    
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $relativePath = substr($item->getPathname(), strlen($tmpDir));
            
            $actualPath = $relativePath;
            if ($rootFolder && str_starts_with($relativePath, $rootFolder)) {
                $actualPath = substr($relativePath, strlen($rootFolder));
            }
            
            if ($actualPath === 'version.json') {
                $newVersionData = json_decode(file_get_contents($item->getPathname()), true);
            }
            
            // Если это файл миграции
            if (str_starts_with($actualPath, 'migrations/') && str_ends_with($actualPath, '.php')) {
                $migrationFiles[] = $item->getPathname(); // Сначала скопируем, потом выполним
            }
            
            if ($actualPath && !isProtected($actualPath)) {
                $targetDir = dirname($actualPath);
                
                // Проверка прав на запись в целевую директорию
                if ($targetDir !== '.' && is_dir($targetDir) && !is_writable($targetDir)) {
                    deleteDir($rollbackTmpDir);
                    echo json_encode(['success' => false, 'error' => "Нет прав на запись в директорию: $targetDir"]);
                    exit;
                }
                
                // Проверка прав на перезапись существующего файла
                if (file_exists($actualPath) && !is_writable($actualPath)) {
                    deleteDir($rollbackTmpDir);
                    echo json_encode(['success' => false, 'error' => "Нет прав на изменение файла: $actualPath"]);
                    exit;
                }
                
                $operations[] = [
                    'source' => $item->getPathname(),
                    'target' => $actualPath,
                    'isNew' => !file_exists($actualPath)
                ];
            }
        }
    }
    
    // 3. Атомарное копирование
    $rollbackOperations = [];
    $updateFailed = false;
    $errorMsg = '';
    
    foreach ($operations as $op) {
        $targetDir = dirname($op['target']);
        if ($targetDir !== '.' && !is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true)) {
                $updateFailed = true;
                $errorMsg = "Не удалось создать директорию: $targetDir";
                break;
            }
        }
        
        if (!$op['isNew']) {
            // Копируем во временную папку отката
            $rollbackFile = $rollbackTmpDir . $op['target'];
            $rbDir = dirname($rollbackFile);
            if (!is_dir($rbDir)) @mkdir($rbDir, 0755, true);
            
            if (@copy($op['target'], $rollbackFile)) {
                $rollbackOperations[] = [
                    'rollback_source' => $rollbackFile,
                    'target' => $op['target'],
                    'was_created' => false
                ];
            } else {
                $updateFailed = true;
                $errorMsg = "Не удалось подготовить rollback для файла: {$op['target']}";
                break;
            }
        } else {
            $rollbackOperations[] = [
                'target' => $op['target'],
                'was_created' => true
            ];
        }
        
        // Применяем обновление
        if (!@copy($op['source'], $op['target'])) {
            $updateFailed = true;
            $errorMsg = "Не удалось скопировать файл: {$op['target']}";
            break;
        }
    }
    
    // 4. Rollback в случае ошибки
    if ($updateFailed) {
        foreach (array_reverse($rollbackOperations) as $rop) {
            if ($rop['was_created']) {
                @unlink($rop['target']);
            } else {
                @copy($rop['rollback_source'], $rop['target']);
            }
        }
        deleteDir($rollbackTmpDir);
        echo json_encode(['success' => false, 'error' => "Сбой при копировании. Система восстановлена. Ошибка: $errorMsg"]);
        exit;
    }
    
    // 5. Запуск миграций (выполняем файлы миграции из распакованного архива, так как они защищены и не скопировались бы, если они .php? Нет, они не защищены, если это .php файлы, но давайте выполним их из целевой папки migrations)
    $migrationErrors = [];
    if (is_dir('migrations')) {
        $migFiles = glob('migrations/*.php');
        if ($migFiles) {
            foreach ($migFiles as $mFile) {
                try {
                    // Используем буферизацию вывода, чтобы миграция не сломала JSON ответ
                    ob_start();
                    include_once $mFile;
                    ob_end_clean();
                } catch (Exception $e) {
                    $migrationErrors[] = "Ошибка в $mFile: " . $e->getMessage();
                }
            }
        }
    }
    
    // 6. Обновление version.json
    if ($newVersionData) {
        $verPayload = [
            'version' => $newVersionData['version'] ?? 'Unknown',
            'dev' => isset($newVersionData['dev']) ? (bool)$newVersionData['dev'] : false,
            'last_updated' => date('Y-m-d\TH:i:s\Z')
        ];
        file_put_contents('version.json', json_encode($verPayload, JSON_PRETTY_PRINT));
    }
    
    // Очистка
    deleteDir($tmpDir);
    deleteDir($rollbackTmpDir);
    
    $response = [
        'success' => true, 
        'updatedCount' => count($operations), 
        'backup' => $backupFilename
    ];
    
    if (!empty($migrationErrors)) {
        $response['migrationErrors'] = $migrationErrors;
    }
    
    echo json_encode($response);
}
?>
