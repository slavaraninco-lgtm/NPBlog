<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Automatically validate CSRF token on POST requests (excluding CLI and login.php)
$currentScript = basename($_SERVER['SCRIPT_NAME']);
if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] === 'POST' && $currentScript !== 'login.php') {
    $csrfHeader = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    $sessionToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if (empty($sessionToken) || empty($csrfHeader) || !hash_equals($sessionToken, $csrfHeader)) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'csrf_error',
            'message' => 'Неверный или отсутствующий CSRF-токен'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

if (!function_exists('validateSafePath')) {
    function validateSafePath($baseDir, $filename) {
        $realBase = realpath($baseDir);
        if ($realBase === false) {
            // Attempt to create baseDir if it doesn't exist
            if (!@mkdir($baseDir, 0777, true)) {
                header('HTTP/1.1 500 Internal Server Error');
                die(json_encode([
                    'success' => false,
                    'error' => 'filesystem_error',
                    'message' => 'Не удалось создать директорию: ' . $baseDir
                ], JSON_UNESCAPED_UNICODE));
            }
            $realBase = realpath($baseDir);
            if ($realBase === false) {
                header('HTTP/1.1 500 Internal Server Error');
                die(json_encode([
                    'success' => false,
                    'error' => 'filesystem_error',
                    'message' => 'Не удалось разрешить путь к директории: ' . $baseDir
                ], JSON_UNESCAPED_UNICODE));
            }
        }
        
        $realBase = str_replace('\\', '/', $realBase);
        $filename = str_replace('\\', '/', $filename);
        
        // Remove trailing slash on baseDir to ensure proper boundary match
        $realBase = rtrim($realBase, '/');
        
        // Strip the base directory from the beginning of filename if it's absolute
        $baseDirClean = rtrim(str_replace('\\', '/', $baseDir), '/');
        if (strpos($filename, $baseDirClean . '/') === 0) {
            $filename = substr($filename, strlen($baseDirClean) + 1);
        } elseif (strpos($filename, $realBase . '/') === 0) {
            $filename = substr($filename, strlen($realBase) + 1);
        }
        
        // Split filename into parts and sanitize path traversal
        $parts = explode('/', $filename);
        $safeParts = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (empty($safeParts)) {
                    header('HTTP/1.1 400 Bad Request');
                    die(json_encode([
                        'success' => false,
                        'error' => 'security_violation',
                        'message' => 'Обнаружена попытка выхода за пределы разрешенного каталога.'
                    ], JSON_UNESCAPED_UNICODE));
                }
                array_pop($safeParts);
            } else {
                $safeParts[] = $part;
            }
        }
        
        $targetPath = $realBase . '/' . implode('/', $safeParts);
        
        // Resolve absolute target path if file exists
        $realTarget = realpath($targetPath);
        if ($realTarget !== false) {
            $realTarget = str_replace('\\', '/', $realTarget);
            if (strpos($realTarget, $realBase . '/') !== 0 && $realTarget !== $realBase) {
                header('HTTP/1.1 400 Bad Request');
                die(json_encode([
                    'success' => false,
                    'error' => 'security_violation',
                    'message' => 'Обнаружен обход пути (Path Traversal).'
                ], JSON_UNESCAPED_UNICODE));
            }
            return $realTarget;
        }
        
        return $targetPath;
    }
}

function getDataPath($subpath = '') {
    $settingsFile = __DIR__ . '/editor_settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }
    
    $activePath = '';
    if (!empty($_SESSION['active_blog_path'])) {
        $activePath = $_SESSION['active_blog_path'];
    } elseif (!empty($settings['active_blog_path'])) {
        $activePath = $settings['active_blog_path'];
        $_SESSION['active_blog_path'] = $activePath;
    } elseif (!empty($settings['blog_paths']) && is_array($settings['blog_paths']) && count($settings['blog_paths']) > 0) {
        $activePath = $settings['blog_paths'][0];
        $_SESSION['active_blog_path'] = $activePath;
    } elseif (!empty($settings['data_path'])) {
        $activePath = $settings['data_path'];
        $_SESSION['active_blog_path'] = $activePath;
    } else {
        $activePath = __DIR__ . '/data';
    }

    $dataDir = $activePath;
    
    // Make absolute if relative
    if (strpos($dataDir, '/') !== 0 && strpos($dataDir, ':\\') !== 1) {
        $dataDir = __DIR__ . '/' . ltrim($dataDir, '/');
    }

    if (!is_dir($dataDir)) {
        if (!@mkdir($dataDir, 0777, true)) {
            // Only fallback if we literally cannot create the directory
            $dataDir = __DIR__ . '/data/';
        }
    } else {
        if (!is_writable($dataDir)) {
            $isApiRequest = (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) || 
                            ($_SERVER['REQUEST_METHOD'] === 'POST') || 
                            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
            if ($isApiRequest) {
                header('HTTP/1.1 403 Forbidden');
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode([
                    'success' => false,
                    'error' => 'permission_denied',
                    'message' => 'Нет прав на запись к папке блога: ' . $dataDir
                ], JSON_UNESCAPED_UNICODE));
            } else {
                die('<html><head><meta charset="utf-8"><title>Ошибка доступа</title></head><body style="padding:40px;font-family:sans-serif;background:#fff;color:#333;"><h2>Ошибка доступа</h2><p>У веб-сервера нет прав на запись к папке блога: <b>' . htmlspecialchars($dataDir) . '</b></p><p>Пожалуйста, настройте права доступа (например, chmod 777 или chown), чтобы редактор мог сохранять статьи.</p><p><button onclick="window.history.back()">Вернуться назад</button></p></body></html>');
            }
        }
    }
    
    // Ensure trailing slash and correct path separators
    $dataDir = rtrim(str_replace('\\', '/', $dataDir), '/') . '/';
    return $dataDir . $subpath;
}

function getDataUrl($subpath = '') {
    $dataDir = getDataPath();
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']) : '';
    $docRoot = rtrim($docRoot, '/');
    
    if (!empty($docRoot) && strpos($dataDir, $docRoot) === 0) {
        $webPrefix = '/' . ltrim(substr($dataDir, strlen($docRoot)), '/');
        $webPrefix = rtrim($webPrefix, '/') . '/';
    } else {
        // Find script directory prefix to handle subfolders correctly
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $subDir = '';
        if (!empty($scriptName) && php_sapi_name() !== 'cli') {
            $subDir = rtrim(dirname($scriptName), '/\\');
        }
        $webPrefix = (!empty($subDir) ? $subDir : '') . '/serve_data.php?file=';
    }
    return $webPrefix . $subpath;
}

if (!function_exists('getClientIp')) {
    function getClientIp() {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
}

if (!function_exists('safeWriteJson')) {
    function safeWriteJson($filePath, $data) {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $tmpFile = $filePath . '.' . uniqid('tmp_', true);
        if (@file_put_contents($tmpFile, $json, LOCK_EX) === false) {
            return false;
        }
        @chmod($tmpFile, 0666);
        if (@rename($tmpFile, $filePath)) {
            return true;
        }
        // Fallback if rename fails (e.g. on some Windows file locks)
        $res = @file_put_contents($filePath, $json, LOCK_EX) !== false;
        @unlink($tmpFile);
        return $res;
    }
}

if (!function_exists('renderIpBlockedPage')) {
    function renderIpBlockedPage($clientIp, $settings) {
        ?>
        <!DOCTYPE html>
        <html lang="ru" <?php echo isset($settings['amoledTheme']) && $settings['amoledTheme'] ? 'data-amoled="true"' : ''; ?>>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Доступ заблокирован</title>
            <script>
                const savedTheme = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-theme', savedTheme);
            </script>
            <style>
                :root {
                    --bg-color: #ffffff;
                    --text-color: #333333;
                    --border-color: #000000;
                    --shadow-color: rgba(0, 0, 0, 0.08);
                    --danger-color: #d32f2f;
                    --card-bg: #ffffff;
                }
                [data-theme="dark"] {
                    --bg-color: #121212;
                    --text-color: #f5f5f5;
                    --border-color: #ffffff;
                    --shadow-color: rgba(0, 0, 0, 0.5);
                    --danger-color: #f44336;
                    --card-bg: #1e1e1e;
                }
                html[data-amoled="true"] {
                    --bg-color: #000000;
                    --card-bg: #000000;
                }
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                    background-color: var(--bg-color);
                    color: var(--text-color);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    padding: 20px;
                    transition: background-color 0.3s, color 0.3s;
                }
                .block-dialog {
                    background: var(--card-bg);
                    border: 2px solid var(--border-color);
                    border-radius: 16px;
                    padding: 40px 32px;
                    width: 100%;
                    max-width: 440px;
                    box-shadow: 0 12px 32px var(--shadow-color);
                    text-align: center;
                    animation: dialogIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
                }
                @keyframes dialogIn {
                    from { opacity: 0; transform: scale(0.92) translateY(10px); }
                    to { opacity: 1; transform: scale(1) translateY(0); }
                }
                .icon-wrapper {
                    width: 72px;
                    height: 72px;
                    background: rgba(211, 47, 47, 0.1);
                    color: var(--danger-color);
                    border: 2px solid var(--danger-color);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 32px;
                    margin: 0 auto 24px auto;
                }
                [data-theme="dark"] .icon-wrapper {
                    background: rgba(244, 67, 54, 0.15);
                }
                h1 {
                    font-size: 22px;
                    font-weight: 800;
                    margin-bottom: 12px;
                    letter-spacing: -0.02em;
                }
                p {
                    font-size: 14px;
                    line-height: 1.6;
                    opacity: 0.8;
                    margin-bottom: 24px;
                }
                .ip-box {
                    background: var(--bg-color);
                    border: 1px solid var(--border-color);
                    border-radius: 8px;
                    font-family: monospace;
                    font-size: 16px;
                    font-weight: bold;
                    letter-spacing: 0.5px;
                    margin-bottom: 24px;
                    display: inline-block;
                    padding: 8px 16px;
                    color: var(--danger-color);
                }
                .hint-text {
                    font-size: 12px;
                    opacity: 0.5;
                    line-height: 1.5;
                }
            </style>
        </head>
        <body>
            <div class="block-dialog">
                <div class="icon-wrapper">🚫</div>
                <h1>Доступ ограничен</h1>
                <p>Ваш IP-адрес не входит в список разрешенных адресов для входа в панель управления NPBlog.</p>
                <div class="ip-box"><?php echo htmlspecialchars($clientIp); ?></div>

            </div>
        </body>
        </html>
        <?php
    }
}

// Check authentication
$settingsFile = __DIR__ . '/editor_settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

// --- IP Whitelist Check ---
if (isset($settings['ip_whitelist_enabled']) && $settings['ip_whitelist_enabled'] && php_sapi_name() !== 'cli') {
    $clientIp = getClientIp();
    $allowedIpsFile = __DIR__ . '/allowed_ips.txt';
    $allowedIps = [];
    if (file_exists($allowedIpsFile)) {
        $lines = file($allowedIpsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $cleaned = trim(preg_replace('/#.*/', '', $line));
            if ($cleaned !== '') {
                $allowedIps[] = $cleaned;
            }
        }
    }
    
    $isAllowed = false;
    foreach ($allowedIps as $ip) {
        if ($clientIp === $ip) {
            $isAllowed = true;
            break;
        }
    }
    
    if (!$isAllowed) {
        $currentScript = basename($_SERVER['SCRIPT_NAME']);
        if ($currentScript === 'index.php' || $currentScript === 'login.php') {
            renderIpBlockedPage($clientIp, $settings);
            exit();
        } else {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false, 
                'error' => 'ip_blocked', 
                'message' => 'Доступ заблокирован для вашего IP: ' . $clientIp
            ]);
            exit();
        }
    }
}

$passwordHash = isset($settings['password_hash']) ? $settings['password_hash'] : '';

if (!empty($passwordHash) && php_sapi_name() !== 'cli') {
    // Session is valid for 24 hours (86400 seconds)
    $isAuthorized = isset($_SESSION['authenticated']) && 
                    $_SESSION['authenticated'] === true && 
                    isset($_SESSION['auth_time']) && 
                    (time() - $_SESSION['auth_time'] < 86400);
                    
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    
    if (!$isAuthorized && $currentScript !== 'login.php' && $currentScript !== 'serve_data.php') {
        if ($currentScript === 'index.php') {
            // Render beautiful login page and exit
            renderLoginPage($settings);
            exit();
        } else {
            // API endpoints get 401 response
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'unauthorized', 'message' => 'Необходима авторизация']);
            exit();
        }
    }
}

function getClientLockoutInfo() {
    $lockoutFile = getDataPath('login_lockouts.json');
    $clientIp = getClientIp();
    $key = hash('sha256', $clientIp);
    $data = [];
    if (file_exists($lockoutFile)) {
        $data = json_decode(@file_get_contents($lockoutFile), true) ?: [];
    }
    if (isset($data[$key])) {
        $lockoutUntil = isset($data[$key]['lockout_until']) ? (int)$data[$key]['lockout_until'] : 0;
        $attempts = isset($data[$key]['attempts']) ? (int)$data[$key]['attempts'] : 0;
        if ($lockoutUntil > time()) {
            return ['is_locked' => true, 'remaining' => $lockoutUntil - time(), 'attempts' => $attempts];
        }
    }
    return ['is_locked' => false, 'remaining' => 0, 'attempts' => 0];
}

function renderLoginPage($settings) {
    $lockoutInfo = getClientLockoutInfo();
    $remaining_lockout = $lockoutInfo['remaining'];
    $is_locked = $lockoutInfo['is_locked'];
    
    $lockout_msg = '';
    if ($is_locked) {
        $minutes = ceil($remaining_lockout / 60);
        $lockout_msg = "Превышено количество попыток ввода. Доступ заблокирован на $minutes мин.";
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="ru" <?php echo isset($settings['amoledTheme']) && $settings['amoledTheme'] ? 'data-amoled="true"' : ''; ?>>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
        <title>Вход в NPBlog</title>
        <script>
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            
            // Global Fetch Interceptor for login page
            (function() {
                const originalFetch = window.fetch;
                window.fetch = function(input, init) {
                    if (!init) init = {};
                    if (!init.headers) init.headers = {};
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    if (csrfToken) {
                        if (init.headers instanceof Headers) {
                            init.headers.set('X-CSRF-Token', csrfToken);
                        } else {
                            init.headers['X-CSRF-Token'] = csrfToken;
                        }
                    }
                    return originalFetch(input, init);
                };
            })();
        </script>
        <style>
            :root {
                --bg-color: #ffffff;
                --text-color: #333333;
                --border-color: #000000;
                --shadow-color: rgba(0, 0, 0, 0.08);
                --danger-color: #d32f2f;
                --input-focus-shadow: rgba(0, 0, 0, 0.1);
            }
            [data-theme="dark"] {
                --bg-color: #121212;
                --text-color: #f5f5f5;
                --border-color: #ffffff;
                --shadow-color: rgba(0, 0, 0, 0.5);
                --danger-color: #f44336;
                --input-focus-shadow: rgba(255, 255, 255, 0.25);
            }
            html[data-amoled="true"] {
                --bg-color: #000000;
            }
            html[data-theme="dark"][data-amoled="true"] .login-dialog {
                border-color: #222222;
                box-shadow: 0 12px 32px rgba(0, 0, 0, 0.8);
            }
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }
            body {
                font-family: Arial, sans-serif;
                background-color: var(--bg-color);
                color: var(--text-color);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                padding: 20px;
                transition: background-color 0.3s, color 0.3s;
            }
            .login-dialog {
                background: var(--bg-color);
                border: 1px solid var(--border-color);
                border-radius: 12px;
                padding: 32px;
                width: 100%;
                max-width: 360px;
                box-shadow: 0 12px 32px var(--shadow-color);
                box-sizing: border-box;
                animation: dialogContentIn 0.28s cubic-bezier(0.34, 1.2, 0.64, 1) forwards;
            }
            @keyframes dialogContentIn {
                from { opacity: 0; transform: scale(0.95); }
                to { opacity: 1; transform: scale(1); }
            }
            .login-header {
                text-align: center;
                margin-bottom: 24px;
            }
            .login-title {
                font-size: 24px;
                font-weight: 700;
                margin-bottom: 4px;
                letter-spacing: -0.02em;
            }
            .login-subtitle {
                font-size: 13px;
                opacity: 0.6;
            }
            .form-group {
                width: 100%;
                margin-bottom: 20px;
                box-sizing: border-box;
            }
            .form-label {
                display: block;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 8px;
            }
            .input-wrapper {
                position: relative;
                width: 100%;
            }
            .form-input {
                width: 100%;
                box-sizing: border-box;
                padding: 10px 40px 10px 12px;
                background: var(--bg-color);
                border: 1px solid var(--border-color);
                border-radius: 8px;
                color: var(--text-color);
                font-size: 14px;
                font-family: Arial, sans-serif;
                outline: none;
                transition: box-shadow 0.15s ease;
            }
            .form-input:focus {
                box-shadow: 0 0 0 2px var(--input-focus-shadow);
            }
            .toggle-password {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: var(--text-color);
                cursor: pointer;
                font-size: 16px;
                outline: none;
                padding: 4px;
                opacity: 0.5;
                transition: opacity 0.15s ease;
            }
            .toggle-password:hover {
                opacity: 1;
            }
            .submit-btn {
                width: 100%;
                height: 38px;
                box-sizing: border-box;
                background: var(--bg-color);
                color: var(--text-color);
                border: 1px solid var(--border-color);
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .submit-btn:hover:not(:disabled) {
                background: var(--text-color);
                color: var(--bg-color);
            }
            .submit-btn:disabled {
                opacity: 0.4;
                cursor: not-allowed;
            }
            .error-message {
                color: var(--danger-color);
                font-size: 13px;
                margin-top: 12px;
                text-align: center;
                display: none;
                animation: fadeIn 0.15s ease;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .lockout-info {
                padding: 12px;
                background: rgba(211, 47, 47, 0.08);
                border: 1px solid var(--danger-color);
                border-radius: 8px;
                color: var(--danger-color);
                font-size: 13px;
                line-height: 1.4;
                margin-bottom: 20px;
                text-align: center;
            }
            [data-theme="dark"] .lockout-info {
                background: rgba(244, 67, 54, 0.1);
            }
        </style>
    </head>
    <body>
        <div class="login-dialog">
            <div class="login-header">
                <div class="login-title">NPBlog</div>
                <div class="login-subtitle">Панель управления редактором</div>
            </div>
            
            <div class="lockout-info" id="lockoutInfo" style="display: <?php echo $is_locked ? 'block' : 'none'; ?>;">
                <?php echo $lockout_msg; ?>
            </div>
            
            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label class="form-label" for="password">Пароль доступа</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" class="form-input" required placeholder="Введите ваш пароль" <?php echo $is_locked ? 'disabled' : ''; ?> autocomplete="current-password">
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility()" aria-label="Показать/скрыть пароль">👁️</button>
                    </div>
                </div>
                
                <button type="submit" id="submitBtn" class="submit-btn" <?php echo $is_locked ? 'disabled' : ''; ?>>Войти</button>
                <div class="error-message" id="errorMessage"></div>
            </form>
        </div>

        <script>
            let lockoutTimeRemaining = <?php echo $is_locked ? $remaining_lockout : 0; ?>;
            
            if (lockoutTimeRemaining > 0) {
                startLockoutCountdown();
            }

            function togglePasswordVisibility() {
                const passwordInput = document.getElementById('password');
                const btn = document.querySelector('.toggle-password');
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    btn.textContent = '🔒';
                } else {
                    passwordInput.type = 'password';
                    btn.textContent = '👁️';
                }
            }

            async function handleLogin(e) {
                e.preventDefault();
                const password = document.getElementById('password').value;
                const errDiv = document.getElementById('errorMessage');
                const submitBtn = document.getElementById('submitBtn');
                
                errDiv.style.display = 'none';
                submitBtn.disabled = true;
                
                try {
                    const response = await fetch('login.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ password })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        window.location.reload();
                    } else {
                        errDiv.textContent = data.message || 'Ошибка входа';
                        errDiv.style.display = 'block';
                        
                        if (data.lockoutTimeRemaining) {
                            lockoutTimeRemaining = data.lockoutTimeRemaining;
                            document.getElementById('password').disabled = true;
                            submitBtn.disabled = true;
                            document.getElementById('lockoutInfo').style.display = 'block';
                            updateLockoutMessage();
                            startLockoutCountdown();
                        } else {
                            submitBtn.disabled = false;
                        }
                    }
                } catch (err) {
                    errDiv.textContent = 'Произошла сетевая ошибка';
                    errDiv.style.display = 'block';
                    submitBtn.disabled = false;
                }
            }

            function startLockoutCountdown() {
                const interval = setInterval(() => {
                    lockoutTimeRemaining--;
                    if (lockoutTimeRemaining <= 0) {
                        clearInterval(interval);
                        document.getElementById('password').disabled = false;
                        document.getElementById('submitBtn').disabled = false;
                        document.getElementById('lockoutInfo').style.display = 'none';
                        document.getElementById('errorMessage').style.display = 'none';
                    } else {
                        updateLockoutMessage();
                    }
                }, 1000);
            }

            function updateLockoutMessage() {
                const minutes = Math.ceil(lockoutTimeRemaining / 60);
                document.getElementById('lockoutInfo').innerHTML = `Превышено количество попыток ввода. Доступ заблокирован на ${minutes} мин.`;
            }
        </script>
    </body>
    </html>
    <?php
}


