// ——— Общие UI-хелперы для менеджеров бэкапов и автосохранений ———
(function (window, document) {
    'use strict';

    const ICONS = {
        archive: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"></path><path d="M1 3h22v5H1z"></path><path d="M10 12h4"></path></svg>',
        history: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M12 7v5l3 2"></path></svg>',
        search: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>',
        fileText: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>',
        fileDeleted: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line></svg>',
        chevronDown: '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>',
        clock: '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
        eye: '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        restore: '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>',
        trash: '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>'
    };

    /**
     * Локализация с безопасной подстановкой параметров и фоллбэком.
     *
     * @param {string} key Ключ перевода
     * @param {string} fallback Текст по умолчанию
     * @param {Object} [params] Параметры подстановки
     * @returns {string}
     */
    function translate(key, fallback, params) {
        if (typeof window.t === 'function') {
            return window.t(key, fallback, params);
        }
        let text = fallback || '';
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (k) {
                text = text.replace(new RegExp('\\{' + k + '\\}', 'g'), params[k]);
            });
        }
        return text;
    }

    /**
     * Безопасное HTML-экранирование пользовательских строк.
     *
     * @param {*} text Исходное значение
     * @returns {string}
     */
    function safeEscapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    /**
     * Извлекает короткое текстовое превью из HTML или base64-markdown без запуска скриптов и загрузки медиа.
     *
     * @param {string} rawContent Содержимое снимка
     * @param {number} [maxLen=110] Максимальная длина превью
     * @returns {string}
     */
    function extractPlainPreview(rawContent, maxLen) {
        const limit = maxLen || 110;
        if (!rawContent || typeof rawContent !== 'string') return '';

        let source = rawContent;
        // Декодируем обёртку Markdown-снимка: <div id="markdown-source" data-base64="...">
        if (source.indexOf('id="markdown-source"') !== -1) {
            const match = source.match(/id="markdown-source"\s+data-base64="([^"]*)"/);
            if (match && match[1]) {
                try {
                    source = decodeURIComponent(escape(atob(match[1])));
                } catch (e) {
                    // Оставляем исходную строку, если base64 повреждён
                }
            }
        }

        let plain = '';
        if (typeof DOMParser === 'function') {
            try {
                const doc = new DOMParser().parseFromString(source, 'text/html');
                plain = (doc && doc.body && doc.body.textContent) ? doc.body.textContent : '';
            } catch (e) {
                plain = source.replace(/<[^>]*>/g, ' ');
            }
        } else {
            plain = source.replace(/<[^>]*>/g, ' ');
        }

        // Убираем служебную Markdown-разметку в превью и лишние пробелы
        plain = plain
            .replace(/[#*_`~>\[\]]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        if (!plain) return '';
        if (plain.length > limit) {
            return plain.slice(0, limit).trimEnd() + '…';
        }
        return plain;
    }

    /**
     * Формирует HTML блока состояния (загрузка / пусто / ошибка / не найдено) без смайлов.
     *
     * @param {Object} opts Параметры состояния
     * @param {string} [opts.icon] Ключ иконки из ICONS
     * @param {string} opts.title Заголовок
     * @param {string} [opts.subtitle] Пояснение
     * @param {boolean} [opts.isLoading] Показать спиннер вместо иконки
     * @param {boolean} [opts.isError] Выделить состояние ошибки
     * @returns {string}
     */
    function renderStateBox(opts) {
        const iconHtml = opts.isLoading
            ? '<div class="manager-spinner" aria-hidden="true"></div>'
            : '<div class="manager-state-icon' + (opts.isError ? ' is-error' : '') + '" aria-hidden="true">' + (ICONS[opts.icon] || ICONS.archive) + '</div>';
        const subtitleHtml = opts.subtitle
            ? '<p class="manager-state-hint">' + safeEscapeHtml(opts.subtitle) + '</p>'
            : '';
        return '<div class="manager-state-box">' + iconHtml + '<p class="manager-state-title">' + safeEscapeHtml(opts.title) + '</p>' + subtitleHtml + '</div>';
    }

    /**
     * Синхронизирует текст и состояние кнопки «Развернуть все / Свернуть все».
     *
     * @param {HTMLElement|null} toggleAllBtn Кнопка переключения
     * @param {HTMLElement|null} listEl Контейнер групп
     */
    function syncToggleAllButton(toggleAllBtn, listEl) {
        if (!toggleAllBtn || !listEl) return;
        const visibleGroups = Array.from(listEl.querySelectorAll('.backup-post-group')).filter(function (g) {
            return g.style.display !== 'none';
        });
        if (visibleGroups.length === 0) {
            toggleAllBtn.disabled = true;
            return;
        }
        toggleAllBtn.disabled = false;
        const allExpanded = visibleGroups.every(function (g) {
            return g.classList.contains('expanded');
        });
        toggleAllBtn.textContent = allExpanded
            ? translate('modals.manager_collapse_all', 'Свернуть все')
            : translate('modals.manager_expand_all', 'Развернуть все');
        toggleAllBtn.setAttribute('aria-pressed', allExpanded ? 'true' : 'false');
    }

    /**
     * Переключает раскрытие карточки-группы и обновляет aria-expanded.
     *
     * @param {HTMLElement|null} groupEl Элемент .backup-post-group
     * @param {HTMLElement|null} [toggleAllBtn] Кнопка массового раскрытия
     * @param {HTMLElement|null} [listEl] Родительский контейнер групп
     */
    function toggleGroupElement(groupEl, toggleAllBtn, listEl) {
        if (!groupEl) return;
        const isExpanded = groupEl.classList.toggle('expanded');
        const headerBtn = groupEl.querySelector('.backup-post-header');
        if (headerBtn) {
            headerBtn.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        }
        syncToggleAllButton(toggleAllBtn, listEl || groupEl.parentElement);
    }

    /**
     * Подключает верхнюю панель поиска, счётчика и кнопки «Развернуть/Свернуть все».
     *
     * @param {Object} cfg Конфигурация панели
     */
    function initToolbar(cfg) {
        const toolbar = document.getElementById(cfg.toolbarId);
        const searchInput = document.getElementById(cfg.searchInputId);
        const searchClear = document.getElementById(cfg.searchClearId);
        const toggleAllBtn = document.getElementById(cfg.toggleAllBtnId);
        const statsEl = document.getElementById(cfg.statsId);
        const listEl = typeof cfg.listEl === 'string' ? document.getElementById(cfg.listEl) : cfg.listEl;
        const emptySearchEl = typeof cfg.emptySearchEl === 'string' ? document.getElementById(cfg.emptySearchEl) : cfg.emptySearchEl;

        if (!toolbar) return;

        if (!cfg.visible) {
            toolbar.style.display = 'none';
            return;
        }

        toolbar.style.display = 'flex';
        if (statsEl) {
            const articlesText = translate('modals.manager_articles_count', 'Статей: {count}', { count: cfg.groupCount });
            const itemsText = translate(cfg.itemCountKey, cfg.itemCountFallback, { count: cfg.itemCount });
            statsEl.textContent = articlesText + ' • ' + itemsText;
        }

        function applyFilter() {
            if (!listEl) return;
            const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
            if (searchClear) {
                searchClear.style.display = query.length > 0 ? 'inline-flex' : 'none';
            }

            let visibleCount = 0;
            listEl.querySelectorAll('.backup-post-group').forEach(function (group) {
                const hay = (group.getAttribute('data-search-text') || '').toLowerCase();
                const matched = !query || hay.indexOf(query) !== -1;
                group.style.display = matched ? '' : 'none';
                if (matched) visibleCount++;
            });

            if (emptySearchEl) {
                emptySearchEl.style.display = visibleCount === 0 ? 'flex' : 'none';
            }
            syncToggleAllButton(toggleAllBtn, listEl);
        }

        if (searchInput) {
            searchInput.value = '';
            searchInput.oninput = applyFilter;
        }
        if (searchClear) {
            searchClear.style.display = 'none';
            searchClear.onclick = function () {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                applyFilter();
            };
        }
        if (toggleAllBtn) {
            toggleAllBtn.onclick = function () {
                if (!listEl) return;
                const visibleGroups = Array.from(listEl.querySelectorAll('.backup-post-group')).filter(function (g) {
                    return g.style.display !== 'none';
                });
                const expand = visibleGroups.some(function (g) {
                    return !g.classList.contains('expanded');
                });
                visibleGroups.forEach(function (group) {
                    group.classList.toggle('expanded', expand);
                    const headerBtn = group.querySelector('.backup-post-header');
                    if (headerBtn) {
                        headerBtn.setAttribute('aria-expanded', expand ? 'true' : 'false');
                    }
                });
                syncToggleAllButton(toggleAllBtn, listEl);
            };
        }

        applyFilter();
    }

    window.NPBlogManagerUI = {
        icons: ICONS,
        t: translate,
        escapeHtml: safeEscapeHtml,
        extractPlainPreview: extractPlainPreview,
        renderStateBox: renderStateBox,
        toggleGroupElement: toggleGroupElement,
        syncToggleAllButton: syncToggleAllButton,
        initToolbar: initToolbar
    };
})(window, document);

// ——— Менеджер бэкапов ———
async function openBackupManager() {
    const content = document.getElementById('backupManagerContent');

    if (window.Modal) {
        Modal.open('#backupManagerOverlay');
    } else {
        const overlay = document.getElementById('backupManagerOverlay');
        if (overlay) overlay.classList.add('show');
    }

    window.NPBlogManagerUI.initToolbar({
        toolbarId: 'backupManagerToolbar',
        visible: false
    });

    if (content) {
        content.innerHTML = window.NPBlogManagerUI.renderStateBox({
            isLoading: true,
            title: window.NPBlogManagerUI.t('modals.loading', 'Загрузка...')
        });
    }

    try {
        const fetchBackups = (window.api && window.api.backups)
            ? window.api.backups.list()
            : fetch('/get_backups.php').then(r => r.json()).then(d => d.backups || (d.data && d.data.backups) || {});
        const backups = await fetchBackups;

        if (!backups || Object.keys(backups).length === 0) {
            if (content) {
                content.innerHTML = window.NPBlogManagerUI.renderStateBox({
                    icon: 'archive',
                    title: window.NPBlogManagerUI.t('modals.backup_no_backups', 'Нет сохраненных бэкапов'),
                    subtitle: window.NPBlogManagerUI.t('modals.backup_empty_hint', 'Резервные копии создаются автоматически при обновлении и удалении статей')
                });
            }
        } else {
            renderBackups(backups);
        }
    } catch (error) {
        console.error('Ошибка загрузки бэкапов:', error);
        if (content) {
            content.innerHTML = window.NPBlogManagerUI.renderStateBox({
                icon: 'archive',
                isError: true,
                title: window.NPBlogManagerUI.t('modals.backup_load_error', 'Ошибка загрузки бэкапов')
            });
        }
    }
}

function closeBackupManager() {
    if (window.Modal) {
        Modal.close('#backupManagerOverlay');
    } else {
        const overlay = document.getElementById('backupManagerOverlay');
        if (overlay) overlay.classList.remove('show');
    }
}

function renderBackups(backups) {
    const content = document.getElementById('backupManagerContent');
    if (!content) return;

    const ui = window.NPBlogManagerUI;
    const viewText = ui.t('common.view', 'Посмотреть');
    const restoreText = ui.t('common.restore', 'Восстановить');
    const deleteText = ui.t('common.delete', 'Удалить');
    const deletedBadgeText = ui.t('modals.manager_deleted_badge', 'Удалена');
    const latestBadgeText = ui.t('modals.manager_latest_badge', 'Последний');

    // Сортируем: сначала активные статьи, затем удалённые; внутри по убыванию числового ID
    const entries = Object.keys(backups).map(function (postId) {
        return { postId: postId, post: backups[postId] };
    }).sort(function (a, b) {
        const delA = a.post && a.post.deleted === true ? 1 : 0;
        const delB = b.post && b.post.deleted === true ? 1 : 0;
        if (delA !== delB) return delA - delB;
        const numA = parseInt(a.postId, 10);
        const numB = parseInt(b.postId, 10);
        if (!isNaN(numA) && !isNaN(numB)) return numB - numA;
        return String(b.postId).localeCompare(String(a.postId));
    });

    let totalBackups = 0;
    let html = '<div id="backupGroupsList" class="manager-groups-list">';

    entries.forEach(function (entry, groupIdx) {
        const postId = entry.postId;
        const post = entry.post || {};
        const isDeleted = post.deleted === true;
        const sortedBackups = Array.isArray(post.backups)
            ? post.backups.slice().sort(function (a, b) { return (b.backupNumber || 0) - (a.backupNumber || 0); })
            : [];
        totalBackups += sortedBackups.length;

        const safeTitle = ui.escapeHtml(post.postTitle || ui.t('common.untitled', 'Без названия'));
        const safePostId = ui.escapeHtml(postId);
        const isExpanded = groupIdx === 0;
        const latestDate = sortedBackups.length > 0 && sortedBackups[0].date ? ui.escapeHtml(sortedBackups[0].date) : '';
        const countLabel = ui.t('modals.manager_backups_count', 'Копий: {count}', { count: sortedBackups.length });
        const deletedMeta = isDeleted && post.deletedAt
            ? ' • ' + ui.escapeHtml(ui.t('modals.backup_post_deleted_at', 'Статья удалена: {date}', { date: post.deletedAt }))
            : '';

        const itemsHtml = sortedBackups.map(function (backup, idx) {
            const backupNumText = ui.t('modals.backup_item_number', 'Бэкап #{number}', { number: backup.backupNumber });
            const latestPill = idx === 0
                ? '<span class="manager-badge manager-badge-accent">' + ui.escapeHtml(latestBadgeText) + '</span>'
                : '';
            const safeFilename = ui.escapeHtml(backup.filename || '');
            const safeDate = ui.escapeHtml(backup.date || '');

            return (
                '<div class="backup-item">' +
                    '<div class="backup-info">' +
                        '<div class="manager-item-head">' +
                            '<span class="backup-number">' + ui.escapeHtml(backupNumText) + '</span>' +
                            latestPill +
                        '</div>' +
                        '<div class="manager-item-meta">' +
                            '<span class="manager-meta-icon" aria-hidden="true">' + ui.icons.clock + '</span>' +
                            '<span class="backup-date">' + safeDate + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="backup-actions">' +
                        '<button type="button" class="backup-btn view" data-post-id="' + safePostId + '" data-filename="' + safeFilename + '" data-backup-number="' + ui.escapeHtml(backup.backupNumber) + '">' +
                            ui.icons.eye + '<span>' + ui.escapeHtml(viewText) + '</span>' +
                        '</button>' +
                        (!isDeleted
                            ? '<button type="button" class="backup-btn restore" data-post-id="' + safePostId + '" data-filename="' + safeFilename + '" data-backup-number="' + ui.escapeHtml(backup.backupNumber) + '" data-date="' + safeDate + '">' +
                                ui.icons.restore + '<span>' + ui.escapeHtml(restoreText) + '</span>' +
                              '</button>'
                            : '') +
                        '<button type="button" class="backup-btn delete" data-post-id="' + safePostId + '" data-filename="' + safeFilename + '" data-backup-number="' + ui.escapeHtml(backup.backupNumber) + '" data-date="' + safeDate + '">' +
                            ui.icons.trash + '<span>' + ui.escapeHtml(deleteText) + '</span>' +
                        '</button>' +
                    '</div>' +
                '</div>'
            );
        }).join('');

        html +=
            '<div class="backup-post-group' + (isDeleted ? ' deleted-post' : '') + (isExpanded ? ' expanded' : '') + '" id="backup-group-' + safePostId + '" data-search-text="' + safeTitle.toLowerCase() + ' ' + safePostId.toLowerCase() + '">' +
                '<button type="button" class="backup-post-header" aria-expanded="' + (isExpanded ? 'true' : 'false') + '">' +
                    '<span class="manager-group-main">' +
                        '<span class="manager-group-icon' + (isDeleted ? ' is-danger' : '') + '" aria-hidden="true">' +
                            (isDeleted ? ui.icons.fileDeleted : ui.icons.fileText) +
                        '</span>' +
                        '<span class="manager-group-text">' +
                            '<span class="manager-group-title-row">' +
                                '<span class="backup-post-title">' + safeTitle + '</span>' +
                                '<span class="manager-badge manager-badge-neutral">#' + safePostId + '</span>' +
                                (isDeleted ? '<span class="manager-badge manager-badge-danger">' + ui.escapeHtml(deletedBadgeText) + '</span>' : '') +
                            '</span>' +
                            '<span class="manager-group-meta">' +
                                ui.escapeHtml(countLabel) +
                                (latestDate ? ' • ' + latestDate : '') +
                                (deletedMeta ? '<span class="manager-meta-danger">' + deletedMeta + '</span>' : '') +
                            '</span>' +
                        '</span>' +
                    '</span>' +
                    '<span class="manager-group-right">' +
                        '<span class="manager-count-pill">' + sortedBackups.length + '</span>' +
                        '<span class="backup-post-toggle" aria-hidden="true">' + ui.icons.chevronDown + '</span>' +
                    '</span>' +
                '</button>' +
                '<div class="backup-list-collapse">' +
                    '<div class="backup-list-inner">' +
                        '<div class="backup-list">' + itemsHtml + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
    });

    html += '</div>';
    html += '<div id="backupSearchEmpty" style="display: none;">' + ui.renderStateBox({
        icon: 'search',
        title: ui.t('modals.manager_nothing_found', 'Ничего не найдено'),
        subtitle: ui.t('modals.manager_nothing_found_hint', 'Попробуйте изменить поисковый запрос')
    }) + '</div>';

    content.innerHTML = html;

    const listEl = document.getElementById('backupGroupsList');
    const toggleAllBtn = document.getElementById('backupToggleAllBtn');

    content.querySelectorAll('.backup-post-header').forEach(function (headerBtn) {
        headerBtn.addEventListener('click', function () {
            ui.toggleGroupElement(headerBtn.closest('.backup-post-group'), toggleAllBtn, listEl);
        });
    });

    content.querySelectorAll('.backup-btn.view').forEach(function (btn) {
        btn.addEventListener('click', function () {
            viewBackup(
                btn.getAttribute('data-post-id'),
                btn.getAttribute('data-filename'),
                Number(btn.getAttribute('data-backup-number'))
            );
        });
    });

    content.querySelectorAll('.backup-btn.restore').forEach(function (btn) {
        btn.addEventListener('click', function () {
            restoreBackup(
                btn.getAttribute('data-post-id'),
                btn.getAttribute('data-filename'),
                Number(btn.getAttribute('data-backup-number')),
                btn.getAttribute('data-date')
            );
        });
    });

    content.querySelectorAll('.backup-btn.delete').forEach(function (btn) {
        btn.addEventListener('click', function () {
            deleteBackup(
                btn.getAttribute('data-post-id'),
                btn.getAttribute('data-filename'),
                Number(btn.getAttribute('data-backup-number')),
                btn.getAttribute('data-date')
            );
        });
    });

    ui.initToolbar({
        toolbarId: 'backupManagerToolbar',
        searchInputId: 'backupSearchInput',
        searchClearId: 'backupSearchClear',
        toggleAllBtnId: 'backupToggleAllBtn',
        statsId: 'backupManagerStats',
        listEl: listEl,
        emptySearchEl: document.getElementById('backupSearchEmpty'),
        visible: entries.length > 0,
        groupCount: entries.length,
        itemCount: totalBackups,
        itemCountKey: 'modals.manager_backups_count',
        itemCountFallback: 'Копий: {count}'
    });
}

function toggleBackupGroup(postId) {
    const group = document.getElementById('backup-group-' + postId);
    window.NPBlogManagerUI.toggleGroupElement(
        group,
        document.getElementById('backupToggleAllBtn'),
        document.getElementById('backupGroupsList')
    );
}

function viewBackup(postId, filename, backupNumber) {
    try {
        const activeBlog = (window.currentActiveBlogPath || 'data').replace(/^\/+|\/+$/g, '');
        let viewUrl = `/view_backup.php?postId=${encodeURIComponent(postId)}&filename=${encodeURIComponent(filename)}&blog=${encodeURIComponent(activeBlog)}`;
        if (backupNumber) {
            viewUrl += `&backupNumber=${encodeURIComponent(backupNumber)}`;
        }

        const newWindow = window.open(viewUrl, '_blank');
        if (!newWindow) {
            showNotification(window.t ? window.t('notifications.popup_blocked', 'Всплывающее окно заблокировано браузером. Разрешите всплывающие окна для просмотра бэкапа.') : 'Всплывающее окно заблокировано браузером. Разрешите всплывающие окна для просмотра бэкапа.', 'warning');
        }
    } catch (error) {
        console.error('Ошибка просмотра бэкапа:', error);
        showNotification(window.t ? window.t('notifications.backup_view_error', 'Ошибка при просмотре бэкапа') : 'Ошибка при просмотре бэкапа', 'error');
    }
}

// Восстановление бэкапа
function restoreBackup(postId, filename, backupNumber, backupDate) {
    const ui = window.NPBlogManagerUI;
    showConfirm(
        ui.t(
            'notifications.backup_restore_confirm_param',
            'Вы действительно хотите восстановить статью из бэкапа #{number} от {date}? Текущая версия статьи в редакторе будет заменена.',
            { number: backupNumber, date: backupDate }
        ),
        ui.t('notifications.backup_restore_title', 'Восстановить бэкап?')
    ).then(async (result) => {
        if (!result) return;

        showNotification(ui.t('notifications.backup_restoring', 'Восстановление бэкапа...'), 'info');

        try {
            const payload = {
                postId: postId,
                filename: filename
            };
            if (backupNumber) payload.backupNumber = backupNumber;

            const response = await fetch('/restore_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8',
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                showNotification(ui.t('notifications.backup_restored', 'Бэкап успешно восстановлен'), 'success');
                // Закрываем окно менеджера бэкапов
                closeBackupManager();
                // Перезагружаем страницу через секунду для отображения восстановленной статьи
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(ui.t('common.error', 'Ошибка') + ': ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Ошибка восстановления бэкапа:', error);
            showNotification(ui.t('notifications.backup_restore_error', 'Ошибка при восстановлении бэкапа'), 'error');
        }
    });
}

function escapeHtml(text) {
    return window.NPBlogManagerUI.escapeHtml(text);
}

// Удаление бэкапа
function deleteBackup(postId, filename, backupNumber, backupDate) {
    const ui = window.NPBlogManagerUI;
    showConfirm(
        ui.t(
            'notifications.backup_delete_confirm_param',
            'Вы действительно хотите окончательно удалить бэкап #{number} от {date}? Это действие необратимо.',
            { number: backupNumber, date: backupDate }
        ),
        ui.t('notifications.backup_delete_title', 'Удалить бэкап?')
    ).then(async (result) => {
        if (!result) return;

        showNotification(ui.t('notifications.backup_deleting', 'Удаление бэкапа...'), 'info');

        try {
            const payload = {
                postId: postId,
                filename: filename
            };
            if (backupNumber) payload.backupNumber = backupNumber;

            const response = await fetch('/delete_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8',
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                showNotification(ui.t('notifications.backup_deleted', 'Бэкап успешно удален'), 'success');
                // Обновляем список бэкапов в окне менеджера
                openBackupManager();
            } else {
                showNotification(ui.t('common.error', 'Ошибка') + ': ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Ошибка удаления бэкапа:', error);
            showNotification(ui.t('notifications.backup_delete_error', 'Ошибка при удалении бэкапа'), 'error');
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // Обработчик для сохранения состояния галочки "Вставить как гиперссылку"
    const insertAsHyperlinkCheckbox = document.getElementById('insertAsHyperlink');
    if (insertAsHyperlinkCheckbox) {
        insertAsHyperlinkCheckbox.addEventListener('change', function () {
            localStorage.setItem('insertAsHyperlink', this.checked);
        });
    }

    // Загружаем настройки автосохранения при загрузке страницы
    if (typeof loadAutosaveSettings === 'function') {
        loadAutosaveSettings();
    } else if (typeof window.loadAndApplyAllSettings === 'function') {
        window.loadAndApplyAllSettings();
    }

    // Применяем настройки внешнего вида
    if (typeof applyAppearanceSettings === 'function') {
        applyAppearanceSettings();
    } else if (typeof window.loadAndApplyAllSettings === 'function') {
        window.loadAndApplyAllSettings();
    }

    // Применяем экспериментальные настройки
    if (typeof applyExperimentalSettings === 'function') {
        applyExperimentalSettings();
    } else if (typeof window.loadAndApplyAllSettings === 'function') {
        window.loadAndApplyAllSettings();
    }

    // Проверяем предупреждение о DEV сборке
    if (typeof checkDevWarning === 'function') {
        checkDevWarning();
    }

    // Проверяем наличие несохраненного локального черновика
    if (typeof checkLocalDraftOnStartup === 'function') {
        setTimeout(checkLocalDraftOnStartup, 500);
    }

    // Отслеживаем ввод в поле заголовка для локального сохранения
    const titleInputEl = document.getElementById('title');
    if (titleInputEl) {
        titleInputEl.addEventListener('input', function () {
            if (typeof markEditorDirty === 'function') {
                markEditorDirty();
            }
        });
    }
});
