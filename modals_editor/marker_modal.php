<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно выделения маркером
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает выбор стилей линии (ровное, от руки, зигзаг, волна) и маркерных цветов,
 * вставку тегов <mark> в визуальном и кодовом режимах, мультиязычность (i18n).
 * ==============================================================================
 */
?>
<div id="markerDialog" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.marker_title">Выделить маркером</h3>
                    <p class="modal-subtitle" data-i18n="modals.marker_subtitle">Стиль и цвет текстовыделителя</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeMarkerDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <!-- Выбор стиля линии -->
            <div class="modal-form-group">
                <label class="modal-label" data-i18n="modals.marker_style_label">Выберите стиль:</label>
                <div class="marker-styles">
                    <button type="button" class="marker-style-btn active" data-style="straight" title="Ровное">
                        <div class="marker-preview-box">
                            <span class="marker-style-preview marker-preview-straight" data-i18n="modals.marker_preview_text">Текст</span>
                        </div>
                        <span class="marker-style-name" data-i18n="modals.marker_style_straight">Ровное</span>
                    </button>
                    <button type="button" class="marker-style-btn" data-style="rough" title="Кривое">
                        <div class="marker-preview-box">
                            <span class="marker-style-preview marker-preview-rough" data-i18n="modals.marker_preview_text">Текст</span>
                        </div>
                        <span class="marker-style-name" data-i18n="modals.marker_style_rough">Кривое</span>
                    </button>
                </div>
            </div>

            <!-- Выбор цвета маркера -->
            <div class="modal-form-group" style="margin-top: 14px;">
                <label class="modal-label" data-i18n="modals.marker_color_label">Выберите цвет (нажмите для применения):</label>
                <div class="marker-colors">
                    <button type="button" class="marker-color-btn active" data-color="#ffeb3b" style="background: #ffeb3b;" title="Желтый"></button>
                    <button type="button" class="marker-color-btn" data-color="#4caf50" style="background: #4caf50;" title="Зеленый"></button>
                    <button type="button" class="marker-color-btn" data-color="#2196f3" style="background: #2196f3;" title="Синий"></button>
                    <button type="button" class="marker-color-btn" data-color="#ff9800" style="background: #ff9800;" title="Оранжевый"></button>
                    <button type="button" class="marker-color-btn" data-color="#e91e63" style="background: #e91e63;" title="Розовый"></button>
                    <button type="button" class="marker-color-btn" data-color="#9c27b0" style="background: #9c27b0;" title="Фиолетовый"></button>
                </div>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeMarkerDialog()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.cancel">Отмена</button>
        </div>
    </div>
</div>
