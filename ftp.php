<?php
require_once __DIR__ . '/security_bootstrap.php';

define('CREDENTIALS_FILE', 'ftp.json');

function saveCredentials($data) {
    $data['saved_at'] = date('Y-m-d H:i:s');
    safeWriteJson(CREDENTIALS_FILE, $data);
}

function loadCredentials() {
    if (file_exists(CREDENTIALS_FILE)) {
        return json_decode(file_get_contents(CREDENTIALS_FILE), true);
    }
    return null;
}

function resetCredentials() {
    if (file_exists(CREDENTIALS_FILE)) {
        unlink(CREDENTIALS_FILE);
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'get_settings') {
    header('Content-Type: application/json; charset=utf-8');
    $savedCredentials = loadCredentials();
    $settingsFile = __DIR__ . '/editor_settings.json';
    $settings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];
    $availableBlogPaths = isset($settings['blog_paths']) && is_array($settings['blog_paths']) ? $settings['blog_paths'] : [];
    if (empty($availableBlogPaths)) {
        $availableBlogPaths = [isset($settings['data_path']) ? $settings['data_path'] : 'data'];
    }
    $currentActiveBlog = isset($_SESSION['active_blog_path']) ? $_SESSION['active_blog_path'] : (isset($settings['active_blog_path']) ? $settings['active_blog_path'] : $availableBlogPaths[0]);
    echo json_encode([
        'success' => true,
        'credentials' => $savedCredentials,
        'availableBlogPaths' => $availableBlogPaths,
        'currentActiveBlog' => $currentActiveBlog
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    header('Content-Type: application/json; charset=utf-8');
    resetCredentials();
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $ftpServer = $_POST['ftpServer'] ?? '';
    $ftpUsername = $_POST['ftpUsername'] ?? '';
    $ftpPassword = $_POST['ftpPassword'] ?? '';
    $ftpDirectory = $_POST['ftpDirectory'] ?? '';
    $ftpSsl = isset($_POST['ftpSsl']) ? intval($_POST['ftpSsl']) : 0;
    $ftpSkipExisting = isset($_POST['ftpSkipExisting']) ? intval($_POST['ftpSkipExisting']) : 0;

    if (empty($ftpServer) || empty($ftpUsername) || empty($ftpDirectory)) {
        echo json_encode(['success' => false, 'message' => 'Заполните все обязательные поля']);
        exit;
    }

    if (isset($_POST['remember'])) {
        saveCredentials([
            'ftpServer' => $ftpServer,
            'ftpUsername' => $ftpUsername,
            'ftpDirectory' => $ftpDirectory,
            'ftpSsl' => $ftpSsl,
            'ftpSkipExisting' => $ftpSkipExisting
        ]);
        
        if (isset($_POST['saveOnly'])) {
            echo json_encode(['success' => true, 'message' => 'Настройки успешно сохранены']);
            exit;
        }
    }

    $blogToUpload = $_POST['blogToUpload'] ?? '';
    
    // Validate blog path
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
        echo json_encode(['success' => false, 'message' => 'Выбранная папка блога не найдена: ' . basename($localDataDir)]);
        exit;
    }

    $ftpDirectory = '/' . trim($ftpDirectory, '/');

    $connId = @ftp_connect($ftpServer);
    if (!$connId) {
        echo json_encode(['success' => false, 'message' => 'Не удалось подключиться к FTP серверу']);
        exit;
    }

    $loginResult = @ftp_login($connId, $ftpUsername, $ftpPassword);
    if (!$loginResult) {
        echo json_encode(['success' => false, 'message' => 'Ошибка авторизации FTP']);
        ftp_close($connId);
        exit;
    }

    ftp_pasv($connId, true);

    // Функция для рекурсивной загрузки папки
    function uploadDirectory($connId, $localDir, $remoteDir) {
        $uploaded = 0;
        $failed = 0;
        
        // Создаём удалённую директорию если её нет
        if (!@ftp_chdir($connId, $remoteDir)) {
            if (!@ftp_mkdir($connId, $remoteDir)) {
                return ['uploaded' => 0, 'failed' => 1, 'error' => "Не удалось создать директорию: $remoteDir"];
            }
        }
        
        // Получаем список файлов и папок
        $items = scandir($localDir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $localPath = $localDir . '/' . $item;
            $remotePath = $remoteDir . '/' . $item;
            
            if (is_dir($localPath)) {
                // Рекурсивно загружаем подпапку
                $result = uploadDirectory($connId, $localPath, $remotePath);
                $uploaded += $result['uploaded'];
                $failed += $result['failed'];
                
                if (isset($result['error'])) {
                    return ['uploaded' => $uploaded, 'failed' => $failed, 'error' => $result['error']];
                }
            } else {
                // Загружаем файл
                if (@ftp_put($connId, $remotePath, $localPath, FTP_BINARY)) {
                    $uploaded++;
                } else {
                    $failed++;
                }
            }
        }
        
        return ['uploaded' => $uploaded, 'failed' => $failed];
    }

    // Загружаем выбранную папку блога
    $remoteFolderName = basename($localDataDir);
    $result = uploadDirectory($connId, $localDataDir, $ftpDirectory . '/' . $remoteFolderName);
    
    ftp_close($connId);
    
    if (isset($result['error'])) {
        echo json_encode(['success' => false, 'message' => $result['error']]);
    } else if ($result['failed'] > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Загружено файлов: {$result['uploaded']}, ошибок: {$result['failed']}"
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'message' => "Папка $remoteFolderName успешно загружена! Загружено файлов: {$result['uploaded']}"
        ]);
    }
    exit;
}

$savedCredentials = loadCredentials();

// Загружаем доступные пути для селектора
$settingsFile = __DIR__ . '/editor_settings.json';
$settings = file_exists($settingsFile) ? (json_decode(file_get_contents($settingsFile), true) ?: []) : [];
$availableBlogPaths = isset($settings['blog_paths']) && is_array($settings['blog_paths']) ? $settings['blog_paths'] : [];
if (empty($availableBlogPaths)) {
    $availableBlogPaths = [isset($settings['data_path']) ? $settings['data_path'] : 'data'];
}
$currentActiveBlog = isset($_SESSION['active_blog_path']) ? $_SESSION['active_blog_path'] : (isset($settings['active_blog_path']) ? $settings['active_blog_path'] : $availableBlogPaths[0]);

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    <title>FTP Загрузчик - NPBlog</title>
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --primary-color: rgb(255, 255, 255);
        }
        
        [data-theme="dark"] {
            --bg-color: #121212;
            --text-color: #f5f5f5;
            --primary-color: rgb(0, 0, 0);
            --primary-color2: rgb(255, 255, 255);
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
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--text-color);
            gap: 20px;
        }

        h1 {
            font-size: 2em;
            font-weight: 700;
            flex: 1;
        }

        .theme-toggle {
            padding: 10px 20px;
            background: var(--bg-color);
            color: var(--text-color);
            border: 2px solid var(--text-color);
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            white-space: nowrap;
            flex-shrink: 0;
            width: auto;
            max-width: fit-content;
        }

        .theme-toggle:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: var(--bg-color);
            color: var(--text-color);
            text-decoration: none;
            border: 2px solid var(--text-color);
            border-radius: 8px;
            transition: all 0.2s;
            font-weight: 600;
        }

        .back-link:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }

        .saved-info {
            background: rgba(33, 150, 243, 0.1);
            border: 2px solid rgba(33, 150, 243, 0.3);
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .saved-info-header {
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }

        .saved-info-header:hover {
            background: rgba(33, 150, 243, 0.15);
        }

        .saved-info h3 {
            margin: 0;
            font-size: 1.1em;
        }

        .saved-info-toggle {
            font-size: 18px;
            opacity: 0.6;
            transition: transform 0.3s;
        }

        .saved-info.expanded .saved-info-toggle {
            transform: rotate(180deg);
        }

        .saved-info-content {
            display: none;
            padding: 0 20px 20px 20px;
        }

        .saved-info.expanded .saved-info-content {
            display: block;
        }

        .saved-info p {
            margin-bottom: 8px;
            font-size: 0.95em;
            opacity: 0.9;
        }

        .saved-info p:last-child {
            margin-bottom: 0;
        }

        .form-container {
            background: var(--bg-color);
            border: 2px solid var(--text-color);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.95em;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            background-color: var(--bg-color);
            color: var(--text-color);
            border: 2px solid var(--text-color);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] input[type="text"]:focus,
        [data-theme="dark"] input[type="password"]:focus {
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }
        
        .filename-note {
            font-size: 0.85em;
            opacity: 0.7;
            margin-top: 6px;
            line-height: 1.4;
        }

        .remember-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 25px 0;
            padding: 15px;
            background: rgba(0, 0, 0, 0.03);
            border: 2px solid var(--text-color);
            border-radius: 10px;
        }

        [data-theme="dark"] .remember-group {
            background: rgba(255, 255, 255, 0.05);
        }

        .remember-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .remember-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        
        button {
            padding: 14px 28px;
            border: 2px solid var(--text-color);
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
        }

        button.primary {
            background: var(--text-color);
            color: var(--bg-color);
        }

        button.primary:hover {
            opacity: 0.85;
            transform: translateY(-1px);
        }

        button.secondary {
            background: var(--bg-color);
            color: var(--text-color);
        }

        button.secondary:hover {
            background: var(--text-color);
            color: var(--bg-color);
        }

        button.danger {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
        }

        button.danger:hover {
            background: #c82333;
            border-color: #c82333;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .notification-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 100000 !important;
            max-width: 400px;
        }

        .notification {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .notification.success {
            background: #28a745;
            color: #fff;
            border: 2px solid #1e7e34;
        }

        .notification.error {
            background: #dc3545;
            color: #fff;
            border: 2px solid #c82333;
        }

        .notification.info {
            background: #17a2b8;
            color: #fff;
            border: 2px solid #117a8b;
        }
        
        /* Прогресс-бар и логи */
        .upload-progress {
            display: none;
            background: var(--bg-color);
            border: 2px solid var(--text-color);
            border-radius: 12px;
            padding: 30px;
            margin-top: 20px;
        }
        
        .upload-progress.active {
            display: block;
        }
        
        .progress-header {
            margin-bottom: 20px;
        }
        
        .progress-header h3 {
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        
        .progress-bar-container {
            width: 100%;
            height: 30px;
            background: rgba(0, 0, 0, 0.1);
            border: 2px solid var(--text-color);
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        [data-theme="dark"] .progress-bar-container {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 13px;
        }
        
        .progress-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.9em;
            opacity: 0.8;
            margin-bottom: 20px;
        }
        
        .logs-container {
            max-height: 400px;
            overflow-y: auto;
            background: rgba(0, 0, 0, 0.05);
            border: 2px solid var(--text-color);
            border-radius: 10px;
            padding: 15px;
        }
        
        [data-theme="dark"] .logs-container {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .log-entry {
            padding: 8px 12px;
            margin-bottom: 6px;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Courier New', monospace;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .log-entry:last-child {
            margin-bottom: 0;
        }
        
        .log-entry.info {
            background: rgba(33, 150, 243, 0.1);
            border-left: 3px solid #2196F3;
        }
        
        .log-entry.success {
            background: rgba(76, 175, 80, 0.1);
            border-left: 3px solid #4CAF50;
        }
        
        .log-entry.error {
            background: rgba(244, 67, 54, 0.1);
            border-left: 3px solid #F44336;
        }
        
        .log-time {
            color: var(--text-color);
            opacity: 0.6;
            font-size: 11px;
            white-space: nowrap;
        }
        
        .log-message {
            flex: 1;
            word-break: break-word;
        }

        @media (max-width: 768px) {
            body {
                padding: 15px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .form-container {
                padding: 20px;
            }

            .button-group {
                flex-direction: column;
            }

            button {
                width: 100%;
            }

            .notification-container {
                left: 10px;
                right: 10px;
                max-width: calc(100% - 20px);
            }
        }

        /* Custom Select */
        .custom-select-wrapper {
            position: relative;
            display: block;
            width: 100%;
            box-sizing: border-box;
            font-family: inherit;
        }

        .custom-select-native {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            margin: -1px !important;
            padding: 0 !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            border: 0 !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        .custom-select-trigger {
            width: 100%;
            min-height: 42px;
            padding: 9px 14px;
            background: var(--bg-color);
            border: 2px solid var(--text-color);
            border-radius: 8px;
            color: var(--text-color);
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            text-align: left;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            user-select: none;
            outline: none;
        }

        .custom-select-trigger:hover,
        .custom-select-wrapper.is-open .custom-select-trigger {
            border-color: var(--text-color);
            box-shadow: 0 0 0 2px rgba(128, 128, 128, 0.2);
        }

        .custom-select-value {
            flex: 1 1 auto;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .custom-select-arrow {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            opacity: 0.7;
            transition: transform 0.2s ease;
            color: currentColor;
        }

        .custom-select-arrow svg {
            display: block;
            width: 12px;
            height: 12px;
        }

        .custom-select-wrapper.is-open .custom-select-arrow {
            transform: rotate(180deg);
            opacity: 1;
        }

        .custom-select-popover {
            display: block;
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            width: 100%;
            box-sizing: border-box;
            z-index: 1000;
            background: var(--bg-color) !important;
            border: 2px solid var(--text-color);
            border-radius: 8px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px);
            pointer-events: none;
            transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s;
            overflow: hidden;
            padding: 4px;
        }

        .custom-select-wrapper.is-open .custom-select-popover {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
            pointer-events: auto;
        }

        .custom-select-popover.drop-up {
            top: auto;
            bottom: calc(100% + 5px);
            transform: translateY(4px);
        }

        .custom-select-wrapper.is-open .custom-select-popover.drop-up {
            transform: translateY(0);
        }

        .custom-select-popover-inner {
            max-height: 200px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .custom-select-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            width: 100%;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: var(--text-color);
            font-size: 13.5px;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
            transition: background 0.12s ease;
            box-sizing: border-box;
            outline: none;
        }

        .custom-select-option:hover {
            background: rgba(128, 128, 128, 0.12);
        }

        .custom-select-option.is-selected {
            background: rgba(128, 128, 128, 0.2);
            font-weight: 600;
        }

        .custom-select-option .custom-option-check {
            font-size: 13px;
            font-weight: bold;
            opacity: 0;
            color: var(--text-color);
        }

        .custom-select-option.is-selected .custom-option-check {
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="notification-container" id="notificationContainer"></div>

    <div class="header">
        <h1>FTP Загрузчик</h1>
        <button class="theme-toggle" id="themeToggle">🌓 Тема</button>
    </div>

    <a href="index.php" class="back-link">← Назад к редактору</a>
    
    <?php if ($savedCredentials): ?>
    <div class="saved-info" id="savedInfo">
        <div class="saved-info-header" onclick="toggleSavedInfo()">
            <h3>📁 Сохранённые настройки FTP</h3>
            <span class="saved-info-toggle">▼</span>
        </div>
        <div class="saved-info-content">
            <p><strong>Последнее сохранение:</strong> <?= htmlspecialchars($savedCredentials['saved_at'] ?? 'неизвестно') ?></p>
            <p><strong>Сервер:</strong> <?= htmlspecialchars($savedCredentials['ftpServer'] ?? '') ?></p>
            <p><strong>Пользователь:</strong> <?= htmlspecialchars($savedCredentials['ftpUsername'] ?? '') ?></p>
            <p><strong>Корневая директория:</strong> <?= htmlspecialchars($savedCredentials['ftpDirectory'] ?? '') ?></p>
            <p><strong>SSL/TLS (Безопасное):</strong> <?= !empty($savedCredentials['ftpSsl']) ? 'Да' : 'Нет' ?></p>
            <p><strong>Умная синхронизация:</strong> <?= (!isset($savedCredentials['ftpSkipExisting']) || !empty($savedCredentials['ftpSkipExisting'])) ? 'Включена' : 'Выключена' ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="form-container">
        <form id="uploadForm">
            <?php if (count($availableBlogPaths) > 0): ?>
            <div class="form-group" style="background: rgba(33, 150, 243, 0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(33, 150, 243, 0.2); margin-bottom: 25px;">
                <label for="blogToUpload">Выберите блог для загрузки</label>
                <select id="blogToUpload" name="blogToUpload">
                    <?php foreach ($availableBlogPaths as $path): ?>
                        <?php $folderName = basename(str_replace('\\', '/', $path)); ?>
                        <option value="<?= htmlspecialchars($path) ?>" <?= $path === $currentActiveBlog ? 'selected' : '' ?>>
                            <?= htmlspecialchars($folderName) ?> (<?= htmlspecialchars($path) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="filename-note">Выбранная папка блога будет загружена целиком на FTP.</p>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="ftpServer">FTP Сервер *</label>
                <input type="text" id="ftpServer" name="ftpServer" 
                       value="<?= htmlspecialchars($savedCredentials['ftpServer'] ?? '') ?>" 
                       placeholder="ftp.example.com" required>
            </div>
            
            <div class="form-group">
                <label for="ftpUsername">Имя пользователя *</label>
                <input type="text" id="ftpUsername" name="ftpUsername" 
                       value="<?= htmlspecialchars($savedCredentials['ftpUsername'] ?? '') ?>" 
                       placeholder="username" required>
            </div>
            
            <div class="form-group">
                <label for="ftpPassword">Пароль *</label>
                <input type="password" id="ftpPassword" name="ftpPassword" 
                       placeholder="••••••••" required>
            </div>
            
            <div class="form-group">
                <label for="ftpDirectory">Корневая директория сервера *</label>
                <input type="text" id="ftpDirectory" name="ftpDirectory" 
                       value="<?= htmlspecialchars($savedCredentials['ftpDirectory'] ?? '') ?>" 
                       placeholder="/public_html или /" required>
                <p class="filename-note">Папка data будет загружена в эту директорию. Например, если указать "/public_html", то файлы будут в /public_html/data/</p>
            </div>
            
            <div class="remember-group" style="display: flex; flex-direction: column; align-items: flex-start; gap: 12px; padding: 20px;">
                <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
                    <input type="checkbox" id="ftpSsl" name="ftpSsl" <?= !empty($savedCredentials['ftpSsl']) ? 'checked' : '' ?>>
                    <label for="ftpSsl" style="margin: 0; cursor: pointer; font-weight: 500;">Использовать SSL/TLS (безопасное соединение FTPS)</label>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
                    <input type="checkbox" id="ftpSkipExisting" name="ftpSkipExisting" <?= (!isset($savedCredentials['ftpSkipExisting']) || !empty($savedCredentials['ftpSkipExisting'])) ? 'checked' : '' ?>>
                    <label for="ftpSkipExisting" style="margin: 0; cursor: pointer; font-weight: 500;">Умная синхронизация (пропускать файлы с одинаковым размером)</label>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
                    <input type="checkbox" id="remember" name="remember" checked>
                    <label for="remember" style="margin: 0; cursor: pointer; font-weight: 500;">Запомнить настройки FTP (пароль не сохраняется)</label>
                </div>
            </div>
            
            <div class="button-group">
                <button type="button" id="uploadBtn" class="primary">📤 Загрузить выбранный блог</button>
                <button type="button" id="resetBtn" class="danger">🗑️ Сбросить настройки</button>
            </div>
        </form>
    </div>
    
    <!-- Прогресс загрузки -->
    <div class="upload-progress" id="uploadProgress">
        <div class="progress-header">
            <h3>📤 Загрузка файлов на FTP</h3>
        </div>
        
        <div class="progress-bar-container">
            <div class="progress-bar" id="progressBar" style="width: 0%;">0%</div>
        </div>
        
        <div class="progress-stats">
            <span id="progressStats">Загружено: 0 / 0</span>
            <span id="progressPercent">0%</span>
        </div>
        
        <div class="logs-container" id="logsContainer"></div>
    </div>
    
    <script>
        // Notification system
        function showNotification(message, type = 'info') {
            const container = document.getElementById('notificationContainer');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(-20px)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Toggle saved info
        function toggleSavedInfo() {
            const savedInfo = document.getElementById('savedInfo');
            if (savedInfo) {
                savedInfo.classList.toggle('expanded');
            }
        }

        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });

        // Upload handler
        document.getElementById('uploadBtn').addEventListener('click', function() {
            const ftpServer = document.getElementById('ftpServer').value.trim();
            const ftpUsername = document.getElementById('ftpUsername').value.trim();
            const ftpPassword = document.getElementById('ftpPassword').value;
            const ftpDirectory = document.getElementById('ftpDirectory').value.trim();
            const ftpSsl = document.getElementById('ftpSsl').checked ? '1' : '0';
            const ftpSkipExisting = document.getElementById('ftpSkipExisting').checked ? '1' : '0';
            const remember = document.getElementById('remember').checked;
            
            if (!ftpServer || !ftpUsername || !ftpPassword || !ftpDirectory) {
                showNotification('Заполните все обязательные поля', 'error');
                return;
            }
            
            const uploadBtn = document.getElementById('uploadBtn');
            const resetBtn = document.getElementById('resetBtn');
            const uploadProgress = document.getElementById('uploadProgress');
            const logsContainer = document.getElementById('logsContainer');
            const progressBar = document.getElementById('progressBar');
            const progressStats = document.getElementById('progressStats');
            const progressPercent = document.getElementById('progressPercent');
            
            uploadBtn.disabled = true;
            resetBtn.disabled = true;
            uploadBtn.textContent = '⏳ Загрузка...';
            
            uploadProgress.classList.add('active');
            logsContainer.innerHTML = '';
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            
            // Сохраняем настройки если нужно
            if (remember) {
                fetch('ftp.php', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: new URLSearchParams({
                        ftpServer: ftpServer,
                        ftpUsername: ftpUsername,
                        ftpPassword: 'dummy',
                        ftpDirectory: ftpDirectory,
                        ftpSsl: ftpSsl,
                        ftpSkipExisting: ftpSkipExisting,
                        remember: '1',
                        saveOnly: '1'
                    })
                });
            }
            
            const blogToUpload = document.getElementById('blogToUpload') ? document.getElementById('blogToUpload').value : '';
            
            function addLog(message, level = 'info') {
                const logEntry = document.createElement('div');
                logEntry.className = `log-entry ${level}`;
                
                const time = new Date().toLocaleTimeString('ru-RU');
                logEntry.innerHTML = `
                    <span class="log-time">${time}</span>
                    <span class="log-message">${message}</span>
                `;
                
                logsContainer.appendChild(logEntry);
                logsContainer.scrollTop = logsContainer.scrollHeight;
            }
            
            // Используем EventSource для потоковой загрузки
            const formData = new URLSearchParams();
            formData.append('ftpServer', ftpServer);
            formData.append('ftpUsername', ftpUsername);
            formData.append('ftpPassword', ftpPassword);
            formData.append('ftpDirectory', ftpDirectory);
            formData.append('ftpSsl', ftpSsl);
            formData.append('ftpSkipExisting', ftpSkipExisting);
            formData.append('blogToUpload', blogToUpload);
            
            fetch('ftp_upload_stream.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            })
            .then(response => {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                
                function readStream() {
                    reader.read().then(({ done, value }) => {
                        if (done) {
                            uploadBtn.disabled = false;
                            resetBtn.disabled = false;
                            uploadBtn.textContent = '📤 Загрузить выбранный блог';
                            return;
                        }
                        
                        const chunk = decoder.decode(value, { stream: true });
                        const lines = chunk.split('\n');
                        
                        lines.forEach(line => {
                            if (line.startsWith('data: ')) {
                                try {
                                    const event = JSON.parse(line.substring(6));
                                    
                                    if (event.type === 'log') {
                                        addLog(event.data.message, event.data.level);
                                    } else if (event.type === 'progress') {
                                        const percent = event.data.percent;
                                        progressBar.style.width = percent + '%';
                                        progressBar.textContent = percent + '%';
                                        progressStats.textContent = `Загружено: ${event.data.current} / ${event.data.total}`;
                                        progressPercent.textContent = percent + '%';
                                    } else if (event.type === 'complete') {
                                        if (event.data.failed === 0) {
                                            showNotification(event.data.message, 'success');
                                        } else {
                                            showNotification(event.data.message, 'error');
                                        }
                                        uploadBtn.disabled = false;
                                        resetBtn.disabled = false;
                                        uploadBtn.textContent = '📤 Загрузить папку data';
                                    } else if (event.type === 'error') {
                                        showNotification('Ошибка: ' + event.data.message, 'error');
                                        addLog('Ошибка: ' + event.data.message, 'error');
                                        uploadBtn.disabled = false;
                                        resetBtn.disabled = false;
                                        uploadBtn.textContent = '📤 Загрузить папку data';
                                    }
                                } catch (e) {
                                    console.error('Parse error:', e);
                                }
                            }
                        });
                        
                        readStream();
                    });
                }
                
                readStream();
            })
            .catch(error => {
                showNotification('Ошибка: ' + error.message, 'error');
                addLog('Ошибка: ' + error.message, 'error');
                uploadBtn.disabled = false;
                resetBtn.disabled = false;
                uploadBtn.textContent = '📤 Загрузить папку data';
            });
        });

        // Reset handler
        document.getElementById('resetBtn').addEventListener('click', function() {
            if (confirm('Вы уверены, что хотите сбросить сохранённые настройки FTP?')) {
                const formData = new FormData();
                formData.append('action', 'reset');
                fetch('ftp.php', {
                    method: 'POST',
                    body: formData
                }).then(() => {
                    window.location.reload();
                }).catch(() => {
                    window.location.reload();
                });
            }
        });

        // Enter key submit
        document.getElementById('uploadForm').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('uploadBtn').click();
            }
        });

        // Initialize custom selects in ftp.php
        (function() {
            function initCustomSelects() {
                document.querySelectorAll('select:not([data-custom-select-initialized="true"])').forEach(select => {
                    select.dataset.customSelectInitialized = 'true';
                    select.classList.add('custom-select-native');

                    const wrapper = document.createElement('div');
                    wrapper.className = 'custom-select-wrapper';
                    select.parentNode.insertBefore(wrapper, select);
                    wrapper.appendChild(select);

                    const trigger = document.createElement('button');
                    trigger.type = 'button';
                    trigger.className = 'custom-select-trigger';

                    const valSpan = document.createElement('span');
                    valSpan.className = 'custom-select-value';
                    const selectedOption = select.options[select.selectedIndex] || select.options[0];
                    valSpan.textContent = selectedOption ? selectedOption.textContent : 'Выберите...';

                    const arrowSpan = document.createElement('span');
                    arrowSpan.className = 'custom-select-arrow';
                    arrowSpan.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';

                    trigger.appendChild(valSpan);
                    trigger.appendChild(arrowSpan);
                    wrapper.appendChild(trigger);

                    const popover = document.createElement('div');
                    popover.className = 'custom-select-popover';
                    const popoverInner = document.createElement('div');
                    popoverInner.className = 'custom-select-popover-inner';
                    popover.appendChild(popoverInner);
                    wrapper.appendChild(popover);

                    Array.from(select.options).forEach(opt => {
                        const optBtn = document.createElement('button');
                        optBtn.type = 'button';
                        optBtn.className = 'custom-select-option' + (opt.value === select.value ? ' is-selected' : '');
                        optBtn.dataset.value = opt.value;

                        const textSpan = document.createElement('span');
                        textSpan.textContent = opt.textContent;
                        const checkSpan = document.createElement('span');
                        checkSpan.className = 'custom-option-check';
                        checkSpan.textContent = '✓';

                        optBtn.appendChild(textSpan);
                        optBtn.appendChild(checkSpan);

                        optBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            select.value = opt.value;
                            valSpan.textContent = opt.textContent;
                            wrapper.querySelectorAll('.custom-select-option').forEach(b => b.classList.toggle('is-selected', b === optBtn));
                            wrapper.classList.remove('is-open');
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        });

                        popoverInner.appendChild(optBtn);
                    });

                    trigger.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const isOpen = wrapper.classList.contains('is-open');
                        document.querySelectorAll('.custom-select-wrapper.is-open').forEach(w => w.classList.remove('is-open'));
                        if (!isOpen) {
                            const rect = trigger.getBoundingClientRect();
                            const spaceBelow = window.innerHeight - rect.bottom;
                            const spaceAbove = rect.top;
                            if (spaceBelow < 180 && spaceAbove > spaceBelow) {
                                popover.classList.add('drop-up');
                            } else {
                                popover.classList.remove('drop-up');
                            }
                            wrapper.classList.add('is-open');
                        }
                    });
                });
            }

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.custom-select-wrapper')) {
                    document.querySelectorAll('.custom-select-wrapper.is-open').forEach(w => w.classList.remove('is-open'));
                }
            });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initCustomSelects);
            } else {
                initCustomSelects();
            }
        })();
    </script>
</body>
</html>
