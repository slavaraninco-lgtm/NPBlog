<?php
/**
 * NPBlog - Language Management Helper
 * Dynamically parses available languages from lang/languages.json
 */

if (!function_exists('getAvailableLanguages')) {
    function getAvailableLanguages() {
        $langDir = __DIR__ . '/lang';
        $manifestPath = $langDir . '/languages.json';
        $languages = [];

        if (file_exists($manifestPath)) {
            $content = @file_get_contents($manifestPath);
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) continue;

                    $file = isset($item['file']) ? trim($item['file']) : (isset($item['filename']) ? trim($item['filename']) : '');
                    $code = isset($item['code']) ? strtolower(trim($item['code'])) : (isset($item['id']) ? strtolower(trim($item['id'])) : (isset($item['lang']) ? strtolower(trim($item['lang'])) : ''));

                    if (empty($code) && !empty($file)) {
                        $code = strtolower(pathinfo($file, PATHINFO_FILENAME));
                    }
                    if (empty($file) && !empty($code)) {
                        $file = $code . '.json';
                    }

                    if (empty($code)) continue;

                    $name = isset($item['name']) ? trim($item['name']) : 
                            (isset($item['title']) ? trim($item['title']) : 
                            (isset($item['label']) ? trim($item['label']) : 
                            (isset($item['lang_name']) ? trim($item['lang_name']) : strtoupper($code))));

                    $smile = isset($item['smile']) ? trim($item['smile']) : 
                             (isset($item['emoji']) ? trim($item['emoji']) : 
                             (isset($item['icon']) ? trim($item['icon']) : 
                             (isset($item['flag']) ? trim($item['flag']) : '🌐')));

                    $languages[] = [
                        'code' => $code,
                        'name' => $name,
                        'file' => $file,
                        'smile' => $smile,
                        'icon' => $smile,
                        'emoji' => $smile
                    ];
                }
            }
        }

        // Fallback: if languages.json is absent or empty, discover all .json in lang/
        if (empty($languages) && is_dir($langDir)) {
            $files = glob($langDir . '/*.json');
            $knownIcons = [
                'ru' => '🇷🇺', 'en' => '🇬🇧', 'uk' => '🇺🇦', 'lv' => '🇱🇻',
                'de' => '🇩🇪', 'fr' => '🇫🇷', 'es' => '🇪🇸', 'it' => '🇮🇹',
                'pl' => '🇵🇱', 'pt' => '🇵🇹', 'zh' => '🇨🇳', 'ja' => '🇯🇵'
            ];
            $knownNames = [
                'ru' => 'Русский', 'en' => 'English', 'uk' => 'Українська', 'lv' => 'Latviešu',
                'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano',
                'pl' => 'Polski', 'pt' => 'Português', 'zh' => '中文', 'ja' => '日本語'
            ];

            if ($files) {
                foreach ($files as $filePath) {
                    $basename = basename($filePath);
                    if ($basename === 'languages.json') continue;
                    $code = strtolower(pathinfo($basename, PATHINFO_FILENAME));
                    $name = isset($knownNames[$code]) ? $knownNames[$code] : strtoupper($code);
                    $smile = isset($knownIcons[$code]) ? $knownIcons[$code] : '🌐';

                    $dictContent = @file_get_contents($filePath);
                    if ($dictContent) {
                        $dict = json_decode($dictContent, true);
                        if (is_array($dict) && !empty($dict['lang_name'])) {
                            $name = $dict['lang_name'];
                        }
                    }

                    $languages[] = [
                        'code' => $code,
                        'name' => $name,
                        'file' => $basename,
                        'smile' => $smile,
                        'icon' => $smile,
                        'emoji' => $smile
                    ];
                }
            }
        }

        // Final safety fallback
        if (empty($languages)) {
            $languages = [
                ['code' => 'ru', 'name' => 'Русский', 'file' => 'ru.json', 'smile' => '🇷🇺', 'icon' => '🇷🇺', 'emoji' => '🇷🇺']
            ];
        }

        return $languages;
    }
}

if (!function_exists('getAvailableLanguageCodes')) {
    function getAvailableLanguageCodes() {
        $langs = getAvailableLanguages();
        return array_values(array_unique(array_map(function($l) { return $l['code']; }, $langs)));
    }
}

if (!function_exists('isValidLanguageCode')) {
    function isValidLanguageCode($code) {
        if (empty($code) || !is_string($code)) return false;
        $code = strtolower(trim($code));
        return in_array($code, getAvailableLanguageCodes(), true);
    }
}

if (!function_exists('getLanguageByCode')) {
    function getLanguageByCode($code) {
        $code = strtolower(trim((string)$code));
        $langs = getAvailableLanguages();
        foreach ($langs as $lang) {
            if ($lang['code'] === $code) {
                return $lang;
            }
        }
        return !empty($langs) ? $langs[0] : null;
    }
}
