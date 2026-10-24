<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно успешного обновления (NPBlog Update Success Notice)
 * ==============================================================================
 * Соответствует фреймворку модальных окон (modals/modal.css, modals/modal.js)
 * Лаконичный дизайн без шапки: крупный заголовок с версией по центру и чистая
 * кнопка "Посмотреть изменения".
 * ==============================================================================
 */
?>
<!-- Модальное окно успешного обновления NPBlog -->
<div id="updateSuccessNoticeModal" class="modal-overlay" data-size="sm">
    <div class="modal-dialog modal-sm" style="text-align: center; position: relative; max-width: 420px;">
        <!-- Кнопка закрытия окна в верхнем углу -->
        <button type="button" class="modal-close-btn" onclick="closeUpdateSuccessNoticeModal()" data-modal-close data-i18n-title="common.close" title="Закрыть" style="position: absolute; top: 12px; right: 12px; z-index: 10;">×</button>

        <div class="modal-body" style="padding: 40px 24px 32px 24px;">
            <!-- Крупный заголовок по центру: NPBlog "номер версии" -->
            <h2 id="updateSuccessNoticeHeading" style="margin: 0 0 24px 0; font-size: 28px; font-weight: 700; color: var(--modal-text); letter-spacing: -0.5px; line-height: 1.2;">
                NPBlog <span id="updateSuccessNoticeVersion">2.305</span>
            </h2>

            <!-- Кнопка "Посмотреть изменения" без смайликов и иконок -->
            <div style="display: flex; justify-content: center;">
                <button type="button" id="updateViewChangelogBtn" onclick="openChangelogModalFromNotice()" class="modal-btn modal-btn-primary" style="padding: 10px 26px; font-size: 14.5px; font-weight: 600;" data-i18n="modals.update_view_changes_btn">Посмотреть изменения</button>
            </div>
        </div>
    </div>
</div>

<!-- Отдельное модальное окно списка изменений -->
<div id="updateChangelogModal" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <div class="modal-header">
            <div class="modal-header-start">
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.update_changelog_modal_title">Список изменений</h3>
                    <p class="modal-subtitle">NPBlog <span id="updateChangelogModalVersion">2.305</span></p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeUpdateChangelogModal()" data-modal-close data-i18n-title="common.close" title="Закрыть">×</button>
            </div>
        </div>

        <div class="modal-body">
            <div id="updateSuccessChangelogList" style="max-height: 360px; overflow-y: auto; padding: 4px;">
                <div style="opacity: 0.6; font-size: 13px;" data-i18n="modals.update_changelog_loading">Загрузка списка изменений...</div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-primary" onclick="closeUpdateChangelogModal()" data-modal-close data-i18n="common.close">Закрыть</button>
        </div>
    </div>
</div>
