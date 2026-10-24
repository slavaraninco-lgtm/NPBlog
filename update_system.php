<?php
require_once __DIR__ . '/security_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

// Защищенные файлы и папки, которые НЕ должны обновляться
$protectedPaths = [
    getDataPath('blog/'),
    getDataPath('fonts/'),
    getDataPath('images/'),
    getDataPath('audio/'),
    getDataPath('video/'),
    getDataPath('files/'),
    getBackupPath(),
    getAutosavePath(),
    getEditorBackupPath(),
    'data_backup/',
    'autosave/',
    'editor_backup/',
    getDataPath('global-settings.json'),
    'editor_settings.json',
    'post_backgrounds.json',
    'blog-view-settings.json',
    'appearance_settings.json',
    'version.json' // version.json is updated separately
];

function isProtected($path) {
    global $protectedPaths;
    
    $normPath = ltrim(str_replace('\\', '/', $path), './');
    $lowerNormPath = strtolower($normPath);
    $ext = strtolower(pathinfo($normPath, PATHINFO_EXTENSION));
    
    // Специальное исключение: JS и CSS файлы (скрипты/стили) должны обновляться всегда
    if ($ext === 'js' || $ext === 'css') {
        return false;
    }
    
    // Специальное исключение: data/blog.html должен обновляться
    $dataBlogHtml = function_exists('getDataPath') ? strtolower(str_replace('\\', '/', getDataPath('blog.html'))) : 'data/blog.html';
    if ($lowerNormPath === 'data/blog.html' || $lowerNormPath === $dataBlogHtml) {
        return false;
    }
    
    // Специальное исключение: JSON файлы локализации в папке lang должны обновляться
    if (strpos($lowerNormPath, 'lang/') === 0 && $ext === 'json') {
        return false;
    }
    
    // Защищаем все остальные json файлы (метаданные, история, настройки и т.д.)
    if ($ext === 'json') {
        return true;
    }
    
    foreach ($protectedPaths as $protected) {
        $normProtected = strtolower(ltrim(str_replace('\\', '/', $protected), './'));
        // Если это папка
        if (substr($normProtected, -1) === '/') {
            if (strpos($lowerNormPath, $normProtected) === 0) {
                return true;
            }
        } else {
            // Если это конкретный файл
            if ($lowerNormPath === $normProtected) {
                return true;
            }
        }
    }
    return false;
}

function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return true;
    $files = array_diff(scandir($dirPath), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dirPath/$file")) ? deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
    }
    return rmdir($dirPath);
}

function sanitizeGithubRepo($rawRepo) {
    $repo = trim((string)$rawRepo);
    $repo = preg_replace('#^https?://github\.com/#i', '', $repo);
    $repo = preg_replace('#^github\.com/#i', '', $repo);
    $repo = trim($repo, '/');
    $repo = preg_replace('/\.git$/i', '', $repo);
    if ($repo === '' || !preg_match('#^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$#', $repo)) {
        return 'slavaraninco-lgtm/NPBlog';
    }
    return $repo;
}

function fetchGithubData($url, $token = '') {
    $headers = [
        'Accept: application/vnd.github.v3+json, application/atom+xml, text/xml, */*'
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: token ' . $token;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NPBlog-Updater');
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode >= 200 && $httpCode < 300 && $response !== false) {
            return $response;
        }
        return false;
    }
    
    if (ini_get('allow_url_fopen')) {
        $headerStr = "User-Agent: NPBlog-Updater\r\nAccept: application/vnd.github.v3+json, application/atom+xml, text/xml, */*\r\n";
        if ($token !== '') {
            $headerStr .= "Authorization: token " . $token . "\r\n";
        }
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => $headerStr,
                'timeout' => 20,
                'follow_location' => 1
            ]
        ];
        $context = stream_context_create($opts);
        return @file_get_contents($url, false, $context);
    }
    
    return false;
}

function parseCanaryAtomFeed($xmlString) {
    if (!$xmlString) return false;
    
    $commits = [];
    if (function_exists('simplexml_load_string')) {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlString);
        if ($xml && isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $id = (string)$entry->id;
                $sha = '';
                if (preg_match('#Commit/([a-f0-9]{40})#i', $id, $m)) {
                    $sha = $m[1];
                } elseif (isset($entry->link) && preg_match('#/commit/([a-f0-9]{40})#i', (string)$entry->link['href'], $m)) {
                    $sha = $m[1];
                }
                $title = trim((string)$entry->title);
                $author = isset($entry->author->name) ? (string)$entry->author->name : 'dev';
                if ($sha !== '') {
                    $commits[] = [
                        'sha' => $sha,
                        'message' => $title,
                        'author' => $author
                    ];
                }
            }
        }
    }
    
    // Fallback regex parsing if simplexml not available
    if (empty($commits)) {
        preg_match_all('#<entry>(.*?)</entry>#is', $xmlString, $entries);
        if (!empty($entries[1])) {
            foreach ($entries[1] as $entryStr) {
                $sha = '';
                if (preg_match('#Commit/([a-f0-9]{40})#i', $entryStr, $m)) {
                    $sha = $m[1];
                }
                $title = '';
                if (preg_match('#<title>(.*?)</title>#is', $entryStr, $m)) {
                    $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
                }
                $author = 'dev';
                if (preg_match('#<author>.*?<name>(.*?)</name>#is', $entryStr, $m)) {
                    $author = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
                }
                if ($sha !== '') {
                    $commits[] = [
                        'sha' => $sha,
                        'message' => $title,
                        'author' => $author
                    ];
                }
            }
        }
    }
    
    return !empty($commits) ? $commits : false;
}

function parseReleasesAtomFeed($xmlString) {
    if (!$xmlString) return false;
    
    $releases = [];
    if (function_exists('simplexml_load_string')) {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($xmlString);
        if ($xml && isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $id = (string)$entry->id;
                $parts = explode('/', $id);
                $tag = end($parts);
                $title = trim((string)$entry->title);
                $content = trim(strip_tags((string)$entry->content));
                if ($tag !== '') {
                    $releases[] = [
                        'tag' => $tag,
                        'title' => $title,
                        'content' => $content
                    ];
                }
            }
        }
    }
    
    // Fallback regex parsing
    if (empty($releases)) {
        preg_match_all('#<entry>(.*?)</entry>#is', $xmlString, $entries);
        if (!empty($entries[1])) {
            foreach ($entries[1] as $entryStr) {
                $tag = '';
                if (preg_match('#/releases/tag/([^"\'\s<]+)#i', $entryStr, $m)) {
                    $tag = trim($m[1]);
                }
                $title = '';
                if (preg_match('#<title>(.*?)</title>#is', $entryStr, $m)) {
                    $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
                }
                $content = '';
                if (preg_match('#<content[^>]*>(.*?)</content>#is', $entryStr, $m)) {
                    $content = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
                }
                if ($tag !== '') {
                    $releases[] = [
                        'tag' => $tag,
                        'title' => $title,
                        'content' => $content
                    ];
                }
            }
        }
    }
    
    return !empty($releases) ? $releases : false;
}

function getLocalGitInfo() {
    $gitDir = __DIR__ . '/.git';
    $branch = '';
    $commit = '';

    if (is_dir($gitDir) && file_exists($gitDir . '/HEAD')) {
        $head = trim((string)@file_get_contents($gitDir . '/HEAD'));
        if (strpos($head, 'ref: ') === 0) {
            $refPath = substr($head, 5);
            $parts = explode('/', $refPath);
            $branch = end($parts);
            $refFile = $gitDir . '/' . $refPath;
            if (file_exists($refFile)) {
                $c = trim((string)@file_get_contents($refFile));
                if (preg_match('/^[a-f0-9]{40}$/i', $c)) {
                    $commit = strtolower($c);
                }
            }
            if ($commit === '' && file_exists($gitDir . '/packed-refs')) {
                $packed = @file_get_contents($gitDir . '/packed-refs');
                if ($packed && preg_match('/^([a-f0-9]{40})\s+' . preg_quote($refPath, '/') . '/m', $packed, $m)) {
                    $commit = strtolower($m[1]);
                }
            }
        } elseif (preg_match('/^[a-f0-9]{40}$/i', $head)) {
            $commit = strtolower($head);
        }
    }

    if ($commit === '' && function_exists('exec')) {
        $out = [];
        $ret = -1;
        @exec('git rev-parse HEAD 2>/dev/null', $out, $ret);
        if ($ret === 0 && !empty($out[0]) && preg_match('/^[a-f0-9]{40}$/i', trim($out[0]))) {
            $commit = strtolower(trim($out[0]));
        }
        $outB = [];
        $retB = -1;
        @exec('git rev-parse --abbrev-ref HEAD 2>/dev/null', $outB, $retB);
        if ($retB === 0 && !empty($outB[0])) {
            $branch = trim($outB[0]);
        }
    }

    return [
        'commit' => $commit,
        'branch' => $branch
    ];
}

function isCanaryUpToDate($currentCommit, $latestSha) {
    if (empty($currentCommit) || empty($latestSha)) {
        return false;
    }
    $c = strtolower(trim($currentCommit));
    $l = strtolower(trim($latestSha));
    if ($c === $l) {
        return true;
    }
    $shortC = substr($c, 0, 7);
    $shortL = substr($l, 0, 7);
    return ($shortC === $shortL);
}

if ($action === 'get_version') {
    $currentVersion = 'Unknown';
    $isDev = false;
    $currentCommit = '';
    if (file_exists('version.json')) {
        $currData = @json_decode(file_get_contents('version.json'), true);
        if ($currData) {
            $isDev = !empty($currData['dev']) && ($currData['dev'] === true || $currData['dev'] === 'true');
            $currentVersion = isset($currData['version']) ? (string)$currData['version'] : 'Unknown';
            $currentCommit = isset($currData['commit']) ? (string)$currData['commit'] : '';
            if ($currentCommit === '$Format:%H$' || strpos($currentCommit, '$Format:') !== false) {
                $currentCommit = '';
            }
        }
    }

    if ($currentCommit === '' && preg_match('/dev\s*\(([a-f0-9]{7,40})\)/i', $currentVersion, $m)) {
        $currentCommit = $m[1];
        $isDev = true;
    }

    $gitInfo = getLocalGitInfo();
    if ($currentCommit === '' && !empty($gitInfo['commit'])) {
        $currentCommit = $gitInfo['commit'];
    }
    if ($gitInfo['branch'] === 'dev') {
        $isDev = true;
    }

    echo json_encode([
        'success' => true,
        'version' => $currentVersion,
        'dev' => $isDev,
        'commit' => $currentCommit
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_changelog') {
    $currentVersion = 'Unknown';
    $isDev = false;
    $lastUpdated = '';
    $changelog = [];
    if (file_exists('version.json')) {
        $currData = @json_decode(file_get_contents('version.json'), true);
        if ($currData) {
            $isDev = !empty($currData['dev']) && ($currData['dev'] === true || $currData['dev'] === 'true');
            $currentVersion = $currData['version'] ?? ($isDev ? 'dev' : 'Unknown');
            $lastUpdated = $currData['last_updated'] ?? '';
            if (!empty($currData['changelog']) && is_array($currData['changelog'])) {
                $changelog = $currData['changelog'];
            }
        }
    }
    // Если в version.json нет changelog, попробуем поискать changelog.json
    if (empty($changelog) && file_exists('changelog.json')) {
        $clData = @json_decode(file_get_contents('changelog.json'), true);
        if ($clData && is_array($clData)) {
            $changelog = $clData;
        }
    }
    echo json_encode([
        'success' => true,
        'version' => $currentVersion,
        'dev' => $isDev,
        'last_updated' => $lastUpdated,
        'changelog' => $changelog
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'check_auto_update') {
    $currentVersion = 'Unknown';
    $isDev = false;
    $currentCommit = '';
    if (file_exists('version.json')) {
        $currData = @json_decode(file_get_contents('version.json'), true);
        if ($currData) {
            $isDev = !empty($currData['dev']) && ($currData['dev'] === true || $currData['dev'] === 'true');
            $currentVersion = isset($currData['version']) ? (string)$currData['version'] : 'Unknown';
            $currentCommit = isset($currData['commit']) ? (string)$currData['commit'] : '';
            if ($currentCommit === '$Format:%H$' || strpos($currentCommit, '$Format:') !== false) {
                $currentCommit = '';
            }
        }
    }

    // Если в строке версии указан хеш вида dev (xxxxxxx)
    if ($currentCommit === '' && preg_match('/dev\s*\(([a-f0-9]{7,40})\)/i', $currentVersion, $m)) {
        $currentCommit = $m[1];
        $isDev = true;
    }

    // Проверяем локальный git-репозиторий
    $gitInfo = getLocalGitInfo();
    if ($currentCommit === '' && !empty($gitInfo['commit'])) {
        $currentCommit = $gitInfo['commit'];
    }
    if ($gitInfo['branch'] === 'dev') {
        $isDev = true;
    }

    $settings = [];
    if (file_exists('editor_settings.json')) {
        $settings = @json_decode(file_get_contents('editor_settings.json'), true) ?: [];
    }

    $channel = isset($_GET['channel']) && in_array($_GET['channel'], ['stable', 'canary'], true)
        ? $_GET['channel']
        : (isset($settings['update_channel']) ? $settings['update_channel'] : 'stable');
    $repo = isset($_GET['repo'])
        ? sanitizeGithubRepo($_GET['repo'])
        : sanitizeGithubRepo(isset($settings['update_repo']) ? $settings['update_repo'] : 'slavaraninco-lgtm/NPBlog');
    $githubToken = isset($settings['update_github_token']) ? trim((string)$settings['update_github_token']) : '';

    $cacheDir = __DIR__ . '/sys_update_tmp';
    $cacheKey = md5($channel . '_' . $repo);
    $cacheFile = $cacheDir . '/cache_' . $cacheKey . '.json';
    $forceCheck = isset($_GET['force']) && ($_GET['force'] === '1' || $_GET['force'] === 'true');

    // Кэширование ответа на 15 минут для предотвращения превышения лимитов GitHub API
    if (!$forceCheck && file_exists($cacheFile)) {
        $cacheContent = @file_get_contents($cacheFile);
        $cached = @json_decode($cacheContent, true);
        if (is_array($cached) && isset($cached['_cached_at']) && (time() - $cached['_cached_at'] < 900)) {
            unset($cached['_cached_at']);
            $cached['currentVersion'] = $currentVersion;
            if ($channel === 'canary') {
                $cachedCommit = isset($cached['commit']) ? $cached['commit'] : '';
                $cached['hasUpdate'] = !isCanaryUpToDate($currentCommit, $cachedCommit);
            } else {
                $cleanLatest = ltrim(isset($cached['tag']) ? $cached['tag'] : (isset($cached['latestVersion']) ? $cached['latestVersion'] : ''), 'vV');
                $cleanCurrent = ltrim($currentVersion, 'vV');
                $numericCurrent = preg_replace('/[^0-9.]/', '', $cleanCurrent);
                if ($cleanCurrent === 'Unknown' || $cleanCurrent === 'dev') {
                    $cached['hasUpdate'] = true;
                } elseif ($numericCurrent !== '') {
                    $cached['hasUpdate'] = (version_compare($cleanLatest, $numericCurrent, '>') === true);
                } else {
                    $cached['hasUpdate'] = (version_compare($cleanLatest, $cleanCurrent, '>') === true);
                }
            }
            echo json_encode($cached, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $responsePayload = null;

    if ($channel === 'canary') {
        // Canary: проверка по коммитам в ветке dev
        $commits = [];

        // 1. Попытка через REST API
        $commitsUrl = 'https://api.github.com/repos/' . $repo . '/commits?sha=dev&per_page=15';
        $commitsJson = fetchGithubData($commitsUrl, $githubToken);
        if ($commitsJson !== false) {
            $apiCommits = @json_decode($commitsJson, true);
            if (is_array($apiCommits) && !empty($apiCommits)) {
                foreach ($apiCommits as $c) {
                    $rawMsg = isset($c['commit']['message']) ? trim($c['commit']['message']) : '';
                    $msgLines = explode("\n", $rawMsg);
                    $firstLine = trim($msgLines[0]);
                    $author = isset($c['author']['login']) ? $c['author']['login'] : (isset($c['commit']['author']['name']) ? $c['commit']['author']['name'] : 'dev');
                    $sha = isset($c['sha']) ? $c['sha'] : '';
                    if ($sha !== '') {
                        $commits[] = [
                            'sha' => $sha,
                            'message' => $firstLine,
                            'author' => $author
                        ];
                    }
                }
            }
        }

        // 2. Если REST API недоступен или лимит исчерпан — резервный запрос через Atom feed (безлимитный)
        if (empty($commits)) {
            $atomUrl = 'https://github.com/' . $repo . '/commits/dev.atom';
            $atomXml = fetchGithubData($atomUrl, $githubToken);
            if ($atomXml !== false) {
                $atomCommits = parseCanaryAtomFeed($atomXml);
                if (is_array($atomCommits) && !empty($atomCommits)) {
                    $commits = $atomCommits;
                }
            }
        }

        if (empty($commits)) {
            echo json_encode([
                'success' => false,
                'error' => 'Не удалось получить данные ветки dev из репозитория ' . $repo . '. Проверьте подключение к сети, имя репозитория или лимиты GitHub API.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $latestCommit = $commits[0];
        $latestSha = isset($latestCommit['sha']) ? $latestCommit['sha'] : '';
        $shortSha = substr($latestSha, 0, 7);

        // Числовая версия из удаленного dev-репозитория (например, "2.358")
        $devNumericVer = ($currentVersion !== 'Unknown' && $currentVersion !== 'dev') ? $currentVersion : '';
        $rawDevUrl = 'https://raw.githubusercontent.com/' . $repo . '/' . $latestSha . '/version.json';
        $rawDevJson = fetchGithubData($rawDevUrl, $githubToken);
        if ($rawDevJson !== false) {
            $remV = @json_decode($rawDevJson, true);
            if (!empty($remV['version']) && $remV['version'] !== 'dev') {
                $devNumericVer = (string)$remV['version'];
            }
        }

        $latestVersion = ($devNumericVer !== '') ? $devNumericVer . ' dev (' . $shortSha . ')' : 'dev (' . $shortSha . ')';
        $currentDisplayVersion = ($currentVersion !== '' && $currentVersion !== 'Unknown' && $currentVersion !== 'dev')
            ? ($currentCommit !== '' ? $currentVersion . ' dev (' . substr($currentCommit, 0, 7) . ')' : $currentVersion)
            : ($currentCommit !== '' ? 'dev (' . substr($currentCommit, 0, 7) . ')' : 'dev');

        $hasUpdate = !isCanaryUpToDate($currentCommit, $latestSha);

        // Чейнджлог по названиям коммитов: "коммит - автор"
        $changelog = [];
        foreach ($commits as $c) {
            if (!empty($c['message'])) {
                $changelog[] = $c['message'] . ' - ' . $c['author'];
            }
        }

        $archiveUrl = 'https://github.com/' . $repo . '/archive/' . $latestSha . '.zip';

        $responsePayload = [
            'success' => true,
            'hasUpdate' => $hasUpdate,
            'channel' => 'canary',
            'repo' => $repo,
            'currentVersion' => $currentDisplayVersion,
            'numericVersion' => $currentVersion,
            'latestVersion' => $latestVersion,
            'commit' => $latestSha,
            'shortCommit' => $shortSha,
            'changelog' => $changelog,
            'archiveUrl' => $archiveUrl
        ];
    } else {
        // Stable: релизы GitHub по релиз-тегам
        $latestTag = '';
        $releaseBody = '';

        // 1. Попытка через REST API /releases/latest
        $releaseUrl = 'https://api.github.com/repos/' . $repo . '/releases/latest';
        $releaseJson = fetchGithubData($releaseUrl, $githubToken);
        if ($releaseJson !== false) {
            $relData = @json_decode($releaseJson, true);
            if (isset($relData['tag_name'])) {
                $latestTag = (string)$relData['tag_name'];
                $releaseBody = isset($relData['body']) ? (string)$relData['body'] : '';
            }
        }

        // 2. Попытка через REST API /tags
        if ($latestTag === '') {
            $tagsUrl = 'https://api.github.com/repos/' . $repo . '/tags?per_page=5';
            $tagsJson = fetchGithubData($tagsUrl, $githubToken);
            if ($tagsJson !== false) {
                $tagsData = @json_decode($tagsJson, true);
                if (is_array($tagsData) && !empty($tagsData) && isset($tagsData[0]['name'])) {
                    $latestTag = (string)$tagsData[0]['name'];
                }
            }
        }

        // 3. Резервный запрос через Atom feed (безлимитный)
        if ($latestTag === '') {
            $releasesAtomUrl = 'https://github.com/' . $repo . '/releases.atom';
            $releasesAtomXml = fetchGithubData($releasesAtomUrl, $githubToken);
            if ($releasesAtomXml !== false) {
                $releases = parseReleasesAtomFeed($releasesAtomXml);
                if (is_array($releases) && !empty($releases)) {
                    $latestTag = $releases[0]['tag'];
                    $releaseBody = !empty($releases[0]['content']) ? $releases[0]['content'] : $releases[0]['title'];
                }
            }
        }

        if ($latestTag === '') {
            echo json_encode([
                'success' => false,
                'error' => 'Не удалось найти релизы или теги в репозитории ' . $repo . '. Проверьте подключение к сети или лимиты GitHub API.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cleanLatest = ltrim($latestTag, 'vV');
        $cleanCurrent = ltrim($currentVersion, 'vV');
        $numericCurrent = preg_replace('/[^0-9.]/', '', $cleanCurrent);

        $hasUpdate = false;
        if ($cleanCurrent === 'Unknown' || $cleanCurrent === 'dev') {
            $hasUpdate = true;
        } elseif ($numericCurrent !== '') {
            $hasUpdate = (version_compare($cleanLatest, $numericCurrent, '>') === true);
        } else {
            $hasUpdate = (version_compare($cleanLatest, $cleanCurrent, '>') === true);
        }

        // Чейнджлог берем строго из файла version.json
        $changelog = [];
        $rawVersionUrl = 'https://raw.githubusercontent.com/' . $repo . '/' . $latestTag . '/version.json';
        $rawVersionJson = fetchGithubData($rawVersionUrl, $githubToken);

        if ($rawVersionJson === false) {
            $contentsUrl = 'https://api.github.com/repos/' . $repo . '/contents/version.json?ref=' . urlencode($latestTag);
            $contentsRes = fetchGithubData($contentsUrl, $githubToken);
            if ($contentsRes !== false) {
                $cData = @json_decode($contentsRes, true);
                if (isset($cData['content'])) {
                    $rawVersionJson = @base64_decode($cData['content']);
                }
            }
        }

        if ($rawVersionJson !== false) {
            $remoteVData = @json_decode($rawVersionJson, true);
            if (!empty($remoteVData['changelog']) && is_array($remoteVData['changelog'])) {
                $changelog = $remoteVData['changelog'];
            }
        }

        if (empty($changelog)) {
            $changelog = ['Список изменений отсутствует в version.json для релиза ' . $latestTag];
        }

        $archiveUrl = 'https://github.com/' . $repo . '/archive/refs/tags/' . $latestTag . '.zip';

        $responsePayload = [
            'success' => true,
            'hasUpdate' => $hasUpdate,
            'channel' => 'stable',
            'repo' => $repo,
            'currentVersion' => $currentVersion,
            'latestVersion' => $cleanLatest,
            'tag' => $latestTag,
            'changelog' => $changelog,
            'archiveUrl' => $archiveUrl
        ];
    }

    // Сохраняем в кэш на 15 минут
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $toCache = $responsePayload;
    $toCache['_cached_at'] = time();
    @file_put_contents($cacheFile, json_encode($toCache, JSON_UNESCAPED_UNICODE), LOCK_EX);

    echo json_encode($responsePayload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'auto_update_progress') {
    $token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['token']) : '';
    $progressFile = 'sys_update_tmp/' . $token . '_progress.json';
    if ($token !== '' && file_exists($progressFile)) {
        $content = @file_get_contents($progressFile);
        $data = @json_decode($content, true);
        if ($data) {
            echo json_encode(['success' => true, 'progress' => $data], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

if ($action === 'prepare_auto_update') {
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    if (!class_exists('ZipArchive')) {
        echo json_encode([
            'success' => false,
            'error' => 'Расширение PHP ZipArchive (php-zip) не установлено на сервере.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $data = @json_decode($rawInput, true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'Неверный формат запроса']);
        exit;
    }

    $channel = isset($data['channel']) && $data['channel'] === 'canary' ? 'canary' : 'stable';
    $repo = sanitizeGithubRepo(isset($data['repo']) ? $data['repo'] : 'slavaraninco-lgtm/NPBlog');
    $tag = isset($data['tag']) ? trim($data['tag']) : '';
    $commit = isset($data['commit']) ? trim($data['commit']) : '';
    $archiveUrl = isset($data['archiveUrl']) ? trim($data['archiveUrl']) : '';
    $changelog = isset($data['changelog']) && is_array($data['changelog']) ? $data['changelog'] : [];

    $settings = [];
    if (file_exists('editor_settings.json')) {
        $settings = @json_decode(file_get_contents('editor_settings.json'), true) ?: [];
    }
    $githubToken = isset($settings['update_github_token']) ? trim((string)$settings['update_github_token']) : '';

    // Защита: проверяем что URL ведет на GitHub для этого репозитория
    $expectedPrefix1 = 'https://github.com/' . $repo . '/';
    $expectedPrefix2 = 'https://codeload.github.com/' . $repo . '/';
    if (strpos($archiveUrl, $expectedPrefix1) !== 0 && strpos($archiveUrl, $expectedPrefix2) !== 0) {
        if ($channel === 'canary' && $commit !== '') {
            $archiveUrl = 'https://github.com/' . $repo . '/archive/' . $commit . '.zip';
        } elseif ($tag !== '') {
            $archiveUrl = 'https://github.com/' . $repo . '/archive/refs/tags/' . $tag . '.zip';
        } else {
            echo json_encode(['success' => false, 'error' => 'Некорректная ссылка на архив']);
            exit;
        }
    }

    $token = uniqid('auto_update_');
    $tmpBase = 'sys_update_tmp/';
    if (!is_dir($tmpBase)) {
        if (!@mkdir($tmpBase, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Не удалось создать временную директорию sys_update_tmp на сервере']);
            exit;
        }
    }

    $progressFile = $tmpBase . $token . '_progress.json';
    file_put_contents($progressFile, json_encode([
        'status' => 'downloading',
        'percent' => 5,
        'message' => 'Подключение к GitHub и загрузка архива...',
        'detail' => ''
    ], JSON_UNESCAPED_UNICODE));

    $zipFile = $tmpBase . $token . '.zip';
    $fp = @fopen($zipFile, 'w+');
    if (!$fp) {
        @unlink($progressFile);
        echo json_encode(['success' => false, 'error' => 'Не удалось создать временный файл архива на сервере']);
        exit;
    }

    $downloadFailed = false;
    $downloadError = '';
    $downloadedBytes = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($archiveUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NPBlog-Updater');
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        $curlHeaders = ['User-Agent: NPBlog-Updater'];
        if ($githubToken !== '') {
            $curlHeaders[] = 'Authorization: token ' . $githubToken;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($progressFile) {
            static $lastWrite = 0;
            $now = microtime(true);
            if ($now - $lastWrite < 0.35) {
                return;
            }
            $lastWrite = $now;

            $percent = 10;
            $detail = '';
            if ($downloadSize > 0) {
                $pct = (int)(($downloaded / $downloadSize) * 45);
                $percent = 10 + $pct;
                $detail = round($downloaded / 1048576, 1) . ' / ' . round($downloadSize / 1048576, 1) . ' МБ';
            } elseif ($downloaded > 0) {
                $detail = round($downloaded / 1048576, 1) . ' МБ';
                $percent = min(50, 10 + (int)($downloaded / 200000));
            }
            file_put_contents($progressFile, json_encode([
                'status' => 'downloading',
                'percent' => $percent,
                'detail' => $detail,
                'message' => 'Загрузка архива обновления...'
            ], JSON_UNESCAPED_UNICODE));
        });
        $cRes = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $downloadedBytes = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);
        fclose($fp);

        if (!$cRes || $httpCode < 200 || $httpCode >= 300 || $downloadedBytes < 1000) {
            $downloadFailed = true;
            $downloadError = 'Сбой загрузки архива с GitHub (HTTP ' . $httpCode . ')';
        }
    } else {
        fclose($fp);
        $streamHeaders = "User-Agent: NPBlog-Updater\r\n";
        if ($githubToken !== '') {
            $streamHeaders .= "Authorization: token " . $githubToken . "\r\n";
        }
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => $streamHeaders,
                'timeout' => 180,
                'follow_location' => 1
            ]
        ];
        $context = stream_context_create($opts);
        $remoteData = @file_get_contents($archiveUrl, false, $context);
        if ($remoteData === false || strlen($remoteData) < 1000) {
            $downloadFailed = true;
            $downloadError = 'Сбой загрузки архива через file_get_contents';
        } else {
            file_put_contents($zipFile, $remoteData);
            $downloadedBytes = strlen($remoteData);
        }
    }

    if ($downloadFailed) {
        @unlink($zipFile);
        @unlink($progressFile);
        echo json_encode(['success' => false, 'error' => $downloadError], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Распаковка архива
    file_put_contents($progressFile, json_encode([
        'status' => 'extracting',
        'percent' => 58,
        'message' => 'Распаковка и анализ архива...',
        'detail' => round($downloadedBytes / 1048576, 1) . ' МБ'
    ], JSON_UNESCAPED_UNICODE));

    $zip = new ZipArchive();
    $zipStatus = $zip->open($zipFile);
    if ($zipStatus !== TRUE) {
        @unlink($zipFile);
        @unlink($progressFile);
        echo json_encode(['success' => false, 'error' => 'Загруженный файл не является корректным ZIP архивом (код: ' . $zipStatus . ')'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tmpDir = $tmpBase . $token . '/';
    if (!is_dir($tmpDir)) {
        if (!@mkdir($tmpDir, 0755, true)) {
            $zip->close();
            @unlink($zipFile);
            @unlink($progressFile);
            echo json_encode(['success' => false, 'error' => 'Не удалось создать папку распаковки'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $zipComment = trim((string)$zip->getArchiveComment());
    if ($commit === '' && preg_match('/^[a-f0-9]{40}$/i', $zipComment)) {
        $commit = strtolower($zipComment);
    }

    $zip->extractTo($tmpDir);
    $zip->close();
    @unlink($zipFile);

    $filesToReplace = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $rel = substr($item->getPathname(), strlen($tmpDir));
            $rel = str_replace('\\', '/', $rel);
            $filesToReplace[] = $rel;
        }
    }

    $rootFolder = '';
    $hasRootFiles = false;
    $firstLevelFolders = [];
    foreach ($filesToReplace as $f) {
        $parts = explode('/', $f);
        if (count($parts) > 1) {
            $firstLevelFolders[$parts[0]] = true;
        } else {
            $hasRootFiles = true;
        }
    }
    if (!$hasRootFiles && count($firstLevelFolders) === 1) {
        reset($firstLevelFolders);
        $rootFolder = key($firstLevelFolders) . '/';
    }

    $filteredFiles = [];
    foreach ($filesToReplace as $f) {
        $actualPath = $f;
        if ($rootFolder && strpos($f, $rootFolder) === 0) {
            $actualPath = substr($f, strlen($rootFolder));
        }
        if ($actualPath && !isProtected($actualPath)) {
            $filteredFiles[] = $actualPath;
        }
    }

    // Если canary: записываем метаданные коммита в распакованный version.json, сохраняя числовую версию
    if ($channel === 'canary' && $commit !== '') {
        $vPath = $tmpDir . $rootFolder . 'version.json';
        $existingVData = [];
        if (file_exists($vPath)) {
            $existingVData = @json_decode(file_get_contents($vPath), true) ?: [];
        }

        // Сохраняем числовую версию из репозитория/архива (например "2.358")
        $numericVer = (!empty($existingVData['version']) && $existingVData['version'] !== 'dev')
            ? (string)$existingVData['version']
            : '';

        if ($numericVer === '' && file_exists('version.json')) {
            $localV = @json_decode(file_get_contents('version.json'), true);
            if (!empty($localV['version']) && $localV['version'] !== 'dev') {
                $numericVer = (string)$localV['version'];
            }
        }

        if ($numericVer === '') {
            $numericVer = 'Unknown';
        }

        $vData = [
            'version' => $numericVer,
            'dev' => true,
            'commit' => $commit,
            'last_updated' => date('Y-m-d\TH:i:s\Z')
        ];
        if (!empty($existingVData['changelog']) && is_array($existingVData['changelog'])) {
            $vData['changelog'] = $existingVData['changelog'];
        } elseif (!empty($changelog)) {
            $vData['changelog'] = $changelog;
        }
        file_put_contents($vPath, json_encode($vData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    file_put_contents($progressFile, json_encode([
        'status' => 'ready',
        'percent' => 70,
        'message' => 'Архив готов к установке. Запуск обновления...',
        'detail' => count($filteredFiles) . ' файлов'
    ], JSON_UNESCAPED_UNICODE));

    echo json_encode([
        'success' => true,
        'token' => $token,
        'rootFolder' => $rootFolder,
        'fileCount' => count($filteredFiles),
        'archiveBytes' => $downloadedBytes,
        'archiveSize' => round($downloadedBytes / 1048576, 2) . ' МБ'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'preview') {
    if (!class_exists('ZipArchive')) {
        echo json_encode([
            'success' => false,
            'error' => 'Расширение PHP ZipArchive (php-zip) не установлено на сервере. Установите его: apt install php-zip'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($_FILES['updateFile'])) {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        $postMaxSize = ini_get('post_max_size');
        if ($contentLength > 0) {
            echo json_encode([
                'success' => false,
                'error' => "Размер архива превышает лимит сервера post_max_size ($postMaxSize). Проверьте размер архива или увеличьте лимит в php.ini."
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Файл архива не получен сервером'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $file = $_FILES['updateFile'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Размер файла превышает лимит upload_max_filesize (' . ini_get('upload_max_filesize') . ') в php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'Размер файла превышает лимит формы.',
            UPLOAD_ERR_PARTIAL => 'Файл был передан лишь частично.',
            UPLOAD_ERR_NO_FILE => 'Файл не был передан.',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная папка загрузок на сервере.',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось сохранить файл на диск сервера.',
            UPLOAD_ERR_EXTENSION => 'Загрузка файла прервана расширением PHP.'
        ];
        $msg = $uploadErrors[$file['error']] ?? ('Ошибка загрузки файла (' . $file['error'] . ')');
        echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $zip = new ZipArchive;
    $zipStatus = $zip->open($file['tmp_name']);
    if ($zipStatus === TRUE) {
        $filesToReplace = [];
        $tmpToken = uniqid('update_');
        $tmpDir = 'sys_update_tmp/' . $tmpToken . '/';
        
        if (!is_dir($tmpDir)) {
            if (!@mkdir($tmpDir, 0755, true)) {
                $zip->close();
                echo json_encode([
                    'success' => false,
                    'error' => 'Нет прав на создание временной папки sys_update_tmp на сервере. Проверьте права веб-сервера (chmod / chown).'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        
        $zip->extractTo($tmpDir);
        
        $newVersion = 'Unknown';
        
        // Рекурсивный обход распакованных файлов
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $relativePath = substr($item->getPathname(), strlen($tmpDir));
                $relativePath = str_replace('\\', '/', $relativePath);
                $filesToReplace[] = $relativePath;
            }
        }
        $zip->close();
        
        // Проверяем, есть ли корневая папка-обёртка в архиве
        $rootFolder = '';
        $hasRootFiles = false;
        $firstLevelFolders = [];
        foreach ($filesToReplace as $f) {
            $parts = explode('/', $f);
            if (count($parts) > 1) {
                $firstLevelFolders[$parts[0]] = true;
            } else {
                $hasRootFiles = true;
            }
        }
        
        if (!$hasRootFiles && count($firstLevelFolders) === 1) {
            reset($firstLevelFolders);
            $rootFolder = key($firstLevelFolders) . '/';
        }
        
        $filteredFiles = [];
        foreach ($filesToReplace as $f) {
            $actualPath = $f;
            if ($rootFolder && strpos($f, $rootFolder) === 0) {
                $actualPath = substr($f, strlen($rootFolder));
            }
            
            // Check version
            if ($actualPath === 'version.json') {
                $vData = @json_decode(file_get_contents($tmpDir . $f), true);
                if ($vData) {
                    if (!empty($vData['dev']) && ($vData['dev'] === true || $vData['dev'] === 'true')) {
                        $newVersion = 'dev';
                    } elseif (isset($vData['version'])) {
                        $newVersion = $vData['version'];
                    }
                }
            }
            
            if ($actualPath && !isProtected($actualPath)) {
                $filteredFiles[] = $actualPath;
            }
        }
        
        // Get current version
        $currentVersion = 'Unknown';
        if (file_exists('version.json')) {
            $currData = @json_decode(file_get_contents('version.json'), true);
            if ($currData) {
                if (!empty($currData['dev']) && ($currData['dev'] === true || $currData['dev'] === 'true')) {
                    $currentVersion = 'dev';
                } elseif (isset($currData['version'])) {
                    $currentVersion = $currData['version'];
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'files' => $filteredFiles,
            'token' => $tmpToken,
            'rootFolder' => $rootFolder,
            'currentVersion' => $currentVersion,
            'newVersion' => $newVersion
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $zipErrors = [
            ZipArchive::ER_NOZIP => 'Файл не является корректным ZIP-архивом.',
            ZipArchive::ER_INCONS => 'Архив повреждён (несогласованная структура).',
            ZipArchive::ER_CRC => 'Ошибка контрольной суммы CRC в архиве.',
            ZipArchive::ER_OPEN => 'Не удалось открыть файл архива.'
        ];
        $errText = $zipErrors[$zipStatus] ?? ('Код ошибки ZipArchive: ' . $zipStatus);
        echo json_encode(['success' => false, 'error' => 'Не удалось открыть ZIP архив: ' . $errText], JSON_UNESCAPED_UNICODE);
    }
} 
elseif ($action === 'update') {
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');

    if (!class_exists('ZipArchive')) {
        echo json_encode(['success' => false, 'error' => 'Расширение PHP ZipArchive не установлено на сервере.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['token'] ?? '';
    $rootFolder = $data['rootFolder'] ?? '';
    
    if (!$token) {
        echo json_encode(['success' => false, 'error' => 'Нет токена обновления']);
        exit;
    }
    
    $tmpDir = 'sys_update_tmp/' . $token . '/';
    if (!is_dir($tmpDir)) {
        echo json_encode(['success' => false, 'error' => 'Временная папка с файлами обновления не найдена']);
        exit;
    }
    
    // 1. Создаем быстрый и надежный бэкап системы (исключая тяжелый .git, медиафайлы и архивы)
    $backupDir = getEditorBackupPath();
    if (!is_dir($backupDir)) {
        if (!@mkdir($backupDir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Нет прав на создание папки бэкапов: ' . $backupDir], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    $backupFilename = $backupDir . 'backup_' . date('Ymd_His') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($backupFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $rootPath = realpath('./');
        $normEditorBackup = rtrim(str_replace('\\', '/', realpath($backupDir) ?: $backupDir), '/') . '/';
        $normDataBackup = rtrim(str_replace('\\', '/', realpath(getBackupPath()) ?: getBackupPath()), '/') . '/';
        $normAutosave = rtrim(str_replace('\\', '/', realpath(getAutosavePath()) ?: getAutosavePath()), '/') . '/';
        $backupDirs = [$normEditorBackup, $normDataBackup, $normAutosave];

        $dirIterator = new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $filterIterator = new RecursiveCallbackFilterIterator($dirIterator, function ($current) use ($rootPath, $backupDirs) {
            $rel = ltrim(str_replace('\\', '/', substr($current->getPathname(), strlen($rootPath))), '/');
            if ($current->isDir()) {
                $relDir = strtolower($rel) . '/';
                // Пропускаем папки, не относящиеся к системе или содержащие огромные данные
                if (strpos($relDir, '.git/') === 0 ||
                    strpos($relDir, '.github/') === 0 ||
                    strpos($relDir, '.vscode/') === 0 ||
                    strpos($relDir, '.idea/') === 0 ||
                    strpos($relDir, 'node_modules/') === 0 ||
                    strpos($relDir, 'sys_update_tmp/') === 0 ||
                    strpos($relDir, 'rollback_tmp/') === 0 ||
                    strpos($relDir, 'editor_backup/') === 0 ||
                    strpos($relDir, 'data_backup/') === 0 ||
                    strpos($relDir, 'autosave/') === 0 ||
                    strpos($relDir, 'data/images/') === 0 ||
                    strpos($relDir, 'data/video/') === 0 ||
                    strpos($relDir, 'data/audio/') === 0 ||
                    strpos($relDir, 'data/files/') === 0 ||
                    strpos($relDir, 'data/blog/') === 0) {
                    return false;
                }
                return true;
            }
            $relLower = strtolower($rel);
            if (substr($relLower, -4) === '.zip') return false;
            foreach ($backupDirs as $bDir) {
                if (!empty($bDir) && strpos(str_replace('\\', '/', $current->getPathname()), $bDir) === 0) {
                    return false;
                }
            }
            return true;
        });

        $files = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось создать файл бэкапа: ' . $backupFilename], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 2. Pre-flight checks and build atomic operations
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $operations = [];
    $rollbackTmpDir = 'rollback_tmp/' . $token . '/';
    if (!is_dir($rollbackTmpDir)) {
        @mkdir($rollbackTmpDir, 0755, true);
    }
    
    $newVersionData = null;
    $migrationFiles = [];
    
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $relativePath = substr($item->getPathname(), strlen($tmpDir));
            $relativePath = str_replace('\\', '/', $relativePath);
            
            $actualPath = $relativePath;
            if ($rootFolder && strpos($relativePath, $rootFolder) === 0) {
                $actualPath = substr($relativePath, strlen($rootFolder));
            }
            
            if ($actualPath === 'version.json') {
                $newVersionData = json_decode(file_get_contents($item->getPathname()), true);
            }
            
            // Если это файл миграции
            if (strpos($actualPath, 'migrations/') === 0 && substr($actualPath, -4) === '.php') {
                $migrationFiles[] = $item->getPathname();
            }
            
            if ($actualPath && !isProtected($actualPath)) {
                $targetDir = dirname($actualPath);
                
                // Проверка прав на запись в целевую директорию
                if ($targetDir !== '.' && is_dir($targetDir) && !is_writable($targetDir)) {
                    deleteDir($rollbackTmpDir);
                    echo json_encode(['success' => false, 'error' => "Нет прав на запись в директорию: $targetDir (проверьте chown/chmod на сервере)"], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                // Проверка прав на перезапись существующего файла
                if (file_exists($actualPath) && !is_writable($actualPath)) {
                    deleteDir($rollbackTmpDir);
                    echo json_encode(['success' => false, 'error' => "Нет прав на изменение файла: $actualPath (проверьте chown/chmod на сервере)"], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $operations[] = [
                    'source' => $item->getPathname(),
                    'target' => $actualPath,
                    'isNew' => !file_exists($actualPath)
                ];
            }
        }
    }
    
    // 3. Атомарное копирование
    $rollbackOperations = [];
    $updateFailed = false;
    $errorMsg = '';
    
    foreach ($operations as $op) {
        $targetDir = dirname($op['target']);
        if ($targetDir !== '.' && !is_dir($targetDir)) {
            if (!@mkdir($targetDir, 0755, true)) {
                $updateFailed = true;
                $errorMsg = "Не удалось создать директорию: $targetDir";
                break;
            }
        }
        
        if (!$op['isNew']) {
            // Копируем во временную папку отката
            $rollbackFile = $rollbackTmpDir . $op['target'];
            $rbDir = dirname($rollbackFile);
            if (!is_dir($rbDir)) @mkdir($rbDir, 0755, true);
            
            if (@copy($op['target'], $rollbackFile)) {
                $rollbackOperations[] = [
                    'rollback_source' => $rollbackFile,
                    'target' => $op['target'],
                    'was_created' => false
                ];
            } else {
                $updateFailed = true;
                $errorMsg = "Не удалось подготовить rollback для файла: {$op['target']}";
                break;
            }
        } else {
            $rollbackOperations[] = [
                'target' => $op['target'],
                'was_created' => true
            ];
        }
        
        // Применяем обновление
        if (!@copy($op['source'], $op['target'])) {
            $updateFailed = true;
            $errorMsg = "Не удалось скопировать файл: {$op['target']}";
            break;
        }
    }
    
    // 4. Rollback в случае ошибки
    if ($updateFailed) {
        foreach (array_reverse($rollbackOperations) as $rop) {
            if ($rop['was_created']) {
                @unlink($rop['target']);
            } else {
                @copy($rop['rollback_source'], $rop['target']);
            }
        }
        deleteDir($rollbackTmpDir);
        echo json_encode(['success' => false, 'error' => "Сбой при копировании. Система восстановлена. Ошибка: $errorMsg"]);
        exit;
    }
    
    // 5. Запуск миграций (выполняем файлы миграции из распакованного архива, так как они защищены и не скопировались бы, если они .php? Нет, они не защищены, если это .php файлы, но давайте выполним их из целевой папки migrations)
    $migrationErrors = [];
    if (is_dir('migrations')) {
        $migFiles = glob('migrations/*.php');
        if ($migFiles) {
            foreach ($migFiles as $mFile) {
                try {
                    // Используем буферизацию вывода, чтобы миграция не сломала JSON ответ
                    ob_start();
                    include_once $mFile;
                    ob_end_clean();
                } catch (Exception $e) {
                    $migrationErrors[] = "Ошибка в $mFile: " . $e->getMessage();
                }
            }
        }
    }
    
    // 6. Обновление version.json
    if ($newVersionData) {
        $verPayload = [
            'version' => isset($newVersionData['version']) ? $newVersionData['version'] : 'Unknown',
            'dev' => isset($newVersionData['dev']) ? (bool)$newVersionData['dev'] : false,
            'last_updated' => date('Y-m-d\TH:i:s\Z')
        ];
        if (!empty($newVersionData['changelog']) && is_array($newVersionData['changelog'])) {
            $verPayload['changelog'] = $newVersionData['changelog'];
        }
        if (!empty($newVersionData['commit'])) {
            $verPayload['commit'] = $newVersionData['commit'];
        }
        file_put_contents('version.json', json_encode($verPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    // Очистка
    deleteDir($tmpDir);
    deleteDir($rollbackTmpDir);
    @unlink('sys_update_tmp/' . $token . '_progress.json');
    
    $response = [
        'success' => true, 
        'updatedCount' => count($operations), 
        'backup' => $backupFilename
    ];
    
    if (!empty($migrationErrors)) {
        $response['migrationErrors'] = $migrationErrors;
    }
    
    echo json_encode($response);
}
?>
