<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;

class AuthController
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(array $params, array $body): void
    {
        Auth::checkIpWhitelist();

        $lockout = Auth::checkLockoutInfo();
        if ($lockout['is_locked']) {
            Response::error(
                'too_many_attempts',
                'Превышено количество попыток ввода. Доступ заблокирован на ' . ceil($lockout['remaining'] / 60) . ' мин.',
                429,
                ['lockout_time_remaining' => $lockout['remaining']]
            );
        }

        $password = (string)($body['password'] ?? '');
        $deviceName = (string)($body['device_name'] ?? 'Mobile Device');

        // Check if password verification passes
        if (!Auth::isPasswordSet() || Auth::verifyPassword($password)) {
            Auth::resetLockout();

            $tokenData = Auth::createToken($deviceName);

            Response::json($tokenData, 200, 'Авторизация успешна');
        } else {
            $lockoutInfo = Auth::registerFailedAttempt();

            if ($lockoutInfo['is_locked']) {
                Response::error(
                    'account_locked',
                    'Превышено количество попыток ввода. Доступ заблокирован на 15 минут.',
                    429,
                    ['lockout_time_remaining' => $lockoutInfo['remaining']]
                );
            }

            $remainingAttempts = 3 - $lockoutInfo['attempts'];
            Response::error(
                'invalid_credentials',
                'Неверный пароль. Осталось попыток: ' . $remainingAttempts,
                401,
                ['remaining_attempts' => $remainingAttempts]
            );
        }
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(array $params, array $body): void
    {
        Auth::requireAuth();
        Auth::revokeCurrentToken();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['authenticated'] = false;
            @session_destroy();
        }

        Response::json(null, 200, 'Сессия успешно завершена, токен отозван');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(array $params, array $body): void
    {
        Auth::requireAuth();

        $versionFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/version.json';
        $version = '2.287';
        if (file_exists($versionFile)) {
            $vData = json_decode(@file_get_contents($versionFile) ?: '[]', true);
            $version = $vData['version'] ?? $version;
        }

        $tokenData = Auth::getAuthenticatedToken();

        Response::json([
            'authenticated' => true,
            'password_protected' => Auth::isPasswordSet(),
            'app_version' => $version,
            'active_blog_path' => function_exists('getDataPath') ? getDataPath() : '',
            'token' => $tokenData ? [
                'id' => $tokenData['id'],
                'device_name' => $tokenData['device_name'],
                'created_at' => $tokenData['created_at'],
                'expires_at' => $tokenData['expires_at'],
                'last_used_at' => $tokenData['last_used_at']
            ] : null
        ], 200);
    }

    /**
     * GET /api/v1/auth/tokens
     */
    public function listTokens(array $params, array $body): void
    {
        Auth::requireAuth();
        $tokens = Auth::listTokens();
        Response::json($tokens, 200);
    }

    /**
     * DELETE /api/v1/auth/tokens/{id}
     */
    public function revokeToken(array $params, array $body): void
    {
        Auth::requireAuth();
        $tokenId = $params['id'] ?? '';

        if (empty($tokenId)) {
            Response::error('missing_id', 'ID токена не указан', 400);
        }

        $revoked = Auth::revokeToken($tokenId);
        if ($revoked) {
            Response::json(null, 200, 'Токен успешно отозван');
        } else {
            Response::error('token_not_found', 'Токен с указанным ID не найден', 404);
        }
    }

    /**
     * POST /api/v1/auth/change-password
     */
    public function changePassword(array $params, array $body): void
    {
        Auth::requireAuth();

        $currentPassword = (string)($body['current_password'] ?? '');
        $newPassword = (string)($body['new_password'] ?? '');

        if (Auth::isPasswordSet() && !Auth::verifyPassword($currentPassword)) {
            Response::error('invalid_current_password', 'Текущий пароль указан неверно', 400);
        }

        if (mb_strlen($newPassword) < 6) {
            Response::error('password_too_short', 'Новый пароль должен содержать не менее 6 символов', 422);
        }

        $settingsFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/editor_settings.json';
        $settings = json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: [];
        $settings['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);

        if (function_exists('safeWriteJson')) {
            safeWriteJson($settingsFile, $settings);
        } else {
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        Response::json(null, 200, 'Пароль успешно изменен');
    }
}
