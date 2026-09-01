function saveToHistory(force = true) {
    if (isRestoringHistory) return;

    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');
    if (!ve || !ta) return;

    const currentState = {
        visual: ve.innerHTML,
        code: ta.value,
        mode: editorMode,
        visualSelection: getSelectionOffsets(ve),
        codeSelection: { start: ta.selectionStart, end: ta.selectionEnd }
    };

    if (force) {
        lastActionType = 'formatting';
        cursorMoved = false;
        // Удаляем все состояния после текущего индекса
        historyStack = historyStack.slice(0, historyIndex + 1);
        historyStack.push(currentState);
        historyIndex++;

        while (historyStack.length > MAX_HISTORY_STATES) {
            historyStack.shift();
            historyIndex = Math.max(0, historyIndex - 1);
        }
        markEditorDirty();
    }

    updateUndoRedoButtons();

    // Сохраняем в файл с задержкой
    clearTimeout(historySaveTimeout);
    historySaveTimeout = setTimeout(() => {
        saveHistoryToFile();
    }, 1000);
}

function saveHistoryToFile() {
    fetch('save_history.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            history: historyStack,
            index: historyIndex
        })
    }).catch(error => {
        console.error('Ошибка сохранения истории:', error);
    });
}

function loadHistoryFromFile() {
    fetch('get_history.php?t=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                historyStack = data.history || [];
                historyIndex = data.index ?? -1;
                updateUndoRedoButtons();
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки истории:', error);
        });
}

function undoEdit() {
    if (historyIndex <= 0) return;

    historyIndex--;
    restoreHistoryState(historyStack[historyIndex]);
    updateUndoRedoButtons();
    saveHistoryToFile();
}

function redoEdit() {
    if (historyIndex >= historyStack.length - 1) return;

    historyIndex++;
    restoreHistoryState(historyStack[historyIndex]);
    updateUndoRedoButtons();
    saveHistoryToFile();
}

function restoreHistoryState(state) {
    isRestoringHistory = true;

    const ve = document.getElementById('contentVisual');
    const ta = document.getElementById('content');

    ve.innerHTML = state.visual;
    ta.value = state.code;

    // Восстанавливаем обработчики для изображений и других элементов
    addColumnResizers();

    // Восстанавливаем выделение
    if (state.mode === 'visual') {
        ve.focus();
        if (state.visualSelection) {
            setSelectionOffsets(ve, state.visualSelection.start, state.visualSelection.end);
        }
    } else {
        ta.focus();
        if (state.codeSelection) {
            ta.setSelectionRange(state.codeSelection.start, state.codeSelection.end);
        }
    }

    isRestoringHistory = false;
}

function updateUndoRedoButtons() {
    const undoBtn = document.getElementById('undoBtn');
    const redoBtn = document.getElementById('redoBtn');

    if (undoBtn) {
        undoBtn.disabled = historyIndex <= 0;
        undoBtn.style.opacity = historyIndex <= 0 ? '0.4' : '1';
        undoBtn.style.cursor = historyIndex <= 0 ? 'not-allowed' : 'pointer';
    }

    if (redoBtn) {
        redoBtn.disabled = historyIndex >= historyStack.length - 1;
        redoBtn.style.opacity = historyIndex >= historyStack.length - 1 ? '0.4' : '1';
        redoBtn.style.cursor = historyIndex >= historyStack.length - 1 ? 'not-allowed' : 'pointer';
    }
}

function clearHistory() {
    historyStack = [];
    historyIndex = -1;
    updateUndoRedoButtons();

    // Очищаем файл истории
    fetch('clear_history.php', {
        method: 'POST'
    }).catch(error => {
        console.error('Ошибка очистки истории:', error);
    });
}
