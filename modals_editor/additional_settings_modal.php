<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно дополнительных настроек статьи
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<div id="additionalSettingsModal" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.extra_settings_title">Дополнительные настройки статьи</h3>
                    <p class="modal-subtitle" id="additionalSettingsPostTitle" data-i18n="modals.extra_settings_subtitle">Настройки фона и подложки</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeAdditionalSettings()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
            <!-- Информация о глобальном фоне -->
            <div id="globalBackgroundInfo" class="modal-section-card" style="display: none; padding: 14px; background: rgba(245, 158, 11, 0.08); border: 1.5px solid #f59e0b; border-radius: 8px;">
                <p style="color: var(--text-color); font-weight: 600; margin: 0 0 10px 0; font-size: 14px;" data-i18n="settings.bg_global_applied">🌍 Применен глобальный фон:</p>
                <div style="display: flex; align-items: center; gap: 14px;">
                    <img id="globalBackgroundPreview" src="" alt="Глобальный фон" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color); flex-shrink: 0;">
                    <div style="min-width: 0; flex: 1;">
                        <p id="globalBackgroundName" style="color: var(--text-color); font-weight: 500; font-size: 13px; margin: 0; word-break: break-all;"></p>
                        <p id="globalBackgroundModeText" style="color: var(--text-color); font-size: 12px; opacity: 0.75; margin: 4px 0 0 0;"></p>
                        <p style="color: var(--text-color); font-size: 11px; opacity: 0.6; margin: 4px 0 0 0; font-style: italic;" data-i18n="settings.bg_override_hint">Загрузите свой фон ниже, чтобы переопределить глобальный</p>
                    </div>
                </div>
            </div>

            <!-- Информация о текущем фоне статьи -->
            <div id="currentBackgroundInfo" class="modal-section-card" style="display: none; padding: 14px;">
                <p style="color: var(--text-color); font-weight: 600; margin: 0 0 10px 0; font-size: 14px;" data-i18n="settings.bg_article_current">🖼️ Текущий фон статьи:</p>
                <div style="display: flex; align-items: center; gap: 14px;">
                    <img id="currentBackgroundPreview" src="" alt="Фон" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border-color); flex-shrink: 0;">
                    <div style="min-width: 0; flex: 1;">
                        <p id="currentBackgroundName" style="color: var(--text-color); font-weight: 500; font-size: 13px; margin: 0; word-break: break-all;"></p>
                        <p id="currentBackgroundMode" style="color: var(--text-color); font-size: 12px; opacity: 0.75; margin: 4px 0 0 0;"></p>
                    </div>
                </div>
            </div>

            <!-- Секция выбора и загрузки фона -->
            <div class="modal-section-card" style="padding: 16px;">
                <div class="modal-section-title" style="margin-bottom: 12px; font-weight: 600; font-size: 14px;" data-i18n="settings.bg_img_section">Фоновое изображение</div>
                
                <div class="modal-form-group">
                    <label class="modal-label" for="backgroundInput" data-i18n="settings.bg_file_label">Файл изображения:</label>
                    <input type="file" id="backgroundInput" accept="image/*" class="modal-input" style="padding: 8px 12px;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label class="modal-label" for="backgroundMode" data-i18n="settings.bg_mode_label">Режим отображения:</label>
                        <select id="backgroundMode" class="modal-select">
                            <option value="cover" data-i18n="settings.bg_mode_cover">Растянуть (cover)</option>
                            <option value="contain" data-i18n="settings.bg_mode_contain">По размеру (contain)</option>
                            <option value="repeat" data-i18n="settings.bg_mode_repeat">Замостить (repeat)</option>
                        </select>
                    </div>
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label class="modal-label" for="backgroundScope" data-i18n="settings.bg_scope_label">Область фона:</label>
                        <select id="backgroundScope" class="modal-select">
                            <option value="content" data-i18n="settings.bg_scope_content">Только статья (920px)</option>
                            <option value="fullpage" data-i18n="settings.bg_scope_fullpage">Вся страница</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" onclick="uploadBackground()" class="modal-btn modal-btn-primary" data-i18n="settings.bg_upload_btn">Загрузить фон</button>
                    <button type="button" onclick="removeBackground()" class="modal-btn modal-btn-ghost" data-i18n="settings.bg_btn_reset">Вернуть стандартный фон</button>
                </div>
            </div>

            <!-- Секция подложки под статью -->
            <div class="modal-section-card" style="padding: 16px;">
                <div class="modal-section-title" style="margin-bottom: 12px; font-weight: 600; font-size: 14px;" data-i18n="settings.bg_overlay_section">Подложка под статью</div>

                <div class="modal-form-group" style="margin-bottom: 12px;">
                    <label class="modal-checkbox-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none;">
                        <input type="checkbox" id="overlayEnabled" onchange="toggleOverlaySettings()" style="width: 18px; height: 18px; cursor: pointer;">
                        <span data-i18n="settings.bg_overlay_enable">Включить подложку под статью</span>
                    </label>
                </div>

                <div id="overlaySettings" style="display: none; padding: 12px; background: rgba(0,0,0,0.03); border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 14px;">
                    <div class="modal-form-group">
                        <label class="modal-label" for="overlayColor" data-i18n="settings.bg_overlay_color">Цвет подложки:</label>
                        <input type="color" id="overlayColor" value="#ffffff" class="modal-input" style="height: 38px; padding: 2px 4px; cursor: pointer;">
                    </div>
                    <div class="modal-form-group" style="margin-bottom: 0;">
                        <label class="modal-label" for="overlayOpacity">
                            <span data-i18n="settings.bg_overlay_opacity">Прозрачность:</span> <span id="overlayOpacityValue" style="font-weight: 600;">90%</span>
                        </label>
                        <input type="range" id="overlayOpacity" min="0" max="100" value="90" oninput="updateOpacityValue()" style="width: 100%; cursor: pointer;">
                    </div>
                </div>

                <button type="button" class="modal-btn modal-btn-secondary" onclick="saveOverlaySettings()" data-i18n="settings.bg_overlay_save_btn">Сохранить настройки подложки</button>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="closeAdditionalSettings()" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
