<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно вставки ссылки
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает вставку ссылки по URL, текст ссылки, автозаполнение из буфера,
 * автоподстановку выделенного текста и мультиязычность (i18n).
 * ==============================================================================
 */
?>
<div id="linkDialog" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.link_title">Вставить ссылку</h3>
                    <p class="modal-subtitle" data-i18n="modals.link_subtitle">Добавление гиперссылки в текст статьи</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeLinkDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <!-- URL адрес ссылки -->
            <div class="modal-form-group">
                <label class="modal-label modal-label-required" for="linkUrl" data-i18n="modals.link_url_label">URL адрес ссылки:</label>
                <input type="text" id="linkUrl" class="modal-input" placeholder="https://example.com" value="https://" required autofocus onkeydown="if(event.key==='Enter') insertLinkFromDialog()">
                <div class="modal-help-text" data-i18n="modals.link_url_hint">Адрес веб-страницы (включая https://)</div>
            </div>

            <!-- Текст ссылки -->
            <div class="modal-form-group" style="margin-top: 14px;">
                <label class="modal-label" for="linkText" data-i18n="modals.link_text_label">Текст ссылки (необязательно):</label>
                <input type="text" id="linkText" class="modal-input" placeholder="Оставьте пустым — будет использован выделенный текст" data-i18n-placeholder="modals.link_text_ph" onkeydown="if(event.key==='Enter') insertLinkFromDialog()">
                <div class="modal-help-text" data-i18n="modals.link_text_hint">Если не заполнено, будет использован текущий выделенный текст.</div>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeLinkDialog()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" onclick="insertLinkFromDialog()" class="modal-btn modal-btn-primary" data-i18n="common.insert">Вставить</button>
        </div>
    </div>
</div>
