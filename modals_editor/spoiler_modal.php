<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно сворачиваемого блока (спойлера)
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает создание раскрывающихся блоков <details><summary>, оборачивание
 * выделенного текста в статье, мультиязычность (i18n) и отправку по Enter.
 * ==============================================================================
 */
?>
<div id="spoilerDialog" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-info">🔽</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.spoiler_title">Сворачиваемый блок</h3>
                    <p class="modal-subtitle" data-i18n="modals.spoiler_subtitle">Блок с раскрывающимся содержимым (спойлер)</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeSpoilerDialog()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело окна -->
        <div class="modal-body">
            <div class="modal-form-group">
                <label class="modal-label modal-label-required" for="spoilerTitle" data-i18n="modals.spoiler_block_title">Заголовок блока:</label>
                <input type="text" id="spoilerTitle" class="modal-input" placeholder="Например: Подробности" data-i18n-placeholder="modals.spoiler_ph" autofocus onkeydown="if(event.key==='Enter') insertSpoiler()">
                <div class="modal-help-text">Если текст в статье был предварительно выделен, он станет содержимым спойлера.</div>
            </div>
        </div>

        <!-- Подвал окна -->
        <div class="modal-footer">
            <button type="button" onclick="closeSpoilerDialog()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" onclick="insertSpoiler()" class="modal-btn modal-btn-primary" data-i18n="common.insert">Вставить</button>
        </div>
    </div>
</div>
