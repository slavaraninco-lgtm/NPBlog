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

$lockoutFile = getDataPath('login_lockouts.json');
$clientIp = getClientIp();
$ipKey = hash('sha256', $clientIp);
$lockouts = [];
if (file_exists($lockoutFile)) {
    $lockouts = json_decode(@file_get_contents($lockoutFile), true) ?: [];
}

$lockout_until = isset($lockouts[$ipKey]['lockout_until']) ? (int)$lockouts[$ipKey]['lockout_until'] : 0;
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
    // Reset lockout state for this IP
    if (isset($lockouts[$ipKey])) {
        unset($lockouts[$ipKey]);
        safeWriteJson($lockoutFile, $lockouts);
    }
    
    // Regenerate session to prevent session fixation
    session_regenerate_id(true);
    
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
    // Increment failed attempts for this IP
    $attempts = isset($lockouts[$ipKey]['attempts']) ? (int)$lockouts[$ipKey]['attempts'] : 0;
    $attempts++;
    
    if ($attempts >= 3) {
        $lockout_until = time() + 15 * 60; // 15 minutes lockout
        $lockouts[$ipKey] = [
            'attempts' => $attempts,
            'lockout_until' => $lockout_until,
            'ip' => $clientIp
        ];
        safeWriteJson($lockoutFile, $lockouts);
        
        echo json_encode([
            'success' => false,
            'message' => 'Превышено количество попыток ввода. Доступ заблокирован на 15 минут.',
            'lockoutTimeRemaining' => 15 * 60
        ]);
    } else {
        $lockouts[$ipKey] = [
            'attempts' => $attempts,
            'lockout_until' => 0,
            'ip' => $clientIp
        ];
        safeWriteJson($lockoutFile, $lockouts);
        $remainingAttempts = 3 - $attempts;
        echo json_encode([
            'success' => false,
            'message' => 'Неверный пароль. Осталось попыток: ' . $remainingAttempts
        ]);
    }
}
exit();
