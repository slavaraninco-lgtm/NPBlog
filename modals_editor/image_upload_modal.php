<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно добавления изображения
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает загрузку файлов (Drag & Drop), вставку по URL, сетки 2x2/3x3,
 * галереи, кастомные размеры и мультиязычность (i18n).
 * ==============================================================================
 */
?>
<div id="imageUploadDialog" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">🖼️</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.image_title">Добавить изображение</h3>
                    <p class="modal-subtitle" data-i18n="modals.image_subtitle">Загрузка с устройства или по ссылке</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeImageDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <!-- Переключатель источника (Файл / Ссылка) -->
            <div class="modal-tabs" style="margin: -20px -20px 18px -20px; padding: 0 20px; background: rgba(128,128,128,0.04);">
                <label class="modal-tab-btn is-active" style="cursor: pointer; user-select: none;">
                    <input type="radio" name="imageSource" value="file" checked style="display: none;">
                    <span data-i18n="modals.image_tab_file">📁 Загрузить файл</span>
                </label>
                <label class="modal-tab-btn" style="cursor: pointer; user-select: none;">
                    <input type="radio" name="imageSource" value="url" style="display: none;">
                    <span data-i18n="modals.image_tab_url">🔗 Вставить ссылку</span>
                </label>
            </div>

            <!-- Блок загрузки файла -->
            <div id="fileUploadContainer">
                <div id="imageDropzone" class="modal-dropzone" onclick="if(event.target.tagName !== 'BUTTON' && !event.target.closest('#imageFilesPreview')) document.getElementById('imageFile').click()">
                    <input type="file" id="imageFile" accept="image/*" multiple style="display: none;" onchange="handleImageFileSelect(this)">
                    <div class="modal-dropzone-icon">🖼️</div>
                    <div class="dropzone-text" id="imageDropzoneText" style="font-weight: 600; margin-bottom: 4px;" data-i18n="modals.image_drop_text">Выберите изображения или перетащите их сюда</div>
                    <div class="dropzone-subtext" style="font-size: 12px; opacity: 0.65; margin-bottom: 12px;" data-i18n="modals.image_drop_subtext">Поддерживаются JPG, PNG, GIF, WEBP (до 25 МБ)</div>
                    <button type="button" class="modal-btn" onclick="event.stopPropagation(); document.getElementById('imageFile').click()" data-i18n="modals.image_browse_btn">Обзор файлов...</button>
                    
                    <!-- Превью выбранных файлов -->
                    <div id="imageFilesPreview" style="display: none; width: 100%; margin-top: 15px; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 10px; max-height: 150px; overflow-y: auto; padding: 5px;"></div>
                </div>
            </div>

            <!-- Контейнер превью сетки (для grid-расположения) -->
            <div id="imageGridPreviewContainer" style="display: none; margin: 15px 0;"></div>

            <!-- Блок вставки по URL -->
            <div id="urlContainer" style="display: none;">
                <div class="modal-form-group">
                    <label class="modal-label" for="imageUrl" data-i18n="modals.image_url_label">URL изображения</label>
                    <textarea id="imageUrl" class="modal-textarea" style="min-height: 75px;" placeholder="Введите URL изображения (несколько — каждое с новой строки или через запятую)" data-i18n-placeholder="modals.image_url_ph"></textarea>
                    <div class="modal-help-text">Можно указать прямые ссылки на картинки в интернете.</div>
                </div>
            </div>
            
            <!-- Подпись к изображению -->
            <div class="modal-form-group" style="margin-top: 14px;">
                <label class="modal-label" for="imageCaption" data-i18n="modals.image_caption_label">Подпись к изображению:</label>
                <input type="text" id="imageCaption" class="modal-input" placeholder="Введите подпись (необязательно)" data-i18n-placeholder="modals.image_caption_ph">
            </div>

            <!-- Настройки размера и расположения -->
            <div class="modal-grid-2">
                <div class="modal-form-group">
                    <label class="modal-label" for="imageSize" data-i18n="modals.image_size_label">Размер:</label>
                    <select id="imageSize" class="modal-select">
                        <option value="small" data-i18n="modals.image_size_small">Маленький</option>
                        <option value="medium" selected data-i18n="modals.image_size_medium">Средний</option>
                        <option value="large" data-i18n="modals.image_size_large">Большой</option>
                        <option value="custom" data-i18n="modals.image_size_custom">Свой размер</option>
                    </select>
                </div>
                <div class="modal-form-group">
                    <label class="modal-label" for="gridLayout" data-i18n="modals.image_layout_label">Расположение:</label>
                    <select id="gridLayout" class="modal-select">
                        <option value="" data-i18n="modals.image_layout_normal">Обычное</option>
                        <option value="2x1">2×1</option>
                        <option value="2x2">2×2</option>
                        <option value="3x1">3×1</option>
                        <option value="3x2">3×2</option>
                        <option value="3x3">3×3</option>
                    </select>
                </div>
            </div>

            <!-- Кастомный размер (Ширина x Высота) -->
            <div id="customSizeInputs" style="display: none; margin-top: 10px;">
                <div class="modal-grid-2">
                    <div class="modal-form-group">
                        <label class="modal-label">Ширина</label>
                        <div style="display: flex; gap: 6px;">
                            <input type="number" id="customWidth" class="modal-input" placeholder="Ширина">
                            <select id="widthUnit" class="modal-select" style="width: 70px; flex-shrink: 0;">
                                <option value="px">px</option>
                                <option value="%">%</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-label">Высота</label>
                        <div style="display: flex; gap: 6px;">
                            <input type="number" id="customHeight" class="modal-input" placeholder="Высота">
                            <select id="heightUnit" class="modal-select" style="width: 70px; flex-shrink: 0;">
                                <option value="px">px</option>
                                <option value="%">%</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Опции чекбоксов -->
            <div style="margin-top: 14px; display: flex; flex-direction: column; gap: 10px;">
                <label class="modal-checkbox-label" for="noBorderRadius">
                    <input type="checkbox" id="noBorderRadius" class="modal-checkbox">
                    <span data-i18n="modals.image_no_rounded">Убрать закругление по краям</span>
                </label>
                
                <label class="modal-checkbox-label" id="insertGalleryContainer" for="insertGallery">
                    <input type="checkbox" id="insertGallery" class="modal-checkbox">
                    <span style="display: inline-flex; align-items: center; gap: 6px;">
                        
                        <span data-i18n="modals.image_gallery_scroll">Вставить как карусель (галерею с пролистыванием)</span>
                        <span style="font-size: 11px; opacity: 0.65; font-weight: normal;">(для 2+ изображений)</span>
                    </span>
                </label>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeImageDialog()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" onclick="processImage()" class="modal-btn modal-btn-primary" data-i18n="modals.image_add_btn">Добавить</button>
        </div>
    </div>
</div>
