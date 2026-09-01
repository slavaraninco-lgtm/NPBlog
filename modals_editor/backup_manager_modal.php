<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно менеджера бэкапов
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * Поддерживает группировку бэкапов по статьям (активным и удалённым),
 * просмотр содержимого бэкапа в новой вкладке, восстановление и удаление бэкапов.
 * ==============================================================================
 */
?>
<div id="backupManagerOverlay" class="modal-overlay" data-size="lg">
    <div class="modal-dialog modal-lg" style="height: 80vh; max-height: 800px; display: flex; flex-direction: column;">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.backup_manager_title">Менеджер бэкапов</h3>
                    <p class="modal-subtitle" data-i18n="modals.backup_manager_subtitle">История версий и резервные копии статей блога</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeBackupManager()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Содержимое со списком бэкапов -->
        <div class="modal-body" id="backupManagerContent" style="padding: 20px; overflow-y: auto; flex: 1;">
            <div style="text-align: center; opacity: 0.6; padding: 20px; color: var(--text-color);">
                <span data-i18n="modals.loading">Загрузка...</span>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" onclick="closeBackupManager()" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
