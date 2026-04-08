<?php

define('CREDENTIALS_FILE', 'ftp.json');

function saveCredentials($data) {
    $data['saved_at'] = date('Y-m-d H:i:s');
    file_put_contents(CREDENTIALS_FILE, json_encode($data, JSON_PRETTY_PRINT));
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

if (isset($_GET['reset'])) {
    resetCredentials();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $ftpServer = $_POST['ftpServer'] ?? '';
    $ftpUsername = $_POST['ftpUsername'] ?? '';
    $ftpPassword = $_POST['ftpPassword'] ?? '';
    $ftpDirectory = $_POST['ftpDirectory'] ?? '';

    if (empty($ftpServer) || empty($ftpUsername) || empty($ftpDirectory)) {
        echo json_encode(['success' => false, 'message' => 'Заполните все обязательные поля']);
        exit;
    }

    if (isset($_POST['remember'])) {
        saveCredentials([
            'ftpServer' => $ftpServer,
            'ftpUsername' => $ftpUsername,
            'ftpDirectory' => $ftpDirectory
        ]);
    }

    $localDataDir = __DIR__ . '/data';
    if (!is_dir($localDataDir)) {
        echo json_encode(['success' => false, 'message' => 'Папка data не найдена']);
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

    // Загружаем папку data
    $result = uploadDirectory($connId, $localDataDir, $ftpDirectory . '/data');
    
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
            'message' => "Папка data успешно загружена! Загружено файлов: {$result['uploaded']}"
        ]);
    }
    exit;
}

$savedCredentials = loadCredentials();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            z-index: 10000;
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
        </div>
    </div>
    <?php endif; ?>
    
    <div class="form-container">
        <form id="uploadForm">
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
            
            <div class="remember-group">
                <input type="checkbox" id="remember" name="remember" checked>
                <label for="remember">Запомнить настройки FTP (пароль не сохраняется)</label>
            </div>
            
            <div class="button-group">
                <button type="button" id="uploadBtn" class="primary">📤 Загрузить папку data</button>
                <button type="button" id="resetBtn" class="danger">🗑️ Сбросить настройки</button>
            </div>
        </form>
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
            const remember = document.getElementById('remember').checked;
            
            if (!ftpServer || !ftpUsername || !ftpPassword || !ftpDirectory) {
                showNotification('Заполните все обязательные поля', 'error');
                return;
            }
            
            const uploadBtn = document.getElementById('uploadBtn');
            uploadBtn.disabled = true;
            uploadBtn.textContent = '⏳ Загрузка папки data...';
            
            showNotification('Начинается загрузка папки data на FTP сервер...', 'info');
            
            const formData = new FormData();
            formData.append('ftpServer', ftpServer);
            formData.append('ftpUsername', ftpUsername);
            formData.append('ftpPassword', ftpPassword);
            formData.append('ftpDirectory', ftpDirectory);
            if (remember) formData.append('remember', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showNotification('Ошибка: ' + data.message, 'error');
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = '📤 Загрузить папку data';
                }
            })
            .catch(error => {
                showNotification('Ошибка: ' + error.message, 'error');
                uploadBtn.disabled = false;
                uploadBtn.textContent = '📤 Загрузить папку data';
            });
        });

        // Reset handler
        document.getElementById('resetBtn').addEventListener('click', function() {
            if (confirm('Вы уверены, что хотите сбросить сохранённые настройки FTP?')) {
                window.location.href = window.location.href + '?reset=1';
            }
        });

        // Enter key submit
        document.getElementById('uploadForm').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('uploadBtn').click();
            }
        });
    </script>
</body>
</html>
