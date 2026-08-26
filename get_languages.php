<?php
require_once __DIR__ . '/security_bootstrap.php';
require_once __DIR__ . '/lang_helper.php';
header('Content-Type: application/json; charset=utf-8');

$languages = getAvailableLanguages();

echo json_encode([
    'success' => true,
    'languages' => $languages
], JSON_UNESCAPED_UNICODE);
