// ——— Система includes ———
function openSaveInclude() {
    const input = document.getElementById('includeNameInput');
    if (input) input.value = '';

    if (window.Modal) {
        Modal.open('#saveIncludeOverlay');
    } else {
        const overlay = document.getElementById('saveIncludeOverlay');
        if (overlay) overlay.classList.add('show');
    }

    // Закрываем меню "Прочее"
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');

    if (input) setTimeout(() => input.focus(), 100);
}

function closeSaveInclude() {
    if (window.Modal) {
        Modal.close('#saveIncludeOverlay');
    } else {
        const overlay = document.getElementById('saveIncludeOverlay');
        if (overlay) overlay.classList.remove('show');
    }
}

async function confirmSaveInclude() {
    const input = document.getElementById('includeNameInput');
    const name = input.value.trim();

    if (!name) {
        showNotification('Введите название файла', 'warning');
        return;
    }

    // Получаем контент из редактора
    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');
    let content;

    if (editorMode === 'visual') {
        content = ve.innerHTML;
    } else {
        content = ta.value;
    }

    if (!content.trim()) {
        showNotification('Нет контента для сохранения', 'warning');
        return;
    }

    // Блокируем кнопку
    const saveBtn = document.querySelector('.save-include-btn.save');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Сохранение...';
    }

    try {
        const response = await fetch('save_include.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ name: name, content: content })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Include сохранен: ' + (data.displayName || data.filename), 'success');
            includesListLoaded = false; // Сбрасываем флаг для перезагрузки списка
            closeSaveInclude();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка сохранения include:', error);
        showNotification('Ошибка при сохранении include', 'error');
    } finally {
        // Разблокируем кнопку
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить';
        }
    }
}

let includesListLoaded = false;
let articlesListLoaded = false;
let draftsListLoaded = false;

// Функции для работы с черновиками
function saveDraft() {
    const title = document.getElementById('title').value.trim();
    let content = editorMode === 'visual'
        ? document.getElementById('contentVisual').innerHTML
        : document.getElementById('content').value;

    if (window.enableMarkdown) {
        if (editorMode === 'visual') {
            document.getElementById('content').value = convertHtmlToMarkdown(document.getElementById('contentVisual').innerHTML);
        }
        const rawMarkdown = document.getElementById('content').value;
        const base64Markdown = btoa(unescape(encodeURIComponent(rawMarkdown)));
        content = parseMarkdownToHtml(rawMarkdown) + `\n<script type="text/markdown" id="markdown-source" data-base64="${base64Markdown}"></script>`;
    } else if (editorMode === 'visual') {
        content = cleanContentForSave(content);
    }

    if (!title && !content) {
        showAlert('Нечего сохранять в черновик');
        return;
    }

    fetch('save_draft.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ title: title, content: content })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Черновик сохранен');
                draftsListLoaded = false; // Сбрасываем флаг чтобы перезагрузить список
            } else {
                showAlert('Ошибка: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка сохранения черновика:', error);
            showAlert('Ошибка при сохранении черновика');
        });

    // Закрываем меню
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');
}

function toggleDraftsSubmenu(event) {
    event.stopPropagation();

    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');

    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });

    if (!isOpen) {
        button.classList.add('submenu-open');
        loadDraftsList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadDraftsList() {
    const submenu = document.getElementById('draftsSubmenu');
    if (!submenu) return;

    try {
        const response = await fetch('get_drafts.php');
        const data = await response.json();

        if (data.success) {
            if (data.drafts.length === 0) {
                submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('more_menu.no_drafts', 'Нет черновиков') : 'Нет черновиков') + '</div>';
            } else {
                const untitledText = window.t ? window.t('more_menu.untitled', 'Без названия') : 'Без названия';
                const delDraftText = window.t ? window.t('more_menu.delete_draft', 'Удалить черновик') : 'Удалить черновик';
                const currentLocale = (window.NPBlogI18n && typeof window.NPBlogI18n.getLocale === 'function') ? window.NPBlogI18n.getLocale() : 'ru-RU';
                submenu.innerHTML = data.drafts.map(draft => {
                    const displayTitle = draft.title || untitledText;
                    const date = new Date(draft.timestamp * 1000).toLocaleString(currentLocale, {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    return `<div class="draft-item-wrap">
                        <button type="button" class="more-submenu-item draft-load-btn" onclick="loadDraft('${draft.filename}')" title="${escapeHtml(displayTitle)}">
                            <div class="draft-title">${escapeHtml(displayTitle)}</div>
                            <div class="draft-date">${date}</div>
                        </button>
                        <button type="button" class="draft-delete-btn" onclick="deleteDraft('${draft.filename}', event)" title="${delDraftText}">×</button>
                    </div>`;
                }).join('');
            }
            draftsListLoaded = true;
        }
    } catch (error) {
        console.error('Ошибка загрузки черновиков:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('common.load_error', 'Ошибка загрузки') : 'Ошибка загрузки') + '</div>';
    }
}

async function loadDraft(filename) {
    try {
        const response = await fetch('get_drafts.php');
        const data = await response.json();

        if (data.success) {
            const draft = data.drafts.find(d => d.filename === filename);

            if (draft) {
                // Вставляем заголовок и контент
                document.getElementById('title').value = draft.title || '';

                let isMarkdown = false;
                let mdContent = '';
                if (draft.content && draft.content.includes('id="markdown-source"')) {
                    const match = draft.content.match(/id="markdown-source"\s+data-base64="([^"]*)"/);
                    if (match && match[1]) {
                        try {
                            mdContent = decodeURIComponent(escape(atob(match[1])));
                            isMarkdown = true;
                        } catch (e) {
                            console.error('Error decoding markdown', e);
                        }
                    }
                }

                if (isMarkdown) {
                    window.enableMarkdown = true;
                    document.body.classList.add('markdown-mode');
                    const enableMarkdownCheck = document.getElementById('enableMarkdown');
                    if (enableMarkdownCheck) enableMarkdownCheck.checked = true;

                    document.getElementById('content').value = mdContent;
                    const ve = document.getElementById('contentVisual');
                    if (ve) {
                        ve.contentEditable = 'true';
                        if (editorMode === 'visual') {
                            ve.innerHTML = parseMarkdownToHtml(mdContent);
                        }
                    }
                } else {
                    if (window.enableMarkdown) {
                        document.getElementById('content').value = draft.content || '';
                        const ve = document.getElementById('contentVisual');
                        if (ve) {
                            ve.contentEditable = 'true';
                            if (editorMode === 'visual') {
                                ve.innerHTML = parseMarkdownToHtml(draft.content || '');
                            }
                        }
                    } else {
                        window.enableMarkdown = false;
                        document.body.classList.remove('markdown-mode');
                        const enableMarkdownCheck = document.getElementById('enableMarkdown');
                        if (enableMarkdownCheck) enableMarkdownCheck.checked = false;

                        document.getElementById('content').value = draft.content || '';
                        const ve = document.getElementById('contentVisual');
                        if (ve) {
                            ve.contentEditable = 'true';
                            if (editorMode === 'visual') {
                                ve.innerHTML = draft.content || '';
                            }
                        }
                    }
                }

                // Закрываем меню
                const moreMenu = document.getElementById('moreMenuWrap');
                if (moreMenu) moreMenu.classList.remove('is-open');

                showNotification(window.t ? window.t('notifications.draft_loaded', 'Черновик загружен') : 'Черновик загружен', 'success');
            } else {
                showAlert(window.t ? window.t('notifications.draft_not_found', 'Черновик не найден') : 'Черновик не найден');
            }
        } else {
            showAlert(window.t ? window.t('notifications.draft_load_error', 'Ошибка загрузки черновика') : 'Ошибка загрузки черновика');
        }
    } catch (error) {
        console.error('Ошибка загрузки черновика:', error);
        showAlert(window.t ? window.t('notifications.draft_load_failed', 'Ошибка при загрузке черновика') : 'Ошибка при загрузке черновика');
    }
}

async function deleteDraft(filename, event) {
    event.stopPropagation();

    const result = await showConfirm(window.t ? window.t('notifications.draft_delete_confirm', 'Удалить этот черновик?') : 'Удалить этот черновик?');
    if (!result) return;

    try {
        const response = await fetch('delete_draft.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ filename: filename })
        });

        const data = await response.json();

        if (data.success) {
            showNotification(window.t ? window.t('notifications.draft_deleted', 'Черновик удален') : 'Черновик удален', 'success');
            draftsListLoaded = false;
            loadDraftsList(); // Перезагружаем список
        } else {
            showAlert((window.t ? window.t('common.error', 'Ошибка') : 'Ошибка') + ': ' + data.error);
        }
    } catch (error) {
        console.error('Ошибка удаления черновика:', error);
        showAlert(window.t ? window.t('notifications.draft_delete_error', 'Ошибка при удалении черновика') : 'Ошибка при удалении черновика');
    }
}

function toggleIncludesSubmenu(event) {
    event.stopPropagation();

    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');

    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });

    if (!isOpen) {
        button.classList.add('submenu-open');
        loadIncludesList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadIncludesList() {
    if (includesListLoaded) return;

    const submenu = document.getElementById('includesSubmenu');
    if (!submenu) return;

    try {
        const response = await fetch('get_includes.php');
        const data = await response.json();

        if (data.success) {
            if (data.files.length === 0) {
                submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('more_menu.no_includes', 'Нет сохраненных includes') : 'Нет сохраненных includes') + '</div>';
            } else {
                const delIncText = window.t ? window.t('more_menu.delete_include', 'Удалить include') : 'Удалить include';
                submenu.innerHTML = data.files.map(file =>
                    `<div class="draft-item-wrap">
                        <button type="button" class="more-submenu-item draft-load-btn" onclick="insertInclude('${file.name}')" title="${escapeHtml(file.displayName)}">${escapeHtml(file.displayName)}</button>
                        <button type="button" class="draft-delete-btn" onclick="deleteInclude('${file.name}', event)" title="${delIncText}">×</button>
                    </div>`
                ).join('');
            }
            includesListLoaded = true;
        }
    } catch (error) {
        console.error('Ошибка загрузки includes:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('common.load_error', 'Ошибка загрузки') : 'Ошибка загрузки') + '</div>';
    }
}

async function deleteInclude(filename, event) {
    if (event) event.stopPropagation();

    const result = await showConfirm(window.t ? window.t('notifications.include_delete_confirm', 'Удалить этот include?') : 'Удалить этот include?');
    if (!result) return;

    try {
        const response = await fetch('delete_include.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ filename: filename })
        });

        const data = await response.json();

        if (data.success) {
            showNotification(window.t ? window.t('notifications.include_deleted', 'Include успешно удален') : 'Include успешно удален', 'success');
            includesListLoaded = false;
            loadIncludesList();
        } else {
            showNotification((window.t ? window.t('common.error', 'Ошибка') : 'Ошибка') + ': ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка удаления include:', error);
        showNotification(window.t ? window.t('notifications.include_delete_error', 'Ошибка при удалении include') : 'Ошибка при удалении include', 'error');
    }
}

async function insertInclude(filename) {
    try {
        const response = await fetch('get_include_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ filename: filename })
        });

        const data = await response.json();

        if (data.success) {
            const ve = document.getElementById('contentVisual');
            const ta = document.getElementById('content');

            if (editorMode === 'visual') {
                insertHtmlAtCursor(data.content);
            } else {
                const start = ta.selectionStart;
                const end = ta.selectionEnd;
                const text = ta.value;
                ta.value = text.substring(0, start) + data.content + text.substring(end);
                ta.selectionStart = ta.selectionEnd = start + data.content.length;
            }

            // Закрываем меню
            const moreMenu = document.getElementById('moreMenuWrap');
            if (moreMenu) moreMenu.classList.remove('is-open');

            showNotification(window.t ? window.t('notifications.include_inserted', 'Include вставлен') : 'Include вставлен', 'success');
        } else {
            showNotification((window.t ? window.t('common.error', 'Ошибка') : 'Ошибка') + ': ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка вставки include:', error);
        showNotification(window.t ? window.t('notifications.include_insert_error', 'Ошибка при вставке include') : 'Ошибка при вставке include', 'error');
    }
}

// Функции для вставки ссылок на статьи
function toggleArticlesSubmenu(event) {
    event.stopPropagation();

    const button = event.currentTarget;
    const isOpen = button.classList.contains('submenu-open');

    document.querySelectorAll('.more-menu-item.has-submenu').forEach(btn => {
        if (btn !== button) {
            btn.classList.remove('submenu-open');
        }
    });

    if (!isOpen) {
        button.classList.add('submenu-open');
        loadArticlesList();
    } else {
        button.classList.remove('submenu-open');
    }
}

async function loadArticlesList() {
    const submenu = document.getElementById('articlesSubmenu');
    if (!submenu) return;

    try {
        const response = await fetch('serve_data.php?file=blog/posts-meta.json&t=' + Date.now());
        const articles = await response.json();

        if (!articles || articles.length === 0) {
            submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('more_menu.no_articles', 'Нет статей') : 'Нет статей') + '</div>';
        } else {
            submenu.innerHTML = articles.map(article =>
                `<button type="button" class="more-submenu-item" onclick="insertArticleLink('${article.filename}', '${article.title.replace(/'/g, "\\'")}')">
                    ${article.title}
                </button>`
            ).join('');
        }
    } catch (error) {
        console.error('Ошибка загрузки статей:', error);
        submenu.innerHTML = '<div class="more-submenu-empty">' + (window.t ? window.t('common.load_error', 'Ошибка загрузки') : 'Ошибка загрузки') + '</div>';
    }
}

function insertArticleLink(filename, title) {
    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');

    const linkHtml = `<a href="${filename}">${title}</a>`;

    if (editorMode === 'visual') {
        insertHtmlAtCursor(linkHtml);
    } else {
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const text = ta.value;
        ta.value = text.substring(0, start) + linkHtml + text.substring(end);
        ta.selectionStart = ta.selectionEnd = start + linkHtml.length;
    }

    // Закрываем меню
    const moreMenu = document.getElementById('moreMenuWrap');
    if (moreMenu) moreMenu.classList.remove('is-open');

    showNotification(window.t ? window.t('notifications.post_link_inserted', 'Ссылка на статью вставлена') : 'Ссылка на статью вставлена', 'success');
}
