<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$settingsFile = 'editor_settings.json';

// Загружаем существующие настройки
$existingSettings = [];
if (file_exists($settingsFile)) {
    $content = file_get_contents($settingsFile);
    $existingSettings = json_decode($content, true) ?: [];
}

// Обновляем настройки
if (isset($data['hideEditorModeButtons'])) {
    $existingSettings['hideEditorModeButtons'] = (bool)$data['hideEditorModeButtons'];
}

if (isset($data['language'])) {
    $lang = strtolower(trim($data['language']));
    if (in_array($lang, ['ru', 'en', 'uk', 'lv'])) {
        $existingSettings['language'] = $lang;
        $_SESSION['editor_language'] = $lang;
    }
}

if (isset($data['amoledTheme'])) {
    $existingSettings['amoledTheme'] = (bool)$data['amoledTheme'];
}

if (isset($data['enableUndoRedo'])) {
    $existingSettings['enableUndoRedo'] = (bool)$data['enableUndoRedo'];
}

if (isset($data['smoothTyping'])) {
    $existingSettings['smoothTyping'] = (bool)$data['smoothTyping'];
}

if (isset($data['headerBottomPosition'])) {
    $existingSettings['headerBottomPosition'] = (bool)$data['headerBottomPosition'];
}

if (isset($data['contentWidth'])) {
    $existingSettings['contentWidth'] = (int)$data['contentWidth'];
}

if (isset($data['enableMarkdown'])) {
    $existingSettings['enableMarkdown'] = (bool)$data['enableMarkdown'];
}

if (isset($data['autosaveEnabled'])) {
    $existingSettings['autosaveEnabled'] = (bool)$data['autosaveEnabled'];
}

if (isset($data['autosaveInterval'])) {
    $existingSettings['autosaveInterval'] = (int)$data['autosaveInterval'];
}

if (isset($data['tutorialCompleted'])) {
    $existingSettings['tutorialCompleted'] = (bool)$data['tutorialCompleted'];
}

if (isset($data['headerLayout']) && is_array($data['headerLayout'])) {
    $existingSettings['headerLayout'] = $data['headerLayout'];
}

if (isset($data['headerHeight'])) {
    $existingSettings['headerHeight'] = (int)$data['headerHeight'];
}

if (isset($data['headerTwoRows'])) {
    $existingSettings['headerTwoRows'] = (bool)$data['headerTwoRows'];
}

if (isset($data['data_path'])) {
    $existingSettings['data_path'] = trim($data['data_path']);
}

if (isset($data['blog_paths']) && is_array($data['blog_paths'])) {
    $cleanPaths = [];
    foreach ($data['blog_paths'] as $p) {
        $trimmed = trim($p);
        if ($trimmed !== '') {
            $cleanPaths[] = $trimmed;
        }
    }
    $existingSettings['blog_paths'] = array_values(array_unique($cleanPaths));
    
    if (!empty($cleanPaths)) {
        if (empty($existingSettings['active_blog_path']) || !in_array($existingSettings['active_blog_path'], $cleanPaths)) {
            $existingSettings['active_blog_path'] = $cleanPaths[0];
            $_SESSION['active_blog_path'] = $cleanPaths[0];
        }
    }
}

if (isset($data['active_blog_path'])) {
    $activePath = trim($data['active_blog_path']);
    $existingSettings['active_blog_path'] = $activePath;
    $_SESSION['active_blog_path'] = $activePath;
    // Set data_path for backwards compatibility
    $existingSettings['data_path'] = $activePath;
}

if (isset($data['rss_enabled'])) {
    $existingSettings['rss_enabled'] = (bool)$data['rss_enabled'];
}
if (isset($data['rss_base_url'])) {
    $existingSettings['rss_base_url'] = trim($data['rss_base_url']);
}
if (isset($data['rss_title'])) {
    $existingSettings['rss_title'] = trim($data['rss_title']);
}
if (isset($data['rss_description'])) {
    $existingSettings['rss_description'] = trim($data['rss_description']);
}
if (isset($data['rss_use_first_line'])) {
    $existingSettings['rss_use_first_line'] = (bool)$data['rss_use_first_line'];
}
if (isset($data['rss_content_template'])) {
    $existingSettings['rss_content_template'] = $data['rss_content_template'];
}

if (isset($data['ip_whitelist_enabled'])) {
    $ipWhitelistEnabled = (bool)$data['ip_whitelist_enabled'];
    $existingSettings['ip_whitelist_enabled'] = $ipWhitelistEnabled;
    
    if ($ipWhitelistEnabled) {
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
        
        if (!in_array($clientIp, $allowedIps)) {
            $content = (file_exists($allowedIpsFile) ? "\n" : "") . $clientIp . " # Auto-added on enable\n";
            file_put_contents($allowedIpsFile, $content, FILE_APPEND);
        }
    }
}

if (isset($data['password_enabled'])) {
    $passwordEnabled = (bool)$data['password_enabled'];
    $hasOldPassword = !empty($existingSettings['password_hash']);
    
    if ($hasOldPassword) {
        $isChangingOrDisabling = (!$passwordEnabled) || !empty($data['new_password']);
        if ($isChangingOrDisabling) {
            $oldPassword = isset($data['old_password']) ? $data['old_password'] : '';
            if (empty($oldPassword) || !password_verify($oldPassword, $existingSettings['password_hash'])) {
                echo json_encode(['success' => false, 'error' => 'Неверный старый пароль']);
                exit;
            }
        }
    }
    
    if (!$passwordEnabled) {
        $existingSettings['password_hash'] = '';
        $existingSettings['failed_attempts'] = 0;
        $existingSettings['lockout_until'] = 0;
    } else {
        if (!empty($data['new_password'])) {
            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            $existingSettings['password_hash'] = password_hash($data['new_password'], $algo);
            $existingSettings['failed_attempts'] = 0;
            $existingSettings['lockout_until'] = 0;
        } else if (empty($existingSettings['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'Необходимо указать новый пароль для включения защиты']);
            exit;
        }
    }
}

// Сохраняем настройки атомарно
if (safeWriteJson($settingsFile, $existingSettings)) {
    require_once __DIR__ . '/rss_helper.php';
    generateRssFeed();
    echo json_encode([
        'success' => true,
        'blogUrl' => getDataUrl('blog.html')
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Не удалось сохранить настройки']);
}
?>
