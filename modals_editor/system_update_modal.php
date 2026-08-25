<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно обновления и отката системы (NPBlog Update & Rollback)
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<!-- Модальное окно обновления NPBlog -->
<div id="systemUpdateModal" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-primary">🚀</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.sys_title">Обновление NPBlog</h3>
                    <div class="modal-subtitle">Обновление компонентов и ядра системы</div>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeSystemUpdateModal()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <!-- Информация о версиях -->
            <div class="modal-section-card" id="systemVersionsInfo" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; margin-bottom: 16px;">
                <div>
                    <span style="opacity: 0.8;" data-i18n="modals.sys_ver_current">Текущая версия:</span>
                    <strong id="currentSysVersion" style="margin-left: 6px; font-weight: 600;" data-i18n="common.loading">Загрузка...</strong>
                </div>
                <button type="button" onclick="openRestoreModal()" class="modal-btn modal-btn-secondary" style="padding: 6px 12px; font-size: 13px;" data-i18n="modals.sys_rollback_btn">
                    ⏪ Откат (Rollback)
                </button>
            </div>

            <!-- Шаг 1: Выбор архива -->
            <div id="updateSelectContainer">
                <p class="modal-text" style="margin-bottom: 12px;" data-i18n="modals.sys_choose_archive">
                    Выберите архив .zip с новой версией NPBlog для автоматической установки.
                </p>
                <input type="file" id="systemUpdateInput" accept=".zip" style="display: none;" onchange="handleSystemUpdatePreview()">
                <button type="button" id="systemUpdateBtn" onclick="document.getElementById('systemUpdateInput').click()" class="modal-btn modal-btn-primary" style="width: 100%; justify-content: center; padding: 12px;" data-i18n="modals.sys_select_btn">
                    📦 Выбрать архив (.zip)
                </button>
            </div>

            <!-- Шаг 2: Предпросмотр обновления -->
            <div id="updatePreviewContainer" style="display: none; flex-direction: column; gap: 12px; margin-top: 12px;">
                <div class="modal-section-card" style="padding: 10px 14px; background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.3);">
                    <span data-i18n="modals.sys_arch_ver">Версия в архиве:</span>
                    <strong id="newSysVersion" style="color: #3b82f6; margin-left: 6px;" data-i18n="common.unknown">Неизвестно</strong>
                </div>

                <div>
                    <h4 class="modal-label" style="margin-bottom: 6px;" data-i18n="modals.sys_will_replace">Будут заменены следующие файлы:</h4>
                    <div id="updateFileList" style="max-height: 160px; overflow-y: auto; background: var(--bg-card, rgba(0,0,0,0.04)); border: 1px solid var(--border-color); padding: 10px; border-radius: 8px; font-size: 12px; font-family: monospace; line-height: 1.6;"></div>
                </div>

                <div class="modal-help-text" style="font-size: 12px; opacity: 0.85;" data-i18n="modals.sys_safe_note">
                    🛡️ Ваши статьи, медиафайлы и настройки останутся нетронутыми. Перед обновлением будет автоматически создан бэкап всей системы.
                </div>
            </div>

            <!-- Шаг 3: Прогресс обновления -->
            <div id="updateProgressContainer" style="display: none; flex-direction: column; gap: 10px; margin-top: 16px;">
                <p id="updateStatusText" class="modal-text" style="font-weight: 600; text-align: center; margin: 0;">Подготовка к обновлению...</p>
                <div style="width: 100%; height: 8px; background: rgba(0,0,0,0.1); border-radius: 999px; overflow: hidden;">
                    <div id="updateProgressBar" style="width: 0%; height: 100%; background: #10b981; border-radius: 999px; transition: width 0.3s ease;"></div>
                </div>
            </div>

            <!-- Шаг 4: Успех -->
            <div id="updateSuccessContainer" style="display: none; flex-direction: column; gap: 14px; align-items: center; text-align: center; padding: 20px 0;">
                <div style="font-size: 40px;">🎉</div>
                <p style="color: #10b981; font-weight: 700; font-size: 18px; margin: 0;" data-i18n="modals.sys_success">Обновление успешно завершено!</p>
                <button type="button" onclick="window.location.reload()" class="modal-btn modal-btn-primary" style="padding: 10px 24px;" data-i18n="modals.sys_reload_page">
                    🔄 Обновить страницу
                </button>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="closeSystemUpdateModal()" data-modal-close data-i18n="common.close">Закрыть</button>
            <button type="button" id="startUpdateProcessBtn" onclick="startSystemUpdateProcess()" class="modal-btn modal-btn-danger" style="display: none;" data-i18n="modals.sys_start_btn">Начать обновление</button>
        </div>
    </div>
</div>

<!-- Модальное окно отката системы (Rollback) -->
<div id="restoreSystemModal" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon icon-warning">⏪</span>
                <div class="modal-titles">
                    <h3 class="modal-title">Откат системы (Rollback)</h3>
                    <div class="modal-subtitle">Восстановление предыдущей версии из резервной копии</div>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeRestoreModal()" data-modal-close title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <p class="modal-text" style="margin-bottom: 12px;">Выберите резервную копию для отката системы:</p>
            <div id="restoreBackupsList" style="display: flex; flex-direction: column; gap: 8px; max-height: 280px; overflow-y: auto;">
                <div style="text-align: center; padding: 20px; opacity: 0.6;">Загрузка списка бэкапов...</div>
            </div>

            <div id="restoreProgressContainer" style="display: none; flex-direction: column; gap: 10px; margin-top: 16px; text-align: center;">
                <div class="modal-spinner" style="margin: 0 auto;"></div>
                <p class="modal-text" style="font-weight: 600; margin: 0;">Восстановление системы... (Пожалуйста, подождите)</p>
            </div>

            <div id="restoreSuccessContainer" style="display: none; flex-direction: column; gap: 14px; align-items: center; text-align: center; padding: 20px 0;">
                <div style="font-size: 40px;">✅</div>
                <p style="color: #10b981; font-weight: 700; font-size: 18px; margin: 0;">Система успешно восстановлена!</p>
                <button type="button" onclick="window.location.reload()" class="modal-btn modal-btn-primary" style="padding: 10px 24px;">
                    🔄 Обновить страницу
                </button>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="closeRestoreModal()" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
