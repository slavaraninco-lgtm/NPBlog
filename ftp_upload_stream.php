<?php
require_once __DIR__ . '/security_bootstrap.php';
// Повышаем стабильность: увеличиваем лимиты времени и памяти
set_time_limit(1800); // 30 минут
ini_set('memory_limit', '512M');

// Отключаем буферизацию для бесперебойного потокового вывода
ob_implicit_flush(true);
if (ob_get_level() > 0) {
    ob_end_flush();
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Отключает буферизацию в Nginx (крайне важно для реалтайм логов)

function sendEvent($type, $data) {
    echo "data: " . json_encode(['type' => $type, 'data' => $data]) . "\n\n";
    flush();
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
    $timeout = 15; // Таймаут подключения 15 секунд вместо бесконечного зависания
    
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

sendEvent('log', ['message' => 'Подключение к FTP серверу...', 'level' => 'info']);

$connId = getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);
if (!$connId) {
    sendEvent('error', ['message' => 'Не удалось подключиться или авторизоваться на FTP сервере. Проверьте адрес, имя пользователя и пароль.']);
    exit;
}

sendEvent('log', ['message' => 'Успешное подключение и авторизация на FTP!', 'level' => 'success']);

$ftpDirectory = '/' . trim($ftpDirectory, '/');

// Подсчитываем общее количество файлов
function countFiles($dir) {
    $count = 0;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        // Пропускаем мусорные файлы
        if (in_array(strtolower($item), ['.ds_store', 'thumbs.db', '.git', '.gitignore'])) {
            continue;
        }
        
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $count += countFiles($path);
        } else {
            $count++;
        }
    }
    return $count;
}

$totalFiles = countFiles($localDataDir);
sendEvent('progress', ['total' => $totalFiles, 'current' => 0, 'percent' => 0]);
sendEvent('log', ['message' => "Найдено файлов для анализа/загрузки: $totalFiles", 'level' => 'info']);

$uploadedCount = 0;
$failedCount = 0;

// Рекурсивная функция загрузки с умной синхронизацией, авто-переподключением по ссылке (&$connId)
function uploadDirectory(&$connId, $localDir, $remoteDir, &$uploadedCount, &$failedCount, $totalFiles, $ftpSkipExisting, $ftpServer, $ftpUsername, $ftpPassword, $ftpSsl) {
    global $localDataDir, $remoteFolderName;
    // Проверяем, живое ли соединение. Если нет, пробуем переподключить
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
            // Если команда упала из-за таймаута соединения, пробуем переподключиться и повторить
            $connId = getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);
            if ($connId && @ftp_mkdir($connId, $remoteDir)) {
                sendEvent('log', ['message' => "Создана директория (после авто-переподключения): $remoteDir", 'level' => 'info']);
            } else {
                sendEvent('log', ['message' => "Ошибка создания директории: $remoteDir", 'level' => 'error']);
                return false;
            }
        } else {
            sendEvent('log', ['message' => "Создана директория: $remoteDir", 'level' => 'info']);
        }
    }
    
    $items = scandir($localDir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        // Пропускаем мусорные файлы ОС и Git
        if (in_array(strtolower($item), ['.ds_store', 'thumbs.db', '.git', '.gitignore'])) {
            continue;
        }
        
        $localPath = $localDir . '/' . $item;
        $remotePath = $remoteDir . '/' . $item;
        
        if (is_dir($localPath)) {
            if (!uploadDirectory($connId, $localPath, $remotePath, $uploadedCount, $failedCount, $totalFiles, $ftpSkipExisting, $ftpServer, $ftpUsername, $ftpPassword, $ftpSsl)) {
                return false;
            }
        } else {
            $relativePath = $remoteFolderName . '/' . ltrim(substr($localPath, strlen($localDataDir)), '/\\');
            $localSize = filesize($localPath);
            
            // Умная синхронизация: пропускаем файлы, если размер на сервере совпадает
            if ($ftpSkipExisting) {
                $remoteSize = @ftp_size($connId, $remotePath);
                
                // Если соединение отвалилось во время SIZE, восстанавливаем его
                if ($remoteSize === -1 && !$connId) {
                    $connId = getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);
                    if ($connId) {
                        $remoteSize = @ftp_size($connId, $remotePath);
                    }
                }

                if ($remoteSize !== -1 && $remoteSize === $localSize) {
                    $uploadedCount++;
                    $percent = round(($uploadedCount / $totalFiles) * 100);
                    sendEvent('progress', ['total' => $totalFiles, 'current' => $uploadedCount, 'percent' => $percent]);
                    sendEvent('log', ['message' => "⚡ Пропущен (размер совпадает): $relativePath", 'level' => 'info']);
                    continue;
                }
            }
            
            sendEvent('log', ['message' => "Загрузка: $relativePath (" . formatBytes($localSize) . ")", 'level' => 'info']);
            
            // Попытка загрузки с механизмом авто-повтора и горячего авто-переподключения (2 попытки)
            $success = false;
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                if ($connId && @ftp_put($connId, $remotePath, $localPath, FTP_BINARY)) {
                    $success = true;
                    break;
                }
                
                if ($attempt < 2) {
                    sendEvent('log', ['message' => "⚠️ Ошибка или таймаут загрузки $relativePath. Выполняем авто-переподключение...", 'level' => 'warning']);
                    if ($connId) {
                        @ftp_close($connId);
                    }
                    $connId = getFtpConnection($ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);
                    if (!$connId) {
                        sendEvent('log', ['message' => "✗ Не удалось восстановить подключение к FTP", 'level' => 'error']);
                        break; 
                    }
                    usleep(300000); // Ожидаем 0.3 сек перед повтором на чистом соединении
                }
            }
            
            if ($success) {
                $uploadedCount++;
                $percent = round(($uploadedCount / $totalFiles) * 100);
                sendEvent('progress', ['total' => $totalFiles, 'current' => $uploadedCount, 'percent' => $percent]);
                sendEvent('log', ['message' => "✓ Загружен: $relativePath", 'level' => 'success']);
            } else {
                $failedCount++;
                sendEvent('log', ['message' => "✗ Ошибка загрузки после переподключения: $relativePath", 'level' => 'error']);
            }
            
            usleep(20000); // Небольшая задержка для плавности SSE потока
        }
    }
    
    return true;
}

// Начинаем загрузку выбранной папки
$success = uploadDirectory($connId, $localDataDir, $ftpDirectory . '/' . $remoteFolderName, $uploadedCount, $failedCount, $totalFiles, $ftpSkipExisting, $ftpServer, $ftpUsername, $ftpPassword, $ftpSsl);

if ($connId) {
    @ftp_close($connId);
}

if ($success && $failedCount === 0) {
    sendEvent('complete', [
        'uploaded' => $uploadedCount,
        'failed' => $failedCount,
        'message' => "🎉 Загрузка успешно завершена! Обработано файлов: $uploadedCount"
    ]);
} else {
    sendEvent('complete', [
        'uploaded' => $uploadedCount,
        'failed' => $failedCount,
        'message' => "Завершено. Загружено/пропущено: $uploadedCount, ошибок: $failedCount"
    ]);
}
