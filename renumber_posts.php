<?php
header('Content-Type: application/json');

require_once 'background_functions.php';

// Функция для перенумерации статей
function renumberPosts() {
    $metaFile = 'data/blog/posts-meta.json';
    $backupMetaFile = 'data_backup/backup-meta.json';
    
    if (!file_exists($metaFile)) {
        return ['success' => false, 'error' => 'Файл метаданных не найден'];
    }
    
    $meta = json_decode(file_get_contents($metaFile), true);
    if (empty($meta)) {
        return ['success' => true, 'message' => 'Нет статей для перенумерации'];
    }
    
    // Сортируем статьи по ID
    usort($meta, function($a, $b) {
        return $a['id'] - $b['id'];
    });
    
    $changes = [];
    $newMeta = [];
    $backupMeta = [];
    
    if (file_exists($backupMetaFile)) {
        $backupMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];
    }
    
    // Загружаем настройки фонов
    $backgrounds = loadBackgrounds();
    $newBackgrounds = [];
    
    // Перенумеровываем статьи
    foreach ($meta as $index => $post) {
        $oldId = $post['id'];
        $newId = $index + 1;
        
        if ($oldId != $newId) {
            $changes[] = [
                'oldId' => $oldId,
                'newId' => $newId,
                'title' => $post['title']
            ];
            
            // Переименовываем файл статьи
            $oldFilename = 'data/blog/post-' . $oldId . '.html';
            $newFilename = 'data/blog/post-' . $newId . '.html';
            
            if (file_exists($oldFilename)) {
                // Читаем содержимое и обновляем внутренние ссылки если есть
                $content = file_get_contents($oldFilename);
                file_put_contents($newFilename, $content);
                unlink($oldFilename);
            }
            
            // Переименовываем фоновое изображение если есть
            if (isset($backgrounds[$oldId]) && isset($backgrounds[$oldId]['background'])) {
                $oldBgFile = $backgrounds[$oldId]['background'];
                if (preg_match('/^bg-' . $oldId . '\.(.+)$/', $oldBgFile, $match)) {
                    $extension = $match[1];
                    $newBgFile = 'bg-' . $newId . '.' . $extension;
                    
                    $oldBgPath = 'data/backgrounds/' . $oldBgFile;
                    $newBgPath = 'data/backgrounds/' . $newBgFile;
                    
                    if (file_exists($oldBgPath)) {
                        rename($oldBgPath, $newBgPath);
                        $backgrounds[$oldId]['background'] = $newBgFile;
                    }
                }
            }
            
            // Переносим настройки фонов на новый ID
            if (isset($backgrounds[$oldId])) {
                $newBackgrounds[$newId] = $backgrounds[$oldId];
            }
            
            // Переименовываем папку с бэкапами (только если статья существует)
            $oldBackupDir = 'data_backup/' . $oldId . '/';
            $newBackupDir = 'data_backup/' . $newId . '/';
            
            if (is_dir($oldBackupDir)) {
                // Создаём новую папку если её нет
                if (!is_dir($newBackupDir)) {
                    mkdir($newBackupDir, 0755, true);
                }
                
                // Переименовываем файлы бэкапов
                $backupFiles = glob($oldBackupDir . $oldId . '-*.html');
                foreach ($backupFiles as $backupFile) {
                    $basename = basename($backupFile);
                    if (preg_match('/' . $oldId . '-(\d+)\.html$/', $basename, $match)) {
                        $backupNumber = $match[1];
                        $newBackupFilename = $newBackupDir . $newId . '-' . $backupNumber . '.html';
                        
                        // Переименовываем файл
                        rename($backupFile, $newBackupFilename);
                    }
                }
                
                // Удаляем старую папку если она пустая
                if (count(glob($oldBackupDir . '*')) === 0) {
                    rmdir($oldBackupDir);
                }
            }
            
            // Обновляем метаданные бэкапов
            if (isset($backupMeta[$oldId])) {
                $backupMeta[$newId] = $backupMeta[$oldId];
                $backupMeta[$newId]['postId'] = $newId;
                
                // Обновляем имена файлов в метаданных бэкапов
                foreach ($backupMeta[$newId]['backups'] as &$backup) {
                    $backup['filename'] = str_replace($oldId . '-', $newId . '-', $backup['filename']);
                }
                
                unset($backupMeta[$oldId]);
            }
        } else {
            // ID не изменился, просто копируем настройки фонов
            if (isset($backgrounds[$oldId])) {
                $newBackgrounds[$newId] = $backgrounds[$oldId];
            }
        }
        
        // Обновляем метаданные статьи
        $newMeta[] = [
            'id' => $newId,
            'title' => $post['title'],
            'date' => $post['date'],
            'filename' => 'post-' . $newId . '.html'
        ];
    }
    
    // Сохраняем обновленные метаданные
    file_put_contents($metaFile, json_encode($newMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Сохраняем обновленные настройки фонов
    saveBackgrounds($newBackgrounds);
    
    // Сортируем бэкапы по ключам и сохраняем
    ksort($backupMeta);
    file_put_contents($backupMetaFile, json_encode($backupMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return [
        'success' => true,
        'changes' => $changes,
        'message' => count($changes) > 0 ? 'Перенумерация выполнена' : 'Нумерация корректна'
    ];
}

// Проверка нумерации без изменений
function checkNumbering() {
    $metaFile = 'data/blog/posts-meta.json';
    
    if (!file_exists($metaFile)) {
        return ['success' => false, 'error' => 'Файл метаданных не найден'];
    }
    
    $meta = json_decode(file_get_contents($metaFile), true);
    if (empty($meta)) {
        return ['success' => true, 'needsFix' => false, 'message' => 'Нет статей'];
    }
    
    // Сортируем статьи по ID
    usort($meta, function($a, $b) {
        return $a['id'] - $b['id'];
    });
    
    $issues = [];
    
    foreach ($meta as $index => $post) {
        $expectedId = $index + 1;
        if ($post['id'] != $expectedId) {
            $issues[] = [
                'currentId' => $post['id'],
                'expectedId' => $expectedId,
                'title' => $post['title']
            ];
        }
    }
    
    return [
        'success' => true,
        'needsFix' => count($issues) > 0,
        'issues' => $issues
    ];
}

// Обработка запроса
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'check';

if ($action === 'check') {
    echo json_encode(checkNumbering());
} else if ($action === 'fix') {
    echo json_encode(renumberPosts());
} else {
    echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}
