<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно подтверждения удаления статьи
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<div id="deleteConfirmOverlay" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-danger">🗑️</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.delete_confirm_title">Удалить статью?</h3>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeDeleteConfirm()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <p class="modal-text" data-i18n="modals.delete_confirm_message">
                Вы уверены, что хотите удалить эту статью? Это действие нельзя отменить.
            </p>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="closeDeleteConfirm()" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" class="modal-btn modal-btn-danger" onclick="confirmDelete()" data-i18n="common.delete">Удалить</button>
        </div>
    </div>
</div>
