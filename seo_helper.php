<?php
if (!function_exists('getDataPath')) {
    require_once __DIR__ . '/security_bootstrap.php';
}

/**
 * UTF-8 safe strlen check with fallback if mbstring extension is not installed.
 */
function seo_strlen($str) {
    if (function_exists('mb_strlen')) {
        return mb_strlen($str, 'UTF-8');
    }
    preg_match_all('/./us', $str, $ar);
    return count($ar[0]);
}

/**
 * UTF-8 safe substr check with fallback if mbstring extension is not installed.
 */
function seo_substr($str, $start, $length) {
    if (function_exists('mb_substr')) {
        return mb_substr($str, $start, $length, 'UTF-8');
    }
    preg_match_all('/./us', $str, $ar);
    return implode('', array_slice($ar[0], $start, $length));
}

/**
 * Returns the global SEO settings.
 */
function getSeoSettings() {
    $settingsFile = getDataPath('global-settings.json');
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }
    return $settings;
}

/**
 * Resolves a relative path/URL to an absolute URL based on the configured baseUrl.
 */
function resolveAbsoluteUrl($url, $baseUrl) {
    if (empty($url)) {
        return '';
    }
    // If already absolute (e.g. starts with http://, https://, or //)
    if (preg_match('/^(https?:)?\/\//i', $url)) {
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        return $url;
    }

    $baseUrl = rtrim($baseUrl, '/');

    // If starts with '../' (relative to blog/ directory where post is saved)
    if (strpos($url, '../') === 0) {
        $cleanPath = substr($url, 3); // Remove '../'
        return rtrim($baseUrl, '/') . '/' . ltrim(getDataUrl($cleanPath), '/');
    }

    // If starts with '/' (absolute path from domain root)
    if (strpos($url, '/') === 0) {
        $parsed = parse_url($baseUrl);
        $hostUrl = (isset($parsed['scheme']) ? $parsed['scheme'] : 'http') . '://' . (isset($parsed['host']) ? $parsed['host'] : '') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        return $hostUrl . $url;
    }

    // Otherwise, treat as relative to domain root / subpath
    return $baseUrl . '/' . $url;
}

/**
 * Generates the SEO description from article HTML content by stripping HTML tags and truncating.
 */
function getSeoDescription($htmlContent, $defaultDesc = '') {
    $search = array(
        '@<script[^>]*?>.*?</script>@si',  // Strip out javascript
        '@<style[^>]*?>.*?</style>@si',    // Strip out stylesheet
        '@<![\s\S]*?--[ \t\n\r]*>@'        // Strip comments
    );
    $text = preg_replace($search, '', $htmlContent);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    if (empty($text)) {
        return $defaultDesc;
    }
    
    if (seo_strlen($text) > 250) {
        $text = seo_substr($text, 0, 247) . '...';
    }
    
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Extracts the first image URL from the article content.
 */
function getFirstImageUrl($htmlContent) {
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $htmlContent, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Generates the SEO Open Graph and Twitter Cards HTML meta tags block.
 */
function generateSeoMetaTagsBlock($postId, $title, $content) {
    $settings = getSeoSettings();
    $baseUrl = isset($settings['baseUrl']) ? $settings['baseUrl'] : '';
    $defaultOgImage = isset($settings['defaultOgImage']) ? $settings['defaultOgImage'] : '';
    $defaultOgDescription = isset($settings['defaultOgDescription']) ? $settings['defaultOgDescription'] : '';
    
    $desc = getSeoDescription($content, $defaultOgDescription);
    $cleanTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    
    // Resolve absolute article URL
    $articleUrl = '';
    if (!empty($baseUrl)) {
        $articleUrl = rtrim($baseUrl, '/') . '/' . ltrim(getDataUrl('blog/post-' . $postId . '.html'), '/');
    }
    
    // Find first image or fall back to default image
    $firstImg = getFirstImageUrl($content);
    $ogImg = !empty($firstImg) ? $firstImg : $defaultOgImage;
    $absoluteOgImg = '';
    if (!empty($ogImg) && !empty($baseUrl)) {
        $absoluteOgImg = resolveAbsoluteUrl($ogImg, $baseUrl);
    }
    
    $meta = "\n    <!-- SEO Metadata -->\n";
    if (!empty($desc)) {
        $meta .= "    <meta name=\"description\" content=\"{$desc}\">\n";
    }
    $meta .= "    <meta property=\"og:title\" content=\"{$cleanTitle}\">\n";
    if (!empty($desc)) {
        $meta .= "    <meta property=\"og:description\" content=\"{$desc}\">\n";
    }
    $meta .= "    <meta property=\"og:type\" content=\"article\">\n";
    if (!empty($articleUrl)) {
        $meta .= "    <meta property=\"og:url\" content=\"{$articleUrl}\">\n";
    }
    if (!empty($absoluteOgImg)) {
        $meta .= "    <meta property=\"og:image\" content=\"{$absoluteOgImg}\">\n";
    }
    $meta .= "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    $meta .= "    <meta name=\"twitter:title\" content=\"{$cleanTitle}\">\n";
    if (!empty($desc)) {
        $meta .= "    <meta name=\"twitter:description\" content=\"{$desc}\">\n";
    }
    if (!empty($absoluteOgImg)) {
        $meta .= "    <meta name=\"twitter:image\" content=\"{$absoluteOgImg}\">\n";
    }
    $meta .= "    <!-- /SEO Metadata -->";
    
    return $meta;
}

/**
 * Parses existing post file, removes old meta tags, and injects updated ones.
 */
function updateHtmlMetaTags($html, $postId, $title, $content) {
    $metaBlock = generateSeoMetaTagsBlock($postId, $title, $content);
    
    // Remove any existing SEO blocks or custom/legacy tags
    $html = preg_replace('/<!-- SEO Metadata -->.*?<!-- \/SEO Metadata -->/s', '', $html);
    $html = preg_replace('/<meta\s+name=["\']description["\']\s+content=["\'][\s\S]*?["\']\s*\/?>/i', '', $html);
    $html = preg_replace('/<meta\s+property=["\']og:[\w:]+["\']\s+content=["\'][\s\S]*?["\']\s*\/?>/i', '', $html);
    $html = preg_replace('/<meta\s+name=["\']twitter:\w+["\']\s+content=["\'][\s\S]*?["\']\s*\/?>/i', '', $html);
    
    // Normalize double or triple spacing inside head
    $html = preg_replace('/(\r?\n){3,}/', "\n\n", $html);
    
    // Inject new meta tags after the title tag if it exists
    if (preg_match('/<\/title>/i', $html)) {
        $html = preg_replace('/<\/title>/i', "</title>\n    " . $metaBlock, $html, 1);
    } else if (preg_match('/<head>/i', $html)) {
        $html = preg_replace('/<head>/i', "<head>\n    " . $metaBlock, $html, 1);
    }
    
    return $html;
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

/**
 * Regenerates the SEO meta tags for all articles under data/blog/post-*.html.
 */
function regenerateAllPostsSeo() {
    $blogDir = getDataPath('blog/');
    $files = glob($blogDir . 'post-*.html');
    if ($files === false) {
        $files = [];
    }
    $processed = 0;
    $updated = 0;
    
    foreach ($files as $file) {
        $html = file_get_contents($file);
        $processed++;
        
        // Extract post ID
        $postId = null;
        if (preg_match('/<meta\s+name=["\']post-id["\']\s+content=["\'](\d+)["\']/i', $html, $match)) {
            $postId = intval($match[1]);
        } else if (preg_match('/post-(\d+)\.html$/', $file, $match)) {
            $postId = intval($match[1]);
        }
        
        if ($postId === null) {
            continue;
        }
        
        // Extract Title
        $title = "Без названия";
        if (preg_match('/<title>(.*?)<\/title>/si', $html, $match)) {
            $title = html_entity_decode(trim(strip_tags($match[1])), ENT_QUOTES, 'UTF-8');
        }
        
        // Extract Content
        $content = extractPostContentFromHtml($html, $postId);
        
        // Update tags
        $newHtml = updateHtmlMetaTags($html, $postId, $title, $content);
        
        if ($newHtml !== $html) {
            file_put_contents($file, $newHtml);
            $updated++;
        }
    }
    
    return [
        'processed' => $processed,
        'updated' => $updated
    ];
}
?>
