<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно проверки нумерации статей
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<div id="numberingCheckOverlay" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.numbering_check_title">Проверка нумерации статей</h3>
                    <p class="modal-subtitle" data-i18n="modals.numbering_check_subtitle">Анализ порядка и целостности ID статей</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeNumberingCheck()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <div class="numbering-check-content" id="numberingCheckContent" style="padding: 10px 0;">
                <div class="numbering-status" style="text-align: center; opacity: 0.6; padding: 20px;" data-i18n="modals.numbering_checking">Проверка...</div>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="closeNumberingCheck()" data-modal-close data-i18n="common.close">Закрыть</button>
            <button type="button" class="modal-btn modal-btn-primary" id="fixNumberingBtn" style="display:none;" onclick="fixNumbering()">Исправить</button>
        </div>
    </div>
</div>
