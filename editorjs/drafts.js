function getLocalDraftStorageKey() {
    return currentEditId ? `npblog_draft_post_${currentEditId}` : 'npblog_draft_new_post';
}

function markEditorDirty() {
    isEditorDirty = true;
    scheduleLocalDraftSave();
}

function scheduleLocalDraftSave() {
    clearTimeout(localDraftSaveTimeout);
    localDraftSaveTimeout = setTimeout(() => {
        saveLocalDraftNow();
    }, 1200);
}

function saveLocalDraftNow() {
    try {
        const titleInput = document.getElementById('title');
        const ve = document.getElementById('contentVisual');
        const ta = document.getElementById('content');
        if (!titleInput || (!ve && !ta)) return;

        const title = titleInput.value.trim();
        const content = editorMode === 'visual' ? (ve ? ve.innerHTML : '') : (ta ? ta.value : '');

        const hasText = title.length > 0 || (content.length > 0 && content !== '<br>' && content !== '<div><br></div>');
        if (!hasText) {
            localStorage.removeItem(getLocalDraftStorageKey());
            return;
        }

        const draft = {
            title: title,
            content: content,
            mode: editorMode,
            currentEditId: currentEditId,
            timestamp: Date.now()
        };
        localStorage.setItem(getLocalDraftStorageKey(), JSON.stringify(draft));
    } catch (e) {
        console.warn('Не удалось сохранить локальный черновик:', e);
    }
}

function clearLocalDraft() {
    isEditorDirty = false;
    try {
        localStorage.removeItem(getLocalDraftStorageKey());
        localStorage.removeItem('npblog_draft_new_post');
    } catch (e) { }
}

function checkLocalDraftOnStartup() {
    try {
        if (currentEditId) return;
        const raw = localStorage.getItem('npblog_draft_new_post');
        if (!raw) return;
        const draft = JSON.parse(raw);
        if (!draft || (!draft.title && !draft.content)) return;

        const title = document.getElementById('title')?.value?.trim();
        const ve = document.getElementById('contentVisual');
        const ta = document.getElementById('content');
        const currentContent = editorMode === 'visual' ? (ve?.innerHTML?.trim() || '') : (ta?.value?.trim() || '');

        if (!title && (!currentContent || currentContent === '<br>' || currentContent === '<div><br></div>')) {
            const dateObj = new Date(draft.timestamp);
            const timeStr = dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const dateStr = dateObj.toLocaleDateString();
            showDraftRestoreToast(draft, `${dateStr} ${timeStr}`);
        }
    } catch (e) {
        console.error('Ошибка проверки локального черновика:', e);
    }
}

function showDraftRestoreToast(draft, timeFormatted) {
    const container = document.getElementById('notificationContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'notification info draft-restore-toast';
    toast.style.cssText = 'display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-left: 4px solid #2196F3; max-width: 480px; box-shadow: 0 4px 16px rgba(0,0,0,0.3);';

    const textSpan = document.createElement('span');
    textSpan.style.flex = '1';
    const displayTitle = draft.title ? (draft.title.length > 30 ? draft.title.substring(0, 30) + '...' : draft.title) : 'Без названия';
    textSpan.innerHTML = `📝 Несохранённый черновик (${timeFormatted}): <b>${escapeHtml(displayTitle)}</b>`;

    const actionsDiv = document.createElement('div');
    actionsDiv.style.display = 'flex';
    actionsDiv.style.gap = '8px';
    actionsDiv.style.alignItems = 'center';

    const restoreBtn = document.createElement('button');
    restoreBtn.type = 'button';
    restoreBtn.textContent = 'Восстановить';
    restoreBtn.style.cssText = 'background: #2196F3; color: white; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold;';
    restoreBtn.onclick = () => {
        if (draft.title) document.getElementById('title').value = draft.title;
        const ve = document.getElementById('contentVisual');
        const ta = document.getElementById('content');
        if (draft.mode === 'code') {
            setMode('code');
            if (ta) ta.value = draft.content || '';
        } else {
            setMode('visual');
            if (ve) {
                ve.innerHTML = draft.content || '';
                wrapExistingEditorImages();
            }
        }
        markEditorDirty();
        showNotification('Локальный черновик успешно восстановлен!', 'success');
        toast.remove();
    };

    const dismissBtn = document.createElement('button');
    dismissBtn.type = 'button';
    dismissBtn.textContent = '×';
    dismissBtn.style.cssText = 'background: transparent; border: none; color: inherit; cursor: pointer; font-size: 18px; line-height: 1; padding: 0 4px;';
    dismissBtn.onclick = () => {
        localStorage.removeItem('npblog_draft_new_post');
        toast.remove();
    };

    actionsDiv.appendChild(restoreBtn);
    actionsDiv.appendChild(dismissBtn);
    toast.appendChild(textSpan);
    toast.appendChild(actionsDiv);
    container.appendChild(toast);
}

window.addEventListener('beforeunload', function (e) {
    if (isEditorDirty && typeof hasEditorContent === 'function' && hasEditorContent()) {
        e.preventDefault();
        e.returnValue = 'У вас есть несохраненные изменения в статье. Вы уверены, что хотите покинуть страницу?';
        return e.returnValue;
    }
});

// Загружаем пользовательские шрифты при инициализации редактора