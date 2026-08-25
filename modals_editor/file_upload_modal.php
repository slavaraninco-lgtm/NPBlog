<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно загрузки файлов и документов
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает Drag & Drop загрузку, вставку файлов как кнопки/гиперссылки,
 * просмотр и удаление загруженных документов с мультиязычностью (i18n).
 * ==============================================================================
 */
?>
<div id="fileUploadDialog" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">📁</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.file_title">Загрузить файл</h3>
                    <p class="modal-subtitle" data-i18n="modals.file_subtitle">Прикрепление файлов и документов к статье</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeFileUploadDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <!-- Drag & Drop зона -->
            <div class="modal-form-group">
                <div id="fileDropzone" class="modal-dropzone" onclick="if(event.target.tagName !== 'BUTTON') document.getElementById('documentFile').click()">
                    <input type="file" id="documentFile" style="display: none;" onchange="handleFileSelect(this)">
                    <div class="modal-dropzone-icon">📤</div>
                    <div class="modal-dropzone-title" data-i18n="modals.file_drop_text">Выберите файл или перетащите его сюда</div>
                    <div id="documentFileName" class="modal-dropzone-desc" data-i18n="modals.file_none" style="margin-top: 4px;">Файл не выбран</div>
                    <button type="button" class="modal-btn modal-btn-secondary" style="margin-top: 8px;" onclick="event.stopPropagation(); document.getElementById('documentFile').click()" data-i18n="common.browse">Обзор...</button>
                </div>
            </div>

            <!-- Опция вставки как гиперссылка -->
            <div class="modal-form-group">
                <label class="modal-checkbox-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none;">
                    <input type="checkbox" id="insertAsHyperlink" class="modal-checkbox" style="cursor: pointer;">
                    <span class="modal-label-text" style="font-size: 13px; font-weight: 500; opacity: 0.9; color: var(--text-color);" data-i18n="modals.file_insert_link">Вставить как гиперссылку</span>
                </label>
            </div>

            <!-- Список ранее загруженных файлов -->
            <div class="modal-form-group" style="margin-top: 14px;">
                <label class="modal-label" data-i18n="modals.file_uploaded_title">Загруженные файлы:</label>
                <div class="file-upload-list" id="fileUploadList" style="max-height: 220px; overflow-y: auto;">
                    <div class="file-upload-empty" data-i18n="common.loading">Загрузка списка файлов...</div>
                </div>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeFileUploadDialog()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
