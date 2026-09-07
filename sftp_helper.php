<?php
/**
 * ==============================================================================
 * NPBlog - Модуль поддержки SFTP (SSH File Transfer Protocol)
 * ==============================================================================
 * Поддерживает два драйвера:
 * 1. Расширение PHP ssh2 (PECL ssh2) - быстрый потоковый режим.
 * 2. PHP cURL с протоколом SFTP (libssh2) - встроен по умолчанию в PHP на Windows
 *    и большинстве дистрибутивов Linux.
 * ==============================================================================
 */

if (!defined('NPBLOG_SFTP_HELPER')) {
    define('NPBLOG_SFTP_HELPER', true);
}

class SftpUploader {
    protected string $server;
    protected int $port;
    protected string $username;
    protected string $password;
    protected int $timeout;
    protected string $driver = 'none'; // 'ssh2' | 'curl' | 'none'
    protected $ssh2Conn = null;
    protected $ssh2Sftp = null;
    protected string $lastError = '';

    public function __construct(string $server = '', int $port = 22, string $username = '', string $password = '', int $timeout = 15) {
        $this->server = trim($server);
        $this->port = ($port > 0 && $port <= 65535) ? $port : 22;
        $this->username = trim($username);
        $this->password = $password;
        $this->timeout = $timeout > 0 ? $timeout : 15;
        $this->driver = self::detectDriver();
    }

    /**
     * Определение лучшего доступного драйвера SFTP в текущем окружении PHP
     */
    public static function detectDriver(): string {
        if (extension_loaded('ssh2') && function_exists('ssh2_connect')) {
            return 'ssh2';
        }
        if (function_exists('curl_version')) {
            $version = curl_version();
            $protocols = $version['protocols'] ?? [];
            if (in_array('sftp', $protocols)) {
                return 'curl';
            }
        }
        return 'none';
    }

    /**
     * Проверка общей доступности поддержки SFTP на сервере
     */
    public static function isSupported(): array {
        $driver = self::detectDriver();
        if ($driver === 'ssh2') {
            return [
                'supported' => true,
                'driver' => 'ssh2',
                'message' => 'Доступно расширение PHP ssh2'
            ];
        }
        if ($driver === 'curl') {
            return [
                'supported' => true,
                'driver' => 'curl',
                'message' => 'Доступен модуль cURL с поддержкой протокола SFTP'
            ];
        }
        return [
            'supported' => false,
            'driver' => 'none',
            'message' => 'SFTP не поддерживается: требуется PHP-расширение ssh2 или cURL с поддержкой sftp'
        ];
    }

    public function setCredentials(string $server, int $port, string $username, string $password): void {
        $this->server = trim($server);
        $this->port = ($port > 0 && $port <= 65535) ? $port : 22;
        $this->username = trim($username);
        $this->password = $password;
    }

    public function getDriver(): string {
        return $this->driver;
    }

    public function getLastError(): string {
        return $this->lastError;
    }

    /**
     * Форматирование URL для cURL SFTP с корректным URL-экранированием каждого сегмента пути
     */
    protected function formatSftpUrl(string $remotePath): string {
        $path = str_replace('\\', '/', $remotePath);
        $isAbsolute = (strpos($path, '/') === 0);
        $trimmed = ltrim($path, '/');
        $segments = explode('/', $trimmed);
        $encodedSegments = array_map('rawurlencode', $segments);
        $encodedPath = implode('/', $encodedSegments);
        
        $prefix = $isAbsolute ? '/' : '/~/';
        return "sftp://{$this->server}:{$this->port}" . $prefix . $encodedPath;
    }

    /**
     * Подключение и проверка авторизации
     */
    public function connect(): bool {
        $this->lastError = '';

        if ($this->driver === 'none') {
            $this->lastError = 'На сервере не обнаружена поддержка SFTP (нет расширения ssh2 и поддержки SFTP в cURL).';
            return false;
        }

        if (empty($this->server) || empty($this->username)) {
            $this->lastError = 'Не указан адрес сервера или имя пользователя.';
            return false;
        }

        if ($this->driver === 'ssh2') {
            return $this->connectSsh2();
        } else {
            return $this->connectCurl();
        }
    }

    protected function connectSsh2(): bool {
        $conn = @ssh2_connect($this->server, $this->port, [], ['disconnect' => function($reason, $message) {
            $this->lastError = "Отключение SSH: $message";
        }]);

        if (!$conn) {
            $this->lastError = "Не удалось установить SSH-соединение с {$this->server}:{$this->port}. Проверьте адрес и порт.";
            return false;
        }

        if (!@ssh2_auth_password($conn, $this->username, $this->password)) {
            $this->lastError = "Ошибка авторизации SFTP: неверное имя пользователя или пароль.";
            return false;
        }

        $sftp = @ssh2_sftp($conn);
        if (!$sftp) {
            $this->lastError = "Не удалось инициализировать подсистему SFTP на удаленном сервере.";
            return false;
        }

        $this->ssh2Conn = $conn;
        $this->ssh2Sftp = $sftp;
        return true;
    }

    protected function connectCurl(): bool {
        $ch = curl_init();
        $testUrl = "sftp://{$this->server}:{$this->port}/";
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_DIRLISTONLY => true,
        ]);

        if (defined('CURLOPT_SSH_AUTH_TYPES') && defined('CURLSSH_AUTH_PASSWORD')) {
            curl_setopt($ch, CURLOPT_SSH_AUTH_TYPES, CURLSSH_AUTH_PASSWORD | (defined('CURLSSH_AUTH_ANY') ? CURLSSH_AUTH_ANY : -1));
        }

        $res = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno === 0) {
            return true;
        }

        // Анализ кода ошибки cURL
        if ($errno === CURLE_COULDNT_CONNECT) {
            $this->lastError = "Не удалось подключиться к {$this->server}:{$this->port}. Проверьте сервер и порт (порт закрыт или сервер недоступен).";
        } elseif ($errno === CURLE_OPERATION_TIMEDOUT) {
            $this->lastError = "Превышен таймаут подключения к {$this->server}:{$this->port} ({$this->timeout} сек).";
        } elseif ($errno === (defined('CURLE_SSH') ? CURLE_SSH : 79)) {
            if (stripos($error, 'authentication') !== false || stripos($error, 'denied') !== false) {
                $this->lastError = "Ошибка авторизации SFTP: неверное имя пользователя или пароль.";
            } else {
                $this->lastError = "Ошибка SSH/SFTP: " . ($error ?: "сбой подсистемы SSH");
            }
        } else {
            $this->lastError = "Ошибка подключения SFTP (#{$errno}): " . ($error ?: 'неизвестная ошибка');
        }

        return false;
    }

    /**
     * Проверка размера удаленного файла (для умной синхронизации)
     * Возвращает размер в байтах или -1, если файл не существует
     */
    public function getFileSize(string $remotePath): int {
        if ($this->driver === 'ssh2' && $this->ssh2Sftp) {
            $cleanPath = '/' . ltrim(str_replace('\\', '/', $remotePath), '/');
            $url = "ssh2.sftp://" . intval($this->ssh2Sftp) . $cleanPath;
            if (@file_exists($url)) {
                $size = @filesize($url);
                return ($size !== false && $size >= 0) ? (int)$size : -1;
            }
            return -1;
        }

        if ($this->driver === 'curl') {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->formatSftpUrl($remotePath),
                CURLOPT_USERPWD => "{$this->username}:{$this->password}",
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FILETIME => true,
            ]);

            if (defined('CURLOPT_SSH_AUTH_TYPES') && defined('CURLSSH_AUTH_PASSWORD')) {
                curl_setopt($ch, CURLOPT_SSH_AUTH_TYPES, CURLSSH_AUTH_PASSWORD | (defined('CURLSSH_AUTH_ANY') ? CURLSSH_AUTH_ANY : -1));
            }

            $res = curl_exec($ch);
            $errno = curl_errno($ch);
            if ($errno === 0) {
                $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                curl_close($ch);
                return ($size >= 0) ? (int)$size : -1;
            }
            curl_close($ch);
            return -1;
        }

        return -1;
    }

    /**
     * Создание удаленной директории
     */
    public function createRemoteDir(string $remoteDir): bool {
        $cleanDir = '/' . trim(str_replace('\\', '/', $remoteDir), '/');

        if ($this->driver === 'ssh2' && $this->ssh2Sftp) {
            $url = "ssh2.sftp://" . intval($this->ssh2Sftp) . $cleanDir;
            if (@is_dir($url)) {
                return true;
            }
            return @ssh2_sftp_mkdir($this->ssh2Sftp, $cleanDir, 0755, true);
        }

        if ($this->driver === 'curl') {
            // В cURL SFTP создание папок происходит автоматически опцией CURLOPT_FTP_CREATE_MISSING_DIRS
            // Дополнительно отправляем quote команду mkdir
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "sftp://{$this->server}:{$this->port}/",
                CURLOPT_USERPWD => "{$this->username}:{$this->password}",
                CURLOPT_QUOTE => ["mkdir {$cleanDir}"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);

            if (defined('CURLOPT_SSH_AUTH_TYPES') && defined('CURLSSH_AUTH_PASSWORD')) {
                curl_setopt($ch, CURLOPT_SSH_AUTH_TYPES, CURLSSH_AUTH_PASSWORD | (defined('CURLSSH_AUTH_ANY') ? CURLSSH_AUTH_ANY : -1));
            }

            @curl_exec($ch);
            curl_close($ch);
            return true;
        }

        return false;
    }

    /**
     * Загрузка локального файла на удаленный SFTP сервер
     */
    public function uploadFile(string $localPath, string $remotePath): bool {
        $this->lastError = '';

        if (!file_exists($localPath)) {
            $this->lastError = "Локальный файл не существует: " . basename($localPath);
            return false;
        }

        if ($this->driver === 'ssh2' && $this->ssh2Sftp) {
            $cleanPath = '/' . ltrim(str_replace('\\', '/', $remotePath), '/');
            $dir = dirname($cleanPath);
            if (!empty($dir) && $dir !== '/' && $dir !== '.') {
                $dirUrl = "ssh2.sftp://" . intval($this->ssh2Sftp) . $dir;
                if (!@is_dir($dirUrl)) {
                    @ssh2_sftp_mkdir($this->ssh2Sftp, $dir, 0755, true);
                }
            }

            $remoteUrl = "ssh2.sftp://" . intval($this->ssh2Sftp) . $cleanPath;
            $src = @fopen($localPath, 'rb');
            if (!$src) {
                $this->lastError = "Не удалось открыть локальный файл для чтения: " . basename($localPath);
                return false;
            }

            $dst = @fopen($remoteUrl, 'wb');
            if (!$dst) {
                @fclose($src);
                $this->lastError = "Не удалось открыть удаленный файл для записи: " . $cleanPath;
                return false;
            }

            $copied = @stream_copy_to_stream($src, $dst);
            @fclose($src);
            @fclose($dst);

            if ($copied === false) {
                $this->lastError = "Сбой потоковой передачи файла через SSH2: " . basename($localPath);
                return false;
            }
            return true;
        }

        if ($this->driver === 'curl') {
            $fp = @fopen($localPath, 'rb');
            if (!$fp) {
                $this->lastError = "Не удалось прочитать локальный файл: " . basename($localPath);
                return false;
            }

            $fileSize = (int)@filesize($localPath);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->formatSftpUrl($remotePath),
                CURLOPT_USERPWD => "{$this->username}:{$this->password}",
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $fp,
                CURLOPT_INFILESIZE => $fileSize,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);

            // Автоматическое создание недостающих директорий в пути
            if (defined('CURLOPT_FTP_CREATE_MISSING_DIRS')) {
                curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, 1);
            }

            if (defined('CURLOPT_SSH_AUTH_TYPES') && defined('CURLSSH_AUTH_PASSWORD')) {
                curl_setopt($ch, CURLOPT_SSH_AUTH_TYPES, CURLSSH_AUTH_PASSWORD | (defined('CURLSSH_AUTH_ANY') ? CURLSSH_AUTH_ANY : -1));
            }

            $res = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            @fclose($fp);

            if ($errno !== 0) {
                $this->lastError = $error ?: "Ошибка cURL #{$errno}";
                return false;
            }

            return true;
        }

        $this->lastError = "Драйвер SFTP не инициализирован.";
        return false;
    }

    public function close(): void {
        if ($this->ssh2Conn) {
            $this->ssh2Sftp = null;
            $this->ssh2Conn = null;
        }
    }
}
