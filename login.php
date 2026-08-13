<?php
header('Content-Type: application/json; charset=utf-8');

// Include security bootstrap to get functions and start session
require_once __DIR__ . '/security_bootstrap.php';

$data = json_decode(file_get_contents('php://input'), true);
$password = isset($data['password']) ? $data['password'] : '';

$settingsFile = __DIR__ . '/editor_settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

$passwordHash = isset($settings['password_hash']) ? $settings['password_hash'] : '';

if (empty($passwordHash)) {
    echo json_encode(['success' => true, 'message' => 'Пароль не установлен']);
    exit();
}

// Check if locked out
$lockout_until = isset($settings['lockout_until']) ? (int)$settings['lockout_until'] : 0;
if ($lockout_until > time()) {
    $remaining = $lockout_until - time();
    echo json_encode([
        'success' => false,
        'message' => 'Превышено количество попыток ввода. Доступ заблокирован.',
        'lockoutTimeRemaining' => $remaining
    ]);
    exit();
}

// Verify password
if (password_verify($password, $passwordHash)) {
    // Reset lockout state
    $settings['failed_attempts'] = 0;
    $settings['lockout_until'] = 0;
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Create session
    $_SESSION['authenticated'] = true;
    $_SESSION['auth_time'] = time();
    
    // Ensure CSRF token is in session
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    echo json_encode([
        'success' => true,
        'csrf_token' => $_SESSION['csrf_token']
    ]);
} else {
    // Increment failed attempts
    $attempts = isset($settings['failed_attempts']) ? (int)$settings['failed_attempts'] : 0;
    $attempts++;
    $settings['failed_attempts'] = $attempts;
    
    if ($attempts >= 3) {
        $lockout_until = time() + 15 * 60; // 15 minutes lockout
        $settings['lockout_until'] = $lockout_until;
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            'success' => false,
            'message' => 'Превышено количество попыток ввода. Доступ заблокирован на 15 минут.',
            'lockoutTimeRemaining' => 15 * 60
        ]);
    } else {
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $remainingAttempts = 3 - $attempts;
        echo json_encode([
            'success' => false,
            'message' => 'Неверный пароль. Осталось попыток: ' . $remainingAttempts
        ]);
    }
}
exit();
