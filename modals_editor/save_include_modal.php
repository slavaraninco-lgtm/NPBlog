<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно сохранения фрагмента в includes
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<div id="saveIncludeOverlay" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.save_include_title">Сохранить в includes</h3>
                    <p class="modal-subtitle" data-i18n="modals.save_include_subtitle">Сохранение фрагмента для повторного использования</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeSaveInclude()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <div class="modal-form-group" style="margin-bottom: 0;">
                <label class="modal-label" for="includeNameInput" data-i18n="modals.save_include_name_label">Название файла:</label>
                <input type="text" class="modal-input" id="includeNameInput" placeholder="Например: контакты" data-i18n-placeholder="modals.save_include_ph">
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="closeSaveInclude()" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" class="modal-btn modal-btn-primary" onclick="confirmSaveInclude()" data-i18n="common.save">Сохранить</button>
        </div>
    </div>
</div>
