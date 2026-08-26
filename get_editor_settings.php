<?php
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/lang_helper.php';
header('Content-Type: application/json; charset=utf-8');

$settingsFile = 'editor_settings.json';

$availableCodes = getAvailableLanguageCodes();
$defaultLang = !empty($availableCodes) ? $availableCodes[0] : 'ru';

$defaults = [
    'hideEditorModeButtons' => false,
    'amoledTheme' => false,
    'enableUndoRedo' => false,
    'smoothTyping' => false,
    'headerBottomPosition' => false,
    'enableMarkdown' => false,
    'autosaveEnabled' => false,
    'autosaveInterval' => 60,
    'tutorialCompleted' => false,
    'initial_setup_completed' => false,
    'contentWidth' => 920,
    'blog_paths' => [],
    'active_blog_path' => '',
    'rss_enabled' => false,
    'rss_base_url' => '',
    'rss_title' => 'NPBlog Feed',
    'rss_description' => 'NPBlog RSS Feed',
    'rss_use_first_line' => true,
    'rss_content_template' => "*content*\n\n<p><a href=\"*url*\">Читать в блоге</a></p>",
    'activeTheme' => 'dark',
    'customThemeCss' => '',
    'language' => $defaultLang
];

if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    $merged = array_merge($defaults, $settings);
    if (isset($settings['password_hash'])) {
        $merged['password_set'] = !empty($settings['password_hash']);
    } else {
        $merged['password_set'] = false;
    }
    unset($merged['password_hash']);
    echo json_encode(['success' => true, 'settings' => $merged]);
} else {
    echo json_encode([
        'success' => true, 
        'settings' => $defaults
    ]);
}
?>
