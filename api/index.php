<?php
declare(strict_types=1);

// Define repository root directory
if (!defined('NPBLOG_ROOT')) {
    define('NPBLOG_ROOT', dirname(__DIR__));
}
if (!defined('NPBLOG_API_REQUEST')) {
    define('NPBLOG_API_REQUEST', true);
}

// Suppress unexpected HTML errors and set error reporting
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', '0');

// Autoload API classes
spl_autoload_register(function ($class) {
    $prefix = 'NPBlog\\Api\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use NPBlog\Api\Response;
use NPBlog\Api\Router;
use NPBlog\Api\Controllers\AuthController;
use NPBlog\Api\Controllers\PostsController;
use NPBlog\Api\Controllers\DraftsController;
use NPBlog\Api\Controllers\BackupsController;
use NPBlog\Api\Controllers\MediaController;
use NPBlog\Api\Controllers\TemplatesController;
use NPBlog\Api\Controllers\SettingsController;
use NPBlog\Api\Controllers\SystemController;

// Global exception and error handler
set_exception_handler(function (\Throwable $e) {
    Response::error(
        'server_error',
        'Внутренняя ошибка сервера API: ' . $e->getMessage(),
        500,
        [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    );
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    // Only die on fatal errors or warnings if needed
    if (in_array($errno, [E_ERROR, E_USER_ERROR], true)) {
        Response::error(
            'php_error',
            $errstr,
            500,
            ['file' => basename($errfile), 'line' => $errline]
        );
    }
    return false;
});

// Check if REST API is completely disabled in editor_settings.json
$settingsFile = NPBLOG_ROOT . '/editor_settings.json';
if (file_exists($settingsFile)) {
    $editorSettings = json_decode(@file_get_contents($settingsFile) ?: '[]', true) ?: [];
    if (isset($editorSettings['enableApi']) && $editorSettings['enableApi'] === false) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            Response::sendCorsHeaders();
            http_response_code(204);
            exit;
        }

        Response::error(
            'api_disabled',
            'REST API полностью отключен в параметрах редактора NPBlog.',
            403,
            [
                'help' => 'Включите API в панели веб-редактора: Настройки -> Экспериментальные -> Включить REST API'
            ]
        );
    }
}

// Initialize Router
$router = new Router('/api');

// --- Root Welcome & Version ---
$welcomeHandler = function () {
    $versionFile = dirname(__DIR__) . '/version.json';
    $version = '2.287';
    if (file_exists($versionFile)) {
        $v = json_decode(@file_get_contents($versionFile) ?: '[]', true);
        $version = $v['version'] ?? $version;
    }

    Response::json([
        'name' => 'NPBlog Mobile REST API',
        'version' => '1.0.0',
        'cms_version' => $version,
        'docs_url' => '/api/docs/',
        'openapi_url' => '/api/docs/openapi.json',
        'endpoints' => [
            'auth' => '/api/v1/auth/login',
            'posts' => '/api/v1/posts',
            'drafts' => '/api/v1/drafts',
            'autosaves' => '/api/v1/autosaves',
            'backups' => '/api/v1/backups',
            'media' => '/api/v1/media',
            'templates' => '/api/v1/templates',
            'includes' => '/api/v1/includes',
            'settings' => '/api/v1/settings/editor',
            'system' => '/api/v1/system/status'
        ]
    ], 200, 'Добро пожаловать в REST API редактора NPBlog');
};

$router->get('/', $welcomeHandler);
$router->get('/v1', $welcomeHandler);

// --- Auth Routes ---
$router->post('/v1/auth/login', [AuthController::class, 'login']);
$router->post('/v1/auth/logout', [AuthController::class, 'logout']);
$router->get('/v1/auth/me', [AuthController::class, 'me']);
$router->get('/v1/auth/tokens', [AuthController::class, 'listTokens']);
$router->delete('/v1/auth/tokens/{id}', [AuthController::class, 'revokeToken']);
$router->post('/v1/auth/change-password', [AuthController::class, 'changePassword']);

// --- Posts Routes ---
$router->get('/v1/posts', [PostsController::class, 'list']);
$router->post('/v1/posts', [PostsController::class, 'create']);
$router->post('/v1/posts/renumber', [PostsController::class, 'renumber']);
$router->post('/v1/posts/regenerate', [PostsController::class, 'regenerate']);
$router->get('/v1/posts/{id}/preview', [PostsController::class, 'preview']);
$router->get('/v1/posts/{id}', [PostsController::class, 'get']);
$router->put('/v1/posts/{id}', [PostsController::class, 'update']);
$router->delete('/v1/posts/{id}', [PostsController::class, 'delete']);

// --- Drafts & Autosaves Routes ---
$router->get('/v1/drafts', [DraftsController::class, 'list']);
$router->post('/v1/drafts', [DraftsController::class, 'save']);
$router->get('/v1/drafts/{filename}', [DraftsController::class, 'get']);
$router->delete('/v1/drafts/{filename}', [DraftsController::class, 'delete']);

$router->get('/v1/autosaves', [DraftsController::class, 'listAutosaves']);
$router->delete('/v1/autosaves', [DraftsController::class, 'clearAllAutosaves']);
$router->post('/v1/autosaves', [DraftsController::class, 'saveAutosave']);
$router->get('/v1/autosaves/{postId}', [DraftsController::class, 'getAutosave']);
$router->delete('/v1/autosaves/{postId}', [DraftsController::class, 'deleteAutosave']);

// --- Backups Routes ---
$router->get('/v1/backups', [BackupsController::class, 'list']);
$router->get('/v1/backups/{postId}', [BackupsController::class, 'getPostBackups']);
$router->get('/v1/backups/{postId}/{backupNumber}', [BackupsController::class, 'getBackupContent']);
$router->post('/v1/backups/{postId}/{backupNumber}/restore', [BackupsController::class, 'restore']);
$router->delete('/v1/backups/{postId}/{backupNumber}', [BackupsController::class, 'delete']);

// --- Media & Assets Routes ---
$router->get('/v1/media', [MediaController::class, 'list']);
$router->post('/v1/media/upload', [MediaController::class, 'upload']);
$router->delete('/v1/media', [MediaController::class, 'delete']);
$router->get('/v1/media/backgrounds', [MediaController::class, 'getBackgrounds']);
$router->post('/v1/media/backgrounds', [MediaController::class, 'saveBackground']);
$router->delete('/v1/media/backgrounds/{postId}', [MediaController::class, 'removeBackground']);
$router->get('/v1/media/smiles', [MediaController::class, 'listSmiles']);
$router->post('/v1/media/smiles', [MediaController::class, 'uploadSmiles']);
$router->delete('/v1/media/smiles/{setName}', [MediaController::class, 'deleteSmileSet']);
$router->get('/v1/media/fonts', [MediaController::class, 'listFonts']);
$router->delete('/v1/media/fonts/{filename}', [MediaController::class, 'deleteFont']);

// --- Templates & Includes Routes ---
$router->get('/v1/templates', [TemplatesController::class, 'list']);
$router->post('/v1/templates', [TemplatesController::class, 'save']);
$router->post('/v1/templates/apply', [TemplatesController::class, 'apply']);
$router->get('/v1/templates/{name}', [TemplatesController::class, 'get']);
$router->delete('/v1/templates/{name}', [TemplatesController::class, 'delete']);

$router->get('/v1/includes', [TemplatesController::class, 'listIncludes']);
$router->post('/v1/includes', [TemplatesController::class, 'saveInclude']);
$router->get('/v1/includes/{name}', [TemplatesController::class, 'getInclude']);
$router->delete('/v1/includes/{name}', [TemplatesController::class, 'deleteInclude']);

// --- Settings Routes ---
$router->get('/v1/settings/editor', [SettingsController::class, 'getEditorSettings']);
$router->put('/v1/settings/editor', [SettingsController::class, 'updateEditorSettings']);
$router->get('/v1/settings/global', [SettingsController::class, 'getGlobalSettings']);
$router->put('/v1/settings/global', [SettingsController::class, 'updateGlobalSettings']);

// --- System Routes ---
$router->get('/v1/system/status', [SystemController::class, 'status']);
$router->get('/v1/system/integrity', [SystemController::class, 'integrity']);
$router->post('/v1/system/integrity/fix', [SystemController::class, 'fixIntegrity']);
$router->get('/v1/system/languages', [SystemController::class, 'languages']);
$router->get('/v1/system/history', [SystemController::class, 'getHistory']);
$router->post('/v1/system/history', [SystemController::class, 'saveHistory']);
$router->delete('/v1/system/history', [SystemController::class, 'clearHistory']);

// Dispatch request
$router->dispatch();
