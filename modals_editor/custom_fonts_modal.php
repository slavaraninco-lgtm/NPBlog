<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно пользовательских шрифтов
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<div id="customFontsModal" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-primary">🔤</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.fonts_title">Пользовательские шрифты</h3>
                    <p class="modal-subtitle" data-i18n="modals.fonts_subtitle">Загрузка и выбор шрифтов (.ttf, .otf, .woff, .woff2)</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeCustomFontsModal()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <!-- Кнопка загрузки шрифта -->
            <input type="file" id="fontUploadInput" accept=".ttf,.otf,.woff,.woff2" style="display: none;" onchange="uploadFontFile()">
            <button type="button" onclick="document.getElementById('fontUploadInput').click()" class="modal-btn modal-btn-primary" style="width: 100%; justify-content: center; padding: 12px; margin-bottom: 16px;" data-i18n="modals.fonts_upload_btn">
                📤 Загрузить шрифт с устройства
            </button>

            <!-- Список доступных шрифтов -->
            <div id="customFontsList" style="display: grid; gap: 10px; max-height: 320px; overflow-y: auto; padding-right: 4px;">
                <!-- Список шрифтов генерируется динамически через JS -->
            </div>

            <!-- Пустое состояние -->
            <div id="customFontsEmpty" style="display: none; text-align: center; padding: 40px 20px; opacity: 0.6;">
                <div style="font-size: 36px; margin-bottom: 10px;">📁</div>
                <p style="font-weight: 600; margin: 0 0 6px 0;" data-i18n="modals.fonts_empty_title">Нет загруженных шрифтов</p>
                <p style="font-size: 13px; margin: 0;" data-i18n="modals.fonts_empty_hint">Нажмите кнопку выше или добавьте файлы шрифтов в папку data/fonts/</p>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="closeCustomFontsModal()" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
