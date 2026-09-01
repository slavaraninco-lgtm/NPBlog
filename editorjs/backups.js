// ——— Менеджер бэкапов ———
async function openBackupManager() {
    const content = document.getElementById('backupManagerContent');

    if (window.Modal) {
        Modal.open('#backupManagerOverlay');
    } else {
        const overlay = document.getElementById('backupManagerOverlay');
        if (overlay) overlay.classList.add('show');
    }

    if (content) {
        content.innerHTML = '<div class="backup-empty">' + (window.t ? window.t('modals.loading', 'Загрузка...') : 'Загрузка...') + '</div>';
    }

    try {
        const response = await fetch('get_backups.php');
        const data = await response.json();

        if (data.success) {
            if (Object.keys(data.backups).length === 0) {
                if (content) content.innerHTML = '<div class="backup-empty">' + (window.t ? window.t('modals.backup_no_backups', 'Нет сохраненных бэкапов') : 'Нет сохраненных бэкапов') + '</div>';
            } else {
                renderBackups(data.backups);
            }
        } else {
            if (content) content.innerHTML = '<div class="backup-empty">' + (window.t ? window.t('modals.backup_load_error', 'Ошибка загрузки бэкапов') : 'Ошибка загрузки бэкапов') + '</div>';
        }
    } catch (error) {
        console.error('Ошибка загрузки бэкапов:', error);
        if (content) content.innerHTML = '<div class="backup-empty">' + (window.t ? window.t('modals.backup_load_error', 'Ошибка загрузки бэкапов') : 'Ошибка загрузки бэкапов') + '</div>';
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
    let html = '';

    const viewText = window.t ? window.t('common.view', 'Посмотреть') : 'Посмотреть';
    const restoreText = window.t ? window.t('common.restore', 'Восстановить') : 'Восстановить';
    const deleteText = window.t ? window.t('common.delete', 'Удалить') : 'Удалить';

    for (const postId in backups) {
        const post = backups[postId];
        const isDeleted = post.deleted === true;
        const safeTitle = escapeHtml(post.postTitle);
        const displayTitle = isDeleted
            ? `🗑️ ${safeTitle}`
            : (window.t ? window.t('modals.backup_article_prefix', `Статья #${postId}: ${safeTitle}`, { id: postId, title: safeTitle }) : `Статья #${postId}: ${safeTitle}`);

        html += `
            <div class="backup-post-group ${isDeleted ? 'deleted-post' : ''}" id="backup-group-${postId}">
                <div class="backup-post-header" onclick="toggleBackupGroup('${postId}')">
                    <h3 class="backup-post-title">${displayTitle}</h3>
                    <span class="backup-post-toggle">▼</span>
                </div>
                <div class="backup-list">
                    ${post.backups.map((backup, index) => {
            const backupNumText = window.t
                ? window.t('modals.backup_item_number', `Бэкап #${backup.backupNumber}`, { number: backup.backupNumber })
                : `Бэкап #${backup.backupNumber}`;
            const deletedInfo = isDeleted
                ? (window.t ? window.t('modals.backup_post_deleted_at', `Статья удалена: ${escapeHtml(post.deletedAt || '')}`, { date: escapeHtml(post.deletedAt || '') }) : `Статья удалена: ${escapeHtml(post.deletedAt || '')}`)
                : '';
            return `
                        <div class="backup-item">
                            <div class="backup-info">
                                <div class="backup-number">${backupNumText}</div>
                                <div class="backup-date">${escapeHtml(backup.date)}</div>
                                ${isDeleted ? '<div class="backup-date" style="color: #d32f2f; font-weight: 600; margin-top: 4px;">' + deletedInfo + '</div>' : ''}
                            </div>
                            <div class="backup-actions">
                                <button type="button" class="backup-btn view" onclick="viewBackup('${postId}', '${backup.filename}')">${viewText}</button>
                                ${!isDeleted ? `<button type="button" class="backup-btn restore" onclick="restoreBackup('${postId}', '${backup.filename}', ${backup.backupNumber}, '${escapeHtml(backup.date)}')">${restoreText}</button>` : ''}
                                <button type="button" class="backup-btn delete" onclick="deleteBackup('${postId}', '${backup.filename}', ${backup.backupNumber}, '${escapeHtml(backup.date)}')">${deleteText}</button>
                            </div>
                        </div>
                    `}).join('')}
                </div>
            </div>
        `;
    }

    content.innerHTML = html;
}

function toggleBackupGroup(postId) {
    const group = document.getElementById('backup-group-' + postId);
    if (group) {
        group.classList.toggle('expanded');
    }
}

async function viewBackup(postId, filename) {
    try {
        const response = await fetch('get_backup_content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json; charset=utf-8',
            },
            body: JSON.stringify({ postId: postId, filename: filename })
        });

        const data = await response.json();

        if (data.success) {
            // Открываем в новом окне
            const newWindow = window.open('', '_blank');
            newWindow.document.write(data.content);
            newWindow.document.close();
        } else {
            showNotification('Ошибка: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Ошибка просмотра бэкапа:', error);
        showNotification('Ошибка при просмотре бэкапа', 'error');
    }
}

// Восстановление бэкапа
function restoreBackup(postId, filename, backupNumber, backupDate) {
    showConfirm(
        `Вы действительно хотите восстановить статью из бэкапа #${backupNumber} от ${backupDate}? Текущая версия статьи в редакторе будет заменена.`,
        'Восстановить бэкап?'
    ).then(async (result) => {
        if (!result) return;

        showNotification('Восстановление бэкапа...', 'info');

        try {
            const response = await fetch('restore_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8',
                },
                body: JSON.stringify({
                    postId: postId,
                    filename: filename
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Бэкап успешно восстановлен', 'success');
                // Закрываем окно менеджера бэкапов
                closeBackupManager();
                // Перезагружаем страницу через секунду для отображения восстановленной статьи
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Ошибка восстановления бэкапа:', error);
            showNotification('Ошибка при восстановлении бэкапа', 'error');
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Удаление бэкапа
function deleteBackup(postId, filename, backupNumber, backupDate) {
    showConfirm(
        `Вы действительно хотите окончательно удалить бэкап #${backupNumber} от ${backupDate}? Это действие необратимо.`,
        'Удалить бэкап?'
    ).then(async (result) => {
        if (!result) return;

        showNotification('Удаление бэкапа...', 'info');

        try {
            const response = await fetch('delete_backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json; charset=utf-8',
                },
                body: JSON.stringify({
                    postId: postId,
                    filename: filename
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Бэкап успешно удален', 'success');
                // Обновляем список бэкапов в окне менеджера
                openBackupManager();
            } else {
                showNotification('Ошибка: ' + data.error, 'error');
            }
        } catch (error) {
            console.error('Ошибка удаления бэкапа:', error);
            showNotification('Ошибка при удалении бэкапа', 'error');
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
    loadAutosaveSettings();

    // Применяем настройки внешнего вида
    applyAppearanceSettings();

    // Применяем экспериментальные настройки
    applyExperimentalSettings();

    // Проверяем предупреждение о DEV сборке
    checkDevWarning();

    // Проверяем наличие несохраненного локального черновика
    setTimeout(checkLocalDraftOnStartup, 500);

    // Отслеживаем ввод в поле заголовка для локального сохранения
    const titleInputEl = document.getElementById('title');
    if (titleInputEl) {
        titleInputEl.addEventListener('input', function () {
            markEditorDirty();
        });
    }
});
