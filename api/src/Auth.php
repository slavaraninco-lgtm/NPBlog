<?php
declare(strict_types=1);

namespace NPBlog\Api;

class Auth
{
    private static ?array $authenticatedToken = null;

    /**
     * Get path to API tokens storage
     */
    private static function getTokensFilePath(): string
    {
        if (function_exists('getDataPath')) {
            return getDataPath('api_tokens.json');
        }
        return (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2)) . '/data/api_tokens.json';
    }

    /**
     * Load all stored tokens
     */
    public static function loadTokens(): array
    {
        $file = self::getTokensFilePath();
        if (!file_exists($file)) {
            return [];
        }
        $data = json_decode(@file_get_contents($file) ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save tokens to file
     */
    public static function saveTokens(array $tokens): bool
    {
        $file = self::getTokensFilePath();
        if (function_exists('safeWriteJson')) {
            return safeWriteJson($file, $tokens);
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return @file_put_contents($file, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }

    /**
     * Create and store a new API token for mobile device
     */
    public static function createToken(?string $deviceName = null, int $ttlSeconds = 2592000): array
    {
        // 2592000 seconds = 30 days
        $plainToken = 'npb_' . bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainToken);
        $tokenId = 'tok_' . bin2hex(random_bytes(8));
        $now = time();

        $clientIp = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $deviceName = trim($deviceName ?? '') ?: 'Mobile Device';

        $tokenData = [
            'id' => $tokenId,
            'hash' => $hash,
            'device_name' => $deviceName,
            'ip_created' => $clientIp,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => $now,
            'expires_at' => $now + $ttlSeconds,
            'last_used_at' => $now
        ];

        $tokens = self::loadTokens();
        // Clean expired tokens
        $tokens = array_values(array_filter($tokens, function ($item) use ($now) {
            return isset($item['expires_at']) && $item['expires_at'] > $now;
        }));

        $tokens[] = $tokenData;
        self::saveTokens($tokens);

        return [
            'access_token' => $plainToken,
            'token_type' => 'Bearer',
            'token_id' => $tokenId,
            'device_name' => $deviceName,
            'expires_at' => $now + $ttlSeconds,
            'expires_in' => $ttlSeconds,
            'created_at' => $now
        ];
    }

    /**
     * Extract token from request headers or query
     */
    public static function extractBearerToken(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = '';

        if (!empty($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (!empty($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (!empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check custom header
        if (!empty($_SERVER['HTTP_X_API_TOKEN'])) {
            return $_SERVER['HTTP_X_API_TOKEN'];
        }

        // Query parameter fallback (useful for image previews/downloads)
        if (!empty($_GET['api_token'])) {
            return (string)$_GET['api_token'];
        }

        return null;
    }

    /**
     * Validate token string and refresh last_used_at
     */
    public static function validateToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $tokens = self::loadTokens();
        $now = time();
        $updated = false;
        $matched = null;

        foreach ($tokens as &$item) {
            if (isset($item['hash']) && hash_equals($item['hash'], $hash)) {
                if (isset($item['expires_at']) && $item['expires_at'] > $now) {
                    $item['last_used_at'] = $now;
                    $matched = $item;
                    $updated = true;
                    break;
                }
            }
        }

        if ($updated) {
            self::saveTokens($tokens);
        }

        return $matched;
    }

    /**
     * Revoke token by ID
     */
    public static function revokeToken(string $tokenId): bool
    {
        $tokens = self::loadTokens();
        $filtered = array_values(array_filter($tokens, function ($item) use ($tokenId) {
            return ($item['id'] ?? '') !== $tokenId;
        }));

        if (count($filtered) !== count($tokens)) {
            return self::saveTokens($filtered);
        }
        return false;
    }

    /**
     * Revoke current request's token
     */
    public static function revokeCurrentToken(): bool
    {
        $token = self::extractBearerToken();
        if (!$token) {
            return false;
        }

        $hash = hash('sha256', $token);
        $tokens = self::loadTokens();
        $filtered = array_values(array_filter($tokens, function ($item) use ($hash) {
            return ($item['hash'] ?? '') !== $hash;
        }));

        return self::saveTokens($filtered);
    }

    /**
     * List active tokens (excluding hashes)
     */
    public static function listTokens(): array
    {
        $tokens = self::loadTokens();
        $now = time();
        $result = [];

        foreach ($tokens as $item) {
            if (isset($item['expires_at']) && $item['expires_at'] > $now) {
                $result[] = [
                    'id' => $item['id'] ?? '',
                    'device_name' => $item['device_name'] ?? 'Mobile Device',
                    'ip_created' => $item['ip_created'] ?? '',
                    'created_at' => $item['created_at'] ?? 0,
                    'expires_at' => $item['expires_at'] ?? 0,
                    'last_used_at' => $item['last_used_at'] ?? 0,
                    'is_current' => self::$authenticatedToken !== null &&
                        (($item['id'] ?? '') === (self::$authenticatedToken['id'] ?? ''))
                ];
            }
        }

        return $result;
    }

    /**
     * Check IP Whitelist
     */
    public static function checkIpWhitelist(): void
    {
        $settingsFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2)) . '/editor_settings.json';
        if (!file_exists($settingsFile)) {
            return;
        }

        $settings = json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: [];
        if (!empty($settings['ip_whitelist_enabled'])) {
            $clientIp = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $allowedIpsFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2)) . '/allowed_ips.txt';
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

            if (!in_array($clientIp, $allowedIps, true)) {
                Response::error(
                    'ip_blocked',
                    "Доступ заблокирован для вашего IP: $clientIp (IP не в белом списке)",
                    403
                );
            }
        }
    }

    /**
     * Check Brute Force Lockout
     */
    public static function checkLockoutInfo(): array
    {
        $lockoutFile = function_exists('getDataPath') ? getDataPath('login_lockouts.json') : (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2)) . '/data/login_lockouts.json';
        $clientIp = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ipKey = hash('sha256', $clientIp);

        $lockouts = [];
        if (file_exists($lockoutFile)) {
            $lockouts = json_decode(@file_get_contents($lockoutFile) ?: '[]', true) ?: [];
        }

        $lockoutUntil = isset($lockouts[$ipKey]['lockout_until']) ? (int)$lockouts[$ipKey]['lockout_until'] : 0;
        $attempts = isset($lockouts[$ipKey]['attempts']) ? (int)$lockouts[$ipKey]['attempts'] : 0;

        if ($lockoutUntil > time()) {
            return [
                'is_locked' => true,
                'remaining' => $lockoutUntil - time(),
                'attempts' => $attempts
            ];
        }

        return [
            'is_locked' => false,
            'remaining' => 0,
            'attempts' => $attempts
        ];
    }

    /**
     * Register a failed login attempt
     */
    public static function registerFailedAttempt(): array
    {
        $lockoutFile = function_exists('getDataPath') ? getDataPath('login_lockouts.json') : (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2)) . '/data/login_lockouts.json';
        $clientIp = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ipKey = hash('sha256', $clientIp);

        $lockouts = [];
        if (file_exists($lockoutFile)) {
            $lockouts = json_decode(@file_get_contents($lockoutFile) ?: '[]', true) ?: [];
        }

        $attempts = isset($lockouts[$ipKey]['attempts']) ? (int)$lockouts[$ipKey]['attempts'] : 0;
        $attempts++;

        if ($attempts >= 3) {
            $lockoutUntil = time() + (15 * 60);
            $lockouts[$ipKey] = [
                'attempts' => $attempts,
                'lockout_until' => $lockoutUntil,
                'ip' => $clientIp
            ];
            if (function_exists('safeWriteJson')) {
                safeWriteJson($lockoutFile, $lockouts);
            } else {
                @file_put_contents($lockoutFile, json_encode($lockouts));
            }
            return [
                'is_locked' => true,
                'remaining' => 15 * 60,
                'attempts' => $attempts
            ];
        }

        $lockouts[$ipKey] = [
            'attempts' => $attempts,
            'lockout_until' => 0,
            'ip' => $clientIp
        ];
        if (function_exists('safeWriteJson')) {
            safeWriteJson($lockoutFile, $lockouts);
        } else {
            @file_put_contents($lockoutFile, json_encode($lockouts));
        }

        return [
            'is_locked' => false,
            'remaining' => 0,
            'attempts' => $attempts
        ];
    }

    /**
     * Reset lockout on successful login
     */
    public static function resetLockout(): void
    {
        $lockoutFile = function_exists('getDataPath') ? getDataPath('login_lockouts.json') : (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2)) . '/data/login_lockouts.json';
        $clientIp = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ipKey = hash('sha256', $clientIp);

        if (file_exists($lockoutFile)) {
            $lockouts = json_decode(@file_get_contents($lockoutFile) ?: '[]', true) ?: [];
            if (isset($lockouts[$ipKey])) {
                unset($lockouts[$ipKey]);
                if (function_exists('safeWriteJson')) {
                    safeWriteJson($lockoutFile, $lockouts);
                } else {
                    @file_put_contents($lockoutFile, json_encode($lockouts));
                }
            }
        }
    }

    /**
     * Verify master password from editor_settings.json
     */
    public static function verifyPassword(string $password): bool
    {
        $settingsFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2)) . '/editor_settings.json';
        if (!file_exists($settingsFile)) {
            return true;
        }

        $settings = json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: [];
        $passwordHash = $settings['password_hash'] ?? '';

        if (empty($passwordHash)) {
            return true; // Password not configured yet
        }

        return password_verify($password, $passwordHash);
    }

    /**
     * Check if password is set in settings
     */
    public static function isPasswordSet(): bool
    {
        $settingsFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 2)) . '/editor_settings.json';
        if (!file_exists($settingsFile)) {
            return false;
        }

        $settings = json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: [];
        return !empty($settings['password_hash']);
    }

    /**
     * Check if request is authenticated
     */
    public static function check(): bool
    {
        // 1. If password is not configured on server, access is open
        if (!self::isPasswordSet()) {
            return true;
        }

        // 2. Validate Bearer token
        $token = self::extractBearerToken();
        if ($token !== null) {
            $tokenData = self::validateToken($token);
            if ($tokenData !== null) {
                self::$authenticatedToken = $tokenData;
                return true;
            }
        }

        // 3. Fallback: check active PHP session (for web editor compatibility)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            $authTime = $_SESSION['auth_time'] ?? 0;
            if (time() - $authTime < 86400) {
                return true;
            }
        }

        return false;
    }

    /**
     * Enforce authentication guard
     */
    public static function requireAuth(): void
    {
        self::checkIpWhitelist();

        if (!self::check()) {
            Response::error(
                'unauthorized',
                'Необходима авторизация. Передайте валидный токен в заголовке Authorization: Bearer <token>',
                401
            );
        }
    }

    /**
     * Get currently authenticated token details
     */
    public static function getAuthenticatedToken(): ?array
    {
        return self::$authenticatedToken;
    }
}
