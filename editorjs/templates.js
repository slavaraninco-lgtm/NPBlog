// --- Менеджер шаблонов ---
let templatesList = [];
let postsList = [];
let currentTemplateName = null;
let postTemplatesMeta = {};
let defaultTemplateName = 'main';

function openTemplateManager() {
    fetch('get_templates.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                templatesList = data.templates;
                postsList = data.posts;
                defaultTemplateName = data.default;
                postTemplatesMeta = data.post_templates || {};
                renderTemplatesGrid();
                if (window.Modal) {
                    Modal.open('#templateManagerDialog');
                } else {
                    document.getElementById('templateManagerDialog').style.display = 'block';
                }
            } else {
                showNotification('Не удалось загрузить шаблоны: ' + data.error, 'error');
            }
        })
        .catch(err => {
            showNotification('Ошибка загрузки шаблонов', 'error');
        });
}

function closeTemplateManager() {
    if (window.Modal) {
        Modal.close('#templateManagerDialog');
    } else {
        document.getElementById('templateManagerDialog').style.display = 'none';
    }
}

function renderTemplatesGrid() {
    const grid = document.getElementById('templatesGrid');
    grid.innerHTML = '';

    templatesList.forEach(tpl => {
        const card = document.createElement('div');
        card.className = 'template-card';
        card.onclick = () => openTemplateDetails(tpl.name);

        // Build badges
        let badges = '';
        if (tpl.name === 'main') {
            badges += `<span style="background: #3b82f6; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left: 4px;">Главный</span>`;
        }
        if (tpl.name === defaultTemplateName) {
            badges += `<span style="background: #10b981; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; margin-left: 4px;">По умолчанию</span>`;
        }

        // Generate miniature preview HTML
        const previewHtml = getTemplatePreviewHtml(tpl.code);

        card.innerHTML = `
                <div class="template-preview-card-wrap">
                    <iframe class="template-preview-iframe" srcdoc="${escapeHtml(previewHtml)}"></iframe>
                    <div style="position: absolute; top:0; left:0; right:0; bottom:0; background:transparent; z-index:2;"></div>
                </div>
                <div style="padding: 12px; flex: 1; display: flex; flex-direction: column; gap: 6px;">
                    <div style="font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                        <span style="color: var(--text-color);">${tpl.title}</span>
                        <div style="display: flex; gap: 2px;">${badges}</div>
                    </div>
                    <div style="font-size: 12px; opacity: 0.7; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 34px; color: var(--text-color);">
                        ${tpl.description || 'Нет описания'}
                    </div>
                </div>
            `;
        grid.appendChild(card);
    });
}

function getTemplatePreviewHtml(templateCode) {
    let mockContent = `
            <p>Это пример текста статьи для предпросмотра шаблона. Здесь вы можете увидеть, как будут выглядеть ваши абзацы, ссылки, списки и другие элементы.</p>
            <h2>Подзаголовок статьи</h2>
            <p>А здесь ссылка на <a href="#">какой-то внешний ресурс</a>.</p>
            <ul>
                <li>Первый пункт списка</li>
                <li>Второй пункт списка</li>
            </ul>
            <div class="blog-image-align-wrap" style="text-align:center">
                <div class="blog-image-wrap">
                    <div style="background:#4CAF50;color:white;padding:40px;border-radius:8px;font-weight:bold;">Пример картинки / медиа</div>
                    <span class="caption">Подпись к медиа-файлу</span>
                </div>
            </div>
        `;
    let preview = templateCode
        .replace(/\{\{TITLE\}\}/g, 'Пример заголовка статьи')
        .replace(/\{\{DATE\}\}/g, '20.06.2026 12:00')
        .replace(/\{\{POST_ID\}\}/g, '1')
        .replace(/\{\{META_TAGS\}\}/g, '')
        .replace(/\{\{CUSTOM_FONTS\}\}/g, '')
        .replace(/\{\{BODY_STYLE\}\}/g, '')
        .replace(/\{\{CONTENT_WRAPPER_START\}\}/g, '')
        .replace(/\{\{CONTENT_WRAPPER_END\}\}/g, '')
        .replace(/\{\{CONTENT\}\}/g, mockContent);

    // Inject <base href="data/blog/"> inside <head> if present to resolve relative URLs of CSS and JS assets correctly
    if (!preview.includes('<base ') && preview.includes('<head>')) {
        preview = preview.replace('<head>', '<head>\n    <base href="data/blog/">');
    } else if (!preview.includes('<base ')) {
        preview = '<base href="data/blog/">' + preview;
    }

    return preview;
}

function escapeHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function triggerTemplateUpload() {
    document.getElementById('templateFileInput').click();
}

async function handleTemplateUpload(input) {
    if (!input.files || input.files.length === 0) return;

    const files = Array.from(input.files);
    input.value = '';

    let successCount = 0;
    let errors = [];

    for (const file of files) {
        const formData = new FormData();
        formData.append('template_file', file);

        try {
            const res = await fetch('upload_template.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (data.success) {
                successCount++;
            } else {
                if (data.missing) {
                    errors.push(`Файл ${file.name}: не хватает плейсхолдеров: ${data.missing.join(', ')}`);
                } else {
                    errors.push(`Файл ${file.name}: ${data.error}`);
                }
            }
        } catch (err) {
            errors.push(`Файл ${file.name}: ошибка сети`);
        }
    }

    if (successCount > 0) {
        showNotification(`Успешно загружено шаблонов: ${successCount}`, 'success');
        openTemplateManager();
    }

    if (errors.length > 0) {
        errors.forEach(err => {
            showNotification(err, 'error');
        });
    }
}

function openTemplateDetails(name) {
    currentTemplateName = name;
    const tpl = templatesList.find(t => t.name === name);
    if (!tpl) return;

    document.getElementById('detailsTemplateTitle').textContent = `Детали шаблона: ${tpl.title}`;
    document.getElementById('detailsTemplateNameInput').value = tpl.title;
    document.getElementById('detailsTemplateNameInput').disabled = tpl.is_system;
    document.getElementById('detailsTemplateDescriptionInput').value = tpl.description || '';
    document.getElementById('detailsTemplateCodeInput').value = tpl.code;

    const deleteBtn = document.getElementById('deleteTemplateBtn');
    if (tpl.is_system || tpl.name === 'main' || tpl.name === defaultTemplateName) {
        deleteBtn.style.display = 'none';
    } else {
        deleteBtn.style.display = 'block';
    }

    updateTemplateLivePreview();
    if (window.Modal) {
        Modal.open('#templateDetailsDialog');
    } else {
        document.getElementById('templateDetailsDialog').style.display = 'block';
    }
}

// Live update live preview inside text area
let previewDebounce = null;
function updateTemplateLivePreview() {
    if (previewDebounce) clearTimeout(previewDebounce);
    previewDebounce = setTimeout(() => {
        const code = document.getElementById('detailsTemplateCodeInput').value;
        const previewHtml = getTemplatePreviewHtml(code);
        const iframe = document.getElementById('templatePreviewIframe');
        iframe.srcdoc = previewHtml;
    }, 300);
}

function closeTemplateDetails() {
    if (window.Modal) {
        Modal.close('#templateDetailsDialog');
    } else {
        document.getElementById('templateDetailsDialog').style.display = 'none';
    }
    const menu = document.getElementById('saveTemplateDropdownMenu');
    if (menu) menu.style.display = 'none';
}

function toggleSaveTemplateDropdown() {
    const menu = document.getElementById('saveTemplateDropdownMenu');
    const isVisible = menu.style.display === 'flex';
    menu.style.display = isVisible ? 'none' : 'flex';
}

// Hide dropdown when clicking outside
document.addEventListener('click', function (e) {
    const btn = document.getElementById('saveTemplateDropdownBtn');
    const menu = document.getElementById('saveTemplateDropdownMenu');
    if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});

function saveTemplateData() {
    const title = document.getElementById('detailsTemplateNameInput').value.trim();
    const description = document.getElementById('detailsTemplateDescriptionInput').value.trim();
    const code = document.getElementById('detailsTemplateCodeInput').value;

    if (title === '') {
        showNotification('Введите название шаблона', 'warning');
        return Promise.reject('Empty title');
    }

    return fetch('save_template.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: currentTemplateName,
            title: title,
            description: description,
            code: code
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                return true;
            } else {
                if (data.missing) {
                    showNotification('В коде отсутствуют обязательные плейсхолдеры: ' + data.missing.join(', '), 'error');
                } else {
                    showNotification('Ошибка сохранения: ' + data.error, 'error');
                }
                throw new Error(data.error);
            }
        });
}

function saveAndApplyTemplateToAll() {
    saveTemplateData()
        .then(() => {
            return fetch('apply_template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    template_name: currentTemplateName,
                    mode: 'default'
                })
            });
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                closeTemplateDetails();
                openTemplateManager(); // Refresh grid
            } else {
                showNotification('Ошибка применения шаблона: ' + data.error, 'error');
            }
        })
        .catch(err => {
            console.error(err);
        });
}

function showApplyToSpecificPostList() {
    saveTemplateData()
        .then(() => {
            document.getElementById('templatePostSearchInput').value = '';
            renderTemplatePostList();
            if (window.Modal) {
                Modal.open('#applyToPostModal');
            } else {
                document.getElementById('applyToPostModal').style.display = 'block';
            }
        })
        .catch(err => {
            console.error(err);
        });
}

function closeApplyToPostModal() {
    if (window.Modal) {
        Modal.close('#applyToPostModal');
    } else {
        document.getElementById('applyToPostModal').style.display = 'none';
    }
}

function renderTemplatePostList() {
    const container = document.getElementById('templatePostList');
    container.innerHTML = '';

    if (postsList.length === 0) {
        container.innerHTML = '<div style="text-align: center; opacity: 0.6; padding: 10px;">Нет статей</div>';
        return;
    }

    postsList.forEach(post => {
        const item = document.createElement('div');
        item.className = 'template-post-item';
        item.setAttribute('data-title', post.title.toLowerCase());

        // Check if this post currently uses this template
        const isAssigned = postTemplatesMeta[post.id] === currentTemplateName;

        item.innerHTML = `
                <div style="flex: 1; min-width: 0; padding-right: 10px;">
                    <div style="font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-color);">${post.title}</div>
                    <div style="font-size: 11px; opacity: 0.6; margin-top: 2px;">Дата: ${post.date} ${isAssigned ? '• <span style="color:#10b981; font-weight:600;">Уже применен</span>' : ''}</div>
                </div>
                <button type="button" onclick="applyTemplateToPost(${post.id})" style="padding: 6px 12px; background: ${isAssigned ? '#10b981' : 'var(--primary-color, #4CAF50)'}; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 500;">
                    ${isAssigned ? 'Переприменить' : 'Выбрать'}
                </button>
            `;
        container.appendChild(item);
    });
}

function filterTemplatePosts() {
    const query = document.getElementById('templatePostSearchInput').value.toLowerCase().trim();
    const items = document.querySelectorAll('.template-post-item');

    items.forEach(item => {
        const title = item.getAttribute('data-title');
        if (title.indexOf(query) !== -1) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function applyTemplateToPost(postId) {
    fetch('apply_template.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            template_name: currentTemplateName,
            mode: 'post',
            post_id: postId
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                closeApplyToPostModal();
                closeTemplateDetails();
                openTemplateManager(); // Refresh grid
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        })
        .catch(err => {
            showNotification('Ошибка сети при применении шаблона', 'error');
        });
}

function deleteCurrentTemplate() {
    showConfirm('Вы действительно хотите удалить этот шаблон?').then(confirmed => {
        if (!confirmed) return;

        fetch('delete_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: currentTemplateName
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification('Шаблон удален', 'success');
                    closeTemplateDetails();
                    openTemplateManager(); // Refresh grid
                } else {
                    showNotification('Ошибка при удалении шаблона: ' + data.error, 'error');
                }
            })
            .catch(err => {
                showNotification('Ошибка сети при удалении шаблона', 'error');
            });
    });
}

function showTemplatePlaceholdersInfo(e) {
    e.preventDefault();
    const info = `Обязательные плейсхолдеры в шаблоне:\n\n` +
        `{{TITLE}} - заголовок статьи\n` +
        `{{DATE}} - дата публикации\n` +
        `{{POST_ID}} - ID статьи\n` +
        `{{CONTENT}} - основной контент\n` +
        `{{META_TAGS}} - метатеги SEO\n` +
        `{{CUSTOM_FONTS}} - блок шрифтов\n` +
        `{{BODY_STYLE}} - стили тела документа\n` +
        `{{CONTENT_WRAPPER_START}} - начало обертки контента\n` +
        `{{CONTENT_WRAPPER_END}} - конец обертки контента`;
    showAlert(info, 'Теги шаблонов');
}

function showTemplateInstructions() {
    if (window.Modal) {
        Modal.open('#templateInstructionsDialog');
    } else {
        document.getElementById('templateInstructionsDialog').style.display = 'block';
    }
}

function closeTemplateInstructions() {
    if (window.Modal) {
        Modal.close('#templateInstructionsDialog');
    } else {
        document.getElementById('templateInstructionsDialog').style.display = 'none';
    }
}

// Export functions to window scope
window.openTemplateManager = openTemplateManager;
window.closeTemplateManager = closeTemplateManager;
window.triggerTemplateUpload = triggerTemplateUpload;
window.handleTemplateUpload = handleTemplateUpload;
window.openTemplateDetails = openTemplateDetails;
window.closeTemplateDetails = closeTemplateDetails;
window.updateTemplateLivePreview = updateTemplateLivePreview;
window.toggleSaveTemplateDropdown = toggleSaveTemplateDropdown;
window.saveAndApplyTemplateToAll = saveAndApplyTemplateToAll;
window.showApplyToSpecificPostList = showApplyToSpecificPostList;
window.closeApplyToPostModal = closeApplyToPostModal;
window.filterTemplatePosts = filterTemplatePosts;
window.applyTemplateToPost = applyTemplateToPost;
window.deleteCurrentTemplate = deleteCurrentTemplate;
window.showTemplatePlaceholdersInfo = showTemplatePlaceholdersInfo;
window.showTemplateInstructions = showTemplateInstructions;
window.closeTemplateInstructions = closeTemplateInstructions;
