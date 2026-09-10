<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно менеджера автосохранений
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает группировку автосохранений по заголовкам статей, загрузку
 * содержимого (включая Markdown/HTML), удаление снимков и отображение даты.
 * ==============================================================================
 */
?>
<div id="autosaveManagerModal" class="modal-overlay" data-size="lg">
    <div class="modal-dialog modal-lg" style="height: 80vh; max-height: 800px; display: flex; flex-direction: column;">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.as_title">Менеджер автосохранений</h3>
                    <p class="modal-subtitle" data-i18n="modals.as_subtitle">Автоматические снимки и черновики статей</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeAutosaveManager()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Список автосохранений -->
        <div class="modal-body" style="padding: 20px; overflow-y: auto; flex: 1;">
            <div id="autosavesList" style="display: grid; gap: 12px;">
                <!-- Список автосохранений загружается динамически -->
            </div>
            <div id="autosavesEmpty" style="display: none; text-align: center; padding: 40px; color: var(--text-color); opacity: 0.6;">
                <p data-i18n="modals.as_empty_title" style="font-weight: 600; font-size: 15px; margin-bottom: 6px;">Нет автосохранений</p>
                <p style="font-size: 13px; margin: 0; opacity: 0.8;" data-i18n="modals.as_empty_hint">Автосохранения появятся здесь после включения функции автосохранения</p>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" onclick="closeAutosaveManager()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
