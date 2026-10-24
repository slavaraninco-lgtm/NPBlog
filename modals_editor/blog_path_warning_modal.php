<div id="blogPathWarningModal" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <div class="modal-header">
            <div class="modal-header-start">
                <div class="modal-titles">
                    <h3 class="modal-title" style="color: #f59e0b; display: flex; align-items: center; gap: 8px;">
                        <span>⚠️</span>
                        <span data-i18n="modals.blog_path_warning_title">Путь к блогу не установлен</span>
                    </h3>
                    <p class="modal-subtitle" data-i18n="modals.blog_path_warning_subtitle">Требуется указать путь к рабочей директории блога</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" data-modal-close data-i18n-title="common.close" title="Закрыть">×</button>
            </div>
        </div>

        <div class="modal-body">
            <div class="modal-alert modal-alert-warning" style="margin-bottom: 16px;">
                <span class="modal-alert-icon">📁</span>
                <div class="modal-alert-content">
                    <div class="modal-alert-title" data-i18n-html="modals.blog_path_warning_current">Текущий путь: <code>/CHANGE/ME</code></div>
                    <div data-i18n="modals.blog_path_warning_desc">В конфигурации редактора установлен дефолтный путь-плейсхолдер. До указания реального пути сохранение статей и файлов заблокировано во избежание ошибок.</div>
                </div>
            </div>

            <p class="modal-text" style="margin-bottom: 12px; font-size: 13.5px; line-height: 1.5;" data-i18n-html="modals.blog_path_warning_hint">
                Вы можете привязать редактор к локальной папке <code>data</code> на этом сервере, либо перейти в параметры и вручную задать произвольный путь.
            </p>

            <div style="background: var(--modal-header-bg, rgba(0,0,0,0.03)); border: 1px solid var(--modal-border); border-radius: var(--modal-radius-inner); padding: 12px 14px; font-size: 12.5px; display: flex; flex-direction: column; gap: 4px;">
                <span style="opacity: 0.7; font-weight: 500;" data-i18n="modals.blog_path_warning_rec_label">Рекомендуемый локальный путь к данным:</span>
                <code id="warningSuggestedPathDisplay" style="font-family: monospace; font-size: 13px; font-weight: 600; word-break: break-all; color: var(--modal-text);">
                    <?= htmlspecialchars(str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/../data')) ?>
                </code>
            </div>
        </div>

        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="modal-btn modal-btn-ghost" data-modal-close data-i18n="modals.blog_path_warning_later">Позже</button>
            <button type="button" class="modal-btn" onclick="closeBlogPathWarningModal(); openGlobalSettings(); if (typeof showGlobalSection === 'function') showGlobalSection('paths');" data-i18n="modals.blog_path_warning_paths_btn">
                Настройки путей
            </button>
            <button type="button" class="modal-btn modal-btn-primary" onclick="autoFixBlogPath()" style="background: #ffffffff; color: #000000ff;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span data-i18n="modals.blog_path_warning_bind_btn">Привязать к папке data</span>
            </button>
        </div>
    </div>
</div>
