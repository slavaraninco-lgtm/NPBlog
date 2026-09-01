<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно восстановления истекшей сессии
 * ==============================================================================
 * Переписано на новый фреймворк модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<div id="sessionExpiredOverlay" class="modal-overlay" data-size="sm" data-backdrop-close="false" data-esc-close="false">
    <div class="modal-dialog modal-sm">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.session_expired_title">Сессия истекла</h3>
                    <p class="modal-subtitle" data-i18n="modals.session_expired_subtitle">Авторизация для продолжения работы</p>
                </div>
            </div>
        </div>

        <!-- Тело -->
        <div class="modal-body">
            <p class="modal-text" style="font-size: 13px; line-height: 1.5; margin-bottom: 16px;" data-i18n="modals.session_expired_desc">
                Срок действия вашей сессии истек или обновился токен безопасности. Пожалуйста, введите ваш пароль, чтобы продолжить работу без потери данных.
            </p>
            <div class="modal-form-group" style="margin-bottom: 0;">
                <input type="password" id="sessionExpiredPassword" class="modal-input" placeholder="Введите ваш пароль" data-i18n-placeholder="modals.session_expired_pwd_ph" onkeydown="if(event.key === 'Enter') submitSessionReauth()">
                <div id="sessionExpiredError" class="modal-error-msg" style="display: none; margin-top: 8px;"></div>
            </div>
        </div>

        <!-- Подвал -->
        <div class="modal-footer">
            <button type="button" onclick="cancelSessionReauth()" class="modal-btn modal-btn-ghost" data-i18n="modals.session_expired_discard_btn">Сбросить изменения</button>
            <button type="button" onclick="submitSessionReauth()" class="modal-btn modal-btn-primary" data-i18n="modals.session_expired_login_btn">Войти</button>
        </div>
    </div>
</div>
