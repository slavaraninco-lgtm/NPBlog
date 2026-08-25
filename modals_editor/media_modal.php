<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно добавления медиа
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает вставку видео по URL (YouTube, Vimeo, iframe), загрузку видеофайлов,
 * загрузку аудиофайлов, подключение онлайн аудиопотоков и мультиязычность (i18n).
 * ==============================================================================
 */
?>
<div id="mediaDialog" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">🎬</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.media_title">Добавить медиа</h3>
                    <p class="modal-subtitle" data-i18n="modals.media_subtitle">Вставка видео, аудио и потокового вещания</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeMediaDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <!-- Переключатель типов медиа -->
            <div class="modal-tabs" style="margin: -20px -20px 18px -20px; padding: 0 16px; background: rgba(128,128,128,0.04); display: flex; flex-wrap: wrap;">
                <label class="modal-tab-btn is-active" style="cursor: pointer; user-select: none;">
                    <input type="radio" name="mediaType" value="video-url" checked style="display: none;">
                    <span>📺 <span data-i18n="modals.media_video_url">Видео (URL)</span></span>
                </label>
                <label class="modal-tab-btn" style="cursor: pointer; user-select: none;">
                    <input type="radio" name="mediaType" value="video-file" style="display: none;">
                    <span>📁 <span data-i18n="modals.media_video_file">Видео файл</span></span>
                </label>
                <label class="modal-tab-btn" style="cursor: pointer; user-select: none;">
                    <input type="radio" name="mediaType" value="audio" style="display: none;">
                    <span>🎵 <span data-i18n="modals.media_audio_file">Аудио файл</span></span>
                </label>
               <!-- <label class="modal-tab-btn" style="cursor: pointer; user-select: none;">
                    <input type="radio" name="mediaType" value="audio-stream" style="display: none;">
                    <span>📻 <span data-i18n="modals.media_audio_stream">Аудио поток</span></span>
                </label> --> 
            </div>

            <!-- Секция: Видео по URL -->
            <div id="videoUrlSection">
                <div class="modal-form-group">
                    <label class="modal-label modal-label-required" for="mediaUrl" data-i18n="modals.media_video_url_label">Ссылка на видео:</label>
                    <input type="text" id="mediaUrl" class="modal-input" placeholder="Вставьте ссылку на YouTube или Vimeo" data-i18n-placeholder="modals.media_video_url_ph" onkeydown="if(event.key==='Enter') insertMedia()">
                    <div class="modal-help-text">Поддерживаются ссылки на YouTube, Vimeo или прямые URL на видеофайлы.</div>
                </div>
            </div>

            <!-- Секция: Видео файл (загрузка и список) -->
            <div id="videoFileSection" style="display: none;">
                <div class="modal-form-group">
                    <label class="modal-label" data-i18n="modals.media_video_upload">Загрузить видео файл:</label>
                    <div id="videoDropzone" class="modal-dropzone" onclick="if(event.target.tagName !== 'BUTTON') document.getElementById('videoFile').click()">
                        <input type="file" id="videoFile" accept="video/*" style="display: none;" onchange="handleMediaFileChange(this, 'video')">
                        <div class="modal-dropzone-icon">🎥</div>
                        <div class="dropzone-text" id="videoDropzoneText" style="font-weight: 600; margin-bottom: 4px;" data-i18n="modals.media_drop_text">Выберите видео или перетащите его сюда</div>
                        <div class="dropzone-subtext" style="font-size: 12px; opacity: 0.65; margin-bottom: 10px;" data-i18n="modals.media_video_subtext">Поддерживаются MP4, WebM, OGG</div>
                        <button type="button" class="modal-btn" onclick="event.stopPropagation(); document.getElementById('videoFile').click()" data-i18n="common.browse">Обзор...</button>
                        <div id="videoFileName" class="dropzone-filename" style="display: none; margin-top: 8px; font-weight: 600; font-size: 13px;"></div>
                    </div>
                </div>
                
                <div class="modal-form-group" style="margin-top: 18px;">
                    <label class="modal-label" data-i18n="modals.media_uploaded_videos">Загруженные видео файлы:</label>
                    <div id="videoFilesList" style="max-height: 180px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--modal-radius-inner); padding: 10px; background: rgba(128,128,128,0.02);">
                        <div style="color: var(--text-color); opacity: 0.6;" data-i18n="common.loading">Загрузка списка...</div>
                    </div>
                </div>
            </div>
            
            <!-- Секция: Аудио файл (загрузка и список) -->
            <div id="audioMediaSection" style="display: none;">
                <div class="modal-form-group">
                    <label class="modal-label" data-i18n="modals.media_audio_upload">Загрузить аудио файл:</label>
                    <div id="audioDropzone" class="modal-dropzone" onclick="if(event.target.tagName !== 'BUTTON') document.getElementById('audioFile').click()">
                        <input type="file" id="audioFile" accept="audio/*" style="display: none;" onchange="handleMediaFileChange(this, 'audio')">
                        <div class="modal-dropzone-icon">🎵</div>
                        <div class="dropzone-text" id="audioDropzoneText" style="font-weight: 600; margin-bottom: 4px;" data-i18n="modals.media_drop_text">Выберите аудио или перетащите его сюда</div>
                        <div class="dropzone-subtext" style="font-size: 12px; opacity: 0.65; margin-bottom: 10px;" data-i18n="modals.media_audio_subtext">Поддерживаются MP3, WAV, OGG</div>
                        <button type="button" class="modal-btn" onclick="event.stopPropagation(); document.getElementById('audioFile').click()" data-i18n="common.browse">Обзор...</button>
                        <div id="audioFileName" class="dropzone-filename" style="display: none; margin-top: 8px; font-weight: 600; font-size: 13px;"></div>
                    </div>
                </div>
                
                <div class="modal-form-group" style="margin-top: 18px;">
                    <label class="modal-label" data-i18n="modals.media_uploaded_audios">Загруженные аудио файлы:</label>
                    <div id="audioFilesList" style="max-height: 180px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--modal-radius-inner); padding: 10px; background: rgba(128,128,128,0.02);">
                        <div style="color: var(--text-color); opacity: 0.6;" data-i18n="common.loading">Загрузка списка...</div>
                    </div>
                </div>
            </div>

            <!-- Секция: Аудио поток -->
            <div id="audioStreamSection" style="display: none;">
                <div class="modal-form-group">
                    <label class="modal-label modal-label-required" for="audioStreamUrl" data-i18n="modals.media_stream_url_label">URL аудиопотока (радио / прямой поток):</label>
                    <input type="text" id="audioStreamUrl" class="modal-input" placeholder="Вставьте ссылку на аудиопоток (например, радио или прямой URL)" data-i18n-placeholder="modals.media_stream_url_ph" onkeydown="if(event.key==='Enter') insertMedia()">
                    <div class="modal-help-text">Прямой URL потокового радио или трансляции.</div>
                </div>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeMediaDialog()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" onclick="insertMedia()" class="modal-btn modal-btn-primary" data-i18n="modals.media_insert_btn">Вставить</button>
        </div>
    </div>
</div>
