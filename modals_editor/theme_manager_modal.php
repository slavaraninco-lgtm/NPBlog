<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно управления темами оформления
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает переключение между стандартной тёмной, светлой и кастомной темами,
 * скачивание базового шаблона стилей, загрузку CSS файлов и встроенный CSS-редактор.
 * ==============================================================================
 */
?>
<div id="themeManagerModal" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">🎨</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.theme_title">Темы оформления</h3>
                    <p class="modal-subtitle" data-i18n="modals.theme_subtitle">Выбор цветовой схемы интерфейса и кастомизация CSS</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeThemeManager()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <!-- Сетка тем -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px;">
                <!-- Темная тема -->
                <div id="themeCardDark" onclick="selectThemeOption('dark')" style="border: 2px solid var(--border-color); border-radius: 12px; padding: 16px 12px; cursor: pointer; text-align: center; background: #121212; color: #ffffff; transition: all 0.2s ease;">
                    <div style="font-size: 28px; margin-bottom: 6px;">🌙</div>
                    <div style="font-weight: 600; font-size: 13px;" data-i18n="modals.theme_card_dark">Темная</div>
                    <div style="font-size: 11px; opacity: 0.7; margin-top: 2px;" data-i18n="modals.theme_card_dark_sub">Стандартная тема</div>
                </div>
                
                <!-- Светлая тема -->
                <div id="themeCardLight" onclick="selectThemeOption('light')" style="border: 2px solid var(--border-color); border-radius: 12px; padding: 16px 12px; cursor: pointer; text-align: center; background: #ffffff; color: #121212; transition: all 0.2s ease;">
                    <div style="font-size: 28px; margin-bottom: 6px;">☀️</div>
                    <div style="font-weight: 600; font-size: 13px;" data-i18n="modals.theme_card_light">Светлая</div>
                    <div style="font-size: 11px; opacity: 0.7; margin-top: 2px;" data-i18n="modals.theme_card_light_sub">Белая тема</div>
                </div>
                
                <!-- Кастомная тема -->
                <div id="themeCardCustom" onclick="selectThemeOption('custom')" style="border: 2px solid var(--border-color); border-radius: 12px; padding: 16px 12px; cursor: pointer; text-align: center; background: var(--modal-bg); color: var(--text-color); transition: all 0.2s ease;">
                    <div style="font-size: 28px; margin-bottom: 6px;">🎨</div>
                    <div style="font-weight: 600; font-size: 13px;" data-i18n="modals.theme_card_custom">Кастомная</div>
                    <div style="font-size: 11px; opacity: 0.7; margin-top: 2px;" data-i18n="modals.theme_card_custom_sub">Пользовательская CSS</div>
                </div>
            </div>

            <!-- Управление CSS темой -->
            <div style="background: var(--modal-bg-subtle, rgba(0,0,0,0.03)); border-radius: 12px; padding: 16px; border: 1px solid var(--border-color);">
                <h4 style="margin: 0 0 12px 0; font-size: 13px; font-weight: 600; color: var(--text-color);" data-i18n="modals.theme_css_heading">Файлы и настройки CSS</h4>
                <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                    <!-- Скачать стандартный CSS -->
                    <a href="download_theme.php" download="editor-style-template.css" class="modal-btn modal-btn-secondary" style="text-decoration: none; font-size: 12px;" data-i18n="modals.theme_download_btn">
                        📥 Скачать стандартный CSS темы
                    </a>
                    
                    <!-- Загрузить кастомный CSS файл -->
                    <label class="modal-btn modal-btn-primary" style="cursor: pointer; font-size: 12px; margin: 0;" data-i18n="modals.theme_upload_btn">
                        📤 Загрузить CSS файл темы
                        <input type="file" id="customThemeFileInput" accept=".css" style="display: none;" onchange="handleCustomThemeFileUpload(event)">
                    </label>
                </div>
                
                <!-- Поле просмотра/редактирования кастомного CSS -->
                <div id="customCssContainer" style="display: none; margin-top: 12px;">
                    <label class="modal-label" for="customCssEditor" data-i18n="modals.theme_code_label">Код кастомных CSS стилей:</label>
                    <textarea id="customCssEditor" class="modal-textarea" style="height: 140px; font-family: Consolas, Monaco, monospace; font-size: 12px;" placeholder="/* Вставьте ваш CSS код здесь */&#10;:root {&#10;    --bg-color: #1e1e2e;&#10;    --text-color: #cdd6f4;&#10;}"></textarea>
                    <div style="margin-top: 8px;">
                        <button type="button" onclick="saveCustomCssCode()" class="modal-btn modal-btn-primary" style="font-size: 12px;" data-i18n="modals.theme_apply_code_btn">Применить код CSS</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeThemeManager()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.close">Закрыть</button>
            <button type="button" onclick="saveSelectedTheme()" class="modal-btn modal-btn-primary" data-i18n="modals.theme_apply_btn">Применить тему</button>
        </div>
    </div>
</div>
