<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно предупреждения о режиме обновлений Canary
 * ==============================================================================
 */
?>
<div id="canaryWarningModal" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-warning">⚠️</span>
                <div class="modal-titles">
                    <h3 class="modal-title" style="color: var(--modal-color-warning, #f59e0b);" data-i18n="modals.canary_warning_title">Внимание: канал canary</h3>
                    <p class="modal-subtitle" data-i18n="modals.canary_warning_subtitle">Экспериментальная dev-сборка</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="cancelCanaryMode()" data-modal-close data-i18n-title="common.close" title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <!-- Alert banner -->
            <div class="modal-alert modal-alert-warning" style="margin-bottom: 14px;">
                <span class="modal-alert-icon">🛠️</span>
                <div class="modal-alert-content">
                    <div class="modal-alert-title" data-i18n="modals.canary_warning_banner_title">Нестабильная ветка разработки</div>
                    <span data-i18n="modals.canary_warning_banner_desc">Файлы загружаются напрямую из ветки dev без предварительного тестирования.</span>
                </div>
            </div>

            <p class="modal-text" style="font-size: 13.5px; line-height: 1.5; margin-bottom: 12px;" data-i18n="modals.canary_warning_text1">
                Эта версия может быть нестабильной, иметь критические ошибки или нарушить работу редактора. ВСЯ ответственность за использование этой версии лежит на вас.
            </p>

            <p class="modal-text" style="font-size: 13px; opacity: 0.85; margin: 0; line-height: 1.4;" data-i18n="modals.canary_warning_text2">
                Рекомендация: регулярно делайте резервные копии статей и файлов через менеджер бэкапов.
            </p>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="cancelCanaryMode()" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" class="modal-btn modal-btn-danger" id="confirmCanaryModeBtn" onclick="confirmCanaryMode()" data-i18n="modals.canary_confirm_btn">Использовать canary</button>
        </div>
    </div>
</div>
