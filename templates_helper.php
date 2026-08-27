<?php
require_once __DIR__ . '/security_bootstrap.php';
// templates_helper.php

function initTemplatesSystem() {
    $templatesDir = getDataPath('blog/templates/');
    if (!is_dir($templatesDir)) {
        @mkdir($templatesDir, 0777, true);
        @chmod($templatesDir, 0777);
    }
    
    $npblogDir = $templatesDir . 'NPBlog/';
    if (!is_dir($npblogDir)) {
        @mkdir($npblogDir, 0777, true);
        @chmod($npblogDir, 0777);
    }
    
    $mainTemplateFile = $npblogDir . 'main.html';
    $legacyMainFile = $templatesDir . 'main.html';
    $legacyTemplateFile = getDataPath('blog/template_post.html');
    
    // Copy template_post.html or main.html to NPBlog/main.html if it exists, or create a default one
    if (!file_exists($mainTemplateFile)) {
        if (file_exists($legacyMainFile)) {
            copy($legacyMainFile, $mainTemplateFile);
            @unlink($legacyMainFile);
        } elseif (file_exists($legacyTemplateFile)) {
            copy($legacyTemplateFile, $mainTemplateFile);
        } else {
            $basicTemplate = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{TITLE}}</title>
    <meta name="post-id" content="{{POST_ID}}">
    {{META_TAGS}}
    <script>
        if(localStorage.getItem(\'theme\') === \'dark\') document.documentElement.setAttribute(\'data-theme\', \'dark\');
        if(/Android/i.test(navigator.userAgent)) document.documentElement.classList.add(\'is-android\');
    </script>
    <link rel="stylesheet" href="assets/blog-post.css?v=1.0.2">
    <style>
{{CUSTOM_FONTS}}
    </style>
</head>
<body {{BODY_STYLE}}>
    <button class="theme-toggle" onclick="toggleTheme()">🌓 Тема</button>
    {{CONTENT_WRAPPER_START}}
    <h1>{{TITLE}}</h1>
    <div class="date">📅 {{DATE}}</div>
    <article id="npblog-post-content" class="content">
{{CONTENT}}
    </article>
    <a href="../blog.html" class="back-link">← Назад к списку статей</a>
    {{CONTENT_WRAPPER_END}}
    <div class="powered-by">Powered by NPBlog</div>
    <div class="image-modal" id="imageModal">
        <button class="image-modal-close" onclick="closeImageModal()">×</button>
        <button class="image-modal-nav image-modal-prev" onclick="navigateModalImage(-1)" title="Предыдущее изображение">‹</button>
        <button class="image-modal-nav image-modal-next" onclick="navigateModalImage(1)" title="Следующее изображение">›</button>
        <div class="image-modal-container" id="imageContainer">
            <img class="image-modal-content" id="modalImage" src="" alt="">
        </div>
        <div class="image-modal-toolbar">
            <button class="image-modal-btn" onclick="zoomOut()" title="Уменьшить">−</button>
            <div class="image-modal-zoom-level" id="zoomLevel">100%</div>
            <button class="image-modal-btn" onclick="zoomIn()" title="Увеличить">+</button>
            <button class="image-modal-btn" onclick="resetZoom()" title="Сбросить">⟲</button>
            <button class="image-modal-btn" onclick="downloadImage()" title="Скачать">⬇</button>
        </div>
    </div>
    <script src="assets/blog-post.js" defer></script>
</body>
</html>';
            file_put_contents($mainTemplateFile, $basicTemplate);
        }
        @chmod($mainTemplateFile, 0666);
    }
    
    $settingsFile = $templatesDir . 'settings.json';
    if (!file_exists($settingsFile)) {
        $initialSettings = [
            'default' => 'main',
            'post_templates' => new stdClass(),
            'templates' => [
                'main' => [
                    'title' => 'Стандартный шаблон',
                    'description' => 'Стандартный шаблон блога с поддержкой темной темы, адаптивным дизайном и подложкой.',
                    'is_system' => true,
                    'path' => 'NPBlog/main.html'
                ]
            ]
        ];
        file_put_contents($settingsFile, json_encode($initialSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @chmod($settingsFile, 0666);
    } else {
        $settings = json_decode(@file_get_contents($settingsFile), true) ?: [];
        $changed = false;
        if (isset($settings['templates']['main']) && !isset($settings['templates']['main']['path'])) {
            $settings['templates']['main']['path'] = 'NPBlog/main.html';
            $changed = true;
        }
        if ($changed) {
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}

function getTemplatePath($postId = null) {
    initTemplatesSystem();
    $templatesDir = getDataPath('blog/templates/');
    $settingsFile = $templatesDir . 'settings.json';
    
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(@file_get_contents($settingsFile), true) ?: [];
    }
    
    // Check if there is a specific template assigned to this post ID
    if ($postId !== null && isset($settings['post_templates']) && isset($settings['post_templates'][$postId])) {
        $postTemplateName = $settings['post_templates'][$postId];
        $path = '';
        if (isset($settings['templates'][$postTemplateName])) {
            $path = isset($settings['templates'][$postTemplateName]['path']) ? $settings['templates'][$postTemplateName]['path'] : '';
        }
        if (empty($path)) {
            if ($postTemplateName === 'main') {
                $path = 'NPBlog/main.html';
            } else {
                $path = $postTemplateName . '.html';
            }
        }
        $postTemplateFile = $templatesDir . $path;
        if (file_exists($postTemplateFile)) {
            return $postTemplateFile;
        }
    }
    
    // Fallback to default template in settings
    if (isset($settings['default'])) {
        $defaultTemplateName = $settings['default'];
        $path = '';
        if (isset($settings['templates'][$defaultTemplateName])) {
            $path = isset($settings['templates'][$defaultTemplateName]['path']) ? $settings['templates'][$defaultTemplateName]['path'] : '';
        }
        if (empty($path)) {
            if ($defaultTemplateName === 'main') {
                $path = 'NPBlog/main.html';
            } else {
                $path = $defaultTemplateName . '.html';
            }
        }
        $defaultTemplateFile = $templatesDir . $path;
        if (file_exists($defaultTemplateFile)) {
            return $defaultTemplateFile;
        }
    }
    
    $fallbackFile = $templatesDir . 'NPBlog/main.html';
    if (file_exists($fallbackFile)) {
        return $fallbackFile;
    }
    return $templatesDir . 'main.html';
}

function getTemplateHtml($templateFile) {
    if (!file_exists($templateFile)) {
        return "";
    }
    $html = file_get_contents($templateFile);
    return rewriteTemplateRelativePaths($html, $templateFile);
}

function rewriteTemplateUrl($url, $subFolder) {
    if (empty($url) || 
        preg_match('/^(?:https?:|\/\/|#|\{\{|data:|mailto:|tel:|javascript:)/i', $url)) {
        return $url;
    }
    
    $isAbsoluteTemplate = false;
    if (strpos($url, '/') === 0) {
        if (stripos($url, '/templates/') === 0) {
            $isAbsoluteTemplate = true;
            $url = substr($url, 1);
        } else {
            return $url;
        }
    }
    
    if (strpos($url, './') === 0) {
        $url = substr($url, 2);
    }
    
    // Check if it starts with templates/
    if (stripos($url, 'templates/') === 0) {
        $url = substr($url, 10); // Strip 'templates/'
        
        // Get the next segment (the old template folder name)
        $slashPos = strpos($url, '/');
        if ($slashPos !== false) {
            $nextSegment = substr($url, 0, $slashPos);
            
            // Check if this segment exists as a directory inside our template directory
            $templatesDir = getDataPath('blog/templates/');
            $targetDir = $templatesDir . $subFolder . '/' . $nextSegment;
            
            // If it does NOT exist as a directory, it's an old template folder name, so strip it!
            if (!is_dir($targetDir)) {
                $url = substr($url, $slashPos + 1);
            }
        }
    }
    
    if (stripos($url, 'blog/') === 0) {
        $url = substr($url, 5);
    }
    if (stripos($url, $subFolder . '/') === 0) {
        $url = substr($url, strlen($subFolder) + 1);
    }
    
    return 'templates/' . $subFolder . '/' . $url;
}

function rewriteTemplateRelativePaths($html, $templateFile) {
    $templatesDir = realpath(getDataPath('blog/templates/'));
    
    $templateDirRealPath = realpath(dirname($templateFile));
    if (!$templatesDir || !$templateDirRealPath) {
        return $html;
    }
    $templateRealPath = $templateDirRealPath . '/' . basename($templateFile);
    
    if (strpos($templateRealPath, $templatesDir) === 0) {
        $relPath = ltrim(substr($templateRealPath, strlen($templatesDir)), '/\\');
        $relPath = str_replace('\\', '/', $relPath);
        $dirPart = dirname($relPath);
        
        if ($dirPart !== '.' && !empty($dirPart)) {
            if ($dirPart === 'NPBlog') {
                return $html;
            }
            
            // Rewrite src="..." and href="..."
            $html = preg_replace_callback('/(src|href)=["\'](.*?)["\']/i', function($matches) use ($dirPart) {
                $attr = $matches[1];
                $url = $matches[2];
                $newUrl = rewriteTemplateUrl($url, $dirPart);
                return $attr . '="' . $newUrl . '"';
            }, $html);
            
            // Rewrite url(...) in CSS/style blocks
            $html = preg_replace_callback('/url\(["\']?(.*?)["\']?\)/i', function($matches) use ($dirPart) {
                $url = $matches[1];
                $newUrl = rewriteTemplateUrl($url, $dirPart);
                return 'url("' . $newUrl . '")';
            }, $html);
        }
    }
    return $html;
}

function replaceCustomFontsPlaceholder($html, $customFontsCss) {
    if (empty($customFontsCss)) {
        return str_replace('{{CUSTOM_FONTS}}', '', $html);
    }
    
    $placeholder = '{{CUSTOM_FONTS}}';
    $pos = 0;
    while (($pos = strpos($html, $placeholder, $pos)) !== false) {
        $before = substr($html, 0, $pos);
        $lastStyleOpen = strripos($before, '<style');
        $lastStyleClose = strripos($before, '</style>');
        
        $isInsideStyle = false;
        if ($lastStyleOpen !== false) {
            if ($lastStyleClose === false || $lastStyleOpen > $lastStyleClose) {
                $isInsideStyle = true;
            }
        }
        
        if ($isInsideStyle) {
            $replacement = $customFontsCss;
        } else {
            $replacement = "<style id=\"npblog-custom-fonts\">\n" . $customFontsCss . "\n    </style>";
        }
        
        $html = substr_replace($html, $replacement, $pos, strlen($placeholder));
        $pos += strlen($replacement);
    }
    
    return $html;
}

function validateTemplateCode($code) {
    $requiredPlaceholders = [
        '{{TITLE}}',
        '{{POST_ID}}',
        '{{META_TAGS}}',
        '{{CUSTOM_FONTS}}',
        '{{BODY_STYLE}}',
        '{{CONTENT_WRAPPER_START}}',
        '{{DATE}}',
        '{{CONTENT}}',
        '{{CONTENT_WRAPPER_END}}'
    ];
    
    $missing = [];
    foreach ($requiredPlaceholders as $placeholder) {
        if (strpos($code, $placeholder) === false) {
            $missing[] = $placeholder;
        }
    }
    
    return $missing;
}

if (!function_exists('extractPostContentFromHtml')) {
function extractPostContentFromHtml($html, $postId = null) {
    if (empty($html)) {
        return "";
    }
    
    // Clean up any duplicated template wrappers/elements from the raw HTML to prevent duplicates
    $html = preg_replace('/<!-- SEO Metadata -->.*?<!-- \/SEO Metadata -->/s', '', $html);
    $html = preg_replace('/<div class="image-modal"\s+id="imageModal">.*?<\/div>\s*<\/div>\s*<\/div>/s', '', $html);
    $html = preg_replace('/<div class="image-modal"\s+id="imageModal">.*?<\/div>/s', '', $html);
    $html = preg_replace('/<button class="theme-toggle"[^>]*>.*?<\/button>/s', '', $html);
    $html = preg_replace('/<a href="[^"]*" class="back-link">.*?<\/a>/s', '', $html);
    $html = preg_replace('/<div class="powered-by">.*?<\/div>/s', '', $html);
    $html = preg_replace('/<script[^>]*src="[^"]*blog-post\.js"[^>]*><\/script>/s', '', $html);
    $html = preg_replace('/<link[^>]*href="[^"]*blog-post\.css[^"]*"[^>]*>/s', '', $html);
    
    if (class_exists('DOMDocument')) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//*[@id="npblog-post-content"] | //*[@class="post-content"] | //div[@class="content"]');
        
        $isGenericContent = false;
        if ($nodes->length === 0) {
            $nodes = $xpath->query('//div[@id="content"]');
            if ($nodes->length > 0) {
                $isGenericContent = true;
            }
        }
        
        if ($nodes->length > 0) {
            $node = $nodes->item(0);
            
            // Clean up any template elements that might have leaked into the content container
            $leakedSelectors = [
                './/meta',
                './/title',
                './/link',
                './/style',
                './/script',
                './/button[contains(@class, "theme-toggle")]',
                './/a[contains(@class, "back-link")]',
                './/div[contains(@class, "powered-by")]',
                './/div[@id="imageModal"]'
            ];
            foreach ($leakedSelectors as $selector) {
                $leakedNodes = $xpath->query($selector, $node);
                foreach ($leakedNodes as $leakedNode) {
                    if ($leakedNode->parentNode) {
                        $leakedNode->parentNode->removeChild($leakedNode);
                    }
                }
            }
            
            if ($isGenericContent) {
                // Clone the node to strip out template-specific elements (spans, brs, and whitespace text nodes)
                $tempNode = $node->cloneNode(true);
                $elementsToRemove = [];
                foreach ($tempNode->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE) {
                        if ($child->nodeName === 'span' || $child->nodeName === 'br') {
                            $elementsToRemove[] = $child;
                        } else {
                            break;
                        }
                    } else if ($child->nodeType === XML_TEXT_NODE) {
                        $text = trim($child->textContent);
                        if ($text === '') {
                            $elementsToRemove[] = $child;
                        } else {
                            // Check if this text node looks like a date/time (e.g. contains post date or fits date regex)
                            $postDate = '';
                            if ($postId !== null) {
                                $metaFile = getDataPath('blog/posts-meta.json');
                                if (file_exists($metaFile)) {
                                    $meta = json_decode(file_get_contents($metaFile), true) ?: [];
                                    foreach ($meta as $item) {
                                        if ($item['id'] == $postId) {
                                            $postDate = isset($item['date']) ? trim($item['date']) : '';
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            $isDate = false;
                            if (!empty($postDate) && strpos($text, $postDate) !== false) {
                                $isDate = true;
                            } else if (preg_match('/^\d{2}\.\d{2}\.\d{4}/', $text)) {
                                $isDate = true;
                            }
                            
                            if ($isDate) {
                                $elementsToRemove[] = $child;
                            } else {
                                break;
                            }
                        }
                    }
                }
                foreach ($elementsToRemove as $el) {
                    $tempNode->removeChild($el);
                }
                $node = $tempNode;
            }
            
            $contentHtml = "";
            foreach ($node->childNodes as $child) {
                $contentHtml .= $dom->saveHTML($child);
            }
            $contentHtml = trim($contentHtml);
            
            // Remove the <?xml declaration if present
            $contentHtml = preg_replace('/^<\?xml[^>]*>/i', '', $contentHtml);
            
            // Decode Cyrillic HTML entities while preserving standard HTML entities
            $contentHtml = str_replace(
                array('&amp;', '&lt;', '&gt;', '&quot;', '&#039;', '&apos;'),
                array('[AMP_MASK]', '[LT_MASK]', '[GT_MASK]', '[QUOT_MASK]', '[APOS_MASK]', '[APOS_MASK]'),
                $contentHtml
            );
            $contentHtml = html_entity_decode($contentHtml, ENT_QUOTES, 'UTF-8');
            $contentHtml = str_replace(
                array('[AMP_MASK]', '[LT_MASK]', '[GT_MASK]', '[QUOT_MASK]', '[APOS_MASK]'),
                array('&amp;', '&lt;', '&gt;', '&quot;', '&#039;'),
                $contentHtml
            );
            
            if (!empty($contentHtml)) {
                return $contentHtml;
            }
        }
    }
    
    // Fallback: Regex extraction (using cleaned HTML)
    if (preg_match('/<article id="npblog-post-content"[^>]*>(.*?)<\/article>/s', $html, $contentMatch)) {
        return trim($contentMatch[1]);
    }
    
    if (preg_match('/<div class="(?:post-)?content"[^>]*>(.*?)\s*<\/div>\s*<(?:footer|a href|div class="image-modal"|\/article|\/div>)/s', $html, $contentMatch)) {
        return trim($contentMatch[1]);
    }
    
    if (preg_match('/<div class="(?:post-)?content"[^>]*>(.*?)<\/div>/s', $html, $contentMatch)) {
        return trim($contentMatch[1]);
    }
    
    return "";
}
}

function regeneratePostWithTemplate($postId, $templateFile) {
    $blogDir = getDataPath('blog/');
    $postFile = $blogDir . 'post-' . $postId . '.html';
    if (!file_exists($postFile)) {
        return false;
    }
    
    $html = file_get_contents($postFile);
    
    // Extract Title (Try to read from posts-meta.json first to be robust)
    $title = "";
    $metaFile = $blogDir . 'posts-meta.json';
    if (file_exists($metaFile)) {
        $meta = json_decode(file_get_contents($metaFile), true) ?: [];
        foreach ($meta as $item) {
            if ($item['id'] == $postId) {
                $title = $item['title'];
                break;
            }
        }
    }
    if (empty($title)) {
        $title = "Без названия";
        if (preg_match('/<h1>(.*?)<\/h1>/s', $html, $titleMatch)) {
            $title = trim(strip_tags($titleMatch[1]));
        } else if (preg_match('/<title>(.*?)<\/title>/s', $html, $titleMatch)) {
            $title = trim(strip_tags($titleMatch[1]));
        }
    }
    
    // Extract Date
    $date = date('d.m.Y H:i');
    if (preg_match('/<div class="date">.*?(\d{2}\.\d{2}\.\d{4}\s\d{2}:\d{2}(?:\s*\(отредактировано\))?).*?<\/div>/s', $html, $dateMatch)) {
        $date = trim(strip_tags($dateMatch[1]));
    }
    
    // Extract Content
    $content = extractPostContentFromHtml($html, $postId);

    // Заменяем все пути serve_data.php на статические прямые пути для готовой статьи
    $dataDir = getDataPath();
    $dirName = basename(rtrim(str_replace('\\', '/', $dataDir), '/'));
    $staticPrefix = '/' . $dirName . '/';
$content = preg_replace('/(?:https?:\/\/[^\/]+)?(?:\/)?serve_data.php\?file=/i', $staticPrefix, $content);
    $content = preg_replace('/(?:[?&]|&amp;)t=\d+/i', '', $content);
    
    if (empty($content)) {
        return false;
    }
    
    // Read the template
    $templateHtml = getTemplateHtml($templateFile);
    
    // Get custom fonts
    require_once __DIR__ . '/get_custom_fonts_css.php';
    $customFontsCss = getCustomFontsCss();
    
    // Get SEO Meta Tags
    require_once __DIR__ . '/seo_helper.php';
    $seoMetaBlock = generateSeoMetaTagsBlock($postId, $title, $content);
    
    // Replace placeholders
    $newHtml = str_replace('{{POST_ID}}', $postId, $templateHtml);
    $newHtml = str_replace('{{TITLE}}', htmlspecialchars($title), $newHtml);
    $newHtml = str_replace('{{DATE}}', htmlspecialchars($date), $newHtml);
    
    $wrappedContent = $content;
    if (strpos($newHtml, 'id="npblog-post-content"') === false) {
        $wrappedContent = '<article id="npblog-post-content" class="content">' . $content . '</article>';
    }
    $newHtml = str_replace('{{CONTENT}}', $wrappedContent, $newHtml);
    $newHtml = str_replace('{{META_TAGS}}', $seoMetaBlock, $newHtml);
    $newHtml = replaceCustomFontsPlaceholder($newHtml, $customFontsCss);

    $editorSettingsFile = __DIR__ . '/editor_settings.json';
    $editorSettings = [];
    if (file_exists($editorSettingsFile)) {
        $editorSettings = json_decode(file_get_contents($editorSettingsFile), true) ?: [];
    }
    $contentWidth = isset($editorSettings['contentWidth']) ? (int)$editorSettings['contentWidth'] : 920;
    $bodyStyle = "style=\"max-width: {$contentWidth}px;\"";
    $newHtml = str_replace('{{BODY_STYLE}}', $bodyStyle, $newHtml);
    $newHtml = str_replace('{{CONTENT_WRAPPER_START}}', '', $newHtml);
    $newHtml = str_replace('{{CONTENT_WRAPPER_END}}', '', $newHtml);
    
    // Save updated HTML
    file_put_contents($postFile, $newHtml);
    
    // Also save backup
    $backupDir = validateSafePath(getBackupPath(), (string)$postId) . '/';
    if (is_dir($backupDir)) {
        $existingBackups = glob($backupDir . $postId . '-*.html');
        $maxBackupNumber = 0;
        foreach ($existingBackups as $backup) {
            if (preg_match('/' . $postId . '-(\d+)\.html$/', $backup, $match)) {
                $backupNum = intval($match[1]);
                if ($backupNum > $maxBackupNumber) {
                    $maxBackupNumber = $backupNum;
                }
            }
        }
        $nextBackupNumber = $maxBackupNumber + 1;
        file_put_contents($backupDir . $postId . '-' . $nextBackupNumber . '.html', $newHtml);
        
        $backupMetaFile = validateSafePath(getBackupPath(), 'backup-meta.json');
        if (file_exists($backupMetaFile)) {
            $backupMeta = json_decode(file_get_contents($backupMetaFile), true) ?: [];
            if (isset($backupMeta[$postId])) {
                $backupMeta[$postId]['backups'][] = [
                    'backupNumber' => $nextBackupNumber,
                    'filename' => $postId . '-' . $nextBackupNumber . '.html',
                    'date' => date('d.m.Y H:i'),
                    'title' => $title
                ];
                safeWriteJson($backupMetaFile, $backupMeta);
            }
        }
    }
    
    return true;
}
