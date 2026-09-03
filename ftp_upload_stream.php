<?php
require_once __DIR__ . '/security_bootstrap.php';

// Немедленно закрываем запись в сессию, чтобы другие запросы и вкладки не блокировались
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Повышаем стабильность: увеличиваем лимиты времени и памяти
set_time_limit(1800); // 30 минут
@ini_set('memory_limit', '512M');

// Отключаем буферизацию Apache/PHP для непрерывного потокового вывода в реальном времени
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', 'Off');
@ini_set('output_buffering', 'Off');
@ini_set('implicit_flush', '1');
ob_implicit_flush(true);
while (ob_get_level() > 0) {
    @ob_end_clean();
}

header('Content-Type: text/event-stream; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Отключает буферизацию в Nginx

// 4KB комментарий для немедленного проталкивания буферов веб-сервера (Apache/Nginx/прокси)
echo ":" . str_repeat(" ", 4096) . "\n\n";
if (ob_get_level() > 0) {
    @ob_flush();
}
@flush();

function sendEvent($type, $data) {
    echo "data: " . json_encode(['type' => $type, 'data' => $data], JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

function formatBytes($bytes, $precision = 1) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Функция для создания и настройки FTP подключения (как в FileZilla)
function getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl) {
    $port = 21;
    $timeout = 15; // Таймаут подключения 15 секунд
    
    if ($ftpSsl && function_exists('ftp_ssl_connect')) {
        $connId = @ftp_ssl_connect($ftpServer, $port, $timeout);
        if (!$connId) {
            $connId = @ftp_connect($ftpServer, $port, $timeout);
        }
    } else {
        $connId = @ftp_connect($ftpServer, $port, $timeout);
    }
    
    if (!$connId) {
        return null;
    }
    
    $loginResult = @ftp_login($connId, $ftpUsername, $ftpPassword);
    if (!$loginResult) {
        @ftp_close($connId);
        return null;
    }
    
    // ВАЖНО: Решение проблемы NAT (как в FileZilla!)
    // Игнорируем внутренний IP-адрес, возвращаемый сервером в ответе PASV, 
    // и используем исходный IP-адрес сервера. Это предотвращает зависание при передаче данных!
    if (defined('FTP_USEPASVADDRESS')) {
        @ftp_set_option($connId, FTP_USEPASVADDRESS, false);
    }
    
    // Устанавливаем таймаут на операции передачи данных в 15 секунд
    @ftp_set_option($connId, FTP_TIMEOUT_SEC, 15);
    
    // Включаем пассивный режим
    @ftp_pasv($connId, true);
    
    return $connId;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendEvent('error', ['message' => 'Неверный метод запроса']);
    exit;
}

$ftpServer = $_POST['ftpServer'] ?? '';
$ftpUsername = $_POST['ftpUsername'] ?? '';
$ftpPassword = $_POST['ftpPassword'] ?? '';
$ftpDirectory = $_POST['ftpDirectory'] ?? '';
$ftpSsl = isset($_POST['ftpSsl']) && $_POST['ftpSsl'] === '1';
$ftpSkipExisting = !isset($_POST['ftpSkipExisting']) || $_POST['ftpSkipExisting'] === '1';

if (empty($ftpServer) || empty($ftpUsername) || empty($ftpDirectory)) {
    sendEvent('error', ['message' => 'Заполните все обязательные поля']);
    exit;
}

$blogToUpload = $_POST['blogToUpload'] ?? '';
$settingsFile = __DIR__ . '/editor_settings.json';
$settings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];
$blogPaths = isset($settings['blog_paths']) ? $settings['blog_paths'] : [];
if (empty($blogPaths)) {
    $blogPaths = [isset($settings['data_path']) ? $settings['data_path'] : 'data'];
}

$localDataDir = rtrim(getDataPath(), '/\\');
if (!empty($blogToUpload) && in_array($blogToUpload, $blogPaths)) {
    $localDataDir = $blogToUpload;
    if (strpos($localDataDir, '/') !== 0 && strpos($localDataDir, ':\\') !== 1) {
        $localDataDir = __DIR__ . '/' . ltrim($localDataDir, '/');
    }
    $localDataDir = rtrim($localDataDir, '/\\');
}

if (!is_dir($localDataDir)) {
    sendEvent('error', ['message' => 'Выбранная папка блога не найдена: ' . basename($localDataDir)]);
    exit;
}
$remoteFolderName = basename($localDataDir);

sendEvent('log', ['message' => "Подключение к FTP серверу $ftpServer...", 'level' => 'info']);

$connId = getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);
if (!$connId) {
    sendEvent('error', ['message' => 'Не удалось подключиться или авторизоваться на FTP сервере. Проверьте адрес, имя пользователя и пароль.']);
    exit;
}

sendEvent('log', ['message' => 'Успешное подключение и авторизация на FTP!', 'level' => 'success']);

$ftpDirectory = '/' . trim($ftpDirectory, '/');

// Подсчитываем общее количество файлов и их общий объём
function countFilesAndSize($dir) {
    $count = 0;
    $totalSize = 0;
    $items = @scandir($dir);
    if (!$items) return ['count' => 0, 'size' => 0];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array(strtolower($item), ['.ds_store', 'thumbs.db', '.git', '.gitignore'])) {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $sub = countFilesAndSize($path);
            $count += $sub['count'];
            $totalSize += $sub['size'];
        } else {
            $count++;
            $totalSize += @filesize($path) ?: 0;
        }
    }
    return ['count' => $count, 'size' => $totalSize];
}

$dirStats = countFilesAndSize($localDataDir);
$totalFiles = max(1, $dirStats['count']);
$totalBytes = $dirStats['size'];
$uploadedCount = 0;
$failedCount = 0;
$uploadedBytes = 0;

sendEvent('progress', [
    'total' => $totalFiles,
    'current' => 0,
    'percent' => 0,
    'currentFile' => 'Подготовка к передаче...',
    'uploadedBytes' => 0,
    'totalBytes' => $totalBytes
]);
sendEvent('log', [
    'message' => "Найдено файлов для анализа/загрузки: $totalFiles (" . formatBytes($totalBytes) . ")",
    'level' => 'info'
]);

// Рекурсивная функция загрузки с умной синхронизацией и авто-переподключением
function uploadDirectory(&$connId, $localDir, $remoteDir, &$uploadedCount, &$failedCount, &$uploadedBytes, $totalFiles, $totalBytes, $ftpSkipExisting, $ftpServer, $ftpUsername, $ftpPassword, $ftpSsl) {
    global $localDataDir, $remoteFolderName;
    
    // Проверяем соединение
    if (!$connId) {
        $connId = getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);
        if (!$connId) {
            sendEvent('log', ['message' => "✗ Ошибка: потеряно соединение с FTP сервером и не удалось переподключиться", 'level' => 'error']);
            return false;
        }
    }

    // Создаём удалённую директорию если её нет
    if (!@ftp_chdir($connId, $remoteDir)) {
        if (!@ftp_mkdir($connId, $remoteDir)) {
            $connId = getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);
            if ($connId && @ftp_mkdir($connId, $remoteDir)) {
                sendEvent('log', ['message' => "Создана директория: $remoteDir", 'level' => 'info']);
            } else {
                sendEvent('log', ['message' => "Ошибка создания директории: $remoteDir", 'level' => 'error']);
                return false;
            }
        } else {
            sendEvent('log', ['message' => "Создана директория: $remoteDir", 'level' => 'info']);
        }
    }
    
    $items = @scandir($localDir);
    if (!$items) return true;
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array(strtolower($item), ['.ds_store', 'thumbs.db', '.git', '.gitignore'])) {
            continue;
        }
        
        $localPath = $localDir . '/' . $item;
        $remotePath = $remoteDir . '/' . $item;
        
        if (is_dir($localPath)) {
            if (!uploadDirectory($connId, $localPath, $remotePath, $uploadedCount, $failedCount, $uploadedBytes, $totalFiles, $totalBytes, $ftpSkipExisting, $ftpServer, $ftpUsername, $ftpPassword, $ftpSsl)) {
                return false;
            }
        } else {
            $relativePath = $remoteFolderName . '/' . ltrim(substr($localPath, strlen($localDataDir)), '/\\');
            $localSize = (int)@filesize($localPath);
            
            // Уведомляем интерфейс о начале обработки данного файла
            $currentPercent = round(($uploadedCount / $totalFiles) * 100);
            sendEvent('file_start', [
                'total' => $totalFiles,
                'current' => $uploadedCount + 1,
                'percent' => $currentPercent,
                'file' => $relativePath,
                'size' => $localSize,
                'sizeFormatted' => formatBytes($localSize)
            ]);
            
            // Умная синхронизация: пропускаем файлы, если размер на сервере совпадает
            if ($ftpSkipExisting) {
                $remoteSize = @ftp_size($connId, $remotePath);
                
                if ($remoteSize === -1 && !$connId) {
                    $connId = getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);
                    if ($connId) {
                        $remoteSize = @ftp_size($connId, $remotePath);
                    }
                }

                if ($remoteSize !== -1 && $remoteSize === $localSize) {
                    $uploadedCount++;
                    $uploadedBytes += $localSize;
                    $percent = round(($uploadedCount / $totalFiles) * 100);
                    sendEvent('progress', [
                        'total' => $totalFiles,
                        'current' => $uploadedCount,
                        'percent' => $percent,
                        'currentFile' => $relativePath,
                        'action' => 'skip',
                        'uploadedBytes' => $uploadedBytes,
                        'totalBytes' => $totalBytes
                    ]);
                    sendEvent('log', [
                        'message' => "⚡ Пропущен (размер совпадает): $relativePath",
                        'level' => 'info',
                        'file' => $relativePath
                    ]);
                    usleep(15000); // 15мс задержка для плавной отрисовки прогресса в UI
                    continue;
                }
            }
            
            sendEvent('log', [
                'message' => "Загрузка: $relativePath (" . formatBytes($localSize) . ")",
                'level' => 'info',
                'file' => $relativePath
            ]);
            
            // Загрузка файла с авто-повтором
            $success = false;
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                if ($connId && @ftp_put($connId, $remotePath, $localPath, FTP_BINARY)) {
                    $success = true;
                    break;
                }
                
                if ($attempt < 2) {
                    sendEvent('log', [
                        'message' => "⚠️ Ошибка или таймаут загрузки $relativePath. Выполняем авто-переподключение...",
                        'level' => 'warning'
                    ]);
                    if ($connId) {
                        @ftp_close($connId);
                    }
                    $connId = getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);
                    if (!$connId) {
                        sendEvent('log', ['message' => "✗ Не удалось восстановить подключение к FTP", 'level' => 'error']);
                        break; 
                    }
                    usleep(300000);
                }
            }
            
            if ($success) {
                $uploadedCount++;
                $uploadedBytes += $localSize;
                $percent = round(($uploadedCount / $totalFiles) * 100);
                sendEvent('progress', [
                    'total' => $totalFiles,
                    'current' => $uploadedCount,
                    'percent' => $percent,
                    'currentFile' => $relativePath,
                    'action' => 'upload',
                    'uploadedBytes' => $uploadedBytes,
                    'totalBytes' => $totalBytes
                ]);
                sendEvent('log', [
                    'message' => "✓ Загружен: $relativePath",
                    'level' => 'success',
                    'file' => $relativePath
                ]);
            } else {
                $failedCount++;
                $percent = round(($uploadedCount / $totalFiles) * 100);
                sendEvent('progress', [
                    'total' => $totalFiles,
                    'current' => $uploadedCount,
                    'percent' => $percent,
                    'currentFile' => $relativePath,
                    'action' => 'error',
                    'uploadedBytes' => $uploadedBytes,
                    'totalBytes' => $totalBytes
                ]);
                sendEvent('log', [
                    'message' => "✗ Ошибка загрузки: $relativePath",
                    'level' => 'error',
                    'file' => $relativePath
                ]);
            }
            
            usleep(20000); // 20мс задержка для плавности SSE потока
        }
    }
    
    return true;
}

// Запуск процесса загрузки
$success = uploadDirectory($connId, $localDataDir, $ftpDirectory . '/' . $remoteFolderName, $uploadedCount, $failedCount, $uploadedBytes, $totalFiles, $totalBytes, $ftpSkipExisting, $ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);

if ($connId) {
    @ftp_close($connId);
}

if ($success && $failedCount === 0) {
    sendEvent('complete', [
        'uploaded' => $uploadedCount,
        'failed' => $failedCount,
        'total' => $totalFiles,
        'message' => "🎉 Загрузка успешно завершена! Обработано файлов: $uploadedCount"
    ]);
} else {
    sendEvent('complete', [
        'uploaded' => $uploadedCount,
        'failed' => $failedCount,
        'total' => $totalFiles,
        'message' => "Завершено. Загружено/пропущено: $uploadedCount, ошибок: $failedCount"
    ]);
}
