<?php
declare(strict_types=1);

namespace NPBlog\Api\Controllers;

use NPBlog\Api\Auth;
use NPBlog\Api\Response;

class SettingsController
{
    public function __construct()
    {
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/security_bootstrap.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/lang_helper.php';
        require_once (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/rss_helper.php';
    }

    /**
     * GET /api/v1/settings/editor
     */
    public function getEditorSettings(array $params, array $body): void
    {
        Auth::requireAuth();

        $settingsFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/editor_settings.json';
        $settings = file_exists($settingsFile) ? (json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: []) : [];

        $hasPassword = !empty($settings['password_hash']);
        unset($settings['password_hash']);

        $settings['password_set'] = $hasPassword;
        $settings['resolved_data_path'] = getDataPath();
        $settings['resolved_backup_path'] = getBackupPath();
        $settings['resolved_autosave_path'] = getAutosavePath();
        $settings['resolved_editor_backup_path'] = getEditorBackupPath();

        Response::json($settings, 200);
    }

    /**
     * PUT /api/v1/settings/editor
     */
    public function updateEditorSettings(array $params, array $body): void
    {
        Auth::requireAuth();

        $settingsFile = (defined('NPBLOG_ROOT') ? NPBLOG_ROOT : dirname(__DIR__, 3)) . '/editor_settings.json';
        $settings = file_exists($settingsFile) ? (json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: []) : [];

        // Whitelist of allowed settings to update
        $allowedFields = [
            'language' => 'string',
            'amoledTheme' => 'bool',
            'enableUndoRedo' => 'bool',
            'smoothTyping' => 'bool',
            'headerBottomPosition' => 'bool',
            'contentWidth' => 'int',
            'enableMarkdown' => 'bool',
            'enableApi' => 'bool',
            'autosaveEnabled' => 'bool',
            'autosaveInterval' => 'int',
            'hideEditorModeButtons' => 'bool',
            'ip_whitelist_enabled' => 'bool',
            'rss_enabled' => 'bool',
            'rss_base_url' => 'string',
            'rss_title' => 'string',
            'rss_description' => 'string',
            'rss_use_first_line' => 'bool',
            'rss_content_template' => 'string',
            'activeTheme' => 'string',
            'customThemeCss' => 'string'
        ];

        foreach ($allowedFields as $field => $type) {
            if (isset($body[$field])) {
                $val = $body[$field];
                $settings[$field] = match ($type) {
                    'bool' => (bool)$val,
                    'int' => (int)$val,
                    default => (string)$val,
                };
            }
        }

        if (safeWriteJson($settingsFile, $settings)) {
            // Update RSS if settings changed
            generateRssFeed();

            unset($settings['password_hash']);
            Response::json($settings, 200, 'Настройки редактора успешно сохранены');
        } else {
            Response::error('write_error', 'Не удалось сохранить файл настроек', 500);
        }
    }

    /**
     * GET /api/v1/settings/global
     */
    public function getGlobalSettings(array $params, array $body): void
    {
        Auth::requireAuth();

        $globalFile = getDataPath('global-settings.json');
        $viewFile = getDataPath('blog-view-settings.json');

        $global = file_exists($globalFile) ? (json_decode(@file_get_contents($globalFile) ?: '[]', true) ?: []) : [];
        $view = file_exists($viewFile) ? (json_decode(@file_get_contents($viewFile) ?: '[]', true) ?: []) : [];

        Response::json([
            'global_settings' => $global,
            'blog_view_settings' => $view
        ], 200);
    }

    /**
     * PUT /api/v1/settings/global
     */
    public function updateGlobalSettings(array $params, array $body): void
    {
        Auth::requireAuth();

        if (isset($body['global_settings']) && is_array($body['global_settings'])) {
            $globalFile = getDataPath('global-settings.json');
            safeWriteJson($globalFile, $body['global_settings']);
        }

        if (isset($body['blog_view_settings']) && is_array($body['blog_view_settings'])) {
            $viewFile = getDataPath('blog-view-settings.json');
            safeWriteJson($viewFile, $body['blog_view_settings']);
        }

        Response::json(null, 200, 'Глобальные настройки блога успешно сохранены');
    }
}
