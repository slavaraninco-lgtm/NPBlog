<?php
declare(strict_types=1);

namespace NPBlog\Api;

class Router
{
    private array $routes = [];
    private string $basePath = '';

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $cleanPath = '/' . trim($path, '/');
        // Convert {param} to named regex (?P<param>[^/]+)
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $cleanPath);
        $regex = '#^' . $pattern . '$#u';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $cleanPath,
            'regex' => $regex,
            'handler' => $handler
        ];
    }

    /**
     * Dispatch current request
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Fast CORS preflight
        if ($method === 'OPTIONS') {
            Response::sendCorsHeaders();
            http_response_code(204);
            exit;
        }

        $uri = $this->resolveRequestPath();
        $allowedMethods = [];
        $matchedRoute = null;
        $params = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $uri, $matches)) {
                $allowedMethods[] = $route['method'];
                if ($route['method'] === $method) {
                    $matchedRoute = $route;
                    // Extract named parameters
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $params[$key] = urldecode($value);
                        }
                    }
                    break;
                }
            }
        }

        if ($matchedRoute !== null) {
            $this->invokeHandler($matchedRoute['handler'], $params);
            return;
        }

        if (!empty($allowedMethods)) {
            Response::error(
                'method_not_allowed',
                "HTTP метод '$method' не поддерживается для эндпоинта '$uri'. Разрешенные методы: " . implode(', ', array_unique($allowedMethods)),
                405
            );
        }

        Response::error(
            'endpoint_not_found',
            "Эндпоинт '$uri' не найден в API NPBlog.",
            404
        );
    }

    /**
     * Resolve the route path from URI or query params
     */
    private function resolveRequestPath(): string
    {
        // 1. Check query parameter _route (used by rewrite or direct call)
        if (!empty($_GET['_route'])) {
            $route = '/' . ltrim((string)$_GET['_route'], '/');
            return rtrim($route, '/') ?: '/';
        }

        // 2. Fallback to REQUEST_URI
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);

        // Strip basePath
        if ($this->basePath !== '' && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }

        // Strip /api/ if needed
        if (strpos($uri, '/api') === 0) {
            $uri = substr($uri, 4);
        }

        $uri = '/' . ltrim($uri, '/');
        return rtrim($uri, '/') ?: '/';
    }

    /**
     * Invoke the route handler
     */
    private function invokeHandler(callable|array $handler, array $params): void
    {
        $body = $this->parseRequestBody();

        if (is_array($handler)) {
            [$class, $action] = $handler;
            $instance = is_string($class) ? new $class() : $class;
            $instance->$action($params, $body);
        } else {
            $handler($params, $body);
        }
    }

    /**
     * Parse incoming JSON or form request body
     */
    private function parseRequestBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if (!empty($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        // Handle multipart or x-www-form-urlencoded
        if (!empty($_POST)) {
            return $_POST;
        }

        // Handle PUT/DELETE/PATCH with raw input
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            parse_str($raw, $parsed);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return [];
    }
}
