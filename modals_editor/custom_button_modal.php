<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно вставки и настройки кнопки со ссылкой
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает визуальный конструктор (GUI), пресеты стилей (градиент, неон, контур и др.),
 * редактор HTML/CSS кода кнопки, интерактивный предпросмотр и смену фонов.
 * ==============================================================================
 */
?>
<div id="customButtonDialog" class="modal-overlay" data-size="xl">
    <div class="modal-dialog modal-xl" style="height: 85vh; max-height: 900px; display: flex; flex-direction: column;">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" id="customButtonDialogTitle" data-i18n="modals.btn_title">Вставить кнопку со ссылкой</h3>
                    <p class="modal-subtitle" data-i18n="modals.btn_subtitle">Конструктор интерактивных кнопок-ссылок</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" onclick="applyBtnPreset('editor')" class="modal-btn modal-btn-ghost" style="padding: 6px 14px; font-size: 13px; display: flex; align-items: center; gap: 6px;" title="Сбросить к стандарту" data-i18n-title="modals.btn_reset_title">
                    <span data-i18n="modals.btn_reset">Сбросить</span>
                </button>
                <button type="button" id="customButtonSubmitBtn" onclick="insertCustomButtonToEditor()" class="modal-btn modal-btn-primary" style="padding: 6px 18px; font-size: 13px; display: flex; align-items: center; gap: 6px;" data-i18n="modals.btn_submit">
                    <span>💾</span> <span>Вставить кнопку</span>
                </button>
                <button type="button" class="modal-close-btn" onclick="closeCustomButtonDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Основная область -->
        <div class="modal-body" style="padding: 0; flex: 1; display: flex; overflow: hidden;">
            <!-- Левая панель инструментов -->
            <div style="width: 320px; border-right: 1px solid var(--border-color); background: var(--modal-bg); display: flex; flex-direction: column; gap: 16px; padding: 20px; overflow-y: auto; box-sizing: border-box;">
                
                <!-- Переключатель вкладок -->
                <div class="modal-tabs" style="margin: 0;">
                    <button type="button" id="btnTabGui" onclick="switchBtnTab('gui')" class="modal-tab-btn active" style="flex: 1; justify-content: center;" data-i18n="modals.btn_tab_gui">🎨 Конструктор</button>
                    <button type="button" id="btnTabCode" onclick="switchBtnTab('code')" class="modal-tab-btn" style="flex: 1; justify-content: center;" data-i18n="modals.btn_tab_code">💻 Код</button>
                </div>

                <!-- Конструктор -->
                <div id="btnTabGuiContent" style="display: flex; flex-direction: column; gap: 16px;">
                    <!-- Основные параметры -->
                    <div>
                        <h4 style="margin: 0 0 8px 0; color: var(--text-color); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;" data-i18n="modals.btn_text_and_link_heading">Текст и Ссылка</h4>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div>
                                <label class="modal-label" for="btnTextInput" style="font-size: 12px; margin-bottom: 4px;" data-i18n="modals.btn_text_label">Текст кнопки:</label>
                                <input type="text" id="btnTextInput" class="modal-input" value="Перейти на сайт" placeholder="Например: Читать далее" data-i18n-placeholder="modals.btn_text_ph" oninput="updateCustomBtnPreview()">
                            </div>
                            <div>
                                <label class="modal-label" for="btnUrlInput" style="font-size: 12px; margin-bottom: 4px;" data-i18n="modals.btn_url_label">Ссылка (URL):</label>
                                <input type="text" id="btnUrlInput" class="modal-input" value="https://example.com" placeholder="https://..." oninput="updateCustomBtnPreview()">
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 12px; margin-top: 2px;">
                                <input type="checkbox" id="btnTargetInput" class="modal-checkbox" checked onchange="updateCustomBtnPreview()">
                                <span style="color: var(--text-color); opacity: 0.9;" data-i18n="modals.btn_target_label">В новой вкладке (target="_blank")</span>
                            </label>
                        </div>
                    </div>

                    <!-- Готовые стили (Пресеты) -->
                    <div>
                        <h4 style="margin: 4px 0 8px 0; color: var(--text-color); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;" data-i18n="modals.btn_presets_label">Готовые стили</h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px;">
                            <button type="button" class="preset-btn modal-btn modal-btn-secondary" style="font-size: 11px; padding: 6px 8px;" onclick="applyBtnPreset('editor')" data-i18n="modals.btn_preset_standard">🔳 Стандартная</button>
                            <button type="button" class="preset-btn modal-btn modal-btn-secondary" style="font-size: 11px; padding: 6px 8px;" onclick="applyBtnPreset('gradient')" data-i18n="modals.btn_preset_gradient">🌈 Градиент</button>
                            <button type="button" class="preset-btn modal-btn modal-btn-secondary" style="font-size: 11px; padding: 6px 8px;" onclick="applyBtnPreset('success')" data-i18n="modals.btn_preset_green">🟢 Зелёная</button>
                            <button type="button" class="preset-btn modal-btn modal-btn-secondary" style="font-size: 11px; padding: 6px 8px;" onclick="applyBtnPreset('outline')" data-i18n="modals.btn_preset_outline">⚪ Контур</button>
                            <button type="button" class="preset-btn modal-btn modal-btn-secondary" style="font-size: 11px; padding: 6px 8px;" onclick="applyBtnPreset('neon')" data-i18n="modals.btn_preset_neon">🟣 Неон</button>
                            <button type="button" class="preset-btn modal-btn modal-btn-secondary" style="font-size: 11px; padding: 6px 8px;" onclick="applyBtnPreset('danger')" data-i18n="modals.btn_preset_red">🔴 Красная</button>
                        </div>
                    </div>

                    <!-- Тонкая настройка стилей -->
                    <div>
                        <h4 style="margin: 4px 0 8px 0; color: var(--text-color); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;" data-i18n="modals.btn_styles_label">Цвета и Форматирование</h4>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <div>
                                <label class="modal-label" style="font-size: 11px; margin-bottom: 4px;" data-i18n="modals.btn_bg_color_label">Цвет фона:</label>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <input type="color" id="btnBgColor" value="#0f1624" style="width: 36px; height: 32px; padding: 2px; cursor: pointer; border-radius: 6px; border: 1px solid var(--border-color); background: transparent;" oninput="document.getElementById('btnBgColorText').value=this.value; updateCustomBtnPreview();">
                                    <input type="text" id="btnBgColorText" class="modal-input" value="rgba(15, 22, 36, 0.72)" placeholder="rgba(15, 22, 36, 0.72)" style="flex: 1; padding: 5px 8px; font-size: 12px;" oninput="document.getElementById('btnBgColor').value=this.value; updateCustomBtnPreview();">
                                </div>
                            </div>

                            <div>
                                <label class="modal-label" style="font-size: 11px; margin-bottom: 4px;" data-i18n="modals.btn_text_color_label">Цвет текста:</label>
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <input type="color" id="btnTextColor" value="#f3f4f6" style="width: 36px; height: 32px; padding: 2px; cursor: pointer; border-radius: 6px; border: 1px solid var(--border-color); background: transparent;" oninput="document.getElementById('btnTextColorText').value=this.value; updateCustomBtnPreview();">
                                    <input type="text" id="btnTextColorText" class="modal-input" value="#f3f4f6" placeholder="#f3f4f6" style="flex: 1; padding: 5px 8px; font-size: 12px;" oninput="document.getElementById('btnTextColor').value=this.value; updateCustomBtnPreview();">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px;">
                                <div>
                                    <label class="modal-label" style="font-size: 10px; margin-bottom: 2px;" data-i18n="modals.btn_radius_label">Скругление:</label>
                                    <input type="text" id="btnBorderRadius" class="modal-input" value="8px" placeholder="8px" oninput="updateCustomBtnPreview()" style="padding: 4px; font-size: 11px; text-align: center;">
                                </div>
                                <div>
                                    <label class="modal-label" style="font-size: 10px; margin-bottom: 2px;" data-i18n="modals.btn_padding_label">Отступы:</label>
                                    <input type="text" id="btnPadding" class="modal-input" value="12px 24px" placeholder="12px 24px" oninput="updateCustomBtnPreview()" style="padding: 4px; font-size: 11px; text-align: center;">
                                </div>
                                <div>
                                    <label class="modal-label" style="font-size: 10px; margin-bottom: 2px;" data-i18n="modals.btn_font_label">Шрифт:</label>
                                    <input type="text" id="btnFontSize" class="modal-input" value="15px" placeholder="15px" oninput="updateCustomBtnPreview()" style="padding: 4px; font-size: 11px; text-align: center;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Редактор Кода -->
                <div id="btnTabCodeContent" style="display: none; flex-direction: column; gap: 12px;">
                    <div>
                        <label class="modal-label" style="font-size: 12px; margin-bottom: 4px;" data-i18n="modals.btn_code_html_label">HTML код кнопки:</label>
                        <textarea id="btnRawHtml" class="modal-textarea" oninput="syncFromRawCode()" style="height: 130px; font-family: Consolas, Monaco, monospace; font-size: 12px;"></textarea>
                    </div>
                    <div>
                        <label class="modal-label" style="font-size: 12px; margin-bottom: 4px;" data-i18n="modals.btn_code_css_label">CSS стили (inline):</label>
                        <textarea id="btnRawCss" class="modal-textarea" oninput="syncFromRawCode()" style="height: 130px; font-family: Consolas, Monaco, monospace; font-size: 12px;"></textarea>
                    </div>
                </div>

                <div style="margin-top: auto;">
                    <button type="button" onclick="applyBtnPreset('editor')" class="modal-btn modal-btn-danger" style="width: 100%; justify-content: center; padding: 8px; font-size: 12px;" data-i18n="modals.btn_clear_styles">
                        🗑️ Сбросить стили
                    </button>
                </div>
            </div>

            <!-- Центральная область предпросмотра -->
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px; overflow: auto; position: relative; background: var(--modal-bg-subtle, rgba(0,0,0,0.03));" id="customBtnCanvasContainer">
                <div style="margin-bottom: 16px; font-size: 12px; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 600; color: var(--text-color);" data-i18n="modals.btn_preview_header">Предпросмотр кнопки</div>
                
                <div id="customBtnPreviewContainer" style="padding: 50px 60px; min-height: 140px; min-width: 320px; display: flex; align-items: center; justify-content: center; border-radius: 16px; border: 1px solid var(--border-color); background: rgba(13, 17, 23, 0.95); box-shadow: 0 10px 30px rgba(0,0,0,0.3); transition: all 0.25s ease;">
                    <a id="customBtnPreview" href="#" target="_blank" class="custom-blog-btn" onclick="event.preventDefault()" data-i18n="modals.btn_default_text">Перейти на сайт</a>
                </div>

                <div style="display: flex; gap: 8px; justify-content: center; margin-top: 20px; font-size: 12px; align-items: center; background: var(--modal-bg); padding: 6px 14px; border-radius: 30px; border: 1px solid var(--border-color);">
                    <span style="opacity: 0.7; font-size: 11px; color: var(--text-color);" data-i18n="modals.btn_preview_bg_label">Фон предпросмотра:</span>
                    <button type="button" onclick="setBtnBgPreview('dark')" class="modal-btn modal-btn-secondary" style="padding: 3px 10px; font-size: 11px; background: #0d1117; color: #fff; border: 1px solid rgba(255,255,255,0.2);" data-i18n="modals.btn_bg_dark">Тёмный</button>
                    <button type="button" onclick="setBtnBgPreview('light')" class="modal-btn modal-btn-secondary" style="padding: 3px 10px; font-size: 11px; background: #ffffff; color: #000; border: 1px solid #ccc;" data-i18n="modals.btn_bg_light">Светлый</button>
                    <button type="button" onclick="setBtnBgPreview('grid')" class="modal-btn modal-btn-secondary" style="padding: 3px 10px; font-size: 11px; background: repeating-conic-gradient(#222 0% 25%, #333 0% 50%) 50% / 16px 16px; color: #fff; border: 1px solid rgba(255,255,255,0.2);" data-i18n="modals.btn_bg_grid">Сетка</button>
                </div>
            </div>
        </div>
    </div>
</div>
