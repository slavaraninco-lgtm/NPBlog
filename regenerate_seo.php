<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (php_sapi_name() !== 'cli' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/seo_helper.php';

try {
    $result = regenerateAllPostsSeo();
    echo json_encode([
        'success' => true,
        'processed' => $result['processed'],
        'updated' => $result['updated']
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()
    ]);
}
?>
