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
    getBackupPath(),
    getAutosavePath(),
    getEditorBackupPath(),
    'data_backup/',
    'autosave/',
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
    
    $normPath = ltrim(str_replace('\\', '/', $path), './');
    $lowerNormPath = strtolower($normPath);
    $ext = strtolower(pathinfo($normPath, PATHINFO_EXTENSION));
    
    // Специальное исключение: JS и CSS файлы (скрипты/стили) должны обновляться всегда
    if ($ext === 'js' || $ext === 'css') {
        return false;
    }
    
    // Специальное исключение: data/blog.html должен обновляться
    $dataBlogHtml = function_exists('getDataPath') ? strtolower(str_replace('\\', '/', getDataPath('blog.html'))) : 'data/blog.html';
    if ($lowerNormPath === 'data/blog.html' || $lowerNormPath === $dataBlogHtml) {
        return false;
    }
    
    // Специальное исключение: JSON файлы локализации в папке lang должны обновляться
    if (strpos($lowerNormPath, 'lang/') === 0 && $ext === 'json') {
        return false;
    }
    
    // Защищаем все остальные json файлы (метаданные, история, настройки и т.д.)
    if ($ext === 'json') {
        return true;
    }
    
    foreach ($protectedPaths as $protected) {
        $normProtected = strtolower(ltrim(str_replace('\\', '/', $protected), './'));
        // Если это папка
        if (substr($normProtected, -1) === '/') {
            if (strpos($lowerNormPath, $normProtected) === 0) {
                return true;
            }
        } else {
            // Если это конкретный файл
            if ($lowerNormPath === $normProtected) {
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

if ($action === 'get_version') {
    $currentVersion = 'Unknown';
    $isDev = false;
    if (file_exists('version.json')) {
        $currData = @json_decode(file_get_contents('version.json'), true);
        if ($currData) {
            if (!empty($currData['dev']) && ($currData['dev'] === true || $currData['dev'] === 'true')) {
                $currentVersion = 'dev';
                $isDev = true;
            } elseif (isset($currData['version'])) {
                $currentVersion = $currData['version'];
            }
        }
    }
    echo json_encode([
        'success' => true,
        'version' => $currentVersion,
        'dev' => $isDev
    ]);
    exit;
}

if ($action === 'get_changelog') {
    $currentVersion = 'Unknown';
    $isDev = false;
    $lastUpdated = '';
    $changelog = [];
    if (file_exists('version.json')) {
        $currData = @json_decode(file_get_contents('version.json'), true);
        if ($currData) {
            $isDev = !empty($currData['dev']) && ($currData['dev'] === true || $currData['dev'] === 'true');
            $currentVersion = $currData['version'] ?? ($isDev ? 'dev' : 'Unknown');
            $lastUpdated = $currData['last_updated'] ?? '';
            if (!empty($currData['changelog']) && is_array($currData['changelog'])) {
                $changelog = $currData['changelog'];
            }
        }
    }
    // Если в version.json нет changelog, попробуем поискать changelog.json
    if (empty($changelog) && file_exists('changelog.json')) {
        $clData = @json_decode(file_get_contents('changelog.json'), true);
        if ($clData && is_array($clData)) {
            $changelog = $clData;
        }
    }
    echo json_encode([
        'success' => true,
        'version' => $currentVersion,
        'dev' => $isDev,
        'last_updated' => $lastUpdated,
        'changelog' => $changelog
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'preview') {
    if (!class_exists('ZipArchive')) {
        echo json_encode([
            'success' => false,
            'error' => 'Расширение PHP ZipArchive (php-zip) не установлено на сервере. Установите его: apt install php-zip'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($_FILES['updateFile'])) {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        $postMaxSize = ini_get('post_max_size');
        if ($contentLength > 0) {
            echo json_encode([
                'success' => false,
                'error' => "Размер архива превышает лимит сервера post_max_size ($postMaxSize). Проверьте размер архива или увеличьте лимит в php.ini."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Файл архива не получен сервером'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $file = $_FILES['updateFile'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Размер файла превышает лимит upload_max_filesize (' . ini_get('upload_max_filesize') . ') в php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает лимит формы.',
            UPLOAD_ERR_PARTIAL => 'Файл был передан лишь частично.',
            UPLOAD_ERR_NO_FILE => 'Файл не был передан.',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка загрузок на сервере.',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось сохранить файл на диск сервера.',
            UPLOAD_ERR_EXTENSION => 'Загрузка файла прервана расширением PHP.'
        ];
        $msg = $uploadErrors[$file['error']] ?? ('Ошибка загрузки файла (' . $file['error'] . ')');
        echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $zip = new ZipArchive;
    $zipStatus = $zip->open($file['tmp_name']);
    if ($zipStatus === TRUE) {
        $filesToReplace = [];
        $tmpToken = uniqid('update_');
        $tmpDir = 'sys_update_tmp/' . $tmpToken . '/';
        
        if (!is_dir($tmpDir)) {
            if (!@mkdir($tmpDir, 0755, true)) {
                $zip->close();
                echo json_encode([
                    'success' => false,
                    'error' => 'Нет прав на создание временной папки sys_update_tmp на сервере. Проверьте права веб-сервера (chmod / chown).'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
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
                $relativePath = str_replace('\\', '/', $relativePath);
                $filesToReplace[] = $relativePath;
            }
        }
        $zip->close();
        
        // Проверяем, есть ли корневая папка-обёртка в архиве
        $rootFolder = '';
        $hasRootFiles = false;
        $firstLevelFolders = [];
        foreach ($filesToReplace as $f) {
            $parts = explode('/', $f);
            if (count($parts) > 1) {
                $firstLevelFolders[$parts[0]] = true;
            } else {
                $hasRootFiles = true;
            }
        }
        
        if (!$hasRootFiles && count($firstLevelFolders) === 1) {
            reset($firstLevelFolders);
            $rootFolder = key($firstLevelFolders) . '/';
        }
        
        $filteredFiles = [];
        foreach ($filesToReplace as $f) {
            $actualPath = $f;
            if ($rootFolder && strpos($f, $rootFolder) === 0) {
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
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $zipErrors = [
            ZipArchive::ER_NOZIP => 'Файл не является корректным ZIP-архивом.',
            ZipArchive::ER_INCONS => 'Архив повреждён (несогласованная структура).',
            ZipArchive::ER_CRC => 'Ошибка контрольной суммы CRC в архиве.',
            ZipArchive::ER_OPEN => 'Не удалось открыть файл архива.'
        ];
        $errText = $zipErrors[$zipStatus] ?? ('Код ошибки ZipArchive: ' . $zipStatus);
        echo json_encode(['success' => false, 'error' => 'Не удалось открыть ZIP архив: ' . $errText], JSON_UNESCAPED_UNICODE);
    }
} 
elseif ($action === 'update') {
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    if (!class_exists('ZipArchive')) {
        echo json_encode(['success' => false, 'error' => 'Расширение PHP ZipArchive не установлено на сервере.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    $rootFolder = $data['rootFolder'] ?? '';
    
    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'Нет токена обновления']);
        exit;
    }
    
    $tmpDir = 'sys_update_tmp/' . $token . '/';
    if (!is_dir($tmpDir)) {
        echo json_encode(['success' => false, 'error' => 'Временная папка с файлами обновления не найдена']);
        exit;
    }
    
    // 1. Создаем быстрый и надежный бэкап системы (исключая тяжелый .git, медиафайлы и архивы)
    $backupDir = getEditorBackupPath();
    if (!is_dir($backupDir)) {
        if (!@mkdir($backupDir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Нет прав на создание папки бэкапов: ' . $backupDir], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    $backupFilename = $backupDir . 'backup_' . date('Ymd_His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($backupFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $rootPath = realpath('./');
        $normEditorBackup = rtrim(str_replace('\\', '/', realpath($backupDir) ?: $backupDir), '/') . '/';
        $normDataBackup = rtrim(str_replace('\\', '/', realpath(getBackupPath()) ?: getBackupPath()), '/') . '/';
        $normAutosave = rtrim(str_replace('\\', '/', realpath(getAutosavePath()) ?: getAutosavePath()), '/') . '/';
        $backupDirs = [$normEditorBackup, $normDataBackup, $normAutosave];

        $dirIterator = new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $filterIterator = new RecursiveCallbackFilterIterator($dirIterator, function ($current) use ($rootPath, $backupDirs) {
            $rel = ltrim(str_replace('\\', '/', substr($current->getPathname(), strlen($rootPath))), '/');
            if ($current->isDir()) {
                $relDir = strtolower($rel) . '/';
                // Пропускаем папки, не относящиеся к системе или содержащие огромные данные
                if (strpos($relDir, '.git/') === 0 ||
                    strpos($relDir, '.github/') === 0 ||
                    strpos($relDir, '.vscode/') === 0 ||
                    strpos($relDir, '.idea/') === 0 ||
                    strpos($relDir, 'node_modules/') === 0 ||
                    strpos($relDir, 'sys_update_tmp/') === 0 ||
                    strpos($relDir, 'rollback_tmp/') === 0 ||
                    strpos($relDir, 'editor_backup/') === 0 ||
                    strpos($relDir, 'data_backup/') === 0 ||
                    strpos($relDir, 'autosave/') === 0 ||
                    strpos($relDir, 'data/images/') === 0 ||
                    strpos($relDir, 'data/video/') === 0 ||
                    strpos($relDir, 'data/audio/') === 0 ||
                    strpos($relDir, 'data/files/') === 0 ||
                    strpos($relDir, 'data/blog/') === 0) {
                    return false;
                }
                return true;
            }
            $relLower = strtolower($rel);
            if (substr($relLower, -4) === '.zip') return false;
            foreach ($backupDirs as $bDir) {
                if (!empty($bDir) && strpos(str_replace('\\', '/', $current->getPathname()), $bDir) === 0) {
                    return false;
                }
            }
            return true;
        });

        $files = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать файл бэкапа: ' . $backupFilename], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 2. Pre-flight checks and build atomic operations
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $operations = [];
    $rollbackTmpDir = 'rollback_tmp/' . $token . '/';
    if (!is_dir($rollbackTmpDir)) {
        @mkdir($rollbackTmpDir, 0755, true);
    }
    
    $newVersionData = null;
    $migrationFiles = [];
    
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $relativePath = substr($item->getPathname(), strlen($tmpDir));
            $relativePath = str_replace('\\', '/', $relativePath);
            
            $actualPath = $relativePath;
            if ($rootFolder && strpos($relativePath, $rootFolder) === 0) {
                $actualPath = substr($relativePath, strlen($rootFolder));
            }
            
            if ($actualPath === 'version.json') {
                $newVersionData = json_decode(file_get_contents($item->getPathname()), true);
            }
            
            // Если это файл миграции
            if (strpos($actualPath, 'migrations/') === 0 && substr($actualPath, -4) === '.php') {
                $migrationFiles[] = $item->getPathname();
            }
            
            if ($actualPath && !isProtected($actualPath)) {
                $targetDir = dirname($actualPath);
                
                // Проверка прав на запись в целевую директорию
                if ($targetDir !== '.' && is_dir($targetDir) && !is_writable($targetDir)) {
                    deleteDir($rollbackTmpDir);
                    echo json_encode(['success' => false, 'error' => "Нет прав на запись в директорию: $targetDir (проверьте chown/chmod на сервере)"], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                // Проверка прав на перезапись существующего файла
                if (file_exists($actualPath) && !is_writable($actualPath)) {
                    deleteDir($rollbackTmpDir);
                    echo json_encode(['success' => false, 'error' => "Нет прав на изменение файла: $actualPath (проверьте chown/chmod на сервере)"], JSON_UNESCAPED_UNICODE);
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
        if (!empty($newVersionData['changelog']) && is_array($newVersionData['changelog'])) {
            $verPayload['changelog'] = $newVersionData['changelog'];
        }
        file_put_contents('version.json', json_encode($verPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
elseif ($action === 'export_package') {
    @set_time_limit(180);
    if (!class_exists('ZipArchive')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Расширение PHP ZipArchive не установлено'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $currVersion = 'unknown';
    if (file_exists('version.json')) {
        $vData = @json_decode(file_get_contents('version.json'), true);
        if (!empty($vData['version'])) {
            $currVersion = $vData['version'];
        }
    }
    
    $cleanVersion = preg_replace('/[^a-zA-Z0-9._-]/', '_', $currVersion);
    $zipFilename = 'npblog_update_v' . $cleanVersion . '.zip';
    $tmpZipPath = sys_get_temp_dir() . '/' . uniqid('npblog_pkg_') . '.zip';
    
    $zip = new ZipArchive();
    if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Не удалось создать временный zip-архив на сервере'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $rootPath = realpath('./');
    $dirIterator = new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS);
    $filterIterator = new RecursiveCallbackFilterIterator($dirIterator, function ($current) use ($rootPath) {
        $rel = ltrim(str_replace('\\', '/', substr($current->getPathname(), strlen($rootPath))), '/');
        if ($current->isDir()) {
            $relDir = strtolower($rel) . '/';
            if (strpos($relDir, '.git/') === 0 ||
                strpos($relDir, '.github/') === 0 ||
                strpos($relDir, '.vscode/') === 0 ||
                strpos($relDir, '.idea/') === 0 ||
                strpos($relDir, 'node_modules/') === 0 ||
                strpos($relDir, 'sys_update_tmp/') === 0 ||
                strpos($relDir, 'rollback_tmp/') === 0 ||
                strpos($relDir, 'editor_backup/') === 0 ||
                strpos($relDir, 'data_backup/') === 0 ||
                strpos($relDir, 'autosave/') === 0 ||
                strpos($relDir, 'data/') === 0) {
                return false;
            }
            return true;
        }
        $relLower = strtolower($rel);
        if (substr($relLower, -4) === '.zip') return false;
        if ($relLower === 'editor_settings.json') return false;
        if ($relLower === 'allowed_ips.txt') return false;
        if ($relLower === 'api_tokens.json') return false;
        if (strpos($relLower, 'data/') === 0) return false;
        return true;
    });
    
    $files = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);
            $zip->addFile($filePath, $relativePath);
        }
    }
    $zip->close();
    
    if (file_exists($tmpZipPath)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
        header('Content-Length: ' . filesize($tmpZipPath));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($tmpZipPath);
        @unlink($tmpZipPath);
        exit;
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Не удалось прочитать сгенерированный архив'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
?>
