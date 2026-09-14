<?php
declare(strict_types=1);

namespace NPBlog\Api;

class Response
{
    /**
     * Send CORS headers
     */
    public static function sendCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, Origin, X-Requested-With, X-API-Token, X-CSRF-Token, Cache-Control, X-Blog-Path, X-Blog');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * Send a successful JSON response
     */
    public static function json(
        mixed $data = null,
        int $status = 200,
        ?string $message = null,
        array $meta = []
    ): void {
        self::sendCorsHeaders();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'success' => true,
            'status' => $status
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send an error JSON response
     */
    public static function error(
        string $code,
        string $message,
        int $status = 400,
        mixed $details = null
    ): void {
        self::sendCorsHeaders();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $errorObj = [
            'code' => $code,
            'message' => $message
        ];

        if ($details !== null) {
            $errorObj['details'] = $details;
        }

        $payload = [
            'success' => false,
            'status' => $status,
            'error' => $errorObj
        ];

        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send raw content with custom content-type (e.g. HTML preview)
     */
    public static function raw(
        string $content,
        string $contentType = 'text/html; charset=utf-8',
        int $status = 200
    ): void {
        self::sendCorsHeaders();
        http_response_code($status);
        header("Content-Type: $contentType");
        echo $content;
        exit;
    }
}
