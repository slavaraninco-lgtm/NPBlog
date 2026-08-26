<?php
/**
 * ==============================================================================
 * NPBlog Modal Framework - PHP Helpers & Renderer
 * ==============================================================================
 * PHP Class and helper functions for rendering modal window templates
 * matching the unified NPBlog editor modal styling.
 * ==============================================================================
 */

if (!class_exists('NPModal')) {

    class NPModal {

        /**
         * Return HTML tags to include modal assets with cache-busting
         */
        public static function assets($basePath = 'modals/') {
            $basePath = rtrim($basePath, '/') . '/';
            $cssPath = __DIR__ . '/modal.css';
            $jsPath = __DIR__ . '/modal.js';
            
            $cssVer = file_exists($cssPath) ? filemtime($cssPath) : '1.0';
            $jsVer = file_exists($jsPath) ? filemtime($jsPath) : '1.0';

            return sprintf(
                "<link rel=\"stylesheet\" href=\"%smodal.css?v=%s\">\n<script src=\"%smodal.js?v=%s\"></script>",
                htmlspecialchars($basePath),
                $cssVer,
                htmlspecialchars($basePath),
                $jsVer
            );
        }

        /**
         * Render a complete declarative modal window
         * 
         * @param array $config [
         *   'id' => string,
         *   'size' => 'xs'|'sm'|'md'|'lg'|'xl'|'fullscreen'|'auto',
         *   'title' => string,
         *   'title_i18n' => string,
         *   'subtitle' => string,
         *   'icon' => string,
         *   'icon_type' => 'info'|'warning'|'danger'|'success',
         *   'badge' => string,
         *   'closable' => bool (true),
         *   'fullscreenable' => bool (false),
         *   'draggable' => bool (false),
         *   'tabs' => array [['id' => '', 'title' => '', 'icon' => '', 'content' => '']],
         *   'body' => string,
         *   'footer' => string|array,
         *   'overlay_class' => string,
         *   'dialog_class' => string
         * ]
         * @return string HTML
         */
        public static function render(array $config) {
            $id = !empty($config['id']) ? htmlspecialchars($config['id']) : 'modal-' . uniqid();
            $size = !empty($config['size']) ? htmlspecialchars($config['size']) : 'md';
            $overlayClass = !empty($config['overlay_class']) ? ' ' . htmlspecialchars($config['overlay_class']) : '';
            $dialogClass = !empty($config['dialog_class']) ? ' ' . htmlspecialchars($config['dialog_class']) : '';
            $closable = !isset($config['closable']) || $config['closable'];
            $fullscreenable = !empty($config['fullscreenable']);
            $draggable = !empty($config['draggable']);

            $dataAttrs = [];
            if ($draggable) $dataAttrs[] = 'data-draggable="true"';
            if (isset($config['backdrop_close']) && !$config['backdrop_close']) $dataAttrs[] = 'data-backdrop-close="false"';
            if (isset($config['esc_close']) && !$config['esc_close']) $dataAttrs[] = 'data-esc-close="false"';

            $html = sprintf(
                '<div class="modal-overlay%s" id="%s" %s>' . "\n",
                $overlayClass,
                $id,
                implode(' ', $dataAttrs)
            );

            $html .= sprintf('    <div class="modal-dialog modal-%s%s">' . "\n", $size, $dialogClass);

            // 1. Header
            if (!isset($config['header']) || $config['header'] !== false) {
                $html .= '        <div class="modal-header">' . "\n";
                $html .= '            <div class="modal-header-start">' . "\n";
                
                // Icon
                if (!empty($config['icon'])) {
                    $iconType = !empty($config['icon_type']) ? ' icon-' . htmlspecialchars($config['icon_type']) : '';
                    $html .= sprintf('                <span class="modal-icon%s">%s</span>' . "\n", $iconType, $config['icon']);
                }

                // Titles
                $html .= '                <div class="modal-titles">' . "\n";
                if (!empty($config['title'])) {
                    $i18nAttr = !empty($config['title_i18n']) ? sprintf(' data-i18n="%s"', htmlspecialchars($config['title_i18n'])) : '';
                    $html .= sprintf('                    <h3 class="modal-title"%s>%s</h3>' . "\n", $i18nAttr, htmlspecialchars($config['title']));
                }
                if (!empty($config['subtitle'])) {
                    $subI18nAttr = !empty($config['subtitle_i18n']) ? sprintf(' data-i18n="%s"', htmlspecialchars($config['subtitle_i18n'])) : '';
                    $html .= sprintf('                    <p class="modal-subtitle"%s>%s</p>' . "\n", $subI18nAttr, htmlspecialchars($config['subtitle']));
                }
                $html .= '                </div>' . "\n";

                // Badge
                if (!empty($config['badge'])) {
                    $html .= sprintf('                <span class="modal-badge">%s</span>' . "\n", htmlspecialchars($config['badge']));
                }

                $html .= '            </div>' . "\n";

                // Header Actions
                $html .= '            <div class="modal-header-actions">' . "\n";
                if ($fullscreenable) {
                    $html .= '                <button type="button" class="modal-fullscreen-btn" title="Развернуть">⛶</button>' . "\n";
                }
                if ($closable) {
                    $html .= '                <button type="button" class="modal-close-btn" data-modal-close title="Закрыть">×</button>' . "\n";
                }
                $html .= '            </div>' . "\n";
                $html .= '        </div>' . "\n";
            }

            // 2. Tabs Navigation
            if (!empty($config['tabs']) && is_array($config['tabs'])) {
                $html .= '        <div class="modal-tabs">' . "\n";
                foreach ($config['tabs'] as $i => $tab) {
                    $tabId = !empty($tab['id']) ? htmlspecialchars($tab['id']) : 'tab-' . $i;
                    $tabTitle = !empty($tab['title']) ? htmlspecialchars($tab['title']) : 'Вкладка ' . ($i + 1);
                    $isActive = ($i === 0) ? ' is-active' : '';
                    $icon = !empty($tab['icon']) ? '<span class="modal-tab-icon">' . $tab['icon'] . '</span> ' : '';
                    $html .= sprintf('            <button type="button" class="modal-tab-btn%s" data-modal-tab="%s">%s%s</button>' . "\n", $isActive, $tabId, $icon, $tabTitle);
                }
                $html .= '        </div>' . "\n";
            }

            // 3. Body
            $bodyClass = !empty($config['body_class']) ? ' ' . htmlspecialchars($config['body_class']) : '';
            $html .= sprintf('        <div class="modal-body%s">' . "\n", $bodyClass);

            if (!empty($config['tabs']) && is_array($config['tabs'])) {
                foreach ($config['tabs'] as $i => $tab) {
                    $tabId = !empty($tab['id']) ? htmlspecialchars($tab['id']) : 'tab-' . $i;
                    $isActive = ($i === 0) ? ' is-active' : '';
                    $tabContent = isset($tab['content']) ? $tab['content'] : '';
                    $html .= sprintf('            <div class="modal-tab-pane%s" id="%s">%s</div>' . "\n", $isActive, $tabId, $tabContent);
                }
            } elseif (!empty($config['body'])) {
                $html .= '            ' . $config['body'] . "\n";
            }

            $html .= '        </div>' . "\n";

            // 4. Footer
            if (!isset($config['footer']) || $config['footer'] !== false) {
                $footerClass = !empty($config['footer_class']) ? ' ' . htmlspecialchars($config['footer_class']) : '';
                $html .= sprintf('        <div class="modal-footer%s">' . "\n", $footerClass);

                if (is_string($config['footer'] ?? null)) {
                    $html .= '            ' . $config['footer'] . "\n";
                } elseif (is_array($config['buttons'] ?? null)) {
                    foreach ($config['buttons'] as $btn) {
                        $btnType = !empty($btn['type']) ? htmlspecialchars($btn['type']) : 'button';
                        $btnClass = 'modal-btn ' . (!empty($btn['class']) ? htmlspecialchars($btn['class']) : (!empty($btn['primary']) ? 'modal-btn-primary' : ''));
                        $btnAttrs = !empty($btn['attrs']) ? ' ' . $btn['attrs'] : '';
                        if (!empty($btn['close'])) $btnAttrs .= ' data-modal-close';
                        $btnText = !empty($btn['text']) ? htmlspecialchars($btn['text']) : 'OK';
                        $html .= sprintf('            <button type="%s" class="%s"%s>%s</button>' . "\n", $btnType, trim($btnClass), $btnAttrs, $btnText);
                    }
                }

                $html .= '        </div>' . "\n";
            }

            $html .= "    </div>\n";
            $html .= "</div>\n";

            return $html;
        }
    }
}
