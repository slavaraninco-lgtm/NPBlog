<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно предупреждения о DEV сборке
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<div id="devWarningDialog" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-warning">⚠️</span>
                <div class="modal-titles">
                    <h3 class="modal-title" style="color: #f59e0b;" data-i18n="modals.dev_warning_title">DEV-версия системы</h3>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="confirmDevWarning()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <p class="modal-text" style="font-weight: 500; margin-bottom: 10px;">
                Вы используете <strong>Development (разрабатываемую)</strong> сборку NPBlog.
            </p>
            <p class="modal-text" style="font-size: 13px; opacity: 0.85;">
                Эта версия может быть <strong>нестабильной</strong>, содержать недоработки и незавершенные функции. Настоятельно рекомендуется периодически делать бэкапы ваших статей и файлов.
            </p>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" onclick="confirmDevWarning()" class="modal-btn modal-btn-primary" style="background: #f59e0b; border-color: #f59e0b;" data-i18n="modals.dev_warning_understand_btn">Я понимаю риски</button>
        </div>
    </div>
</div>
