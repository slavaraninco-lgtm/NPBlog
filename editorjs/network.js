function toggleManagePosts() {
    const managePanel = document.getElementById('managePosts');
    managePanel.classList.toggle('active');

    if (managePanel.classList.contains('active')) {
        if (typeof updateBlogSelectorUI === 'function' && window.allBlogPaths) {
            updateBlogSelectorUI(window.allBlogPaths, window.currentActiveBlogPath);
        }
        loadPosts();
    } else {
        // Очищаем поле поиска при закрытии панели
        const searchInput = document.getElementById('postsSearchInput');
        if (searchInput) {
            searchInput.value = '';
        }
    }
}

function loadPosts() {
    // Добавляем timestamp для предотвращения кэширования
    fetch('serve_data.php?file=blog/posts-meta.json&t=' + Date.now())
        .then(response => response.json())
        .then(posts => {
            const postsList = document.getElementById('postsList');
            if (!posts || posts.length === 0) {
                postsList.innerHTML = '<p class="manage-posts-empty">' + (window.t ? window.t('header.manage_posts_empty', 'Пока нет статей') : 'Пока нет статей') + '</p>';
                return;
            }
            const escapeHtml = function (str) {
                if (!str) return '';
                var div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            };

            // Сортируем статьи по ID в обратном порядке (новые первыми)
            const sortedPosts = [...posts].sort((a, b) => b.id - a.id);
            const btnEdit = window.t ? window.t('header.manage_posts_edit', 'Изменить') : 'Изменить';
            const btnExtra = window.t ? window.t('header.manage_posts_extra', 'Дополнительно') : 'Дополнительно';
            const btnDelete = window.t ? window.t('header.manage_posts_delete', 'Удалить') : 'Удалить';

            postsList.innerHTML = '<ul class="post-list">' +
                sortedPosts.map(post => `
                        <li class="post-item">
                            <div class="post-item-title">${escapeHtml(post.title)}</div>
                            <span class="post-item-date">${escapeHtml(post.date)}</span>
                            <div class="post-item-actions">
                                <button type="button" class="edit-btn" onclick="editPost(${post.id})">${btnEdit}</button>
                                <button type="button" class="additional-btn" onclick="openAdditionalSettings(${post.id}, '${escapeHtml(post.title)}')">${btnExtra}</button>
                                <button type="button" class="delete-btn" onclick="deletePost(${post.id})">${btnDelete}</button>
                            </div>
                        </li>
                    `).join('') +
                '</ul>';
        })
        .catch(error => {
            console.error('Ошибка загрузки статей:', error);
            const postsList = document.getElementById('postsList');
            postsList.innerHTML = '<p class="manage-posts-empty">' + (window.t ? window.t('header.manage_posts_empty', 'Пока нет статей') : 'Пока нет статей') + '</p>';
        });
}

function editPost(postId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

    fetch('get_post_content.php', {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({ id: postId })
    })
        .then(async response => {
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                const clean = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(clean || `Ошибка сервера (HTTP ${response.status})`);
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                document.getElementById('title').value = data.title;

                let isMarkdown = false;
                let mdContent = '';
                if (data.content && data.content.includes('id="markdown-source"')) {
                    const match = data.content.match(/id="markdown-source"\s+data-base64="([^"]*)"/);
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
                        document.getElementById('content').value = data.content || '';
                        const ve = document.getElementById('contentVisual');
                        if (ve) {
                            ve.contentEditable = 'true';
                            if (editorMode === 'visual') {
                                ve.innerHTML = parseMarkdownToHtml(data.content || '');
                            }
                        }
                    } else {
                        window.enableMarkdown = false;
                        document.body.classList.remove('markdown-mode');
                        const enableMarkdownCheck = document.getElementById('enableMarkdown');
                        if (enableMarkdownCheck) enableMarkdownCheck.checked = false;

                        let editedContent = formatHTML(data.content);
                        document.getElementById('content').value = editedContent;
                        const ve = document.getElementById('contentVisual');
                        if (ve) {
                            ve.contentEditable = 'true';
                            if (editorMode === 'visual') {
                                ve.innerHTML = editedContent;
                                wrapExistingEditorImages();
                                addColumnResizers();
                            }
                        }
                    }
                }

                if (editorMode === 'visual' && !window.enableMarkdown) {
                    // Убеждаемся что блоки кода имеют правильную высоту
                    setTimeout(() => {
                        const ve = document.getElementById('contentVisual');
                        if (!ve) return;
                        const codeBlocks = ve.querySelectorAll('.code-block');
                        codeBlocks.forEach(block => {
                            if (block.scrollHeight > 400) {
                                block.style.maxHeight = '400px';
                            } else {
                                block.style.maxHeight = 'none';
                            }
                        });
                    }, 100);
                }
                currentEditId = postId;
                const submitButton = document.getElementById('submitButton');
                submitButton.textContent = 'Сохранить изменения';
                submitButton.classList.add('editing');
                const floatingSaveBtn = document.getElementById('floatingSaveBtn');
                if (floatingSaveBtn) {
                    floatingSaveBtn.textContent = 'Сохранить изменения';
                    floatingSaveBtn.classList.add('editing');
                }
                toggleManagePosts();
                document.getElementById('blogForm').scrollIntoView();

                // Инициализируем историю с текущим состоянием
                clearHistory();
                saveToHistory();
            } else {
                showNotification('Ошибка: ' + (data.message || data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки статьи:', error);
            showNotification('Ошибка при загрузке статьи: ' + error.message, 'error');
        });
}

let deletePostId = null;

function filterPosts() {
    const searchInput = document.getElementById('postsSearchInput');
    const searchTerm = searchInput.value.toLowerCase().trim();
    const postItems = document.querySelectorAll('.post-item');

    let visibleCount = 0;

    postItems.forEach(item => {
        const title = item.querySelector('.post-item-title').textContent.toLowerCase();
        const date = item.querySelector('.post-item-date').textContent.toLowerCase();

        if (title.includes(searchTerm) || date.includes(searchTerm)) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    // Показываем сообщение если ничего не найдено
    const postsList = document.getElementById('postsList');
    let emptyMessage = postsList.querySelector('.search-empty-message');

    if (visibleCount === 0 && searchTerm !== '') {
        if (!emptyMessage) {
            emptyMessage = document.createElement('p');
            emptyMessage.className = 'manage-posts-empty search-empty-message';
            emptyMessage.textContent = 'Ничего не найдено';
            postsList.appendChild(emptyMessage);
        }
    } else if (emptyMessage) {
        emptyMessage.remove();
    }
}

function deletePost(postId) {
    deletePostId = postId;
    if (window.Modal) {
        Modal.open('#deleteConfirmOverlay');
    } else {
        const overlay = document.getElementById('deleteConfirmOverlay');
        if (overlay) overlay.classList.add('show');
    }
}

function closeDeleteConfirm() {
    if (window.Modal) {
        Modal.close('#deleteConfirmOverlay');
    } else {
        const overlay = document.getElementById('deleteConfirmOverlay');
        if (overlay) overlay.classList.remove('show');
    }
    deletePostId = null;
}

function confirmDelete() {
    if (!deletePostId) return;

    fetch('delete_post.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: deletePostId })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const message = data.renumbered
                    ? 'Статья удалена, нумерация обновлена'
                    : 'Статья успешно удалена';
                showNotification(message, 'success');
                loadPosts();
                closeDeleteConfirm();
            } else {
                showNotification('Ошибка при удалении статьи', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка удаления:', error);
            showNotification('Ошибка при удалении статьи', 'error');
        });
}

// Обработчик отправки формы
document.getElementById('modeVisualBtn').addEventListener('click', function () { setMode('visual'); });
document.getElementById('modeCodeBtn').addEventListener('click', function () { setMode('code'); });
setMode('visual');

function handleSubmit(e) {
    if (e) e.preventDefault();
    if (isSavingArticle) return;

    const titleInput = document.getElementById('title');
    const title = titleInput.value.trim();

    if (!title) {
        showNotification('Пожалуйста, введите заголовок статьи', 'error');
        titleInput.focus();
        return;
    }

    const ta = document.getElementById('content');
    const ve = document.getElementById('contentVisual');

    let content;
    if (window.enableMarkdown) {
        if (editorMode === 'visual') {
            ta.value = convertHtmlToMarkdown(ve.innerHTML);
        }
        const rawMarkdown = ta.value;
        const base64Markdown = btoa(unescape(encodeURIComponent(rawMarkdown)));
        content = parseMarkdownToHtml(rawMarkdown) + `\n<script type="text/markdown" id="markdown-source" data-base64="${base64Markdown}"></script>`;
    } else if (editorMode === 'visual') {
        // Очищаем контент от элементов интерфейса редактора
        content = cleanContentForSave(ve.innerHTML);
        ta.value = content;
    } else {
        content = ta.value;
    }

    const submitButton = document.getElementById('submitButton');
    const floatingSaveBtn = document.getElementById('floatingSaveBtn');

    isSavingArticle = true;
    if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Сохранение...';
    }
    if (floatingSaveBtn) {
        floatingSaveBtn.disabled = true;
        floatingSaveBtn.textContent = 'Сохранение...';
    }

    const endpoint = currentEditId ? 'update_post.php' : 'save_post.php';
    const data = { title: title, content: content };
    if (currentEditId) { data.id = currentEditId; }
    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then(async (response) => {
            let payload;
            try { payload = await response.json(); } catch (_) { payload = null; }
            if (!response.ok || (payload && payload.success === false)) {
                let errorMsg = 'Ошибка сервера';
                if (payload) {
                    if (payload.message) {
                        errorMsg = payload.message;
                    } else if (payload.error) {
                        const errorMap = {
                            'filesystem_error': 'Ошибка файловой системы: проверьте путь к папке data и права доступа на запись',
                            'permission_denied': 'Нет прав на запись к указанной папке блога',
                            'csrf_error': 'Сессия устарела или недействителен CSRF-токен. Обновите страницу.',
                            'security_violation': 'Ошибка безопасности: недопустимый путь к файлу',
                            'unauthorized': 'Требуется авторизация'
                        };
                        errorMsg = errorMap[payload.error] || payload.error;
                    }
                } else {
                    errorMsg = `Ошибка сервера (HTTP ${response.status})`;
                }
                throw new Error(errorMsg);
            }
            showNotification(
                currentEditId ? 'Статья успешно обновлена!' : 'Статья успешно добавлена!',
                'success'
            );

            // Очищаем локальный черновик и сбрасываем признак несохраненных изменений
            clearLocalDraft();

            // Очищаем форму
            document.getElementById('blogForm').reset();

            // Очищаем визуальный редактор
            const ve = document.getElementById('contentVisual');
            if (ve) {
                ve.innerHTML = '';
            }

            // Очищаем текстовое поле
            const ta = document.getElementById('content');
            if (ta) {
                ta.value = '';
            }

            // Обновляем список статей
            loadPosts();

            currentEditId = null;
            if (submitButton) {
                submitButton.textContent = 'Сохранить';
                submitButton.classList.remove('editing');
            }
            if (floatingSaveBtn) {
                floatingSaveBtn.textContent = 'Сохранить';
                floatingSaveBtn.classList.remove('editing');
            }

            // Очищаем историю
            clearHistory();
        })
        .catch((err) => {
            const msg = err && err.message ? err.message : 'Ошибка при сохранении статьи';
            showNotification(msg, 'error');
        })
        .finally(() => {
            isSavingArticle = false;
            if (submitButton) {
                submitButton.disabled = false;
                if (currentEditId) {
                    submitButton.textContent = 'Сохранить изменения';
                } else {
                    submitButton.textContent = 'Сохранить';
                }
            }
            if (floatingSaveBtn) {
                floatingSaveBtn.disabled = false;
                if (currentEditId) {
                    floatingSaveBtn.textContent = 'Сохранить изменения';
                } else {
                    floatingSaveBtn.textContent = 'Сохранить';
                }
            }
        });
}

document.getElementById('blogForm').addEventListener('submit', handleSubmit);
document.getElementById('submitButton').addEventListener('click', handleSubmit);

// Обработчики изменения размера
document.getElementById('imageSize').addEventListener('change', function (e) {
    const customInputs = document.getElementById('customSizeInputs');
    customInputs.style.display = e.target.value === 'custom' ? 'flex' : 'none';

    if (e.target.value !== 'custom') {
        document.getElementById('customWidth').value = '';
        document.getElementById('customHeight').value = '';
        document.getElementById('widthUnit').value = 'px';
        document.getElementById('heightUnit').value = 'px';
    }
});

const customFontSizeInput = document.getElementById('customFontSize');
if (customFontSizeInput) {
    customFontSizeInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            setCustomFontSize();
        }
    });
}
const fontSizeCustomMainInput = document.getElementById('fontSizeCustomMain');
if (fontSizeCustomMainInput) {
    fontSizeCustomMainInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyCustomFontSize('fontSizeWrapMain');
        }
    });
}