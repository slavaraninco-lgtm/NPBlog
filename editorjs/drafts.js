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

        const isContentNonEmpty = (typeof isEditorContentEmpty === 'function') 
            ? !isEditorContentEmpty(content) 
            : (content.trim().length > 0 && content !== '<br>' && content !== '<p><br></p>' && content !== '<div><br></div>');
        const hasText = title.length > 0 || isContentNonEmpty;
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

        const isDraftContentEmpty = (typeof isEditorContentEmpty === 'function')
            ? isEditorContentEmpty(draft.content)
            : (!draft.content || draft.content === '<br>' || draft.content === '<p><br></p>');

        if (!draft || (!draft.title && isDraftContentEmpty)) {
            localStorage.removeItem('npblog_draft_new_post');
            return;
        }

        const title = document.getElementById('title')?.value?.trim();
        const ve = document.getElementById('contentVisual');
        const ta = document.getElementById('content');
        const currentContent = editorMode === 'visual' ? (ve?.innerHTML?.trim() || '') : (ta?.value?.trim() || '');
        const isCurrentEmpty = (typeof isEditorContentEmpty === 'function')
            ? isEditorContentEmpty(currentContent)
            : (!currentContent || currentContent === '<br>' || currentContent === '<p><br></p>');

        if (!title && isCurrentEmpty) {
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

    // Убираем старый тост восстановления, если он уже есть в DOM
    const oldToast = container.querySelector('.draft-restore-toast');
    if (oldToast) oldToast.remove();

    const toast = document.createElement('div');
    toast.className = 'notification info draft-restore-toast';

    const displayTitle = draft.title ? (draft.title.length > 35 ? draft.title.substring(0, 35) + '...' : draft.title) : (window.t ? window.t('drafts.untitled', 'Без названия') : 'Без названия');
    const toastTitle = window.t ? window.t('drafts.restore_title', 'Несохранённый черновик') : 'Несохранённый черновик';
    const restoreBtnText = window.t ? window.t('drafts.restore_btn', 'Восстановить') : 'Восстановить';
    const closeBtnTitle = window.t ? window.t('common.close', 'Закрыть') : 'Закрыть';

    toast.innerHTML = `
        <div class="notification-icon">📝</div>
        <div class="notification-content">
            <div class="notification-title">${toastTitle}</div>
            <div class="draft-toast-meta">
                <span class="draft-toast-name" title="${escapeHtml(draft.title || '')}">${escapeHtml(displayTitle)}</span>
                <span class="draft-toast-bullet">•</span>
                <span class="draft-toast-time">${timeFormatted}</span>
            </div>
        </div>
        <div class="draft-actions">
            <button type="button" class="draft-btn-restore">${restoreBtnText}</button>
            <button type="button" class="notification-close" title="${closeBtnTitle}">×</button>
        </div>
    `;

    const restoreBtn = toast.querySelector('.draft-btn-restore');
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
        showNotification(window.t ? window.t('drafts.restored_success', 'Локальный черновик успешно восстановлен!') : 'Локальный черновик успешно восстановлен!', 'success');
        if (typeof closeNotification === 'function') {
            closeNotification(toast);
        } else {
            toast.remove();
        }
    };

    const closeBtn = toast.querySelector('.notification-close');
    closeBtn.onclick = () => {
        localStorage.removeItem('npblog_draft_new_post');
        if (typeof closeNotification === 'function') {
            closeNotification(toast);
        } else {
            toast.remove();
        }
    };

    container.appendChild(toast);

    // Анимация появления
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
}

window.addEventListener('beforeunload', function (e) {
    if (isEditorDirty && typeof hasEditorContent === 'function' && hasEditorContent()) {
        e.preventDefault();
        e.returnValue = 'У вас есть несохраненные изменения в статье. Вы уверены, что хотите покинуть страницу?';
        return e.returnValue;
    }
});

// Загружаем пользовательские шрифты при инициализации редактора