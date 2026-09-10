<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно управления наборами смайлов
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает Drag & Drop загрузку папок и GIF/PNG файлов со смайлами,
 * создание именованных коллекций, просмотр и удаление наборов смайлов (i18n).
 * ==============================================================================
 */
?>
<div id="smileSetsDialog" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.smiles_title">Управление наборами смайлов</h3>
                    <p class="modal-subtitle" data-i18n="modals.smiles_subtitle">Загрузка и управление коллекциями смайлов</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeSmileSetsDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <!-- Drag & Drop зона -->
            <div class="modal-form-group">
                <div id="smileDropzone" class="modal-dropzone" ondragover="handleSmileDragOver(event)" ondragleave="handleSmileDragLeave(event)" ondrop="handleSmileDrop(event)">
                    <input type="file" id="smileFolderInput" webkitdirectory directory multiple style="display: none;" onchange="handleSmileFileSelect(event)">
                    <input type="file" id="smileFilesInput" accept="image/gif,image/png,image/webp,image/jpeg,application/zip" multiple style="display: none;" onchange="handleSmileFileSelect(event)">
                    
                    <div class="modal-dropzone-icon">📁</div>
                    <div class="modal-dropzone-title" id="smileDropzoneText" data-i18n="modals.smiles_drop_text">Перетащите папку со смайлами сюда</div>
                    <div class="modal-dropzone-desc" data-i18n="modals.smiles_drop_subtext">Или выберите файлы / папку на диске</div>
                    
                    <div style="display: flex; gap: 8px; justify-content: center; margin-top: 10px; flex-wrap: wrap;">
                        <button type="button" class="modal-btn modal-btn-secondary" onclick="document.getElementById('smileFolderInput').click()" data-i18n="modals.smiles_select_folder_btn">Выбрать папку</button>
                        <button type="button" class="modal-btn modal-btn-secondary" onclick="document.getElementById('smileFilesInput').click()" data-i18n="modals.smiles_select_gif_btn">Выбрать файлы</button>
                    </div>
                    
                    <!-- Поле ввода имени для набора -->
                    <div id="smileSetNameField" style="display: none; margin-top: 16px; border-top: 1px solid var(--border-color); padding-top: 14px; text-align: left;">
                        <label for="smileSetNameInput" class="modal-label modal-label-required" data-i18n="modals.smiles_set_name_label">Название для нового набора смайлов:</label>
                        <input type="text" id="smileSetNameInput" class="modal-input" placeholder="Например: Аниме" data-i18n-placeholder="modals.smiles_set_name_ph">
                    </div>

                    <div id="smileSelectedFilesInfo" style="display: none; margin-top: 10px; font-size: 13px; font-weight: 500; color: var(--accent-color, #4CAF50);">
                        <span data-i18n="modals.smiles_files_selected_prefix">Выбрано файлов:</span> <span id="smileSelectedCount">0</span>
                    </div>

                    <div style="margin-top: 14px; display: none;" id="smileUploadBtnContainer">
                        <button type="button" onclick="handleSmileSetUpload()" class="modal-btn modal-btn-primary" style="width: 100%; justify-content: center;" data-i18n="modals.smiles_upload_set_btn">Загрузить набор</button>
                    </div>
                </div>
            </div>
            
            <!-- Доступные наборы -->
            <div class="modal-form-group" style="margin-top: 16px;">
                <label class="modal-label" data-i18n="modals.smiles_available_sets">Доступные наборы:</label>
                <div id="smileSetsList" class="file-upload-list" style="max-height: 200px; overflow-y: auto; background: var(--modal-bg);">
                    <div style="text-align: center; opacity: 0.6; padding: 12px; color: var(--text-color);" data-i18n="modals.smiles_loading">Загрузка наборов...</div>
                </div>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeSmileSetsDialog()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
