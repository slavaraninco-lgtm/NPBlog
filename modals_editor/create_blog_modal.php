<?php
/**
 * ==============================================================================
 * NPBlog Editor - Модальное окно создания нового блога
 * ==============================================================================
 * Разработано на базе единого фреймворка модальных окон (modals/modal.css, modals/modal.js)
 * ==============================================================================
 */
?>
<div id="createBlogModalOverlay" class="modal-overlay" data-size="md">
    <div class="modal-dialog modal-md">
        <!-- Шапка окна -->
        <div class="modal-header">
            <div class="modal-header-start">
                <span class="modal-header-icon" style="font-size: 20px;">✨</span>
                <div class="modal-titles">
                    <h3 class="modal-title" data-i18n="modals.create_blog_title">Создать блог</h3>
                    <p class="modal-subtitle" data-i18n="modals.create_blog_subtitle">Создание новой папки со всеми файлами блога и добавление в пути</p>
                </div>
            </div>
            <div class="modal-header-actions">
                <button type="button" class="modal-close-btn" onclick="closeCreateBlogModal()" data-modal-close data-i18n-title="common.close" title="Закрыть">×</button>
            </div>
        </div>

        <!-- Тело модального окна -->
        <div class="modal-body" style="display: flex; flex-direction: column; gap: 16px;">
            <div>
                <label for="createBlogTitleInput" style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: var(--text-color);" data-i18n="modals.create_blog_name_label">
                    Имя будущего блога:
                </label>
                <input type="text" id="createBlogTitleInput" class="modal-input" placeholder="Например: Мой блог, Заметки..." data-i18n-placeholder="modals.create_blog_name_ph" style="width: 100%; box-sizing: border-box;" oninput="onNewBlogTitleInput(this.value)">
                <p style="margin: 4px 0 0 0; font-size: 12px; opacity: 0.7; color: var(--text-color);" data-i18n="modals.create_blog_name_hint">
                    Отображается в заголовке главной страницы блога и вкладке браузера.
                </p>
            </div>

            <div>
                <label for="createBlogFolderInput" style="display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; color: var(--text-color);" data-i18n="modals.create_blog_folder_label">
                    Название папки:
                </label>
                <input type="text" id="createBlogFolderInput" class="modal-input" placeholder="Например: data2, my_blog" data-i18n-placeholder="modals.create_blog_folder_ph" style="width: 100%; box-sizing: border-box; font-family: monospace;" oninput="onNewBlogFolderInput(this.value)">
                <p style="margin: 4px 0 0 0; font-size: 12px; opacity: 0.7; color: var(--text-color);" data-i18n="modals.create_blog_folder_hint">
                    Папка будет создана в корне веб-сервера. Разрешены латинские буквы, цифры, дефис и подчеркивание.
                </p>
            </div>

            <div style="padding: 12px; border-radius: 8px; background: rgba(127, 127, 127, 0.08); border: 1px solid var(--border-color); font-size: 13px;">
                <div style="font-weight: 600; margin-bottom: 4px; color: var(--text-color);" data-i18n="modals.create_blog_path_preview">Будет создан путь:</div>
                <div id="createBlogPathPreview" style="font-family: monospace; color: var(--accent-color, #3b82f6); word-break: break-all;">—</div>
            </div>

            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; margin-top: 4px;">
                <input type="checkbox" id="createBlogMakeActiveCheckbox" checked style="width: 18px; height: 18px; cursor: pointer;">
                <span style="font-size: 14px; color: var(--text-color);" data-i18n="modals.create_blog_make_active">Сделать этот блог активным сразу после создания</span>
            </label>
        </div>

        <!-- Подвал -->
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="button" class="modal-btn modal-btn-ghost" onclick="closeCreateBlogModal()" data-modal-close data-i18n="common.cancel">Отмена</button>
            <button type="button" class="modal-btn modal-btn-primary" id="btnSubmitCreateBlog" onclick="submitCreateBlog()" data-i18n="modals.create_blog_submit">✨ Создать блог</button>
        </div>
    </div>
</div>
