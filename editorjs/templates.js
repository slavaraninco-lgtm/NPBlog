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
        const isMain = tpl.name === 'main';
        const isDefault = tpl.name === defaultTemplateName;

        const card = document.createElement('div');
        card.className = 'template-card';
        card.onclick = () => openTemplateDetails(tpl.name);

        // Build badges
        let badges = '';
        if (isMain) {
            const badgeMainText = window.t ? window.t('modals.tpl_badge_main', 'Главный') : 'Главный';
            badges += `<span class="template-badge template-badge-main">${badgeMainText}</span>`;
        }
        if (isDefault) {
            const badgeDefText = window.t ? window.t('modals.tpl_badge_default', 'По умолчанию') : 'По умолчанию';
            badges += `<span class="template-badge template-badge-default">${badgeDefText}</span>`;
        }

        // Generate miniature preview HTML
        const previewHtml = getTemplatePreviewHtml(tpl.code, true);

        const cardTitle = (isMain && window.t) ? window.t('modals.tpl_default_name', tpl.title) : tpl.title;
        const cardDesc = (isMain && window.t) ? window.t('settings.tpl_default_desc', tpl.description) : (tpl.description || (window.t ? window.t('common.no_description', 'Нет описания') : 'Нет описания'));

        card.innerHTML = `
                <div class="template-preview-card-wrap">
                    <iframe class="template-preview-iframe" srcdoc="${escapeHtml(previewHtml)}" tabindex="-1" aria-hidden="true"></iframe>
                    <div style="position: absolute; top:0; left:0; right:0; bottom:0; background:transparent; z-index:2;"></div>
                </div>
                <div class="template-card-content">
                    <div class="template-card-header-wrap">
                        <div class="template-card-title">${escapeHtml(cardTitle)}</div>
                        ${badges ? `<div class="template-card-badges">${badges}</div>` : ''}
                    </div>
                    <div class="template-card-desc">
                        ${escapeHtml(cardDesc)}
                    </div>
                </div>
            `;
        grid.appendChild(card);
    });
}

function getTemplatePreviewHtml(templateCode, isThumbnail = false) {
    let mockContent;
    if (isThumbnail) {
        mockContent = `
            <p>Это пример текста статьи для предпросмотра шаблона оформления NPBlog. Здесь можно оценить шрифты, интервалы и структуру элементов.</p>
            <h2>Подзаголовок статьи</h2>
            <div class="blog-image-align-wrap" style="text-align:center; margin: 10px 0;">
                <div class="blog-image-wrap">
                    <div style="background:#4CAF50;color:white;padding:14px 20px;border-radius:8px;font-weight:bold;font-size:13px;display:inline-block;min-width:180px;">Пример картинки / медиа</div>
                    <span class="caption" style="display:block;margin-top:4px;font-size:11px;opacity:0.7;">Подпись к медиа-файлу</span>
                </div>
            </div>
        `;
    } else {
        mockContent = `
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
    }

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

    // Sync theme with editor
    const currentTheme = document.documentElement.getAttribute('data-theme') || (localStorage.getItem('theme') === 'dark' ? 'dark' : 'light');
    const isAmoled = document.documentElement.getAttribute('data-amoled') === 'true';

    let headInject = `
    <script>
        try {
            localStorage.setItem('theme', '${currentTheme}');
            document.documentElement.setAttribute('data-theme', '${currentTheme}');
            ${isAmoled ? "document.documentElement.setAttribute('data-amoled', 'true');" : "document.documentElement.removeAttribute('data-amoled');"}
        } catch(e){}
    </script>`;

    if (isThumbnail) {
        headInject += `
    <style id="thumbnail-preview-styles">
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            overflow: hidden !important;
            box-sizing: border-box !important;
            width: 100% !important;
        }
        body {
            max-width: 100% !important;
            padding: 16px 22px 18px 22px !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 100% !important;
        }
        h1 {
            font-size: 1.85em !important;
            margin-bottom: 8px !important;
            padding-bottom: 8px !important;
            line-height: 1.25 !important;
        }
        .date {
            margin-bottom: 12px !important;
            font-size: 0.85em !important;
        }
        .content {
            margin-top: 6px !important;
            font-size: 0.95em !important;
            line-height: 1.45 !important;
            flex: 1 0 auto !important;
        }
        .content p {
            margin-bottom: 8px !important;
        }
        h2 {
            font-size: 1.3em !important;
            margin: 10px 0 6px 0 !important;
        }
        .back-link {
            margin-top: 14px !important;
            padding: 6px 14px !important;
            font-size: 12px !important;
            display: inline-block !important;
            align-self: flex-start !important;
        }
        .powered-by {
            position: static !important;
            margin-top: 14px !important;
            padding-top: 6px !important;
            font-size: 11px !important;
            opacity: 0.55 !important;
            display: block !important;
        }
        .theme-toggle {
            position: absolute !important;
            top: 16px !important;
            right: 22px !important;
            padding: 6px 14px !important;
            font-size: 12px !important;
        }
    </style>`;
    }

    // Inject base tag and head styles/scripts
    if (!preview.includes('<base ') && preview.includes('<head>')) {
        preview = preview.replace('<head>', '<head>\n    <base href="data/blog/">' + headInject);
    } else if (!preview.includes('<base ')) {
        preview = '<base href="data/blog/">' + headInject + preview;
    } else if (preview.includes('</head>')) {
        preview = preview.replace('</head>', headInject + '\n</head>');
    } else {
        preview = headInject + preview;
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

    const prefix = window.t ? window.t('modals.tpl_details_title_prefix', 'Детали шаблона: ') : 'Детали шаблона: ';
    const title = (tpl.name === 'main' && window.t) ? window.t('modals.tpl_default_name', tpl.title) : tpl.title;
    document.getElementById('detailsTemplateTitle').textContent = `${prefix}${title}`;
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
        container.innerHTML = '<div style="text-align: center; opacity: 0.6; padding: 10px;">' + (window.t ? window.t('modals.tpl_no_posts', 'Нет статей') : 'Нет статей') + '</div>';
        return;
    }

    postsList.forEach(post => {
        const item = document.createElement('div');
        item.className = 'template-post-item';
        item.setAttribute('data-title', post.title.toLowerCase());

        // Check if this post currently uses this template
        const isAssigned = postTemplatesMeta[post.id] === currentTemplateName;
        const dateText = window.t ? window.t('modals.tpl_post_date', `Дата: ${post.date}`, { date: post.date }) : `Дата: ${post.date}`;
        const appliedBadge = isAssigned ? `• <span style="color:#10b981; font-weight:600;">${window.t ? window.t('modals.tpl_already_applied', 'Уже применен') : 'Уже применен'}</span>` : '';
        const btnText = isAssigned 
            ? (window.t ? window.t('modals.tpl_reapply', 'Переприменить') : 'Переприменить') 
            : (window.t ? window.t('modals.tpl_select', 'Выбрать') : 'Выбрать');

        item.innerHTML = `
                <div style="flex: 1; min-width: 0; padding-right: 10px;">
                    <div style="font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text-color);">${post.title}</div>
                    <div style="font-size: 11px; opacity: 0.6; margin-top: 2px;">${dateText} ${appliedBadge}</div>
                </div>
                <button type="button" onclick="applyTemplateToPost(${post.id})" style="padding: 6px 12px; background: ${isAssigned ? '#10b981' : 'var(--primary-color, #4CAF50)'}; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 500;">
                    ${btnText}
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
    const t = window.t || function(k, d) { return d; };
    const info = (t('modals.tpl_req_title', 'Обязательные плейсхолдеры в шаблоне:')) + '\n\n' +
        `{{TITLE}} - ` + t('modals.tpl_ph_title_desc', 'заголовок статьи') + '\n' +
        `{{DATE}} - ` + t('modals.tpl_ph_date_desc', 'дата публикации') + '\n' +
        `{{POST_ID}} - ` + t('modals.tpl_ph_post_id_desc', 'ID статьи') + '\n' +
        `{{CONTENT}} - ` + t('modals.tpl_ph_content_desc', 'основной контент статьи') + '\n' +
        `{{META_TAGS}} - ` + t('modals.tpl_ph_meta_desc', 'SEO-метатеги') + '\n' +
        `{{CUSTOM_FONTS}} - ` + t('modals.tpl_ph_fonts_desc', 'блок подключения шрифтов') + '\n' +
        `{{BODY_STYLE}} - ` + t('modals.tpl_ph_body_desc', 'стили фона и цвета текста') + '\n' +
        `{{CONTENT_WRAPPER_START}} - ` + t('modals.tpl_ph_wrap_start_desc', 'начало обертки контента') + '\n' +
        `{{CONTENT_WRAPPER_END}} - ` + t('modals.tpl_ph_wrap_end_desc', 'конец обертки контента');
    showAlert(info, t('modals.tpl_tags_title', 'Теги шаблонов'));
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
