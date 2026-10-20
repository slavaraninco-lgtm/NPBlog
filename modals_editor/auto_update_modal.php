<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно автоматического обновления (Auto-Update Modal)
 * ==============================================================================
 */
?>
<div id="autoUpdateModal" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-icon">🚀</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.auto_update_title">Обновление NPBlog</h3>
                    <p class="modal-subtitle" id="autoUpdateSubtitle" data-i18n="modals.auto_update_subtitle">Доступна новая версия редактора</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" id="autoUpdateHeaderCloseBtn" onclick="closeAutoUpdateModal()" data-modal-close data-i18n-title="common.close" title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <!-- Блок 1: Информация об обновлении -->
            <div id="autoUpdateInfoContainer">
                <div class="modal-section-card" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; margin-bottom: 12px;">
                    <div>
                        <span style="opacity: 0.75; font-size: 13px;" data-i18n="modals.auto_update_current_ver">Текущая версия:</span>
                        <strong id="autoUpdateCurrentVersion" style="color: var(--text-color); margin-left: 6px; font-weight: 600;">...</strong>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="opacity: 0.75; font-size: 13px;" data-i18n="modals.auto_update_new_ver">Новая:</span>
                        <span id="autoUpdateChannelBadge" class="modal-badge" style="font-size: 11px; padding: 2px 8px; border-radius: 999px;">Stable</span>
                        <strong id="autoUpdateNewVersion" style="color: var(--text-color); font-size: 15px; font-weight: 700;">...</strong>
                    </div>
                </div>

                <div style="margin-bottom: 14px;">
                    <h4 class="modal-label" style="margin-bottom: 8px;" data-i18n="modals.auto_update_changelog_title">Список изменений:</h4>
                    <div id="autoUpdateChangelogList" style="max-height: 180px; overflow-y: auto; background: var(--modal-bg-subtle, rgba(0,0,0,0.03)); border: 1px solid var(--border-color); padding: 10px 14px; border-radius: 8px; font-size: 13px; line-height: 1.6;">
                        <span style="opacity: 0.6;" data-i18n="modals.auto_update_loading_changelog">Загрузка списка...</span>
                    </div>
                </div>

                <div class="modal-help-text" style="font-size: 12px; opacity: 0.85;" data-i18n="modals.auto_update_safety_note">
                    🛡️ Ваши статьи и настройки останутся нетронутыми. Перед установкой будет создан бэкап.
                </div>
            </div>

            <!-- Блок 2: Прогресс обновления -->
            <div id="autoUpdateProgressContainer" style="display: none; flex-direction: column; gap: 12px; padding: 14px 0;">
                <p id="autoUpdateStatusText" class="modal-text" style="font-weight: 600; text-align: center; margin: 0;" data-i18n="modals.auto_update_status_downloading">Подготовка к загрузке обновления...</p>
                <div style="width: 100%; height: 10px; background: rgba(128,128,128,0.18); border-radius: 999px; overflow: hidden; position: relative;">
                    <div id="autoUpdateProgressBar" style="width: 5%; height: 100%; background: var(--primary-color, #10b981); border-radius: 999px; transition: width 0.35s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; opacity: 0.75; padding: 0 2px;">
                    <span id="autoUpdateProgressDetail" data-i18n="modals.auto_update_do_not_close">Пожалуйста, не закрывайте вкладку...</span>
                    <span id="autoUpdateProgressPercent" style="font-weight: 600; font-family: monospace;">5%</span>
                </div>
            </div>

            <!-- Блок 3: Успешное завершение -->
            <div id="autoUpdateSuccessContainer" style="display: none; flex-direction: column; gap: 14px; align-items: center; text-align: center; padding: 20px 0;">
                <div style="font-size: 42px;">🎉</div>
                <p style="color: var(--text-color); font-weight: 700; font-size: 18px; margin: 0;" data-i18n="modals.auto_update_success_title">Обновление успешно установлено!</p>
                <button type="button" onclick="try { localStorage.setItem('npblog_just_updated', 'true'); } catch(e){} window.location.reload();" class="modal-btn modal-btn-primary" style="padding: 10px 24px;" data-i18n="modals.sys_reload_page">
                    🔄 Обновить страницу
                </button>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer" id="autoUpdateFooter">
            <button type="button" class="modal-btn modal-btn-ghost" id="autoUpdateCancelBtn" onclick="closeAutoUpdateModal()" data-modal-close data-i18n="modals.auto_update_remind_later">Напомнить позже</button>
            <button type="button" class="modal-btn modal-btn-primary" id="autoUpdateStartBtn" onclick="startAutoUpdateProcess()" data-i18n="modals.auto_update_start_btn">Обновить сейчас</button>
        </div>
    </div>
</div>
